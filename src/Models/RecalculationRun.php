<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Seat\Services\Models\ExtensibleModel;

class RecalculationRun extends ExtensibleModel
{
    protected $table = 'seat_taxes_recalculation_runs';

    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'overrides' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(RecalculationResult::class, 'run_id');
    }
}
