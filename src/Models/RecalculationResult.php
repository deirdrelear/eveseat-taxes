<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Seat\Services\Models\ExtensibleModel;

class RecalculationResult extends ExtensibleModel
{
    protected $table = 'seat_taxes_recalculation_results';

    protected $guarded = [];

    protected $casts = [
        'tax_date' => 'date',
        'original_tax' => 'decimal:2',
        'recalculated_tax' => 'decimal:2',
        'delta' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RecalculationRun::class, 'run_id');
    }
}
