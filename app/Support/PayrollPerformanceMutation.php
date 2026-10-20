<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KPIDefinition;
use App\Models\KPIResult;
use App\Models\KPITarget;
use App\Models\PayrollPeriod;
use App\Models\PerformanceScore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class PayrollPerformanceMutation
{
    private function employee(string $m, int $id, string $ability): Employee
    {
        $e = AccountingAccess::employees(auth()->user(), $m)->where('status', 'ACTIVE')->find($id);
        if (! $e) {
            StockLedger::fail('Karyawan tidak tersedia.', 'employee_id');
        }AccountingAccess::unit($m, $e->home_unit_id, $ability);

        return $e;
    }

    private function targets(Employee $e, string $start, string $end)
    {
        return KPITarget::where('targetable_type', $e->getMorphClass())->where('targetable_id', $e->id)->whereDate('period_start', $start)->whereDate('period_end', $end)->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', auth()->user()->organization_id));
    }

    private function results(int $employee, string $start, string $end)
    {
        return KPIResult::where('measurable_type', (new Employee)->getMorphClass())->where('measurable_id', $employee)->whereDate('period_start', $start)->whereDate('period_end', $end)->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', auth()->user()->organization_id));
    }

    private function score(PerformanceScore $r): void
    {
        $e = $r->employee;
        $start = $r->period_start->format('Y-m-d');
        $end = $r->period_end->format('Y-m-d');
        $targets = $this->targets($e, $start, $end)->get();
        if ($targets->isEmpty()) {
            StockLedger::fail('Belum ada target KPI pada periode tepat ini.');
        }
        $parts = [];
        $total = '0';
        $weight = '0';
        foreach ($targets as $t) {
            $result = $this->results($e->id, $start, $end)->where('kpi_definition_id', $t->kpi_definition_id)->where('status', 'APPROVED')->first();
            if (! $result) {
                StockLedger::fail('Semua target harus mempunyai hasil disetujui sebelum skor dihitung.');
            }$w = (string) ($result->calculation_detail['weight'] ?? '0');
            if (bccomp($w, '0', 4) <= 0) {
                StockLedger::fail('Bobot snapshot KPI tidak valid.');
            }$total = bcadd($total, bcmul($result->score, $w, 8), 8);
            $weight = bcadd($weight, $w, 4);
            $parts[] = ['result_id' => $result->id, 'name' => $result->calculation_detail['name'] ?? $result->kpiDefinition->name, 'score' => $result->score, 'weight' => $w];
        }
        $r->total_score = bcdiv($total, $weight, 4);
        $r->rating = null;
        $r->score_breakdown = ['method' => 'Rata-rata berbobot; skor KPI dibatasi 0–100.', 'results' => $parts, 'calculated_by' => auth()->id(), 'calculated_at' => now()->toIso8601String()];
    }

    public function save(string $m, Model $r, array $d, string $a): void
    {
        if ($m === 'payroll-periods') {
            if (CarbonImmutable::parse($d['starts_on'])->diffInDays(CarbonImmutable::parse($d['ends_on'])) > 365) {
                StockLedger::fail('Periode maksimal 366 hari.');
            }
            if ($r->exists && $r->payrollRuns()->exists()) {
                StockLedger::fail('Periode sudah memiliki proses payroll.');
            }
            if (PayrollPeriod::where('organization_id', auth()->user()->organization_id)->where('id', '!=', $r->id ?? 0)->whereDate('starts_on', '<=', $d['ends_on'])->whereDate('ends_on', '>=', $d['starts_on'])->exists()) {
                StockLedger::fail('Periode payroll tumpang tindih.');
            }$d += ['organization_id' => auth()->user()->organization_id, 'status' => 'OPEN'];
        } elseif ($m === 'payroll-runs') {
            $period = PayrollPeriod::where('organization_id', auth()->user()->organization_id)->where('status', 'OPEN')->lockForUpdate()->find($d['payroll_period_id']);
            if (! $period) {
                StockLedger::fail('Periode tidak tersedia atau terkunci.');
            }
            if ($period->payrollRuns()->where('status', '!=', 'CANCELLED')->exists()) {
                StockLedger::fail('Periode sudah memiliki proses payroll aktif.');
            }$d += ['run_no' => ($period->payrollRuns()->max('run_no') ?? 0) + 1, 'status' => 'DRAFT', 'created_by' => auth()->id()];
        } elseif ($m === 'kpi-definitions') {
            if ($r->exists && ($r->kpiTargets()->exists() || $r->kpiResults()->exists())) {
                StockLedger::fail('KPI sudah digunakan; buat definisi baru untuk formula berbeda.');
            }$d['code'] = strtoupper(trim($d['code']));
            if (KPIDefinition::where('organization_id', auth()->user()->organization_id)->whereRaw('LOWER(code) = ?', [strtolower($d['code'])])->where('id', '!=', $r->id ?? 0)->exists()) {
                StockLedger::fail('Kode KPI sudah dipakai.');
            }$d += ['organization_id' => auth()->user()->organization_id, 'data_source' => 'MANUAL', 'calculation_type' => 'TARGET_RATIO', 'is_active' => true];
        } elseif ($m === 'kpi-targets') {
            $e = $this->employee($m, $d['employee_id'], $a);
            unset($d['employee_id']);
            if (PerformanceScore::where('employee_id', $e->id)->where('status', 'APPROVED')->whereDate('period_start', $d['period_start'])->whereDate('period_end', $d['period_end'])->exists()) {
                StockLedger::fail('Periode performance sudah disetujui; target tidak dapat diubah.');
            }$k = KPIDefinition::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->find($d['kpi_definition_id']);
            if (! $k) {
                StockLedger::fail('KPI tidak tersedia.');
            }
            if ($r->exists && $this->results($r->targetable_id, $r->period_start->format('Y-m-d'), $r->period_end->format('Y-m-d'))->where('kpi_definition_id', $r->kpi_definition_id)->exists()) {
                StockLedger::fail('Target sudah memiliki hasil.');
            }
            if (KPITarget::where('kpi_definition_id', $k->id)->where('targetable_type', $e->getMorphClass())->where('targetable_id', $e->id)->where('id', '!=', $r->id ?? 0)->whereDate('period_start', '<=', $d['period_end'])->whereDate('period_end', '>=', $d['period_start'])->exists()) {
                StockLedger::fail('Periode target KPI karyawan bertumpuk.');
            }$d += ['targetable_type' => $e->getMorphClass(), 'targetable_id' => $e->id];
        } elseif ($m === 'kpi-results') {
            $target = KPITarget::where('targetable_type', (new Employee)->getMorphClass())->whereIn('targetable_id', AccountingAccess::employees(auth()->user(), $m)->select('id'))->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', auth()->user()->organization_id))->find($d['target_id']);
            if (! $target) {
                StockLedger::fail('Target tidak tersedia.');
            }$this->employee($m, $target->targetable_id, $a);
            if ($target->period_end->format('Y-m-d') > now(TradeMutation::timezone())->format('Y-m-d')) {
                StockLedger::fail('Hasil dicatat setelah periode berakhir.');
            }
            if ($this->results($target->targetable_id, $target->period_start->format('Y-m-d'), $target->period_end->format('Y-m-d'))->where('kpi_definition_id', $target->kpi_definition_id)->exists()) {
                StockLedger::fail('Hasil KPI sudah dicatat.');
            }
            $k = $target->kpiDefinition;
            $actual = $d['actual_value'];
            $targetValue = $target->target_value;
            if (bccomp($targetValue, '0', 4) <= 0) {
                StockLedger::fail('Target KPI harus positif.');
            }
            $score = $k->direction === 'LOWER_BETTER' ? (bccomp($actual, '0', 4) === 0 ? '100' : bcdiv(bcmul($targetValue, '100', 8), $actual, 4)) : bcdiv(bcmul($actual, '100', 8), $targetValue, 4);
            if (bccomp($score, '100', 4) > 0) {
                $score = '100';
            }
            $d = ['kpi_definition_id' => $k->id, 'measurable_type' => (new Employee)->getMorphClass(), 'measurable_id' => $target->targetable_id, 'period_start' => $target->period_start, 'period_end' => $target->period_end, 'target_value' => $targetValue, 'actual_value' => $actual, 'score' => $score, 'status' => 'DRAFT', 'calculation_detail' => ['target_id' => $target->id, 'name' => $k->name, 'weight' => $k->weight, 'direction' => $k->direction, 'evidence' => $d['evidence'], 'submitted_by' => auth()->id(), 'formula' => $k->direction === 'LOWER_BETTER' ? 'min(100, target / aktual × 100); aktual 0 = 100' : 'min(100, aktual / target × 100)']];
        } elseif ($m === 'performance-scores') {
            $this->employee($m, $d['employee_id'], $a);
            $r->fill($d);
            $this->score($r);
            $r->status = 'DRAFT';
            $r->save();

            return;
        } elseif ($m === 'employee-recognitions') {
            $this->employee($m, $d['employee_id'], $a);
            $d += ['awarded_by' => auth()->id(), 'awarded_at' => now()];
        }
        $r->fill($d)->save();
    }

    public function action(string $m, Model $r, string $a, array $d): void
    {
        if ($a === 'delete') {
            $used = match ($m) {
                'payroll-periods' => $r->payrollRuns()->exists(),'kpi-definitions' => $r->kpiTargets()->exists() || $r->kpiResults()->exists(),'kpi-targets' => $this->results($r->targetable_id, $r->period_start->format('Y-m-d'), $r->period_end->format('Y-m-d'))->where('kpi_definition_id', $r->kpi_definition_id)->exists(),default => false
            };
            if ($used) {
                StockLedger::fail('Data sudah digunakan.');
            }$r->delete();

            return;
        }
        if (in_array($a, ['activate', 'deactivate'], true)) {
            $r->update(['is_active' => $a === 'activate']);

            return;
        }
        if ($a === 'lock') {
            if (! $r->payrollRuns()->whereIn('status', ['APPROVED', 'PAID'])->exists() || $r->payrollRuns()->whereIn('status', ['DRAFT', 'CALCULATED'])->exists()) {
                StockLedger::fail('Selesaikan dan setujui payroll sebelum mengunci periode.');
            }$r->update(['status' => 'LOCKED', 'locked_at' => now(), 'locked_by' => auth()->id()]);

            return;
        }
        if ($m === 'kpi-results') {
            if (($r->calculation_detail['submitted_by'] ?? null) === auth()->id()) {
                StockLedger::fail('Hasil KPI harus diperiksa pengguna lain.');
            }$detail = $r->calculation_detail;
            $detail['approved_by'] = auth()->id();
            $detail['approved_at'] = now()->toIso8601String();
            $r->update(['status' => 'APPROVED', 'calculation_detail' => $detail]);

            return;
        }
        if ($m === 'performance-scores') {
            if (($r->score_breakdown['calculated_by'] ?? null) === auth()->id()) {
                StockLedger::fail('Skor performance harus diperiksa pengguna lain.');
            }$this->score($r);
            $r->fill(['status' => 'APPROVED', 'approved_by' => auth()->id()])->save();

            return;
        }
        if ($m === 'payroll-runs') {
            if ($a === 'calculate') {
                StockLedger::fail('Formula payroll perusahaan belum dikonfigurasi. Perhitungan ditahan sampai formula gaji, tunjangan, potongan, dan pembulatan ditetapkan.');
            }
            if ($a === 'cancel') {
                if ($r->status === 'APPROVED' || $r->payslips()->where('status','PAID')->exists()) {
                    StockLedger::fail('Payroll yang disetujui/dibayar tidak dapat dibatalkan.');
                }$r->update(['status' => 'CANCELLED']);

                return;
            }
            StockLedger::fail('Validasi formula payroll diperlukan sebelum persetujuan atau pembayaran.');
        }
    }
}
