<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRuleSet extends Model
{
    protected $table = 'seat_taxes_rule_sets';

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'refine_efficiency' => 'decimal:6',
        'settings' => 'array',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRuleRate::class, 'rule_set_id');
    }
}
