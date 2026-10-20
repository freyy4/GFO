<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organizations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'organization_id');
    }

    public function operationalUnits(): HasMany
    {
        return $this->hasMany(OperationalUnit::class, 'organization_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'organization_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'organization_id');
    }

    public function appSettings(): HasMany
    {
        return $this->hasMany(AppSetting::class, 'organization_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'organization_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'organization_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'organization_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'organization_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'organization_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'organization_id');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'organization_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'organization_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'organization_id');
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'organization_id');
    }

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class, 'organization_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'organization_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'organization_id');
    }

    public function shiftTemplates(): HasMany
    {
        return $this->hasMany(ShiftTemplate::class, 'organization_id');
    }

    public function payrollPolicies(): HasMany
    {
        return $this->hasMany(PayrollPolicy::class, 'organization_id');
    }

    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class, 'organization_id');
    }

    public function kpiDefinitions(): HasMany
    {
        return $this->hasMany(KPIDefinition::class, 'organization_id');
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'organization_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'organization_id');
    }

    public function assetReferences(): HasMany
    {
        return $this->hasMany(AssetReference::class, 'organization_id');
    }

    public function sops(): HasMany
    {
        return $this->hasMany(SOP::class, 'organization_id');
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class, 'organization_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'organization_id');
    }

    public function reviewCases(): HasMany
    {
        return $this->hasMany(ReviewCase::class, 'organization_id');
    }

    public function kolProfiles(): HasMany
    {
        return $this->hasMany(KOLProfile::class, 'organization_id');
    }

    public function integrationEndpoints(): HasMany
    {
        return $this->hasMany(IntegrationEndpoint::class, 'organization_id');
    }

    public function calendarEventLinks(): HasMany
    {
        return $this->hasMany(CalendarEventLink::class, 'organization_id');
    }

    public function notificationTemplates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class, 'organization_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
