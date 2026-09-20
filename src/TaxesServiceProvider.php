<?php

namespace DeirdreLear\Seat\Taxes;

use DeirdreLear\Seat\Taxes\database\seeders\ScheduleSeeder;
use Illuminate\Support\Facades\Route;
use Seat\Services\AbstractSeatPlugin;

class TaxesServiceProvider extends AbstractSeatPlugin
{
    public function boot(): void
    {
        $this->addRoutes();
        $this->addViews();
        $this->addMigrations();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/taxes.php', 'taxes');
        $this->mergeConfigFrom(__DIR__ . '/Config/taxes.sidebar.php', 'package.sidebar');

        $this->registerPermissions(
            __DIR__ . '/Config/taxes.permissions.php',
            'taxes'
        );

        $this->registerDatabaseSeeders(ScheduleSeeder::class);
    }

    private function addRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    private function addViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'taxes');
    }

    private function addMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    public function getName(): string
    {
        return 'SeAT Taxes';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/deirdrelear/eveseat-taxes';
    }

    public function getPackagistPackageName(): string
    {
        return 'eveseat-taxes';
    }

    public function getPackagistVendorName(): string
    {
        return 'deirdrelear';
    }
}
