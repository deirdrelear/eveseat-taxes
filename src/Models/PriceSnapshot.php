<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;

class PriceSnapshot extends Model
{
    protected $table = 'seat_taxes_price_snapshots';

    protected $guarded = [];

    protected $casts = [
        'price_date' => 'date',
        'price' => 'decimal:8',
        'metadata' => 'array',
    ];
}
