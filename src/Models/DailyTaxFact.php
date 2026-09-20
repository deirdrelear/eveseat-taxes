<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTaxFact extends Model
{
    protected $table = 'seat_taxes_daily_facts';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'quantity' => 'decimal:4',
        'gross_value' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'price_value' => 'decimal:8',
        'refine_efficiency' => 'decimal:6',
        'source_payload' => 'array',
    ];
}
