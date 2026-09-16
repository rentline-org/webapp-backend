<?php

namespace App\Models;

use App\Enums\LeaseFinancialTermCalculation;
use App\Enums\LeaseFinancialTermFrequency;
use App\Enums\LeaseFinancialTermType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseFinancialTerm extends Model
{
    /** @use HasFactory<\Database\Factories\LeaseFinancialTermFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'lease_amendment_id',
        'payer_contact_id',
        'payee_contact_id',
        'type',
        'calculation',
        'amount',
        'percentage',
        'currency',
        'frequency',
        'calculation_basis',
        'due_day',
        'effective_from',
        'effective_to',
        'is_liability',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeaseFinancialTermType::class,
            'calculation' => LeaseFinancialTermCalculation::class,
            'frequency' => LeaseFinancialTermFrequency::class,
            'amount' => 'decimal:2',
            'percentage' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_liability' => 'boolean',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(LeaseAmendment::class, 'lease_amendment_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'payer_contact_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'payee_contact_id');
    }
}
