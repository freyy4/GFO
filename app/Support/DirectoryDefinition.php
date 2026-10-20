<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Supplier;

class DirectoryDefinition
{
    public static function model(string $module): string
    {
        return match ($module) {
            'suppliers' => Supplier::class, 'positions' => Position::class, 'employees' => Employee::class
        };
    }

    public static function label(string $module): string
    {
        return match ($module) {
            'suppliers' => 'Supplier', 'positions' => 'Jabatan', 'employees' => 'Karyawan'
        };
    }

    public static function fields(string $module): array
    {
        return match ($module) {
            'suppliers' => [
                'code' => ['Kode supplier', 'text', true, 50], 'name' => ['Nama supplier', 'text', true, 255],
                'contact_name' => ['Nama kontak', 'text', false, 255], 'phone' => ['Telepon', 'tel', false, 30],
                'email' => ['Email', 'email', false, 255], 'address' => ['Alamat', 'textarea', false, 5000],
                'tax_number' => ['Nomor pajak', 'text', false, 80],
                'default_lead_time_hours' => ['Lead time standar (jam)', 'number', true, null],
                'payment_terms' => ['Termin pembayaran', 'text', false, 100],
                'approval_status' => ['Status persetujuan', 'select', true, ['PENDING', 'APPROVED', 'REJECTED']],
                'performance_score' => ['Skor performa', 'decimal', false, null],
                'risk_level' => ['Tingkat risiko', 'select', true, ['LOW', 'MEDIUM', 'HIGH']],
            ],
            'positions' => [
                'code' => ['Kode jabatan', 'text', true, 50], 'name' => ['Nama jabatan', 'text', true, 255],
                'work_area' => ['Area kerja', 'text', false, 40],
                'required_skills' => ['Keahlian yang dibutuhkan (JSON)', 'json', false, null],
            ],
            'employees' => [
                'employee_no' => ['Nomor karyawan', 'text', true, 50], 'full_name' => ['Nama lengkap', 'text', true, 255],
                'user_id' => ['Akun pengguna', 'reference', false, null], 'position_id' => ['Jabatan', 'reference', false, null],
                'home_unit_id' => ['Unit asal', 'reference', false, null],
                'phone' => ['Telepon', 'tel', false, 30], 'email' => ['Email', 'email', false, 255],
                'joined_on' => ['Tanggal bergabung', 'date', false, null], 'ended_on' => ['Tanggal berakhir', 'date', false, null],
                'employment_type' => ['Jenis kepegawaian', 'select', true, ['PERMANENT', 'CONTRACT', 'DAILY', 'PART_TIME', 'INTERN']],
                'status' => ['Status', 'select', true, ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'TERMINATED']],
                'base_salary' => ['Gaji pokok', 'decimal', true, null],
                'emergency_contact' => ['Kontak darurat (JSON)', 'json', false, null],
            ],
        };
    }

    public static function filters(string $module): array
    {
        return match ($module) {
            'suppliers' => ['approval_status', 'risk_level'],
            'positions' => ['work_area'],
            'employees' => ['status', 'employment_type', 'home_unit_id', 'position_id'],
        };
    }

    public static function defaults(string $module): array
    {
        return match ($module) {
            'suppliers' => ['approval_status' => 'PENDING', 'risk_level' => 'LOW', 'default_lead_time_hours' => 0],
            'positions' => [],
            'employees' => ['employment_type' => 'PERMANENT', 'status' => 'ACTIVE', 'base_salary' => '0.00'],
        };
    }
}
