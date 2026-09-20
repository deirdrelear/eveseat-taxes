<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Seat\Services\Models\ExtensibleModel;

class DailyTaxResult extends ExtensibleModel
{
    protected $table = 'seat_taxes_daily_results';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'tax_rate' => 'decimal:6',
        'tax_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function fact(): BelongsTo
    {
        return $this->belongsTo(DailyTaxFact::class, 'daily_fact_id');
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(TaxRuleSet::class, 'rule_set_id');
    }
}
