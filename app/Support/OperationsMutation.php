<?php

namespace App\Support;

use App\Http\Requests\OperationsRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OperationsMutation
{
    private function lock(): void
    {
        Organization::whereKey(auth()->user()->organization_id)->lockForUpdate()->firstOrFail();
        PurchasingAccess::forget(auth()->user());
    }

    public static function snapshot(Model $r, string $m): array
    {
        $data = $r->attributesToArray();
        if ($r->exists && ($rel = OperationsDefinition::relation($m))) {
            $data['lines'] = $r->$rel()->get()->toArray();
        }

        return $data;
    }

    public static function audit(Model $r, string $m, string $a, array $before = []): void
    {
        AuditLog::create(['organization_id' => auth()->user()->organization_id, 'user_id' => auth()->id(), 'event' => str_replace('-', '_', $m).'.'.$a, 'auditable_type' => $r->getMorphClass(), 'auditable_id' => $r->id, 'old_values' => $before, 'new_values' => $r->exists ? self::snapshot($r, $m) : null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }

    public function save(OperationsRequest $request, ?Model $record = null): Model
    {
        try {
            return DB::transaction(function () use ($request, $record) {
                $this->lock();
                $m = $request->module();
                $class = OperationsDefinition::models()[$m];
                $r = $record ? OperationsAccess::query($request->user(), $m)->lockForUpdate()->findOrFail($record->id) : new $class;
                $r->exists ? Gate::authorize('operations-action', [$m, $r, 'update']) : Gate::authorize('operations-create', $m);
                $before = self::snapshot($r, $m);
                $ability = $r->exists ? 'update' : 'create';
                $service = OperationsDefinition::production($m) ? new ProductionMutation : new WorkforceMutation;
                $r = $service->save($m, $r, $request->validated(), $ability);
                self::audit($r, $m, $ability, $before);

                return $r;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            StockLedger::fail('Data atau kombinasi referensi sudah digunakan.');
        }
    }

    public function action(string $m, Model $record, string $a, array $data): void
    {
        DB::transaction(function () use ($m, $record, $a, $data) {
            $this->lock();
            $r = OperationsAccess::query(auth()->user(), $m)->lockForUpdate()->findOrFail($record->id);
            Gate::authorize('operations-action', [$m, $r, $a]);
            $before = self::snapshot($r, $m);
            $service = OperationsDefinition::production($m) ? new ProductionMutation : new WorkforceMutation;
            $service->action($m, $r, $a, $data);
            self::audit($r, $m, $a, $before);
        }, 3);
    }
}
