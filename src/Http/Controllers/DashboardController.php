<?php

namespace DeirdreLear\Seat\Taxes\Http\Controllers;

use DeirdreLear\Seat\Taxes\Models\DailyTaxResult;
use DeirdreLear\Seat\Taxes\Models\TaxRuleSet;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('taxes::dashboard', [
            'result_count' => DailyTaxResult::query()->count(),
            'rule_set_count' => TaxRuleSet::query()->count(),
        ]);
    }
}
