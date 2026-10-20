<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'ended_on' => 'date',
            'base_salary' => 'decimal:2',
            'emergency_contact' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function homeUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'home_unit_id');
    }

    public function employeeUnitAssignments(): HasMany
    {
        return $this->hasMany(EmployeeUnitAssignment::class, 'employee_id');
    }

    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class, 'employee_id');
    }

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'employee_id');
    }

    public function employeeCertifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class, 'employee_id');
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class, 'assigned_employee_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function replacementCandidates(): HasMany
    {
        return $this->hasMany(ReplacementCandidate::class, 'employee_id');
    }

    public function shiftClaims(): HasMany
    {
        return $this->hasMany(ShiftClaim::class, 'employee_id');
    }

    public function shiftSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'to_employee_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'employee_id');
    }

    public function performanceScores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class, 'employee_id');
    }

    public function employeeRecognitions(): HasMany
    {
        return $this->hasMany(EmployeeRecognition::class, 'employee_id');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'assigned_employee_id');
    }

    public function reviewCases(): HasMany
    {
        return $this->hasMany(ReviewCase::class, 'employee_id');
    }

    public function caseAppeals(): HasMany
    {
        return $this->hasMany(CaseAppeal::class, 'employee_id');
    }
}
