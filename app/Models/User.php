<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function roleUserAsUser(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'user_id');
    }

    public function roleUserAsGrantedBy(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'granted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'verified_by');
    }

    public function recipeVersions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'approved_by');
    }

    public function supplierPriceHistories(): HasMany
    {
        return $this->hasMany(SupplierPriceHistory::class, 'recorded_by');
    }

    public function purchaseRequisitionsAsRequestedBy(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'requested_by');
    }

    public function purchaseRequisitionsAsApprovedBy(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'approved_by');
    }

    public function purchaseOrdersAsCreatedBy(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function purchaseOrdersAsApprovedBy(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'approved_by');
    }

    public function goodsReceiptsAsReceivedBy(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by');
    }

    public function goodsReceiptsAsQcBy(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'qc_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'performed_by');
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class, 'generated_by');
    }

    public function demandForecastLines(): HasMany
    {
        return $this->hasMany(DemandForecastLine::class, 'overridden_by');
    }

    public function productionPlansAsCreatedBy(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'created_by');
    }

    public function productionPlansAsApprovedBy(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'approved_by');
    }

    public function productionQcChecks(): HasMany
    {
        return $this->hasMany(ProductionQCCheck::class, 'checked_by');
    }

    public function stockTransfersAsRequestedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'requested_by');
    }

    public function stockTransfersAsPickedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'picked_by');
    }

    public function stockTransfersAsDispatchedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'dispatched_by');
    }

    public function stockTransfersAsReceivedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'received_by');
    }

    public function stockCountsAsCountedBy(): HasMany
    {
        return $this->hasMany(StockCount::class, 'counted_by');
    }

    public function stockCountsAsApprovedBy(): HasMany
    {
        return $this->hasMany(StockCount::class, 'approved_by');
    }

    public function inventoryReconciliationsAsPreparedBy(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class, 'prepared_by');
    }

    public function inventoryReconciliationsAsApprovedBy(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class, 'approved_by');
    }

    public function wasteRecordsAsReportedBy(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'reported_by');
    }

    public function wasteRecordsAsApprovedBy(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'approved_by');
    }

    public function registerSessionsAsOpenedBy(): HasMany
    {
        return $this->hasMany(RegisterSession::class, 'opened_by');
    }

    public function registerSessionsAsClosedBy(): HasMany
    {
        return $this->hasMany(RegisterSession::class, 'closed_by');
    }

    public function salesOrdersAsCashier(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'cashier_id');
    }

    public function salesOrdersAsApprovedBy(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'approved_by');
    }

    public function salesRefundsAsRequestedBy(): HasMany
    {
        return $this->hasMany(SalesRefund::class, 'requested_by');
    }

    public function salesRefundsAsApprovedBy(): HasMany
    {
        return $this->hasMany(SalesRefund::class, 'approved_by');
    }

    public function dailyClosingsAsClosedBy(): HasMany
    {
        return $this->hasMany(DailyClosing::class, 'closed_by');
    }

    public function dailyClosingsAsApprovedBy(): HasMany
    {
        return $this->hasMany(DailyClosing::class, 'approved_by');
    }

    public function rostersAsPreparedBy(): HasMany
    {
        return $this->hasMany(Roster::class, 'prepared_by');
    }

    public function rostersAsApprovedBy(): HasMany
    {
        return $this->hasMany(Roster::class, 'approved_by');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'approved_by');
    }

    public function attendanceCorrectionsAsRequestedBy(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'requested_by');
    }

    public function attendanceCorrectionsAsReviewedBy(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'reviewed_by');
    }

    public function openShifts(): HasMany
    {
        return $this->hasMany(OpenShift::class, 'created_by');
    }

    public function shiftClaims(): HasMany
    {
        return $this->hasMany(ShiftClaim::class, 'approved_by');
    }

    public function shiftSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'approved_by');
    }

    public function payrollPolicies(): HasMany
    {
        return $this->hasMany(PayrollPolicy::class, 'approved_by');
    }

    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class, 'locked_by');
    }

    public function payrollRunsAsCreatedBy(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'created_by');
    }

    public function payrollRunsAsApprovedBy(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'approved_by');
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(PayrollComponent::class, 'approved_by');
    }

    public function performanceScores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class, 'approved_by');
    }

    public function employeeRecognitions(): HasMany
    {
        return $this->hasMany(EmployeeRecognition::class, 'awarded_by');
    }

    public function expensesAsSubmittedBy(): HasMany
    {
        return $this->hasMany(Expense::class, 'submitted_by');
    }

    public function expensesAsApprovedBy(): HasMany
    {
        return $this->hasMany(Expense::class, 'approved_by');
    }

    public function pettyCashTransactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class, 'performed_by');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'approved_by');
    }

    public function sops(): HasMany
    {
        return $this->hasMany(SOP::class, 'owner_user_id');
    }

    public function sopVersions(): HasMany
    {
        return $this->hasMany(SOPVersion::class, 'approved_by');
    }

    public function checklistRunsAsPerformedBy(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'performed_by');
    }

    public function checklistRunsAsVerifiedBy(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'verified_by');
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'inspected_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'assigned_to');
    }

    public function reviewCases(): HasMany
    {
        return $this->hasMany(ReviewCase::class, 'assigned_to');
    }

    public function caseEvidence(): HasMany
    {
        return $this->hasMany(CaseEvidence::class, 'submitted_by');
    }

    public function caseActionsAsDecidedBy(): HasMany
    {
        return $this->hasMany(CaseAction::class, 'decided_by');
    }

    public function caseActionsAsApprovedBy(): HasMany
    {
        return $this->hasMany(CaseAction::class, 'approved_by');
    }

    public function caseAppeals(): HasMany
    {
        return $this->hasMany(CaseAppeal::class, 'reviewed_by');
    }

    public function marketingRecommendations(): HasMany
    {
        return $this->hasMany(MarketingRecommendation::class, 'reviewed_by');
    }

    public function marketingBudgets(): HasMany
    {
        return $this->hasMany(MarketingBudget::class, 'approved_by');
    }

    public function campaignsAsOwnerUser(): HasMany
    {
        return $this->hasMany(Campaign::class, 'owner_user_id');
    }

    public function campaignsAsApprovedBy(): HasMany
    {
        return $this->hasMany(Campaign::class, 'approved_by');
    }

    public function creativeAssets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class, 'created_by');
    }

    public function contentCalendarItems(): HasMany
    {
        return $this->hasMany(ContentCalendarItem::class, 'owner_user_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'approved_by');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['id', 'operational_unit_id', 'starts_at', 'ends_at', 'granted_by'])
            ->withTimestamps();
    }
}
