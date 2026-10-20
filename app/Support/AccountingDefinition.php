<?php

namespace App\Support;

use App\Models;

class AccountingDefinition
{
    public const MODELS = [
        'payroll-periods' => Models\PayrollPeriod::class, 'payroll-runs' => Models\PayrollRun::class, 'payslips' => Models\Payslip::class, 'payroll-allocations' => Models\PayrollAllocation::class,
        'kpi-definitions' => Models\KPIDefinition::class, 'kpi-targets' => Models\KPITarget::class, 'kpi-results' => Models\KPIResult::class, 'performance-scores' => Models\PerformanceScore::class, 'employee-recognitions' => Models\EmployeeRecognition::class,
        'expense-categories' => Models\ExpenseCategory::class, 'expenses' => Models\Expense::class, 'bills' => Models\Bill::class, 'recurring-obligations' => Models\RecurringObligation::class, 'petty-cash' => Models\PettyCashTransaction::class, 'booth-pl' => Models\BoothPLSnapshot::class, 'break-even' => Models\BoothPLSnapshot::class, 'payment-reconciliations' => Models\PaymentSettlement::class,
    ];

    public const LABELS = ['payroll-periods' => 'Periode Payroll', 'payroll-runs' => 'Proses Payroll', 'payslips' => 'Slip Gaji', 'payroll-allocations' => 'Alokasi Biaya Karyawan', 'kpi-definitions' => 'Master KPI', 'kpi-targets' => 'Target KPI', 'kpi-results' => 'Hasil KPI', 'performance-scores' => 'Performance Score', 'employee-recognitions' => 'Reward & Recognition', 'finance' => 'Ringkasan Keuangan', 'expense-categories' => 'Kategori Expense', 'expenses' => 'Expense', 'bills' => 'Tagihan', 'recurring-obligations' => 'Biaya Berulang', 'petty-cash' => 'Petty Cash', 'booth-pl' => 'Booth P&L', 'break-even' => 'Break-even', 'payment-reconciliations' => 'Rekonsiliasi Pembayaran'];

    public static function payroll(string $m): bool
    {
        return in_array($m, array_slice(array_keys(self::MODELS), 0, 9), true);
    }

    public static function readOnly(string $m): bool
    {
        return in_array($m, ['finance', 'payslips', 'payroll-allocations', 'break-even', 'payment-reconciliations'], true);
    }

    public static function global(string $m): bool
    {
        return in_array($m, ['payroll-periods', 'payroll-runs', 'kpi-definitions', 'expense-categories'], true);
    }

    public static function fields(string $m): array
    {
        $unit = ['Unit', 'select', true];
        $employee = ['Karyawan', 'select', true];
        $start = ['Awal periode', 'date', true];
        $end = ['Akhir periode', 'date', true];
        $category = ['Kategori biaya', 'select', true];

        return match ($m) {
            'payroll-periods' => ['name' => ['Nama periode', 'text', true], 'starts_on' => $start, 'ends_on' => $end, 'pay_date' => ['Tanggal pembayaran', 'date', false]],
            'payroll-runs' => ['payroll_period_id' => ['Periode payroll', 'select', true]],
            'kpi-definitions' => ['code' => ['Kode', 'text', true], 'name' => ['Nama', 'text', true], 'weight' => ['Bobot', 'decimal', true], 'direction' => ['Arah penilaian', 'select', true]],
            'kpi-targets' => ['kpi_definition_id' => ['KPI', 'select', true], 'employee_id' => $employee, 'period_start' => $start, 'period_end' => $end, 'target_value' => ['Target (> 0)', 'decimal', true]],
            'kpi-results' => ['target_id' => ['Target KPI', 'select', true], 'actual_value' => ['Realisasi', 'decimal', true], 'evidence' => ['Dasar/bukti realisasi', 'textarea', true]],
            'performance-scores' => ['employee_id' => $employee, 'period_start' => $start, 'period_end' => $end],
            'employee-recognitions' => ['employee_id' => $employee, 'type' => ['Jenis penghargaan', 'select', true], 'title' => ['Judul', 'text', true], 'description' => ['Alasan penghargaan', 'textarea', true], 'reward_amount' => ['Nominal penghargaan', 'money', true]],
            'expense-categories' => ['code' => ['Kode', 'text', true], 'name' => ['Nama', 'text', true], 'pl_group' => ['Kelompok P&L', 'select', true], 'is_fixed_cost' => ['Biaya tetap', 'select', true]],
            'expenses' => ['unit_id' => $unit, 'expense_category_id' => $category, 'supplier_id' => ['Supplier', 'select', false], 'expense_date' => ['Tanggal biaya', 'date', true], 'amount' => ['Nominal', 'money', true], 'description' => ['Keterangan', 'textarea', true]],
            'bills' => ['unit_id' => $unit, 'expense_category_id' => $category, 'recurring_obligation_id' => ['Kewajiban berulang', 'select', false], 'bill_no' => ['Nomor tagihan', 'text', true], 'period_start' => $start, 'period_end' => $end, 'amount' => ['Nominal', 'money', true], 'due_date' => ['Jatuh tempo', 'date', true]],
            'recurring-obligations' => ['unit_id' => $unit, 'expense_category_id' => $category, 'supplier_id' => ['Supplier', 'select', false], 'type' => ['Jenis', 'select', true], 'account_or_contract_no' => ['Nomor akun/kontrak', 'text', false], 'billing_cycle' => ['Siklus', 'select', true], 'expected_amount' => ['Estimasi nominal', 'money', true], 'due_day' => ['Tanggal jatuh tempo (1–28)', 'number', true], 'starts_on' => $start, 'ends_on' => ['Tanggal berakhir', 'date', false]],
            'petty-cash' => ['unit_id' => $unit, 'amount' => ['Dana masuk', 'money', true], 'description' => ['Referensi dan keterangan dana masuk', 'textarea', true]],
            'booth-pl' => ['unit_id' => $unit, 'period_type' => ['Jenis periode', 'select', true], 'period_start' => $start, 'period_end' => $end],
            default => [],
        };
    }

