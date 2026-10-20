<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OperationsAccess
{
    public static function access(string $m): string
    {
        return OperationsDefinition::production($m) ? ProductionAccess::class : WorkforceAccess::class;
    }

    public static function query(User $u, string $m)
    {
        $c = self::access($m);

        return $c::query($u, $m);
    }

    public static function units(User $u, string $m, string $a = 'view')
    {
        $c = self::access($m);

        return $c::units($u, $m, $a);
    }

    public static function unitId(Model $r, string $m): ?int
    {
        $c = self::access($m);

        return $c::unitId($r, $m);
    }
}
