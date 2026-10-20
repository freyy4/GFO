<?php

namespace App\Policies;

use App\Models\User;
use App\Support\WorkforceAccess;
use Illuminate\Database\Eloquent\Model;

class WorkforcePolicy
{
    public function view(User $u, string $m, ?Model $r = null): bool
    {
        return ! WorkforceAccess::grants($u, $m)->isEmpty() && (! $r || WorkforceAccess::query($u, $m)->whereKey($r->id)->exists());
    }

    public function create(User $u, string $m): bool
    {
        return WorkforceAccess::write($u, $m, 'create');
    }

    public function action(User $u, string $m, Model $r, string $a): bool
    {
        if (! $this->view($u, $m, $r) || ! WorkforceAccess::write($u, $m, in_array($a, ['cancel', 'activate', 'deactivate', 'revoke'], true) ? 'update' : $a, $r)) {
            return false;
        }
        if (in_array($a, ['approve', 'reject', 'publish', 'verify'], true) && WorkforceAccess::selfOnly($u, $m)) {
            return false;
        }

        return match ($a) {
            'update','delete' => match ($m) {
                'skills','shift-templates','employee-certifications' => true,'employee-skills' => $r->verified_by === null,'rosters' => $r->status === 'DRAFT','shift-assignments' => in_array($r->status, ['ASSIGNED', 'OPEN'], true) && (! $r->roster || $r->roster->status === 'DRAFT'),default => false
            },
            'publish' => $r->status === 'DRAFT','approve','reject' => $r->status === 'PENDING','verify' => $r->verified_by === null,
            'activate' => ! $r->is_active,'deactivate' => (bool) $r->is_active,'revoke' => $r->status === 'VALID',
            'cancel' => in_array($r->status, ['OPEN', 'ASSIGNED'], true),default => false
        };
    }
}
