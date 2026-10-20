<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PettyCashTransaction;
use App\Models\RecurringObligation;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceMutation
{
    private function category(int $id): ExpenseCategory
    {
        $c = ExpenseCategory::where('organization_id', auth()->user()->organization_id)->find($id);
        if (! $c) {
            StockLedger::fail('Kategori tidak tersedia.', 'expense_category_id');
        }

return $c;
    }

    private function supplier(?int $id): void
    {
        if ($id && ! Supplier::where('organization_id', auth()->user()->organization_id)->where('status', 'ACTIVE')->find($id)) {
            StockLedger::fail('Supplier tidak tersedia.', 'supplier_id');
        }
    }

    public static function balance(int $unit): string
    {
        $balance = '0';
        foreach (PettyCashTransaction::where('operational_unit_id', $unit)->orderBy('id')->lockForUpdate()->get() as $t) {
            $balance = bcadd($balance, $t->type === 'IN' ? $t->amount : '-'.$t->amount, 2);
        }

return $balance;
    }

    private function cash(int $unit, string $amount, string $type, string $description, ?Expense $expense = null): void
    {
        $balance = self::balance($unit);
        $balance = bcadd($balance, $type === 'IN' ? $amount : '-'.$amount, 2);
        if (bccomp($balance, '0', 2) < 0) {
            StockLedger::fail('Saldo petty cash tidak mencukupi.');
        }
        $t = PettyCashTransaction::create(['operational_unit_id' => $unit, 'expense_id' => $expense?->id, 'type' => $type, 'amount' => $amount, 'balance_after' => StockLedger::money($balance), 'description' => $description, 'performed_by' => auth()->id(), 'occurred_at' => now()]);
        AccountingMutation::audit($t, 'petty-cash', 'post');
    }

    public function save(string $m, Model $r, array $d, string $a): void
    {
        if (isset($d['unit_id'])) {
            $u = AccountingAccess::unit($m, $d['unit_id'], $a);
            unset($d['unit_id']);
            if ($r->exists && $r->operational_unit_id !== $u->id) {
                StockLedger::fail('Unit dokumen tidak dapat diganti.');
            }$d['operational_unit_id'] = $u->id;
        }
        if (isset($d['expense_category_id'])) {
            $this->category($d['expense_category_id']);
        }$this->supplier($d['supplier_id'] ?? null);
        if ($m === 'expense-categories') {
            if ($r->exists && ($r->expenses()->exists() || $r->bills()->exists() || $r->recurringObligations()->exists())) {
                StockLedger::fail('Kategori sudah digunakan; buat kategori baru untuk klasifikasi berbeda.');
            }
            $d['organization_id'] = auth()->user()->organization_id;
            $d['code'] = strtoupper(trim($d['code']));
            if (ExpenseCategory::where('organization_id', $d['organization_id'])->whereRaw('LOWER(code) = ?', [strtolower($d['code'])])->where('id', '!=', $r->id ?? 0)->exists()) {
                StockLedger::fail('Kode sudah digunakan.', 'code');
            }
        } elseif ($m === 'expenses') {
            $d['organization_id'] = auth()->user()->organization_id;
            if (! $r->exists) {
                $d += ['number' => 'EX-'.Str::ulid(), 'status' => 'DRAFT', 'submitted_by' => auth()->id()];
            }
        } elseif ($m === 'bills') {
            if ($r->exists && $r->expenses()->exists()) {
                StockLedger::fail('Tagihan sudah terhubung ke expense.');
            }
            if (! empty($d['recurring_obligation_id'])) {
                $o = RecurringObligation::where('operational_unit_id', $u->id)->where('expense_category_id', $d['expense_category_id'])->where('status', 'ACTIVE')->find($d['recurring_obligation_id']);
                if (! $o || $d['period_start'] < $o->starts_on->format('Y-m-d') || ($o->ends_on && $d['period_end'] > $o->ends_on->format('Y-m-d'))) {
                    StockLedger::fail('Kewajiban tidak sesuai unit, kategori, atau periode.');
                }
                if (Bill::where('recurring_obligation_id', $o->id)->where('id', '!=', $r->id ?? 0)->where('status', '!=', 'CANCELLED')->whereDate('period_start', '<=', $d['period_end'])->whereDate('period_end', '>=', $d['period_start'])->exists()) {
                    StockLedger::fail('Tagihan kewajiban pada periode ini sudah ada.');
                }
            }
            if (Bill::where('operational_unit_id', $u->id)->where('bill_no', $d['bill_no'])->where('id', '!=', $r->id ?? 0)->exists()) {
                StockLedger::fail('Nomor tagihan unit sudah digunakan.');
            }$d['status'] = 'UNPAID';
        } elseif ($m === 'recurring-obligations') {
            if ($r->exists && $r->bills()->exists()) {
                StockLedger::fail('Kewajiban sudah ditagihkan. Nonaktifkan dan buat kewajiban baru untuk perubahan kontrak.');
            }$d['status'] = 'ACTIVE';
        } elseif ($m === 'petty-cash') {
            $token = $d['submission_token'];
            $event = 'petty_cash.credit.'.$token;
            if (AuditLog::where('organization_id', auth()->user()->organization_id)->where('event', $event)->exists()) {
                StockLedger::fail('Dana masuk ini sudah dicatat.');
            }unset($d['submission_token']);
            $d += ['type' => 'IN', 'balance_after' => StockLedger::money(bcadd(self::balance($u->id), $d['amount'], 2)), 'performed_by' => auth()->id(), 'occurred_at' => now()];
            $r->fill($d)->save();
            AccountingMutation::audit($r, 'petty-cash', 'credit.'.$token);

            return;
        } elseif ($m === 'booth-pl') {
            $r->fill($d);
            (new FinanceReporting)->calculate($r);

            return;
        }
        $r->fill($d)->save();
    }

    public function action(string $m, Model $r, string $a, array $d): void
    {
        if ($a === 'delete') {
            $used = match ($m) {
                'expense-categories' => $r->expenses()->exists() || $r->bills()->exists() || $r->recurringObligations()->exists(),'bills' => $r->expenses()->exists(),'recurring-obligations' => $r->bills()->exists(),default => false
            };
            if ($used) {
                StockLedger::fail('Data sudah dipakai.');
            }$r->delete();

            return;
        }
        if (in_array($a, ['activate', 'deactivate'], true)) {
            $r->update(['status' => $a === 'activate' ? 'ACTIVE' : 'INACTIVE']);

            return;
        }
        if ($a === 'recalculate') {
            (new FinanceReporting)->calculate($r);

            return;
        }
        if ($a === 'generate') {
            $month = CarbonImmutable::createFromFormat('!Y-m', $d['billing_month']);
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();
            if ($start->toDateString() < $r->starts_on->format('Y-m-d') || ($r->ends_on && $end->toDateString() > $r->ends_on->format('Y-m-d'))) {
                StockLedger::fail('Periode tagihan harus berada dalam masa kontrak. Untuk periode parsial, buat tagihan manual.');
            }
            if ($r->bills()->where('status', '!=', 'CANCELLED')->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)->exists()) {
                StockLedger::fail('Periode sudah ditagihkan.');
            }
            if (bccomp($r->expected_amount, '0', 2) <= 0) {
                StockLedger::fail('Estimasi nominal harus positif.');
            }
            $bill = $r->bills()->create(['operational_unit_id' => $r->operational_unit_id, 'expense_category_id' => $r->expense_category_id, 'bill_no' => 'BILL-'.Str::ulid(), 'period_start' => $start, 'period_end' => $end, 'amount' => $r->expected_amount, 'due_date' => $start->day(min(28, max(1, $r->due_day ?? 1))), 'status' => 'UNPAID']);
            AccountingMutation::audit($bill, 'bills', 'generate');

            return;
        }
        if ($a === 'submit') {
            $r->update(['status' => 'SUBMITTED', 'submitted_by' => auth()->id()]);

            return;
        }
        if (in_array($a, ['approve', 'reject'], true)) {
            if ($r->submitted_by === auth()->id()) {
                StockLedger::fail('Pengajuan expense harus ditinjau pengguna lain.');
            }$r->update(['status' => $a === 'approve' ? 'APPROVED' : 'REJECTED', 'approved_by' => auth()->id()]);

            return;
        }
        if ($a === 'cancel') {
            if ($m === 'bills' && $r->expenses()->whereNotIn('status', ['CANCELLED', 'REJECTED'])->exists()) {
                StockLedger::fail('Tagihan memiliki expense aktif.');
            }$r->update(['status' => 'CANCELLED']);

            return;
        }
        if ($a === 'pay') {
            $expense = $r;
            if ($m === 'bills') {
                if ($r->expenses()->whereNotIn('status', ['CANCELLED', 'REJECTED'])->exists()) {
                    StockLedger::fail('Tagihan sudah memiliki expense; selesaikan expense tersebut.');
                }
                $expense = Expense::create(['organization_id' => auth()->user()->organization_id, 'operational_unit_id' => $r->operational_unit_id, 'expense_category_id' => $r->expense_category_id, 'supplier_id' => $r->recurringObligation?->supplier_id, 'bill_id' => $r->id, 'number' => 'EX-'.Str::ulid(), 'expense_date' => now(TradeMutation::timezone())->toDateString(), 'amount' => $r->amount, 'status' => 'APPROVED', 'submitted_by' => auth()->id(), 'approved_by' => auth()->id(), 'description' => 'Pembayaran tagihan '.$r->bill_no]);
            }
            $before = $expense->attributesToArray();
            if ($d['payment_method'] === 'PETTY_CASH') {
                if (! $expense->operational_unit_id) {
                    StockLedger::fail('Petty cash membutuhkan unit.');
                }
                AccountingAccess::unit('petty-cash', $expense->operational_unit_id, 'pay');
                $this->cash($expense->operational_unit_id, $expense->amount, 'OUT', $d['payment_reference'], $expense);
            }
            $expense->update(['status' => 'PAID', 'payment_method' => $d['payment_method'], 'description' => trim($expense->description."\nReferensi pembayaran: ".$d['payment_reference'])]);
            if ($m === 'bills') {
                $r->update(['status' => 'PAID', 'paid_on' => now(TradeMutation::timezone())->toDateString()]);
                AccountingMutation::audit($expense, 'expenses', 'bill-payment', $before);
            } elseif ($expense->bill_id) {
                $bill = $expense->bill()->lockForUpdate()->firstOrFail();
                if ($bill->operational_unit_id !== $expense->operational_unit_id || $bill->expense_category_id !== $expense->expense_category_id || $bill->status !== 'UNPAID' || bccomp($bill->amount,$expense->amount,2) !== 0) {
                    StockLedger::fail('Expense tidak cocok dengan tagihan.');
                }$before = $bill->attributesToArray();
                $bill->update(['status' => 'PAID', 'paid_on' => now(TradeMutation::timezone())->toDateString()]);
                AccountingMutation::audit($bill,'bills','pay',$before);
            }
        }
    }
}
