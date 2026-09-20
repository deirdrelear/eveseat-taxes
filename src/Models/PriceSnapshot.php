<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Seat\Services\Models\ExtensibleModel;

class PriceSnapshot extends ExtensibleModel
{
    protected $table = 'seat_taxes_price_snapshots';

    protected $guarded = [];

    protected $casts = [
        'price_date' => 'date',
        'price' => 'decimal:8',
        'metadata' => 'array',
    ];
}
