<?php

return [
    'taxes' => [
        'name' => 'Taxes',
        'icon' => 'fas fa-calculator',
        'route_segment' => 'taxes',
        'entries' => [
            'dashboard' => [
                'name' => 'Dashboard',
                'icon' => 'fas fa-chart-line',
                'route' => 'taxes.dashboard',
                'permission' => 'taxes.view',
            ],
            'rules' => [
                'name' => 'Rules',
                'icon' => 'fas fa-sliders-h',
                'route' => 'taxes.rules',
                'permission' => 'taxes.manage',
            ],
            'dry_run' => [
                'name' => 'Dry Run',
                'icon' => 'fas fa-vial',
                'route' => 'taxes.dry-run',
                'permission' => 'taxes.recalculate',
            ],
            'diagnostics' => [
                'name' => 'Diagnostics',
                'icon' => 'fas fa-stethoscope',
                'route' => 'taxes.diagnostics',
                'permission' => 'taxes.view',
            ],
        ],
    ],
];
