<?php

namespace App\Policies;

use App\Models\User;
use App\Support\TradeAccess;
use App\Support\TradeDefinition;
use Illuminate\Database\Eloquent\Model;

class TradePolicy
{
    public function view(User $u, string $m, ?Model $r = null): bool
    {
        return ! TradeAccess::grants($u, $m)->isEmpty() && (! $r || TradeAccess::query($u, $m)->whereKey($r->id)->exists());
    }

    public function create(User $u, string $m): bool
    {
        return ! TradeDefinition::readOnly($m) && TradeAccess::write($u, $m, 'create');
    }

    public function action(User $u, string $m, Model $r, string $action): bool
    {
        if (! $this->view($u, $m, $r)) {
            return false;
        }
        $ability = match ($action) {
            'update','cancel','void','release','activate','deactivate' => 'update','delete' => 'delete',default => $action
        };
        if (! TradeAccess::write($u, $m, $ability, $r)) {
            return false;
        }
        if (in_array($action, ['update', 'delete'], true)) {
            return in_array($m, ['customers', 'cash-registers'], true) || (in_array($m, ['sales-orders', 'stock-transfers', 'stock-counts', 'inventory-reconciliations', 'daily-closings', 'payment-settlements'], true) && $r->status === 'DRAFT');
        }

        return match ($action) {
            'checkout','void','dispatch','settle' => $r->status === 'DRAFT',
            'receive' => $r->status === 'DISPATCHED',
            'approve' => in_array($r->status, ['DRAFT', 'PENDING', 'REPORTED'], true),
            'reject' => $r->status === 'PENDING', 'cancel' => in_array($r->status, ['DRAFT', 'REPORTED'], true),
            'release' => $r->status === 'ACTIVE','close' => $r->status === 'OPEN',
            'activate' => ! $r->is_active,'deactivate' => (bool) $r->is_active,default => false
        };
    }
}
