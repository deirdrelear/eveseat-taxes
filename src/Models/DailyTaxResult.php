<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTaxResult extends Model
{
    protected $table = 'seat_taxes_daily_results';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'tax_rate' => 'decimal:6',
        'tax_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];
}
