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
            'diagnostics' => [
                'name' => 'Diagnostics',
                'icon' => 'fas fa-stethoscope',
                'route' => 'taxes.diagnostics',
                'permission' => 'taxes.view',
            ],
        ],
    ],
];
