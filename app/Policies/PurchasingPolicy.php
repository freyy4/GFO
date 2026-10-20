<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PurchasingAccess;
use App\Support\PurchasingMutation;
use Illuminate\Database\Eloquent\Model;

class PurchasingPolicy
{
    public function view(User $user, string $module, ?Model $record = null): bool
    {
        if (PurchasingAccess::grants($user, $module)->isEmpty()) {
            return false;
        }
        if (! $record) {
            return true;
        }
        $q = PurchasingAccess::query($user, $module);
        if ($module === 'purchase-orders') {
            $q->withTrashed();
        }

        return $q->whereKey($record->id)->exists();
    }

    public function create(User $user, string $module): bool
    {
        return PurchasingAccess::canWrite($user, $module, 'create');
    }

    public function action(User $user, string $module, Model $record, string $action): bool
    {
        if (! $this->view($user, $module, $record) || $record->getAttribute('deleted_at')) {
            return false;
        }
        $ability = $action === 'reject' ? 'approve' : (in_array($action, ['submit', 'cancel', 'activate', 'deactivate', 'close'], true) ? 'update' : $action);
        if (! PurchasingAccess::canWrite($user, $module, $ability, $record->getAttribute('destination_unit_id'))) {
            return false;
        }

        return match ($action) {
            'update', 'delete' => match ($module) {
                'purchase-requisitions', 'purchase-orders' => in_array($record->status, ['DRAFT', 'REJECTED'], true),
                'goods-receipts' => $record->status === 'DRAFT',
                'supplier-price-history' => $record->effective_from->format('Y-m-d') > now(PurchasingMutation::timezone(request()))->format('Y-m-d'),
                default => true,
            },
            'submit' => in_array($module, ['purchase-requisitions', 'purchase-orders'], true) && in_array($record->status, ['DRAFT', 'REJECTED'], true),
            'approve', 'reject' => in_array($module, ['purchase-requisitions', 'purchase-orders'], true) && $record->status === 'SUBMITTED',
            'confirm' => $module === 'goods-receipts' && $record->status === 'DRAFT',
            'cancel' => in_array($record->status, ['DRAFT', 'REJECTED', 'SUBMITTED', 'APPROVED'], true),
            'close' => $module === 'supplier-price-history' && $record->effective_until === null,
            'activate' => $module === 'supplier-contracts' ? $record->status !== 'ACTIVE' : ($module === 'supplier-items' && ! $record->is_active),
            'deactivate' => $module === 'supplier-contracts' ? $record->status === 'ACTIVE' : ($module === 'supplier-items' && $record->is_active),
            default => false,
        };
    }
}
