<?php

namespace App\Support;

use App\Models\DemandForecast;
use App\Models\InventoryBatch;
use App\Models\LeaveRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Models\ProductionSchedule;
use App\Models\ProductionTask;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionMutation
{
    private function checkLeave(int $employee, $start, $end): void
    {
        if (LeaveRequest::where('employee_id', $employee)->where('status', 'APPROVED')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            StockLedger::fail('Karyawan sedang izin/cuti pada waktu tugas.');
        }
    }

    private function plan(int $id, string $m): ProductionPlan
    {
        $p = ProductionAccess::plans(auth()->user(), $m)->whereIn('status', ['APPROVED', 'IN_PROGRESS', 'COMPLETED'])->lockForUpdate()->find($id);
        if (! $p) {
            StockLedger::fail('Rencana harus disetujui dan berada dalam akses Anda.');
        }

        return $p;
    }

    public function save(string $m, Model $r, array $d, string $ability): Model
    {
        $bom = new ProductionBom;
        $lines = $d['lines'] ?? [];
        unset($d['lines']);
        $prepared = [];
        if (isset($d['unit_id'])) {
            $unit = OperationsContext::unit($m, $d['unit_id'], $ability);
            unset($d['unit_id']);
            if (! $unit->brand || $unit->brand->organization_id !== auth()->user()->organization_id || $unit->brand->status !== 'ACTIVE') {
                StockLedger::fail('Unit harus memiliki brand aktif.');
            }
            $field = $m === 'demand-forecasts' ? 'operational_unit_id' : 'production_unit_id';
            if ($r->exists && $unit->id !== $r->$field) {
                StockLedger::fail('Unit dokumen tidak dapat diganti.');
            }$d[$field] = $unit->id;
            $d['brand_id'] = $unit->brand_id;
        }
        if ($m === 'demand-forecasts') {
            $seen = [];
            foreach ($lines as $line) {
                $v = ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->where('brand_id', $unit->brand_id)->where('is_active', true))->find($line['product_variant_id']);
                if (! $v || isset($seen[$v->id])) {
                    StockLedger::fail('Varian tidak tersedia atau duplikat.', 'lines');
                }$seen[$v->id] = true;
                if ($line['override_quantity'] ?? null) {
                    if (empty($line['override_reason'])) {
                        StockLedger::fail('Override memerlukan alasan.', 'lines');
                    }
                }
                if (isset($line['override_quantity']) && empty($line['override_reason'])) {
                    StockLedger::fail('Override memerlukan alasan.', 'lines');
                }
                $raw = bcmul($line['baseline_quantity'], bcadd('1', bcdiv($line['safety_percent'], '100', 12), 12), 12);
                $scale = 4;
                if ($v->inventory_item_id) {
                    $item = (new StockLedger)->item($unit->id, $v->inventory_item_id);
                    $scale = min(4, $item->baseUom->decimal_places);
                    if (isset($line['override_quantity'])) {
                        StockLedger::precision($item, $line['override_quantity']);
                    }
                }
                $prepared[] = $line + ['recommended_quantity' => ProductionBom::roundUp($raw, $scale), 'overridden_by' => isset($line['override_quantity']) ? auth()->id() : null];
            }$d += ['method' => 'MANUAL', 'status' => 'DRAFT', 'generated_by' => auth()->id()];
        } elseif ($m === 'production-plans') {
            if ($r->exists && ($r->productionSchedules()->exists() || ProductionBatch::whereIn('production_plan_line_id', $r->productionPlanLines()->select('id'))->exists())) {
                StockLedger::fail('Rencana sudah memiliki jadwal/batch.');
            }
            if (! empty($d['demand_forecast_id'])) {
                $f = DemandForecast::whereIn('operational_unit_id', ProductionAccess::units(auth()->user(), $m)->select('id'))->where('brand_id', $unit->brand_id)->where('status', 'APPROVED')->whereDate('forecast_date', $d['production_date'])->find($d['demand_forecast_id']);
                if (! $f) {
                    StockLedger::fail('Forecast harus approved, sama brand/tanggal, dan dalam akses Anda.');
                }
            }
            if (! empty($d['target_ready_at']) && substr($d['target_ready_at'], 0, 10) !== $d['production_date']) {
                StockLedger::fail('Target siap harus pada tanggal produksi.');
            }
            $seen = [];
            foreach ($lines as $line) {
                $v = $bom->version($line['recipe_version_id'], $unit->id, $d['production_date']);
                $dest = OperationsContext::unit($m, $line['destination_unit_id'], $ability);
                if ($dest->brand_id !== $unit->brand_id) {
                    StockLedger::fail('Tujuan harus memiliki brand sama.');
                }$key = $v->id.':'.$dest->id;
                if (isset($seen[$key])) {
                    StockLedger::fail('Recipe/tujuan duplikat.');
                }$seen[$key] = true;
                $bom->requirements($v, $unit->id, $d['production_date'], $line['target_quantity']);
                $prepared[] = $line + ['planned_batches' => StockLedger::quantity(bcdiv($line['target_quantity'], $v->yield_quantity, 4)), 'status' => 'PLANNED'];
            }if (! $r->exists) {
                $d += ['number' => 'PP-'.Str::ulid(), 'status' => 'DRAFT', 'created_by' => auth()->id()];
            }
        } elseif ($m === 'production-schedules') {
            $p = $this->plan($d['production_plan_id'], $m);
            OperationsContext::unit($m, $p->production_unit_id, $ability);
            if ($r->exists && ($r->production_plan_id !== $p->id || $r->productionTasks()->exists())) {
                StockLedger::fail('Jadwal dengan tugas tidak dapat diubah.');
            }
            foreach (['starts_at', 'ends_at'] as $key) {
                $d[$key] = OperationsContext::dateTime($d[$key]);
            }
            if ($d['starts_at']->setTimezone(TradeMutation::timezone())->format('Y-m-d') !== $p->production_date->format('Y-m-d')) {
                StockLedger::fail('Jadwal harus dimulai pada tanggal produksi.');
            }
            if (ProductionSchedule::where('id', '!=', $r->id ?? 0)->where('status', '!=', 'CANCELLED')->whereHas('productionPlan', fn ($q) => $q->where('production_unit_id', $p->production_unit_id))->where('starts_at', '<', $d['ends_at'])->where('ends_at', '>', $d['starts_at'])->exists()) {
                StockLedger::fail('Jadwal produksi bertabrakan pada unit ini.');
            }
            $d['status'] = 'PLANNED';
        } elseif (in_array($m, ['production-tasks', 'packing-dispatch'], true)) {
            $schedule = ProductionSchedule::whereIn('production_plan_id', ProductionAccess::plans(auth()->user(), $m)->select('id'))->where('status', '!=', 'CANCELLED')->lockForUpdate()->findOrFail($d['production_schedule_id']);
            $p = $this->plan($schedule->production_plan_id, $m);
            OperationsContext::unit($m, $p->production_unit_id, $ability);
            if ($r->exists && $r->production_schedule_id !== $schedule->id) {
                StockLedger::fail('Jadwal tugas tidak dapat diganti.');
            }
            $line = $p->productionPlanLines()->find($d['production_plan_line_id']);
            if (! $line) {
                StockLedger::fail('Baris bukan milik rencana jadwal.');
            }
            foreach (['planned_start_at', 'planned_end_at'] as $key) {
                $d[$key] = OperationsContext::dateTime($d[$key]);
            }
            if ($d['planned_start_at'] < $schedule->starts_at || $d['planned_end_at'] > $schedule->ends_at) {
                StockLedger::fail('Tugas harus berada dalam rentang jadwal.');
            }
            if (! empty($d['assigned_employee_id'])) {
                OperationsContext::employeeAt($d['assigned_employee_id'], $p->production_unit_id, $d['planned_start_at'], $d['planned_end_at']);
                $this->checkLeave($d['assigned_employee_id'], $d['planned_start_at'], $d['planned_end_at']);
                if (ProductionTask::where('assigned_employee_id', $d['assigned_employee_id'])->where('id', '!=', $r->id ?? 0)->whereNotIn('status', ['CANCELLED', 'COMPLETED'])->where('planned_start_at', '<', $d['planned_end_at'])->where('planned_end_at', '>', $d['planned_start_at'])->exists()) {
                    StockLedger::fail('Karyawan memiliki tugas produksi lain pada waktu yang sama.');
                }
            }
            $dependency = $d['depends_on_task_id'] ?? null;
            unset($d['depends_on_task_id']);
            if ($dependency) {
                $prior = $schedule->productionTasks()->find($dependency);
                if (! $prior || $prior->id === $r->id || $prior->planned_end_at > $d['planned_start_at']) {
                    StockLedger::fail('Prasyarat harus dari jadwal sama dan selesai sebelum tugas ini.');
                }$stack = [$dependency];
                $seen = [];
                while ($stack) {
                    $id = array_pop($stack);
                    if ($id === $r->id) {
                        StockLedger::fail('Siklus prasyarat tugas.');
                    }if (isset($seen[$id])) {
                        continue;
                    }$seen[$id] = true;
                    foreach (DB::table('production_task_dependencies')->where('production_task_id', $id)->pluck('depends_on_task_id') as $next) {
                        $stack[] = $next;
                    }
                }
            }
            if ($r->exists && ProductionTask::whereIn('id', DB::table('production_task_dependencies')->where('depends_on_task_id', $r->id)->select('production_task_id'))->where(fn ($q) => $q->where('planned_start_at', '<', $d['planned_end_at'])->orWhere('status', '!=', 'PENDING'))->exists()) {
                StockLedger::fail('Perubahan bertabrakan dengan tugas yang bergantung pada tugas ini.');
            }
            $d['status'] = 'PENDING';
        } elseif ($m === 'production-batches') {
            $line = ProductionAccess::lines(auth()->user(), $m)->lockForUpdate()->findOrFail($d['production_plan_line_id']);
            $p = $this->plan($line->production_plan_id, $m);
            OperationsContext::unit($m, $p->production_unit_id, $ability);
            $v = $bom->version($line->recipe_version_id, $p->production_unit_id, $p->production_date->format('Y-m-d'));
            $allocated = '0';
            foreach (ProductionBatch::where('production_plan_line_id', $line->id)->whereNotIn('status', ['CANCELLED', 'REJECTED'])->lockForUpdate()->get() as $b) {
                $allocated = bcadd($allocated, $b->status === 'RELEASED' ? $b->actual_quantity : $b->planned_quantity, 4);
            }
            if (bccomp(bcadd($allocated, $d['planned_quantity'], 4), $line->target_quantity, 4) > 0) {
                StockLedger::fail('Alokasi batch melebihi target rencana.');
            }
            foreach ($bom->requirements($v, $p->production_unit_id, $p->production_date->format('Y-m-d'), $d['planned_quantity']) as $id => $qty) {
                $item = (new StockLedger)->item($p->production_unit_id, $id);
                $prepared[] = ['inventory_item_id' => $id, 'uom_id' => $item->base_uom_id, 'planned_quantity' => $qty];
            }
            $d += ['production_unit_id' => $p->production_unit_id, 'recipe_version_id' => $v->id, 'batch_no' => 'PB-'.Str::ulid(), 'status' => 'PLANNED', 'qc_status' => 'PENDING'];
        } elseif ($m === 'production-qc') {
            $batch = ProductionAccess::batches(auth()->user(), $m)->where('status', 'AWAITING_QC')->lockForUpdate()->find($d['production_batch_id']);
            if (! $batch) {
                StockLedger::fail('Batch harus menunggu QC.');
            }OperationsContext::unit($m, $batch->production_unit_id, $ability);
            $d += ['checked_by' => auth()->id(), 'checked_at' => now()];
        }
        if (! empty($d['target_ready_at'])) {
            $d['target_ready_at'] = OperationsContext::dateTime($d['target_ready_at']);
        }
        $r->fill($d)->save();
        if ($rel = ProductionDefinition::relation($m)) {
            $r->$rel()->delete();
            $r->$rel()->createMany($prepared);
        }
        if (in_array($m, ['production-tasks', 'packing-dispatch'], true)) {
            DB::table('production_task_dependencies')->where('production_task_id', $r->id)->delete();
            if ($dependency) {
                DB::table('production_task_dependencies')->insert(['production_task_id' => $r->id, 'depends_on_task_id' => $dependency]);
            }
        }
        if ($m === 'production-plans') {
            $this->materials($r);
        }

        return $r;
    }

    private function materials(ProductionPlan $p): void
    {
        $bom = new ProductionBom;
        $totals = [];
        foreach ($p->productionPlanLines as $line) {
            $v = $bom->version($line->recipe_version_id, $p->production_unit_id, $p->production_date->format('Y-m-d'));
            foreach ($bom->requirements($v, $p->production_unit_id, $p->production_date->format('Y-m-d'), $line->target_quantity) as $id => $qty) {
                $totals[$id] = StockLedger::quantity(bcadd($totals[$id] ?? '0', $qty, 4));
            }
        }
        if (! $totals) {
            StockLedger::fail('Rencana memerlukan baris recipe.');
        }$p->productionMaterialRequirements()->delete();
        foreach ($totals as $id => $qty) {
            $available = $bom->available($p->production_unit_id, $id);
            $short = bccomp($qty, $available, 4) > 0 ? bcsub($qty, $available, 4) : '0';
            $p->productionMaterialRequirements()->create(['inventory_item_id' => $id, 'uom_id' => (new StockLedger)->item($p->production_unit_id, $id)->base_uom_id, 'required_quantity' => $qty, 'available_quantity_snapshot' => $available, 'shortage_quantity' => $short, 'procurement_status' => bccomp($short, '0', 4) > 0 ? 'REQUIRED' : 'NOT_REQUIRED']);
        }
    }

    public function action(string $m, Model $r, string $a, array $d): void
    {
        if (in_array($a, ['delete', 'cancel'], true)) {
            $used = match ($m) {
                'demand-forecasts' => $r->productionPlans()->exists(),'production-plans' => $r->productionSchedules()->exists() || ProductionBatch::whereIn('production_plan_line_id', $r->productionPlanLines()->select('id'))->exists(),'production-schedules' => $r->productionTasks()->exists(),'production-tasks','packing-dispatch' => DB::table('production_task_dependencies')->where('depends_on_task_id', $r->id)->exists(),default => false
            };
            if ($used) {
                StockLedger::fail('Dokumen sudah digunakan oleh proses berikutnya.');
            }$a === 'delete' ? $r->delete() : $r->update(['status' => 'CANCELLED']);

            return;
        }
        if ($a === 'approve') {
            if ($m === 'production-plans') {
                $this->materials($r);
                $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            } else {
                $r->update(['status' => 'APPROVED']);
            }

            return;
        }
        if (in_array($m, ['production-tasks', 'packing-dispatch'], true)) {
            $p = $this->plan($r->productionSchedule->production_plan_id, $m);
            if ($r->productionSchedule->status === 'CANCELLED') {
                StockLedger::fail('Jadwal dibatalkan.');
            }
            if ($a === 'start') {
                if (ProductionTask::whereIn('id', DB::table('production_task_dependencies')->where('production_task_id', $r->id)->select('depends_on_task_id'))->where('status', '!=', 'COMPLETED')->exists()) {
                    StockLedger::fail('Selesaikan tugas prasyarat.');
                }
                if (in_array($r->task_type, ['PACKING', 'DISPATCH'], true) && $r->productionPlanLine->status !== 'COMPLETED') {
                    StockLedger::fail('Target hasil pada baris rencana harus selesai dan lolos QC.');
                }
                if ($r->assigned_employee_id) {
                    OperationsContext::employeeAt($r->assigned_employee_id, $p->production_unit_id, now(), now()->addMinute());
                    $this->checkLeave($r->assigned_employee_id, now(), now()->addMinute());
                }
                $r->update(['status' => 'IN_PROGRESS', 'actual_start_at' => now()]);
            } else {
                $r->update(['status' => 'COMPLETED', 'actual_end_at' => now()]);
            }

            return;
        }
        $line = $r->productionPlanLine()->lockForUpdate()->firstOrFail();
        $p = $this->plan($line->production_plan_id, $m);
        $bom = new ProductionBom;
        $ledger = new StockLedger;
        if ($a === 'start') {
            if ($p->production_date->format('Y-m-d') !== now(TradeMutation::timezone())->format('Y-m-d')) {
                StockLedger::fail('Batch hanya dapat dimulai pada tanggal produksi.');
            }
            $bom->version($r->recipe_version_id, $r->production_unit_id, $p->production_date->format('Y-m-d'));
            $bom->issue($r, $d['usages'], $d['usage_reason']);
            $r->update(['status' => 'IN_PROGRESS', 'started_at' => now()]);
            $p->update(['status' => 'IN_PROGRESS']);

            return;
        }
        $v = $r->recipeVersion;
        $output = $bom->output($v, $r->production_unit_id);
        if ($a === 'complete') {
            StockLedger::precision($output, $d['actual_quantity']);
            if (bccomp($d['actual_quantity'], $r->planned_quantity, 4) > 0) {
                StockLedger::fail('Hasil aktual tidak boleh melebihi target batch.');
            }if ($output->tracking_policy === 'LOT_EXPIRY' && empty($d['expires_on'])) {
                StockLedger::fail('Kedaluwarsa wajib untuk hasil dengan pelacakan kedaluwarsa.', 'expires_on');
            }
            $cost = '0';
            foreach ($r->productionMaterialUsages as $usage) {
                $cost = bcadd($cost, bcmul($usage->actual_quantity, $usage->unit_cost_snapshot, 8), 8);
            }
            $batch = InventoryBatch::create(['inventory_item_id' => $output->id, 'batch_no' => $r->batch_no, 'manufactured_on' => now(TradeMutation::timezone())->format('Y-m-d'), 'received_on' => now(TradeMutation::timezone())->format('Y-m-d'), 'expires_on' => $d['expires_on'] ?? null, 'unit_cost' => StockLedger::quantity(bcdiv($cost, $d['actual_quantity'], 4)), 'qc_status' => 'PENDING']);
            $ledger->post($r->production_unit_id, $output->id, $batch->id, $d['actual_quantity'], 'PRODUCTION_OUTPUT', $r, 'Menunggu QC', false);
            $balance = $ledger->balance($r->production_unit_id, $output->id, $batch->id);
            $balance->update(['quarantined' => $d['actual_quantity']]);
            $r->update(['status' => 'AWAITING_QC', 'actual_quantity' => $d['actual_quantity'], 'output_inventory_batch_id' => $batch->id, 'completed_at' => now()]);

            return;
        }
        $batch = InventoryBatch::where('inventory_item_id', $output->id)->lockForUpdate()->findOrFail($r->output_inventory_batch_id);
        if ($a === 'reject') {
            $batch->update(['qc_status' => 'REJECTED']);
            $r->update(['status' => 'REJECTED', 'qc_status' => 'FAIL']);

            return;
        }
        if ($a === 'release') {
            if (! $r->productionQcChecks()->where('check_type', 'FINAL')->where('result', 'PASS')->exists() || $r->productionQcChecks()->where('result', 'FAIL')->exists()) {
                StockLedger::fail('QC FINAL harus PASS dan tidak boleh ada pemeriksaan FAIL.');
            }
            if ($batch->expires_on && $batch->expires_on->format('Y-m-d') < now(TradeMutation::timezone())->format('Y-m-d')) {
                StockLedger::fail('Hasil sudah kedaluwarsa.');
            }
            $balance = $ledger->balance($r->production_unit_id, $output->id, $batch->id);
            if (bccomp($balance->quarantined, $r->actual_quantity, 4) < 0 || bccomp($balance->on_hand, $r->actual_quantity, 4) < 0) {
                StockLedger::fail('Saldo karantina berubah. Periksa hasil produksi.');
            }
            $balance->update(['quarantined' => bcsub($balance->quarantined, $r->actual_quantity, 4)]);
            $batch->update(['qc_status' => 'RELEASED']);
            $r->update(['status' => 'RELEASED', 'qc_status' => 'PASS']);
            $completed = bcadd($line->completed_quantity, $r->actual_quantity, 4);
            $line->update(['completed_quantity' => $completed, 'status' => bccomp($completed, $line->target_quantity, 4) >= 0 ? 'COMPLETED' : 'IN_PROGRESS']);
            if (! $p->productionPlanLines()->where('status', '!=', 'COMPLETED')->exists()) {
                $p->update(['status' => 'COMPLETED']);
            }
        }
    }
}
