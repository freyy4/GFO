<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ProductionAccess;
use Illuminate\Database\Eloquent\Model;

class ProductionPolicy
{
    public function view(User $u, string $m, ?Model $r = null): bool
    {
        return ! ProductionAccess::grants($u, $m)->isEmpty() && (! $r || ProductionAccess::query($u, $m)->whereKey($r->id)->exists());
    }

    public function create(User $u, string $m): bool
    {
        return $m !== 'production-material-requirements' && ProductionAccess::write($u, $m, 'create');
    }

    public function action(User $u, string $m, Model $r, string $action): bool
    {
        if (! $this->view($u, $m, $r) || ! ProductionAccess::write($u, $m, $action === 'cancel' ? 'update' : $action, $r)) {
            return false;
        }

        return match ($action) {
            'update','delete' => match ($m) {
                'demand-forecasts','production-plans' => $r->status === 'DRAFT','production-schedules' => $r->status === 'PLANNED','production-tasks','packing-dispatch' => $r->status === 'PENDING','production-batches' => $action === 'delete' && $r->status === 'PLANNED',default => false
            },
            'approve' => $r->status === 'DRAFT','start' => in_array($r->status, ['PLANNED', 'PENDING'], true),'finish' => $r->status === 'IN_PROGRESS','complete' => $r->status === 'IN_PROGRESS',
            'release','reject' => $r->status === 'AWAITING_QC','cancel' => in_array($r->status, ['DRAFT', 'APPROVED', 'PLANNED', 'PENDING'], true),default => false
        };
    }
}
