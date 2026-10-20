<?php

namespace App\Support;

use App\Models;

class WorkforceDefinition
{
    public const MODELS = ['shift-templates' => Models\ShiftTemplate::class, 'rosters' => Models\Roster::class, 'shift-assignments' => Models\ShiftAssignment::class, 'leave-requests' => Models\LeaveRequest::class, 'attendance-records' => Models\AttendanceRecord::class, 'attendance-corrections' => Models\AttendanceCorrection::class, 'open-shifts' => Models\OpenShift::class, 'replacement-candidates' => Models\ReplacementCandidate::class, 'shift-claims' => Models\ShiftClaim::class, 'shift-swaps' => Models\ShiftSwap::class, 'skills' => Models\Skill::class, 'employee-skills' => Models\EmployeeSkill::class, 'employee-certifications' => Models\EmployeeCertification::class];

    public const LABELS = ['shift-templates' => 'Template Shift', 'rosters' => 'Roster Karyawan', 'shift-assignments' => 'Jadwal Shift', 'leave-requests' => 'Izin & Cuti', 'attendance-records' => 'Kehadiran', 'attendance-corrections' => 'Koreksi Kehadiran', 'open-shifts' => 'Open Shift', 'replacement-candidates' => 'Kandidat Pengganti', 'shift-claims' => 'Shift Claim', 'shift-swaps' => 'Shift Swap', 'skills' => 'Master Skill', 'employee-skills' => 'Skill Karyawan', 'employee-certifications' => 'Sertifikasi Karyawan'];

    public static function fields(string $m): array
    {
        $unit = ['Unit', 'select', true];
        $employee = ['Karyawan', 'select', true];
        $start = ['Mulai', 'datetime-local', true];
        $end = ['Selesai', 'datetime-local', true];
        $reason = ['Alasan', 'textarea', true];

        return match ($m) {
            'shift-templates' => ['code' => ['Kode', 'text', true], 'name' => ['Nama', 'text', true], 'starts_at' => ['Jam mulai', 'time', true], 'ends_at' => ['Jam selesai', 'time', true], 'break_minutes' => ['Istirahat (menit)', 'number-zero', true]],
            'rosters' => ['unit_id' => $unit, 'period_start' => ['Awal periode', 'date', true], 'period_end' => ['Akhir periode', 'date', true]],
            'shift-assignments' => ['unit_id' => $unit, 'roster_id' => ['Roster draft', 'select', false], 'shift_template_id' => ['Template (aturan istirahat)', 'select', false], 'employee_id' => ['Karyawan (kosong untuk slot terbuka)', 'select', false], 'position_id' => ['Jabatan yang dibutuhkan', 'select', false], 'starts_at' => $start, 'ends_at' => $end, 'notes' => ['Catatan', 'textarea', false]],
            'leave-requests' => ['employee_id' => $employee, 'leave_type' => ['Jenis izin', 'select', true], 'starts_at' => $start, 'ends_at' => $end, 'reason' => $reason],
            'attendance-records' => ['shift_assignment_id' => ['Shift karyawan', 'select', true], 'clock_in_at' => ['Waktu masuk', 'datetime-local', true], 'clock_out_at' => ['Waktu keluar', 'datetime-local', true]],
            'attendance-corrections' => ['attendance_record_id' => ['Catatan kehadiran', 'select', true], 'requested_clock_in_at' => ['Koreksi masuk', 'datetime-local', true], 'requested_clock_out_at' => ['Koreksi keluar', 'datetime-local', true], 'reason' => $reason],
            'open-shifts' => ['shift_assignment_id' => ['Slot shift kosong', 'select', true], 'response_deadline_at' => ['Batas respons', 'datetime-local', true]],
            'replacement-candidates','shift-claims' => ['open_shift_id' => ['Open shift', 'select', true], 'employee_id' => $employee],
            'shift-swaps' => ['from_assignment_id' => ['Shift asal', 'select', true], 'to_employee_id' => ['Karyawan penerima', 'select', true], 'to_assignment_id' => ['Shift balasan (opsional)', 'select', false], 'reason' => $reason],
            'skills' => ['code' => ['Kode skill', 'text', true], 'name' => ['Nama skill', 'text', true]],
            'employee-skills' => ['employee_id' => $employee, 'skill_id' => ['Skill', 'select', true], 'level' => ['Level (1–5)', 'number', true]],
            'employee-certifications' => ['employee_id' => $employee, 'name' => ['Nama sertifikasi', 'text', true], 'issuer' => ['Penerbit', 'text', false], 'certificate_no' => ['Nomor sertifikat', 'text', false], 'issued_on' => ['Tanggal terbit', 'date', true], 'expires_on' => ['Tanggal kedaluwarsa', 'date', false]],default => []
        };
    }

    public static function lineFields(string $m): array
    {
        return [];
    }

    public static function relation(string $m): ?string
    {
        return $m === 'rosters' ? 'shiftAssignments' : null;
    }

    public static function actions(string $m): array
    {
        return match ($m) {
            'rosters' => ['publish' => 'Setujui & publikasikan'],
            'leave-requests','attendance-records','attendance-corrections','shift-claims','shift-swaps' => ['approve' => 'Setujui', 'reject' => 'Tolak'],
            'shift-assignments','open-shifts' => ['cancel' => 'Batalkan'],
            'shift-templates' => ['activate' => 'Aktifkan', 'deactivate' => 'Nonaktifkan'],
            'employee-skills' => ['verify' => 'Verifikasi skill'],
            'employee-certifications' => ['revoke' => 'Cabut sertifikasi'],default => []
        };
    }
}
