<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\CampaignMetric;
use App\Models\Employee;
use App\Models\ProductionPlan;
use App\Models\SalesOrder;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardData
{
    public function build(User $user, string $domain, array $filters, string $timezone): array
    {
        $units = DashboardAccess::units($user, $domain)->orderBy('name')->get(['id', 'name', 'status', 'type']);
        request()->attributes->set('dashboard_units_'.$domain, $units);
        $unitQuery = DashboardAccess::units($user, $domain)->when($filters['unit'] ?? null, fn ($q, $id) => $q->whereKey($id));
        $unitIds = (clone $unitQuery)->select('id');
        $currency = $user->organization?->currency ?? 'IDR';
        $base = compact('units', 'filters', 'domain', 'timezone', 'currency');
        if ($domain === 'overview') {
            $query = $unitQuery;
            $statuses = (clone $query)->distinct()->pluck('status')->sort()->values();
            $this->filter($query, $filters, ['code', 'name']);
            $rows = (clone $query)->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
            $cards = [
                ['Unit dalam hasil', $rows->total(), 'count'],
                ['Unit aktif dalam hasil', (clone $query)->where('status', 'ACTIVE')->count(), 'count'],
            ];

            return $base + ['title' => 'Dashboard', 'cards' => $cards, 'statuses' => $statuses,
                'rows' => $rows,
                'distribution' => [], 'note' => 'Ringkasan unit saat ini, berdasarkan akses dashboard dan organization Anda.',
                'tableTitle' => 'Unit dalam cakupan akses'];
        }
        $from = CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc();
        $until = CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc();
        $dates = [$filters['from'], $filters['to']];
        $org = $user->organization_id;
        if ($domain === 'operations') {
            $query = SalesOrder::where('organization_id', $org)->whereIn('operational_unit_id', $unitIds)
                ->whereHas('brand', fn ($q) => $q->withTrashed()->where('organization_id', $org))
                ->where('ordered_at', '>=', $from)->where('ordered_at', '<', $until);
            $statuses = (clone $query)->distinct()->pluck('status')->sort()->values();
            $this->filter($query, $filters, ['number']);
            $completed = (clone $query)->where('status', 'COMPLETED');
            $cards = [
                ['Pesanan dalam hasil', (clone $query)->count(), 'count'],
                ['Nilai pesanan COMPLETED', (clone $completed)->sum('total_amount'), 'money'],
                ['Rata-rata pesanan COMPLETED', (clone $completed)->avg('total_amount'), 'money'],
                ['Rencana produksi dalam periode', ProductionPlan::whereIn('production_unit_id', $unitIds)
                    ->whereHas('brand', fn ($q) => $q->withTrashed()->where('organization_id', $org))->whereBetween('production_date', $dates)->count(), 'count'],
            ];
            $rows = (clone $query)->with('operationalUnit:id,name')->orderByDesc('ordered_at')->orderByDesc('id')->paginate(15)->withQueryString();
            $note = 'Nilai penjualan adalah total pesanan COMPLETED, bukan penerimaan kas atau penjualan bersih setelah refund. Pencarian/status berlaku pada pesanan; jumlah rencana produksi hanya mengikuti periode dan unit.';
            $tableTitle = 'Daftar pesanan';
        } elseif ($domain === 'hr') {
            $query = AttendanceRecord::whereIn('operational_unit_id', $unitIds)
                ->whereHas('employee', fn ($q) => $q->withTrashed()->where('organization_id', $org))
                ->whereBetween('business_date', $dates);
            $statuses = (clone $query)->distinct()->pluck('status')->sort()->values();
            if (! empty($filters['search'])) {
                $query->whereHas('employee', fn ($q) => $q->withTrashed()->where('organization_id', $org)->where(fn ($q) => $q
                    ->where('full_name', 'like', '%'.$filters['search'].'%')->orWhere('employee_no', 'like', '%'.$filters['search'].'%')));
            }
            $this->filter($query, array_diff_key($filters, ['search' => true]), []);
            $employees = Employee::where('organization_id', $org)->where('status', 'ACTIVE');
            if (! DashboardAccess::wide($user, $domain) || ! empty($filters['unit'])) {
                $employees->whereIn('home_unit_id', $unitIds);
            }
            $cards = [
                ['Karyawan aktif saat ini', $employees->count(), 'count'],
                ['Catatan kehadiran dalam hasil', (clone $query)->count(), 'count'],
                ['Catatan terlambat dalam hasil', (clone $query)->where('late_minutes', '>', 0)->count(), 'count'],
                ['Shift dimulai dalam periode', ShiftAssignment::whereIn('operational_unit_id', $unitIds)
                    ->where(fn ($q) => $q->whereNull('employee_id')->orWhereHas('employee', fn ($q) => $q->withTrashed()->where('organization_id', $org)))
                    ->where('starts_at', '>=', $from)->where('starts_at', '<', $until)->count(), 'count'],
            ];
            $rows = (clone $query)->with(['employee' => fn ($q) => $q->withTrashed()->select('id', 'full_name', 'employee_no'), 'operationalUnit:id,name'])
                ->orderByDesc('business_date')->orderByDesc('id')->paginate(15)->withQueryString();
            $note = 'Karyawan aktif adalah kondisi saat ini berdasarkan unit asal. Kehadiran mengikuti unit tempat bekerja dan business date. Pencarian/status hanya memfilter kehadiran; jumlah shift mengikuti periode dan unit. Data gaji tidak ditampilkan.';
            $tableTitle = 'Catatan kehadiran';
        } else {
            $query = Campaign::whereHas('brand', fn ($q) => $q->withTrashed()->where('organization_id', $org));
            if (! DashboardAccess::wide($user, $domain) || ! empty($filters['unit'])) {
                $query->whereHas('campaignTargets')->whereDoesntHave('campaignTargets', fn ($q) => $q
                    ->whereNull('operational_unit_id')->orWhereNotIn('operational_unit_id', $unitIds));
            }
            $query->where(fn ($q) => $q->where(fn ($q) => $q->where('starts_at', '<', $until)->where('ends_at', '>=', $from))
                ->orWhereHas('campaignMetrics', fn ($q) => $q->whereBetween('metric_date', $dates)));
            $statuses = (clone $query)->distinct()->pluck('status')->sort()->values();
            $this->filter($query, $filters, ['code', 'name']);
            $metrics = CampaignMetric::whereIn('campaign_id', (clone $query)->select('id'))->whereBetween('metric_date', $dates);
            $totals = (clone $metrics)->selectRaw('COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(attributed_revenue), 0) as revenue, COALESCE(SUM(conversions), 0) as conversions, COUNT(*) as samples')->first();
            $cards = [
                ['Campaign dalam hasil', (clone $query)->count(), 'count'],
                ['Spend tercatat', $totals->samples ? $totals->spend : null, 'money'],
                ['Revenue teratribusi', $totals->samples ? $totals->revenue : null, 'money'],
                ['ROAS (revenue ÷ spend)', (float) $totals->spend > 0 ? (float) $totals->revenue / (float) $totals->spend : null, 'ratio'],
            ];
            $rows = (clone $query)->withSum(['campaignMetrics as period_spend' => fn ($q) => $q->whereBetween('metric_date', $dates)], 'spend')
                ->withSum(['campaignMetrics as period_revenue' => fn ($q) => $q->whereBetween('metric_date', $dates)], 'attributed_revenue')
                ->orderByDesc('starts_at')->orderByDesc('id')->paginate(15)->withQueryString();
            $note = 'Spend dan revenue berasal dari campaign_metrics pada periode terpilih; ROAS dihitung dari total revenue ÷ total spend. Tanpa metrik ditampilkan —. Filter unit hanya mencakup campaign yang seluruh target unitnya berada dalam cakupan pilihan; metrik tidak tersedia per unit.';
            $tableTitle = 'Campaign dan metrik periode';
        }
        $distribution = (clone $query)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->orderByDesc('total')->get();

        return $base + ['title' => DashboardAccess::LABELS[$domain]] + compact('cards', 'statuses', 'rows', 'note', 'tableTitle', 'distribution');
    }

    private function filter(Builder $query, array $filters, array $searchColumns): void
    {
        if (! empty($filters['search']) && $searchColumns) {
            $query->where(function ($q) use ($filters, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%'.$filters['search'].'%');
                }
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }
}
