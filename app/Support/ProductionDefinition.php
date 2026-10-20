<?php

namespace App\Support;

use App\Models;

class ProductionDefinition
{
    public const MODELS = ['demand-forecasts' => Models\DemandForecast::class, 'production-plans' => Models\ProductionPlan::class, 'production-material-requirements' => Models\ProductionMaterialRequirement::class, 'production-schedules' => Models\ProductionSchedule::class, 'production-tasks' => Models\ProductionTask::class, 'production-batches' => Models\ProductionBatch::class, 'production-qc' => Models\ProductionQCCheck::class, 'packing-dispatch' => Models\ProductionTask::class];

    public const LABELS = ['demand-forecasts' => 'Demand Forecast', 'production-plans' => 'Rencana Produksi', 'production-material-requirements' => 'Kebutuhan Bahan', 'production-schedules' => 'Jadwal Produksi', 'production-tasks' => 'Tugas Produksi', 'production-batches' => 'Batch Produksi', 'production-qc' => 'Quality Control', 'packing-dispatch' => 'Packing & Dispatch'];

    public static function fields(string $m): array
    {
        return match ($m) {
            'demand-forecasts' => ['unit_id' => ['Unit tujuan', 'select', true], 'forecast_date' => ['Tanggal forecast', 'date', true]],
            'production-plans' => ['unit_id' => ['Unit produksi', 'select', true], 'production_date' => ['Tanggal produksi', 'date', true], 'demand_forecast_id' => ['Forecast disetujui (opsional)', 'select', false], 'target_ready_at' => ['Target siap', 'datetime-local', false], 'notes' => ['Catatan', 'textarea', false]],
            'production-schedules' => ['production_plan_id' => ['Rencana produksi', 'select', true], 'starts_at' => ['Mulai', 'datetime-local', true], 'ends_at' => ['Selesai', 'datetime-local', true]],
            'production-tasks','packing-dispatch' => ['production_schedule_id' => ['Jadwal', 'select', true], 'production_plan_line_id' => ['Baris rencana', 'select', true], 'task_type' => ['Jenis tugas', 'select', true], 'name' => ['Nama tugas', 'text', true], 'planned_start_at' => ['Rencana mulai', 'datetime-local', true], 'planned_end_at' => ['Rencana selesai', 'datetime-local', true], 'required_crew' => ['Jumlah kru', 'number', true], 'assigned_employee_id' => ['Karyawan penanggung jawab', 'select', false], 'depends_on_task_id' => ['Tugas prasyarat', 'select', false]],
            'production-batches' => ['production_plan_line_id' => ['Baris rencana', 'select', true], 'planned_quantity' => ['Target batch (satuan hasil recipe)', 'decimal', true]],
            'production-qc' => ['production_batch_id' => ['Batch menunggu QC', 'select', true], 'check_type' => ['Jenis pemeriksaan', 'select', true], 'result' => ['Hasil', 'select', true], 'measured_value' => ['Nilai pengukuran', 'signed-decimal', false], 'unit' => ['Satuan pengukuran', 'text', false], 'notes' => ['Catatan pemeriksaan', 'textarea', true]],default => []
        };
    }

    public static function lineFields(string $m): array
    {
        return match ($m) {
            'demand-forecasts' => ['product_variant_id' => ['Varian produk', 'select', true], 'baseline_quantity' => ['Baseline manual', 'decimal', true], 'safety_percent' => ['Safety (%)', 'decimal', true], 'override_quantity' => ['Override kuantitas', 'optional-decimal', false], 'override_reason' => ['Alasan override', 'text', false]],
            'production-plans' => ['recipe_version_id' => ['Versi recipe disetujui', 'select', true], 'destination_unit_id' => ['Unit tujuan hasil', 'select', true], 'target_quantity' => ['Target hasil', 'decimal', true]],default => []
        };
    }

    public static function relation(string $m): ?string
    {
        return match ($m) {
            'demand-forecasts' => 'demandForecastLines','production-plans' => 'productionPlanLines','production-batches' => 'productionMaterialUsages',default => null
        };
    }

    public static function actions(string $m): array
    {
        return match ($m) {
            'demand-forecasts' => ['approve' => 'Setujui forecast'],
            'production-plans' => ['approve' => 'Setujui rencana', 'cancel' => 'Batalkan rencana'],
            'production-schedules' => ['cancel' => 'Batalkan jadwal'],
            'production-tasks','packing-dispatch' => ['start' => 'Mulai tugas', 'finish' => 'Selesaikan tugas', 'cancel' => 'Batalkan tugas'],
            'production-batches' => ['start' => 'Mulai & pakai bahan', 'complete' => 'Catat hasil & karantina', 'release' => 'Luluskan QC & rilis stok', 'reject' => 'Tolak batch', 'cancel' => 'Batalkan batch'],default => []
        };
    }
}
