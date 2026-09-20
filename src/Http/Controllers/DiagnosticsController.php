<?php

namespace DeirdreLear\Seat\Taxes\Http\Controllers;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\DiagnosticsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DiagnosticsController extends Controller
{
    public function index(Request $request, DiagnosticsService $diagnostics)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->subDay()->startOfDay();

        return view('taxes::diagnostics', [
            'report' => $diagnostics->report($date),
        ]);
    }
}
