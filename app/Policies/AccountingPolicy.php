<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountingAccess as Access;
use App\Support\AccountingDefinition as Definition;
use Illuminate\Database\Eloquent\Model;

class AccountingPolicy
{
    public function view(User $u, string $m, ?Model $r = null): bool
    {
        if (Access::grants($u, $m)->isEmpty() || (in_array($m, ['payroll-periods', 'payroll-runs'], true) && ! Access::wide($u, $m))) {
            return false;
        }

        return ! $r || Access::query($u, $m)->whereKey($r->id)->exists();
    }

    private function write(User $u, string $m, string $a, ?Model $r = null): bool
    {
        if (! $this->view($u, $m, $r) || Definition::readOnly($m)) {
            return false;
        }
        if (Definition::global($m) || ($r && Access::unitId($r, $m) === null)) {
            return Access::wide($u, $m, $a);
        }

        return Access::units($u, $m, $a)->where('status', 'ACTIVE')->when($r, fn ($q) => $q->whereKey(Access::unitId($r, $m)))->exists();
    }

    public function create(User $u, string $m): bool
    {
        return $this->write($u, $m, 'create');
    }

    public function action(User $u, string $m, Model $r, string $a): bool
    {
        if (! $this->write($u, $m, in_array($a, ['activate', 'deactivate', 'cancel'], true) ? 'update' : $a, $r)) {
            return false;
        }

        return match ($a) {
            'update','delete' => match ($m) {
                'payroll-periods' => $r->status === 'OPEN','kpi-definitions','kpi-targets','expense-categories' => true,'expenses','performance-scores' => $r->status === 'DRAFT','bills' => $r->status === 'UNPAID','recurring-obligations' => $r->status === 'ACTIVE',default => false
            },
            'lock' => $r->status === 'OPEN','calculate' => $r->status === 'DRAFT','approve' => $r->status === match ($m) {
                'expenses' => 'SUBMITTED','payroll-runs' => 'CALCULATED',default => 'DRAFT'
            },'pay' => in_array($r->status, ['APPROVED', 'UNPAID'], true),'submit' => $r->status === 'DRAFT','reject' => $r->status === 'SUBMITTED','cancel' => in_array($r->status, ['DRAFT', 'SUBMITTED', 'APPROVED', 'CALCULATED', 'UNPAID'], true),
            'activate' => $m === 'kpi-definitions' ? ! $r->is_active : $r->status === 'INACTIVE','deactivate' => $m === 'kpi-definitions' ? (bool) $r->is_active : $r->status === 'ACTIVE','generate' => $r->status === 'ACTIVE','recalculate' => true,default => false,
        };
    }
}
