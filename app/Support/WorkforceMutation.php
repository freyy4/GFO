<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OpenShift;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Roster;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwap;
use App\Models\ShiftTemplate;
use App\Models\Skill;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class WorkforceMutation
{
    private function employee(int $id, string $m): Employee
    {
        $e = WorkforceAccess::employees(auth()->user(), $m)->where('status', 'ACTIVE')->lockForUpdate()->find($id);
        if (! $e) {
            StockLedger::fail('Karyawan tidak tersedia dalam akses Anda.', 'employee_id');
        }

        return $e;
    }

    private function shift(int $id, string $m, bool $self = true): ShiftAssignment
    {
        return WorkforceAccess::shifts(auth()->user(), $m, $self)->lockForUpdate()->findOrFail($id);
    }

    public function available(Employee $e, int $unit, $start, $end, array $ignore = []): void
    {
        OperationsContext::employeeAt($e->id, $unit, $start, $end);
        if (ShiftAssignment::where('employee_id', $e->id)->whereNotIn('id', $ignore)->whereIn('status', ['ASSIGNED'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            StockLedger::fail('Karyawan memiliki shift lain yang bertabrakan.');
        }
        if (LeaveRequest::where('employee_id', $e->id)->where('status', 'APPROVED')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            StockLedger::fail('Karyawan sedang izin/cuti pada waktu tersebut.');
        }
    }

    private function eligibility(Employee $e, ShiftAssignment $s, array $ignore = []): void
    {
        $this->available($e, $s->operational_unit_id, $s->starts_at, $s->ends_at, $ignore);
        if ($s->position_id && $e->position_id !== $s->position_id && ! $e->employeeUnitAssignments()->where('operational_unit_id', $s->operational_unit_id)->where('position_id', $s->position_id)->whereDate('starts_on', '<=', $s->starts_at->format('Y-m-d'))->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $s->ends_at->format('Y-m-d')))->exists()) {
            StockLedger::fail('Jabatan karyawan belum sesuai kebutuhan shift.');
        }
    }

    private function noPendingSwap(ShiftAssignment $s): void
    {
        if (ShiftSwap::where('status', 'PENDING')->where(fn ($q) => $q->where('from_assignment_id', $s->id)->orWhere('to_assignment_id', $s->id))->exists()) {
            StockLedger::fail('Selesaikan permintaan pertukaran sebelum mengubah shift.');
        }
    }

    private function future(ShiftAssignment $s): void
    {
        if ($s->starts_at <= now() || $s->attendanceRecords()->exists()) {
            StockLedger::fail('Perubahan penugasan hanya sebelum shift dimulai dan belum memiliki kehadiran.');
        }
    }

    private function payroll(int $unit, string $date): void
    {
        if (PayrollPeriod::where('organization_id', auth()->user()->organization_id)->where('status', 'LOCKED')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists()) {
            StockLedger::fail('Periode payroll sudah dikunci.');
        }
    }

    private function times(array &$d): void
    {
        foreach (['starts_at', 'ends_at', 'clock_in_at', 'clock_out_at', 'requested_clock_in_at', 'requested_clock_out_at', 'response_deadline_at'] as $key) {
            if (! empty($d[$key])) {
                $d[$key] = OperationsContext::dateTime($d[$key]);
            }
        }
    }

    private function minutes(AttendanceRecord $r): void
    {
        $shift = $r->shiftAssignment;
        if (! $shift || $shift->employee_id !== $r->employee_id || $shift->operational_unit_id !== $r->operational_unit_id) {
            StockLedger::fail('Kehadiran tidak sesuai penugasan shift.');
        }
        if (! $r->clock_in_at || ! $r->clock_out_at || $r->clock_out_at <= $r->clock_in_at || $r->clock_out_at > now() || $r->clock_in_at < $shift->starts_at->copy()->subDay() || $r->clock_out_at > $shift->ends_at->copy()->addDay()) {
            StockLedger::fail('Waktu kehadiran tidak valid atau terlalu jauh dari jadwal.');
        }
        $break = $shift->shiftTemplate?->break_minutes ?? 0;
        $worked = max(0, (int) $r->clock_in_at->diffInMinutes($r->clock_out_at) - $break);
        $scheduled = max(0, (int) $shift->starts_at->diffInMinutes($shift->ends_at) - $break);
        $r->worked_minutes = $worked;
        $r->late_minutes = max(0, (int) $shift->starts_at->diffInMinutes($r->clock_in_at, false));
        $r->overtime_minutes = max(0, $worked - $scheduled);
    }

    private function open(int $id, string $m): OpenShift
    {
        $o = WorkforceAccess::opens(auth()->user(), $m)->where('status', 'OPEN')->lockForUpdate()->find($id);
        if (! $o || ($o->response_deadline_at && $o->response_deadline_at < now())) {
            StockLedger::fail('Open shift sudah ditutup atau melewati tenggat.');
        }
        $this->future($o->shiftAssignment);
        if ($o->shiftAssignment->roster && $o->shiftAssignment->roster->status !== 'PUBLISHED') {
            StockLedger::fail('Roster harus dipublikasikan sebelum mengisi slot.');
        }

        return $o;
    }

    public function save(string $m, Model $r, array $d, string $ability): Model
    {
        if ($m !== 'shift-templates') {
            $this->times($d);
        }
        if (isset($d['unit_id'])) {
            $unit = OperationsContext::unit($m, $d['unit_id'], $ability);
            unset($d['unit_id']);
            if ($r->exists && $r->operational_unit_id !== $unit->id) {
                StockLedger::fail('Unit tidak dapat diganti.');
            }$d['operational_unit_id'] = $unit->id;
        }
        if (isset($d['employee_id'])) {
            $e = $this->employee($d['employee_id'], $m);
            if (in_array($m, ['leave-requests', 'employee-skills', 'employee-certifications'], true)) {
                if (! $e->home_unit_id) {
                    StockLedger::fail('Tetapkan unit utama karyawan sebelum melanjutkan.');
                }
                OperationsContext::unit($m, $e->home_unit_id, $ability);
            }
            if ($r->exists && $r->employee_id !== $e->id) {
                StockLedger::fail('Identitas karyawan tidak dapat diganti.');
            }
        }
        if (in_array($m, ['skills', 'shift-templates'], true)) {
            $d['organization_id'] = auth()->user()->organization_id;
            $d['code'] = strtoupper(trim($d['code']));
            $class = $r::class;
            if ($class::where('organization_id', $d['organization_id'])->whereRaw('LOWER(code) = ?', [strtolower($d['code'])])->where('id', '!=', $r->id ?? 0)->exists()) {
                StockLedger::fail('Kode sudah digunakan.', 'code');
            }
            if ($m === 'shift-templates') {
                if ($r->exists && $r->shiftAssignments()->exists()) {
                    StockLedger::fail('Template sudah dipakai. Buat template baru untuk aturan berbeda.');
                }
                $start = CarbonImmutable::parse('2000-01-01 '.$d['starts_at']);
                $end = CarbonImmutable::parse('2000-01-01 '.$d['ends_at']);
                if ($end <= $start) {
                    $end = $end->addDay();
                }if ($d['break_minutes'] >= $start->diffInMinutes($end)) {
                    StockLedger::fail('Istirahat harus lebih pendek dari shift.');
                }$d['crosses_midnight'] = $d['ends_at'] <= $d['starts_at'];
            }
        } elseif ($m === 'rosters') {
            if (CarbonImmutable::parse($d['period_start'])->diffInDays(CarbonImmutable::parse($d['period_end'])) > 62) {
                StockLedger::fail('Periode roster maksimal 63 hari.');
            }
            if ($r->exists && $r->shiftAssignments()->exists()) {
                StockLedger::fail('Roster memiliki shift. Hapus atau pindahkan draft shift sebelum mengubah periode.');
            }
            if (Roster::where('operational_unit_id', $unit->id)->where('id', '!=', $r->id ?? 0)->whereDate('period_start', '<=', $d['period_end'])->whereDate('period_end', '>=', $d['period_start'])->exists()) {
                StockLedger::fail('Periode roster tumpang tindih.');
            }$d['prepared_by'] = auth()->id();
            $d['status'] = 'DRAFT';
        } elseif ($m === 'shift-assignments') {
            if ($r->exists) {
                $this->future($r);
                $this->noPendingSwap($r);
                if ($r->openShifts()->exists()) {
                    StockLedger::fail('Shift sudah memiliki proses pengganti.');
                }
            }
            if ($d['ends_at']->diffInHours($d['starts_at'], true) > 24) {
                StockLedger::fail('Durasi shift maksimal 24 jam.');
            }
            if (! empty($d['roster_id'])) {
                $roster = Roster::where('operational_unit_id', $unit->id)->where('status', 'DRAFT')->find($d['roster_id']);
                if (! $roster || $d['starts_at']->setTimezone(TradeMutation::timezone())->format('Y-m-d') < $roster->period_start->format('Y-m-d') || $d['starts_at']->setTimezone(TradeMutation::timezone())->format('Y-m-d') > $roster->period_end->format('Y-m-d')) {
                    StockLedger::fail('Shift harus berada dalam periode roster draft pada unit yang sama.');
                }
            }
            if (! empty($d['position_id']) && ! Position::where('organization_id', auth()->user()->organization_id)->find($d['position_id'])) {
                StockLedger::fail('Jabatan tidak tersedia.');
            }
            if (! empty($d['shift_template_id'])) {
                $template = ShiftTemplate::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->find($d['shift_template_id']);
                if (! $template || $template->break_minutes >= $d['starts_at']->diffInMinutes($d['ends_at'])) {
                    StockLedger::fail('Template tidak tersedia atau istirahat melebihi durasi.');
                }
            }
            $candidate = new ShiftAssignment($d);
            if (isset($e)) {
                $this->eligibility($e, $candidate, $r->exists ? [$r->id] : []);
            }$d['status'] = isset($e) ? 'ASSIGNED' : 'OPEN';
            $d['source'] = 'ROSTER';
        } elseif ($m === 'leave-requests') {
            if ($d['starts_at'] <= now()) {
                StockLedger::fail('Izin/cuti diajukan sebelum waktu mulai.');
            }if (LeaveRequest::where('employee_id', $e->id)->whereIn('status', ['PENDING', 'APPROVED'])->where('starts_at', '<', $d['ends_at'])->where('ends_at', '>', $d['starts_at'])->exists()) {
                StockLedger::fail('Sudah ada izin/cuti pada periode ini.');
            }$d['status'] = 'PENDING';
        } elseif ($m === 'attendance-records') {
            $s = $this->shift($d['shift_assignment_id'], $m);
            if (! $s->employee_id || $s->status !== 'ASSIGNED' || ($s->roster && $s->roster->status !== 'PUBLISHED')) {
                StockLedger::fail('Shift harus ditugaskan dan rosternya dipublikasikan.');
            }OperationsContext::unit($m, $s->operational_unit_id, $ability);
            $this->employee($s->employee_id, $m);
            $d += ['employee_id' => $s->employee_id, 'operational_unit_id' => $s->operational_unit_id, 'business_date' => $s->starts_at->setTimezone(TradeMutation::timezone())->format('Y-m-d'), 'status' => 'PENDING'];
            $this->payroll($s->operational_unit_id, $d['business_date']);
            $r->fill($d);
            $this->minutes($r);
            $d = $r->getAttributes();
        } elseif ($m === 'attendance-corrections') {
            $ar = WorkforceAccess::attendance(auth()->user(), $m)->lockForUpdate()->findOrFail($d['attendance_record_id']);
            OperationsContext::unit($m, $ar->operational_unit_id, $ability);
            $this->payroll($ar->operational_unit_id, $ar->business_date->format('Y-m-d'));
            if ($ar->attendanceCorrections()->where('status', 'PENDING')->exists()) {
                StockLedger::fail('Masih ada koreksi menunggu keputusan.');
            }$test = clone $ar;
            $test->clock_in_at = $d['requested_clock_in_at'];
            $test->clock_out_at = $d['requested_clock_out_at'];
            $this->minutes($test);
            $d += ['status' => 'PENDING', 'requested_by' => auth()->id()];
        } elseif ($m === 'open-shifts') {
            $s = $this->shift($d['shift_assignment_id'], $m, false);
            OperationsContext::unit($m, $s->operational_unit_id, $ability);
            $this->future($s);
            if ($s->employee_id || $s->status !== 'OPEN' || ($s->roster && $s->roster->status !== 'PUBLISHED')) {
                StockLedger::fail('Open shift memerlukan slot kosong yang sudah dipublikasikan.');
            }
            if ($d['response_deadline_at'] <= now() || $d['response_deadline_at'] > $s->starts_at) {
                StockLedger::fail('Tenggat respons harus sebelum shift dan setelah saat ini.');
            }if ($s->openShifts()->where('status', 'OPEN')->exists()) {
                StockLedger::fail('Slot sudah dibuka.');
            }$d += ['status' => 'OPEN', 'created_by' => auth()->id()];
        } elseif (in_array($m, ['replacement-candidates', 'shift-claims'], true)) {
            $open = $this->open($d['open_shift_id'], $m);
            $s = $open->shiftAssignment;
            OperationsContext::unit($m, $s->operational_unit_id, $ability);
            $this->eligibility($e, $s);
            if ($m === 'replacement-candidates') {
                $d += ['ranking_score' => '100', 'score_breakdown' => ['eligible' => true, 'basis' => 'Unit, jabatan, jadwal dan izin sesuai; urutan manual.'], 'status' => 'SUGGESTED'];
            } else {
                $d['status'] = 'PENDING';
            }
        } elseif ($m === 'shift-swaps') {
            $from = $this->shift($d['from_assignment_id'], $m);
            OperationsContext::unit($m, $from->operational_unit_id, $ability);
            $this->future($from);
            $target = WorkforceAccess::employees(auth()->user(), $m, false)->where('status', 'ACTIVE')->findOrFail($d['to_employee_id']);
            if (! $from->employee_id || $from->employee_id === $target->id || $from->status !== 'ASSIGNED') {
                StockLedger::fail('Shift asal harus ditugaskan kepada karyawan berbeda.');
            }$ignore = [$from->id];
            $to = null;
            if (! empty($d['to_assignment_id'])) {
                $to = $this->shift($d['to_assignment_id'], $m, false);
                OperationsContext::unit($m, $to->operational_unit_id, $ability);
                $this->future($to);
                if ($to->employee_id !== $target->id || $to->status !== 'ASSIGNED') {
                    StockLedger::fail('Shift balasan bukan milik penerima.');
                }$ignore[] = $to->id;
            }
            $this->eligibility($target, $from, $ignore);
            if ($to) {
                $this->eligibility($from->employee, $to, $ignore);
            }
            if (ShiftSwap::where('status', 'PENDING')->where(fn ($q) => $q->whereIn('from_assignment_id', $ignore)->orWhereIn('to_assignment_id', $ignore))->exists()) {
                StockLedger::fail('Shift sudah memiliki permintaan pertukaran.');
            }$d['status'] = 'PENDING';
        } elseif ($m === 'employee-skills') {
            if (! Skill::where('organization_id', auth()->user()->organization_id)->find($d['skill_id'])) {
                StockLedger::fail('Skill tidak tersedia.');
            }if ($r->exists && $r->skill_id !== (int) $d['skill_id']) {
                StockLedger::fail('Identitas skill tidak dapat diganti.');
            }
        } elseif ($m === 'employee-certifications') {
            if ($r->exists && $r->status === 'REVOKED') {
                StockLedger::fail('Sertifikasi yang dicabut tidak dapat diubah.');
            }$d['status'] = 'VALID';
        }
        $r->fill($d)->save();

        return $r;
    }

    public function action(string $m, Model $r, string $a, array $d): void
    {
        if ($a === 'delete') {
            $used = match ($m) {
                'rosters' => $r->shiftAssignments()->exists(),'shift-templates' => $r->shiftAssignments()->exists(),'skills' => $r->employeeSkills()->exists(),'shift-assignments' => $r->attendanceRecords()->exists() || $r->openShifts()->exists() || $r->shiftSwapsAsFromAssignment()->exists() || $r->shiftSwapsAsToAssignment()->exists(),default => false
            };
            if ($used) {
                StockLedger::fail('Data sudah digunakan dan tidak dapat dihapus.');
            }if ($m === 'shift-assignments') {
                $this->future($r);
            }$r->delete();

            return;
        }
        if (in_array($a, ['activate', 'deactivate'], true)) {
            $r->update(['is_active' => $a === 'activate']);

            return;
        }
        if ($a === 'verify') {
            $r->update(['verified_by' => auth()->id(), 'verified_on' => now()->format('Y-m-d')]);

            return;
        }
        if ($a === 'revoke') {
            $r->update(['status' => 'REVOKED']);

            return;
        }
        if ($a === 'publish') {
            $shifts = $r->shiftAssignments()->whereIn('status', ['ASSIGNED', 'OPEN'])->lockForUpdate()->get();
            if ($shifts->isEmpty()) {
                StockLedger::fail('Roster belum memiliki shift.');
            }foreach ($shifts as $s) {
                if ($s->starts_at <= now()) {
                    StockLedger::fail('Roster harus dipublikasikan sebelum shift dimulai.');
                }if ($s->employee_id) {
                    $this->eligibility($s->employee, $s, [$s->id]);
                }
            }$r->update(['status' => 'PUBLISHED', 'approved_by' => auth()->id(), 'published_at' => now()]);

            return;
        }
        if ($a === 'cancel') {
            $s = $m === 'open-shifts' ? $r->shiftAssignment : $r;
            $this->future($s);
            $this->noPendingSwap($s);
            if ($m === 'shift-assignments' && $s->openShifts()->where('status', 'OPEN')->exists()) {
                StockLedger::fail('Tutup open shift terlebih dahulu.');
            }$r->update(['status' => 'CANCELLED']);
            if ($m === 'open-shifts') {
                $r->shiftClaims()->where('status', 'PENDING')->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decision_reason' => 'Open shift dibatalkan.']);
            }

            return;
        }
        if (in_array($m, ['leave-requests', 'attendance-records', 'shift-claims'], true) && $r->employee->user_id === auth()->id()) {
            StockLedger::fail('Tidak dapat menyetujui permintaan/kehadiran sendiri.');
        }
        if ($m === 'shift-swaps' && ($r->fromAssignment->employee?->user_id === auth()->id() || $r->toEmployee->user_id === auth()->id())) {
            StockLedger::fail('Pertukaran harus disetujui pengguna selain peserta.');
        }
        if ($m === 'attendance-corrections' && ($r->requested_by === auth()->id() || $r->attendanceRecord->employee->user_id === auth()->id())) {
            StockLedger::fail('Koreksi harus diperiksa pengguna lain.');
        }
        if ($a === 'reject') {
            $r->update($m === 'attendance-corrections' ? ['status' => 'REJECTED', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()] : ['status' => 'REJECTED', 'approved_by' => auth()->id(), ...($m === 'shift-swaps' ? [] : ['approved_at' => now()])]);

            return;
        }
        if ($m === 'leave-requests') {
            $this->employee($r->employee_id, $m);
            if ($r->starts_at <= now()) {
                StockLedger::fail('Izin harus disetujui sebelum waktu mulai.');
            }
            $shifts = ShiftAssignment::where('employee_id', $r->employee_id)->where('status', 'ASSIGNED')->where('starts_at', '<', $r->ends_at)->where('ends_at', '>', $r->starts_at)->lockForUpdate()->get();
            foreach ($shifts as $s) {
                OperationsContext::unit($m, $s->operational_unit_id, 'approve');
                $this->future($s);
                if (ShiftSwap::where('status', 'PENDING')->where(fn ($q) => $q->where('from_assignment_id', $s->id)->orWhere('to_assignment_id', $s->id))->exists()) {
                    StockLedger::fail('Selesaikan permintaan pertukaran shift sebelum izin.');
                }
                $before = $s->attributesToArray();
                $s->update(['status' => 'ON_LEAVE']);
                OperationsMutation::audit($s, 'shift-assignments', 'leave', $before);
                $replacement = $s->replicate(['employee_id', 'status', 'source', 'is_replacement', 'replaces_assignment_id']);
                $replacement->fill(['employee_id' => null, 'status' => 'OPEN', 'source' => 'LEAVE_REPLACEMENT', 'is_replacement' => true, 'replaces_assignment_id' => $s->id])->save();
                OperationsMutation::audit($replacement, 'shift-assignments', 'replacement');
                OpenShift::create(['shift_assignment_id' => $replacement->id, 'leave_request_id' => $r->id, 'status' => 'OPEN', 'response_deadline_at' => $s->starts_at, 'created_by' => auth()->id()]);
            }$r->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        } elseif ($m === 'attendance-records') {
            $this->payroll($r->operational_unit_id, $r->business_date->format('Y-m-d'));
            $this->minutes($r);
            $r->fill(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
        } elseif ($m === 'attendance-corrections') {
            $ar = $r->attendanceRecord()->lockForUpdate()->firstOrFail();
            $this->payroll($ar->operational_unit_id, $ar->business_date->format('Y-m-d'));
            $before = $ar->attributesToArray();
            $ar->clock_in_at = $r->requested_clock_in_at;
            $ar->clock_out_at = $r->requested_clock_out_at;
            $this->minutes($ar);
            $ar->fill(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
            OperationsMutation::audit($ar, 'attendance-records', 'corrected', $before);
            $r->update(['status' => 'APPROVED', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        } elseif ($m === 'shift-claims') {
            $open = $this->open($r->open_shift_id, $m);
            $s = $open->shiftAssignment()->lockForUpdate()->firstOrFail();
            $e = $this->employee($r->employee_id, $m);
            $this->eligibility($e, $s);
            if ($s->employee_id || $s->status !== 'OPEN') {
                StockLedger::fail('Shift sudah diisi.');
            }
            $before = $s->attributesToArray();
            $s->update(['employee_id' => $e->id, 'status' => 'ASSIGNED', 'source' => 'CLAIM']);
            OperationsMutation::audit($s, 'shift-assignments', 'claim', $before);
            $open->update(['status' => 'FILLED']);
            $open->shiftClaims()->where('id', '!=', $r->id)->where('status', 'PENDING')->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decision_reason' => 'Slot diisi kandidat lain.']);
            $open->replacementCandidates()->update(['status' => 'CLOSED']);
            $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        } elseif ($m === 'shift-swaps') {
            $from = $this->shift($r->from_assignment_id, $m);
            $this->future($from);
            $e = WorkforceAccess::employees(auth()->user(), $m, false)->where('status', 'ACTIVE')->findOrFail($r->to_employee_id);
            $to = $r->to_assignment_id ? $this->shift($r->to_assignment_id, $m, false) : null;
            $ignore = [$from->id, ...($to ? [$to->id] : [])];
            if (! $from->employee_id || $from->status !== 'ASSIGNED') {
                StockLedger::fail('Shift asal berubah.');
            }if ($to) {
                OperationsContext::unit($m, $to->operational_unit_id, 'approve');
                $this->future($to);
                if ($to->employee_id !== $e->id || $to->status !== 'ASSIGNED') {
                    StockLedger::fail('Shift balasan berubah.');
                }$this->eligibility($from->employee, $to, $ignore);
            }$this->eligibility($e, $from, $ignore);
            $original = $from->employee_id;
            $before = $from->attributesToArray();
            $from->update(['employee_id' => $e->id, 'source' => 'SWAP']);
            OperationsMutation::audit($from, 'shift-assignments', 'swap', $before);
            if ($to) {
                $before = $to->attributesToArray();
                $to->update(['employee_id' => $original, 'source' => 'SWAP']);
                OperationsMutation::audit($to, 'shift-assignments', 'swap', $before);
            }$r->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'effective_at' => now()]);
        }
    }
}
