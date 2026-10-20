<?php

namespace App\Support;

use App\Http\Requests\AccountingRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountingMutation
{
    public static function audit(Model $r, string $m, string $a, array $before = []): void
    {
        AuditLog::create(['organization_id' => auth()->user()->organization_id, 'user_id' => auth()->id(), 'event' => str_replace('-', '_', $m).'.'.$a, 'auditable_type' => $r->getMorphClass(), 'auditable_id' => $r->id, 'old_values' => $before, 'new_values' => $r->exists ? $r->attributesToArray() : null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }

    public function execute(AccountingRequest $request, ?Model $record = null, ?string $action = null): Model
    {
        try {
            return DB::transaction(function () use ($request, $record, $action) {
                Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
                PurchasingAccess::forget($request->user());
                $m = $request->module();
                $class = AccountingDefinition::MODELS[$m];
                $r = $record ? AccountingAccess::query($request->user(), $m)->lockForUpdate()->findOrFail($record->id) : new $class;
                $a = $action ?? ($r->exists ? 'update' : 'create');
                $r->exists ? Gate::authorize('accounting-action', [$m, $r, $a]) : Gate::authorize('accounting-create', $m);
                $before = $r->attributesToArray();
                $d = $request->validated();
                $service = AccountingDefinition::payroll($m) ? new PayrollPerformanceMutation : new FinanceMutation;
                $action ? $service->action($m, $r, $a, $d) : $service->save($m, $r, $d, $a);
                self::audit($r, $m, $a, $before);

                return $r;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            StockLedger::fail('Data atau kombinasi periode sudah digunakan.');
        }
    }
}
