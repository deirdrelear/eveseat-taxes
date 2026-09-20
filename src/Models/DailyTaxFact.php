<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Seat\Services\Models\ExtensibleModel;

class DailyTaxFact extends ExtensibleModel
{
    protected $table = 'seat_taxes_daily_facts';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'quantity' => 'decimal:4',
        'opening_remainder' => 'integer',
        'refined_batches' => 'integer',
        'closing_remainder' => 'integer',
        'corporation_tax_rate' => 'decimal:6',
        'gross_value' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'price_value' => 'decimal:8',
        'refine_efficiency' => 'decimal:6',
        'sde_snapshot' => 'array',
        'source_payload' => 'array',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(DailyTaxResult::class, 'daily_fact_id');
    }
}
