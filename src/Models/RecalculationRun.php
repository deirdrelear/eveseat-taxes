<?php

namespace DeirdreLear\Seat\Taxes\Models;

use Illuminate\Database\Eloquent\Model;

class RecalculationRun extends Model
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
}
