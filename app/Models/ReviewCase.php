<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCase extends Model
{
    use HasFactory;

    protected $table = 'review_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'alert_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function caseEvidence(): HasMany
    {
        return $this->hasMany(CaseEvidence::class, 'review_case_id');
    }

    public function caseActions(): HasMany
    {
        return $this->hasMany(CaseAction::class, 'review_case_id');
    }

    public function caseAppeals(): HasMany
    {
        return $this->hasMany(CaseAppeal::class, 'review_case_id');
    }
}