    public static function actions(string $m): array
    {
        return match ($m) {
            'payroll-periods' => ['lock' => 'Kunci periode'],
            'payroll-runs' => ['calculate' => 'Hitung payroll', 'approve' => 'Setujui payroll', 'pay' => 'Catat pembayaran payroll', 'cancel' => 'Batalkan'],
            'kpi-definitions' => ['activate' => 'Aktifkan', 'deactivate' => 'Nonaktifkan'],
            'kpi-results','performance-scores' => ['approve' => 'Setujui hasil'],
            'expenses' => ['submit' => 'Ajukan', 'approve' => 'Setujui', 'reject' => 'Tolak', 'pay' => 'Catat pembayaran', 'cancel' => 'Batalkan'],
            'bills' => ['pay' => 'Catat pembayaran tagihan', 'cancel' => 'Batalkan'],
            'recurring-obligations' => ['generate' => 'Buat tagihan periode', 'activate' => 'Aktifkan', 'deactivate' => 'Nonaktifkan'],
            'booth-pl' => ['recalculate' => 'Hitung ulang snapshot'],default => [],
        };
    }

    public static function options(): array
    {
        return ['direction' => ['HIGHER_BETTER' => 'Lebih tinggi lebih baik', 'LOWER_BETTER' => 'Lebih rendah lebih baik'], 'is_fixed_cost' => ['0' => 'Variabel', '1' => 'Tetap'], 'pl_group' => ['RENT' => 'Sewa', 'UTILITY' => 'Utilitas', 'SECURITY_CLEANING' => 'Keamanan & kebersihan', 'MAINTENANCE' => 'Pemeliharaan', 'MARKETING' => 'Pemasaran', 'OTHER_OPEX' => 'Operasional lainnya'], 'type' => ['RENT' => 'Sewa', 'UTILITY' => 'Utilitas', 'SERVICE' => 'Jasa', 'OTHER' => 'Lainnya', 'REWARD' => 'Reward', 'RECOGNITION' => 'Recognition'], 'billing_cycle' => ['MONTHLY' => 'Bulanan'], 'period_type' => ['DAILY' => 'Harian', 'MONTHLY' => 'Bulanan'], 'payment_method' => ['BANK_TRANSFER' => 'Transfer bank', 'CASH' => 'Tunai (di luar petty cash)', 'PETTY_CASH' => 'Petty cash']];
    }

    public static function statuses(string $m): array
    {
        return match ($m) {
            'payroll-periods' => ['OPEN', 'LOCKED'],'payroll-runs' => ['DRAFT', 'CALCULATED', 'APPROVED', 'PAID', 'CANCELLED'],'payslips' => ['DRAFT', 'APPROVED', 'PAID'], 'kpi-definitions','recurring-obligations' => ['ACTIVE', 'INACTIVE'], 'kpi-results','performance-scores' => ['DRAFT', 'APPROVED'], 'expenses' => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PAID', 'CANCELLED'], 'bills' => ['UNPAID', 'PAID', 'CANCELLED'],'payment-reconciliations' => ['PENDING', 'SETTLED', 'CANCELLED'], default => [],
        };
    }
}
