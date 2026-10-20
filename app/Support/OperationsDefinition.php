<?php

namespace App\Support;

class OperationsDefinition
{
    public static function production(string $m): bool
    {
        return isset(ProductionDefinition::MODELS[$m]);
    }

    public static function definition(string $m): string
    {
        return self::production($m) ? ProductionDefinition::class : WorkforceDefinition::class;
    }

    public static function models(): array
    {
        return ProductionDefinition::MODELS + WorkforceDefinition::MODELS;
    }

    public static function labels(): array
    {
        return ProductionDefinition::LABELS + WorkforceDefinition::LABELS;
    }

    public static function fields(string $m): array
    {
        $d = self::definition($m);

        return $d::fields($m);
    }

    public static function lineFields(string $m): array
    {
        $d = self::definition($m);

        return $d::lineFields($m);
    }

    public static function relation(string $m): ?string
    {
        $d = self::definition($m);

        return $d::relation($m);
    }

    public static function actions(string $m): array
    {
        $d = self::definition($m);

        return $d::actions($m);
    }

    public static function statuses(string $m): array
    {
        return match ($m) {
            'demand-forecasts' => ['DRAFT', 'APPROVED'],
            'production-plans' => ['DRAFT', 'APPROVED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'],
            'production-material-requirements' => ['REQUIRED', 'NOT_REQUIRED'],
            'production-schedules' => ['PLANNED', 'CANCELLED'],
            'production-tasks', 'packing-dispatch' => ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'],
            'production-batches' => ['PLANNED', 'IN_PROGRESS', 'AWAITING_QC', 'RELEASED', 'REJECTED', 'CANCELLED'],
            'production-qc' => ['PASS', 'FAIL'],
            'shift-templates' => ['ACTIVE', 'INACTIVE'],
            'rosters' => ['DRAFT', 'PUBLISHED'],
            'shift-assignments' => ['ASSIGNED', 'OPEN', 'ON_LEAVE', 'CANCELLED'],
            'open-shifts' => ['OPEN', 'FILLED', 'CANCELLED'],
            'replacement-candidates' => ['SUGGESTED', 'CLOSED'],
            'employee-skills' => ['VERIFIED', 'UNVERIFIED'],
            'employee-certifications' => ['VALID', 'REVOKED'],
            'skills' => [],
            default => ['PENDING', 'APPROVED', 'REJECTED'],
        };
    }
}
