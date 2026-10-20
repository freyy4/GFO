<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'operational_units';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_m' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OperationalUnit::class, 'parent_id');
    }

    public function roleUser(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'operational_unit_id');
    }

    public function appSettings(): HasMany
    {
        return $this->hasMany(AppSetting::class, 'operational_unit_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'home_unit_id');
    }

    public function employeeUnitAssignments(): HasMany
    {
        return $this->hasMany(EmployeeUnitAssignment::class, 'operational_unit_id');
    }

    public function unitProductPrices(): HasMany
    {
        return $this->hasMany(UnitProductPrice::class, 'operational_unit_id');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'destination_unit_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'destination_unit_id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'destination_unit_id');
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'operational_unit_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'operational_unit_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'operational_unit_id');
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class, 'operational_unit_id');
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'production_unit_id');
    }

    public function productionPlanLines(): HasMany
    {
        return $this->hasMany(ProductionPlanLine::class, 'destination_unit_id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'production_unit_id');
    }

    public function stockTransfersAsSourceUnit(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_unit_id');
    }

    public function stockTransfersAsDestinationUnit(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_unit_id');
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class, 'operational_unit_id');
    }

    public function inventoryReconciliations(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class, 'operational_unit_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'operational_unit_id');
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'operational_unit_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'operational_unit_id');
    }

    public function paymentSettlements(): HasMany
    {
        return $this->hasMany(PaymentSettlement::class, 'operational_unit_id');
    }

    public function dailyClosings(): HasMany
    {
        return $this->hasMany(DailyClosing::class, 'operational_unit_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class, 'operational_unit_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'operational_unit_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'operational_unit_id');
    }

    public function payrollAllocations(): HasMany
    {
        return $this->hasMany(PayrollAllocation::class, 'operational_unit_id');
    }

    public function recurringObligations(): HasMany
    {
        return $this->hasMany(RecurringObligation::class, 'operational_unit_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'operational_unit_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'operational_unit_id');
    }

    public function pettyCashTransactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class, 'operational_unit_id');
    }

    public function boothPlSnapshots(): HasMany
    {
        return $this->hasMany(BoothPLSnapshot::class, 'operational_unit_id');
    }

    public function assetReferences(): HasMany
    {
        return $this->hasMany(AssetReference::class, 'operational_unit_id');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'operational_unit_id');
    }

    public function checklistRuns(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'operational_unit_id');
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'operational_unit_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'operational_unit_id');
    }

    public function salesHealthSnapshots(): HasMany
    {
        return $this->hasMany(SalesHealthSnapshot::class, 'operational_unit_id');
    }

    public function growthSignals(): HasMany
    {
        return $this->hasMany(GrowthSignal::class, 'operational_unit_id');
    }

    public function marketingBudgets(): HasMany
    {
        return $this->hasMany(MarketingBudget::class, 'operational_unit_id');
    }

    public function campaignTargets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class, 'operational_unit_id');
    }

    public function contentCalendarItems(): HasMany
    {
        return $this->hasMany(ContentCalendarItem::class, 'operational_unit_id');
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class, 'operational_unit_id');
    }
}
