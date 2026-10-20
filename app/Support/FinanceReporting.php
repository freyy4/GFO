<?php

namespace App\Support;

use App\Models\BoothPLSnapshot;
use App\Models\Expense;
use App\Models\PayrollAllocation;
use App\Models\SalesOrder;
use App\Models\SalesRefund;
use Carbon\CarbonImmutable;

class FinanceReporting
{
    public function calculate(BoothPLSnapshot $r): void
    {
        $start = CarbonImmutable::parse($r->period_start->format('Y-m-d'), TradeMutation::timezone());
        $end = CarbonImmutable::parse($r->period_end->format('Y-m-d'), TradeMutation::timezone());
        if ($end < $start || $end->toDateString() >= now(TradeMutation::timezone())->toDateString() || ($r->period_type === 'DAILY' && ! $start->isSameDay($end)) || ($r->period_type === 'MONTHLY' && ($start->day !== 1 || ! $start->isSameMonth($end) || $end->day !== $end->daysInMonth))) {
            StockLedger::fail('Gunakan satu hari lampau atau satu bulan lengkap yang sudah berakhir.');
        }
        $from = $start->utc();
        $until = $end->addDay()->utc();
        $org = auth()->user()->organization_id;
        $orders = SalesOrder::where('organization_id', $org)->where('operational_unit_id', $r->operational_unit_id)->whereIn('status', ['COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED'])->where('ordered_at', '>=', $from)->where('ordered_at', '<', $until)->get();
        $revenue = '0';
        $cogs = '0';
        $refunds = '0';
        foreach ($orders as $o) {
            $revenue = bcadd($revenue, bcsub($o->total_amount, $o->tax_amount, 2), 2);
            $cogs = bcadd($cogs, $o->cogs_amount, 2);
        }
        foreach (SalesRefund::where('status', 'REFUNDED')->where('refunded_at', '>=', $from)->where('refunded_at', '<', $until)->whereHas('salesOrder', fn ($q) => $q->where('organization_id', $org)->where('operational_unit_id', $r->operational_unit_id))->with('salesOrder')->get() as $refund) {
            $o = $refund->salesOrder;
            if (bccomp($o->total_amount, '0', 2) > 0) {
                $refunds = bcadd($refunds, StockLedger::money(bcdiv(bcmul($refund->amount, bcsub($o->total_amount, $o->tax_amount, 2), 8), $o->total_amount, 8)), 2);
            }
        }$revenue = bcsub($revenue, $refunds, 2);
        $groups = ['rent_cost' => '0', 'utility_cost' => '0', 'security_cleaning_cost' => '0', 'maintenance_cost' => '0', 'marketing_cost' => '0', 'other_opex' => '0'];
        $fixed = '0';
        $variable = '0';
        foreach (Expense::where('organization_id', $org)->where('operational_unit_id', $r->operational_unit_id)->whereIn('status', ['APPROVED', 'PAID'])->whereDate('expense_date', '>=', $start->toDateString())->whereDate('expense_date', '<=', $end->toDateString())->whereHas('expenseCategory', fn ($q) => $q->where('organization_id', $org))->with('expenseCategory')->get() as $e) {
            $key = match ($e->expenseCategory->pl_group) {
                'RENT' => 'rent_cost','UTILITY' => 'utility_cost','SECURITY_CLEANING' => 'security_cleaning_cost','MAINTENANCE' => 'maintenance_cost','MARKETING' => 'marketing_cost',default => 'other_opex'
            };
            $groups[$key] = bcadd($groups[$key], $e->amount, 2);
            if ($e->expenseCategory->is_fixed_cost) {
                $fixed = bcadd($fixed, $e->amount, 2);
            } else {
                $variable = bcadd($variable, $e->amount, 2);
            }
        }
        $labor = '0';
        foreach (PayrollAllocation::where('operational_unit_id', $r->operational_unit_id)->whereHas('payslip.payrollRun', fn ($q) => $q->whereIn('status', ['APPROVED', 'PAID'])->whereHas('payrollPeriod', fn ($q) => $q->where('organization_id', $org)->whereDate('starts_on', '<=', $end->toDateString())->whereDate('ends_on', '>=', $start->toDateString())))->with('payslip.payrollRun.payrollPeriod')->get() as $a) {
            $p = $a->payslip->payrollRun->payrollPeriod;
            $pStart = CarbonImmutable::parse($p->starts_on->format('Y-m-d'));
            $pEnd = CarbonImmutable::parse($p->ends_on->format('Y-m-d'));
            $overlapStart = max($pStart->toDateString(), $start->toDateString());
            $overlapEnd = min($pEnd->toDateString(), $end->toDateString());
            $days = (int) CarbonImmutable::parse($overlapStart)->diffInDays(CarbonImmutable::parse($overlapEnd)) + 1;
            $allDays = (int) $pStart->diffInDays($pEnd) + 1;
            $labor = bcadd($labor, StockLedger::money(bcdiv(bcmul($a->allocated_amount, (string) $days, 6), (string) $allDays, 8)), 2);
        }
        $fixed = bcadd($fixed, $labor, 2);
        $opex = '0';
        foreach ($groups as $v) {
            $opex = bcadd($opex, $v, 2);
        }$gross = bcsub($revenue, $cogs, 2);
        $contribution = bcsub(bcsub($gross, $labor, 2), $opex, 2);
        $margin = bcsub(bcsub($revenue, $cogs, 2), $variable, 2);
        $beSales = null;
        $beOrders = null;
        if (bccomp($revenue, '0', 2) > 0 && bccomp($margin, '0', 2) > 0) {
            $beSales = StockLedger::money(bcdiv(bcmul($fixed, $revenue, 8), $margin, 8));
            if ($orders->count()) {
                $beOrders = StockLedger::quantity(bcdiv(bcmul($beSales, (string) $orders->count(), 8), $revenue, 4));
            }
        }
        $r->fill($groups + ['revenue' => StockLedger::signedMoney($revenue), 'cogs' => StockLedger::money($cogs), 'gross_profit' => StockLedger::signedMoney($gross), 'labor_cost' => StockLedger::money($labor), 'contribution' => StockLedger::signedMoney($contribution), 'break_even_sales' => $beSales, 'break_even_orders' => $beOrders, 'calculated_at' => now(), 'calculation_detail' => ['basis' => 'Pendapatan tanpa pajak, refund berdasarkan tanggal refund; HPP tidak dibalik karena refund tidak mengembalikan stok. Expense approved/paid berdasarkan tanggal expense. Biaya tenaga kerja disebar menurut hari kalender periode payroll.', 'order_count' => $orders->count(), 'refunds_excluding_tax' => $refunds, 'fixed_cost' => $fixed, 'variable_opex' => $variable, 'labor_assumption' => 'Biaya tenaga kerja dianggap tetap untuk estimasi break-even.', 'break_even_note' => $beSales === null ? 'Belum dapat dihitung: pendapatan/margin kontribusi tidak positif.' : 'Estimasi berdasarkan bauran penjualan periode ini.']])->save();
    }
}
