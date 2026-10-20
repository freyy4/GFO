<?php

namespace App\Support;

use Illuminate\Support\Str;

class AccountingDisplay
{
    public static function money(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $parts = explode('.', ltrim($value, '-'), 2);

        return ($negative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[0]).','.str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'operational_unit_id' => 'Unit','employee_id','targetable_id','measurable_id' => 'Karyawan','kpi_definition_id' => 'KPI','expense_category_id' => 'Kategori biaya','supplier_id' => 'Supplier','recurring_obligation_id' => 'Kewajiban berulang','payroll_period_id' => 'Periode payroll','payroll_run_id' => 'Proses payroll','payslip_id' => 'Slip gaji','bill_id' => 'Tagihan','expense_id' => 'Expense',
            'number','bill_no' => 'Nomor dokumen','starts_on','period_start' => 'Awal periode','ends_on','period_end' => 'Akhir periode','status' => 'Status','amount' => 'Nominal','net_pay' => 'Gaji bersih','gross_pay' => 'Gaji kotor','base_salary' => 'Gaji pokok','total_deductions' => 'Total potongan','gross_total' => 'Total gaji kotor','net_total' => 'Total gaji bersih','deduction_total' => 'Total potongan',
            'revenue' => 'Pendapatan bersih tanpa pajak','cogs' => 'HPP','gross_profit' => 'Laba kotor','labor_cost' => 'Biaya tenaga kerja','rent_cost' => 'Biaya sewa','utility_cost' => 'Biaya utilitas','security_cleaning_cost' => 'Keamanan & kebersihan','maintenance_cost' => 'Biaya pemeliharaan','marketing_cost' => 'Biaya pemasaran','other_opex' => 'Biaya operasional lainnya','contribution' => 'Hasil setelah biaya operasional','break_even_sales' => 'Estimasi omzet impas','break_even_orders' => 'Estimasi jumlah order impas','calculated_at' => 'Dihitung pada',
            'score' => 'Skor KPI','total_score' => 'Skor performance','target_value' => 'Target','actual_value' => 'Realisasi','weight' => 'Bobot','reward_amount' => 'Nominal penghargaan','awarded_at' => 'Waktu penghargaan','awarded_by' => 'Pemberi penghargaan','allocated_amount' => 'Biaya dialokasikan','allocation_percent' => 'Persentase alokasi','worked_minutes' => 'Menit kerja','allocation_method' => 'Metode alokasi',
            'expected_amount' => 'Nominal diharapkan','settled_amount' => 'Dana diterima','fee_amount' => 'Biaya settlement','variance_amount' => 'Selisih settlement','balance_after' => 'Saldo setelah transaksi','occurred_at' => 'Waktu transaksi','paid_on','paid_at' => 'Waktu pembayaran','approved_by' => 'Disetujui oleh','approved_at' => 'Waktu persetujuan','submitted_by' => 'Diajukan oleh','performed_by' => 'Dicatat oleh','created_by' => 'Dibuat oleh','created_at' => 'Dibuat pada','locked_by' => 'Dikunci oleh','locked_at' => 'Waktu penguncian',
            default => Str::headline($key),
        };
    }
}
