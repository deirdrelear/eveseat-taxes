<?php

namespace DeirdreLear\Seat\Taxes\Http\Controllers;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\DailyDryRunService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seat\Eveapi\Models\Universe\UniverseName;
use Throwable;

class DryRunController extends Controller
{
    public function index(Request $request, DailyDryRunService $dryRun)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'run' => ['nullable', 'boolean'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], 'UTC')
            : CarbonImmutable::now('UTC')->subDay()->startOfDay();

        $result = null;
        $error = null;
        $corporationNames = [];

        if ($request->boolean('run')) {
            try {
                $result = $dryRun->run($date);

                $corporationIds = array_map(
                    'intval',
                    array_keys($result['summary']['by_corporation'])
                );

                $corporationNames = UniverseName::query()
                    ->whereIn('entity_id', $corporationIds)
                    ->pluck('name', 'entity_id')
                    ->all();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('taxes::dry-run', [
            'date' => $date->toDateString(),
            'result' => $result,
            'error' => $error,
            'corporation_names' => $corporationNames,
        ]);
    }
}
