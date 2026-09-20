<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Seat\Services\Models\ExtensibleModel;

class TaxRuleRate extends ExtensibleModel
{
    protected $table = 'seat_taxes_rule_rates';

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:6',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(TaxRuleSet::class, 'rule_set_id');
    }
}
