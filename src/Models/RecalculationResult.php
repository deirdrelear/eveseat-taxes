<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;

class RecalculationResult extends Model
{
    protected $table = 'seat_taxes_recalculation_results';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'original_tax' => 'decimal:2',
        'recalculated_tax' => 'decimal:2',
        'delta' => 'decimal:2',
    ];
}
