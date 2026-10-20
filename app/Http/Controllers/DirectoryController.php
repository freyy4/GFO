<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexDirectoryRequest;
use App\Http\Requests\SaveDirectoryRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use App\Support\DirectoryAccess;
use App\Support\DirectoryDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

abstract class DirectoryController extends Controller
{
    protected string $module;

    public function index(IndexDirectoryRequest $request): View
    {
        $filters = $request->validated();
        $query = DirectoryAccess::query($request->user(), $this->module);
        if ($this->module !== 'positions') {
            $query->withTrashed();
            if (($filters['archived'] ?? 'no') !== 'all') {
                ($filters['archived'] ?? 'no') === 'yes' ? $query->onlyTrashed() : $query->withoutTrashed();
            }
        }
        $name = $this->module === 'employees' ? 'full_name' : 'name';
        $code = $this->module === 'employees' ? 'employee_no' : 'code';
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where($code, 'like', '%'.$filters['search'].'%')->orWhere($name, 'like', '%'.$filters['search'].'%'));
        }
        foreach (DirectoryDefinition::filters($this->module) as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $records = $query->orderBy($name)->orderBy('id')->paginate(15)->withQueryString();

        return view('directory.index', $this->viewData($request) + compact('records', 'filters'));
    }

    public function create(Request $request): View
    {
        $class = DirectoryDefinition::model($this->module);
        Gate::authorize('create', $class);

        return view('directory.create', $this->viewData($request) + ['record' => new $class(DirectoryDefinition::defaults($this->module))]);
    }

    public function store(SaveDirectoryRequest $request): RedirectResponse
    {
        $record = $this->save($request);

        return to_route($this->module.'.show', $record)->with('success', DirectoryDefinition::label($this->module).' berhasil dibuat.');
    }

    public function show(Request $request, Model $record): View
    {
        Gate::authorize('view', $record);

        return view('directory.show', $this->viewData($request) + ['record' => $record]);
    }

    public function edit(Request $request, Model $record): View
    {
        Gate::authorize('update', $record);

        return view('directory.edit', $this->viewData($request) + ['record' => $record]);
    }

    public function update(SaveDirectoryRequest $request, Model $record): RedirectResponse
    {
        $record = $this->save($request, $record);

        return to_route($this->module.'.show', $record)->with('success', DirectoryDefinition::label($this->module).' berhasil diperbarui.');
    }

    private function save(SaveDirectoryRequest $request, ?Model $record = null): Model
    {
        try {
            return DB::transaction(function () use ($request, $record) {
                Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
                DirectoryAccess::forget($request->user());
                $class = DirectoryDefinition::model($this->module);
                $record = $record ? DirectoryAccess::query($request->user(), $this->module)->lockForUpdate()->findOrFail($record->id) : new $class;
                Gate::authorize($record->exists ? 'update' : 'create', $record->exists ? $record : $class);
                $data = $request->validated();
                Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->validate();
                $code = $this->module === 'employees' ? 'employee_no' : 'code';
                if (DB::table($this->module)->where('organization_id', $request->user()->organization_id)
                    ->whereRaw('LOWER('.$code.') = ?', [strtolower($data[$code])])
                    ->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))->exists()) {
                    throw ValidationException::withMessages([$code => 'Kode/nomor sudah digunakan dalam organization ini.']);
                }
                if ($this->module === 'employees') {
                    foreach (['home_unit_id' => DirectoryAccess::units($request->user()),
                        'position_id' => Position::where('organization_id', $request->user()->organization_id),
                        'user_id' => User::where('organization_id', $request->user()->organization_id)] as $field => $query) {
                        if (! empty($data[$field]) && ! $query->lockForUpdate()->find($data[$field])) {
                            throw ValidationException::withMessages([$field => 'Pilihan tidak tersedia dalam akses Anda.']);
                        }
                    }
                }
                foreach (DirectoryDefinition::fields($this->module) as $key => $field) {
                    if ($field[1] === 'json') {
                        $data[$key] = isset($data[$key]) ? json_decode($data[$key]) : null;
                    }
                }
                $before = $record->toArray();
                $event = $record->exists ? 'update' : 'create';
                $record->fill($data);
                $record->organization_id = $request->user()->organization_id;
                $record->save();
                $this->audit($request, $record, $event, $before);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['record' => 'Kode, nomor karyawan, atau akun pengguna sudah digunakan. Muat ulang dan periksa data Anda.']);
        }
    }

    public function destroy(Request $request, Model $record): RedirectResponse
    {
        return $this->archive($request, $record, false);
    }

    public function restore(Request $request, Model $record): RedirectResponse
    {
        return $this->archive($request, $record, true);
    }

    private function archive(Request $request, Model $record, bool $restore): RedirectResponse
    {
        Gate::authorize($restore ? 'restore' : 'delete', $record);
        DB::transaction(function () use ($request, $record, $restore) {
            Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
            DirectoryAccess::forget($request->user());
            $query = DirectoryAccess::query($request->user(), $this->module);
            if ($this->module !== 'positions') {
                $query->withTrashed();
            }
            $record = $query->lockForUpdate()->findOrFail($record->id);
            Gate::authorize($restore ? 'restore' : 'delete', $record);
            $before = $record->toArray();
            if ($this->module === 'positions') {
                foreach (['employees', 'employee_unit_assignments', 'shift_assignments'] as $table) {
                    if (DB::table($table)->where('position_id', $record->id)->lockForUpdate()->first(['id'])) {
                        throw ValidationException::withMessages(['record' => 'Jabatan masih digunakan dan tidak dapat dihapus, termasuk oleh karyawan yang diarsipkan.']);
                    }
                }
                $record->delete();
            } else {
                $record->setAttribute($this->module === 'employees' ? 'status' : 'approval_status', $this->module === 'employees' ? 'INACTIVE' : 'PENDING');
                if ($restore) {
                    $record->restore();
                } else {
                    $record->save();
                    $record->delete();
                }
            }
            $this->audit($request, $record, $restore ? 'restore' : 'delete', $before);
        }, 3);

        return to_route($this->module.'.index')->with('success', $restore ? 'Data berhasil dipulihkan. Periksa status sebelum digunakan kembali.' : 'Data berhasil dihapus.');
    }

    private function viewData(Request $request): array
    {
        $options = [];
        if ($this->module === 'employees') {
            $options['home_unit_id'] = DirectoryAccess::units($request->user())->orderBy('name')->pluck('name', 'id')->all();
            $options['position_id'] = Position::where('organization_id', $request->user()->organization_id)->orderBy('name')->pluck('name', 'id')->all();
            $options['user_id'] = User::where('organization_id', $request->user()->organization_id)->orderBy('name')->get()
                ->mapWithKeys(fn ($user) => [$user->id => $user->name.' ('.$user->email.')'])->all();
        }

        return ['module' => $this->module, 'label' => DirectoryDefinition::label($this->module),
            'modelClass' => DirectoryDefinition::model($this->module), 'fields' => DirectoryDefinition::fields($this->module),
            'filterFields' => DirectoryDefinition::filters($this->module), 'options' => $options];
    }

    private function audit(Request $request, Model $record, string $event, array $before): void
    {
        AuditLog::create(['organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => $this->module.'.'.$event, 'auditable_type' => $record->getMorphClass(), 'auditable_id' => $record->id,
            'old_values' => $before, 'new_values' => $record->exists ? $record->toArray() : null,
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
