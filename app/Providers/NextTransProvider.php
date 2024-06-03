<?php

namespace App\Providers;

use App\Contracts\Interfaces\NextTransServicesInterfaces;
use App\Services\NextTransServices;
use Illuminate\Support\ServiceProvider;

class NextTransProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->bind(NextTransServicesInterfaces::class, NextTransServices::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
