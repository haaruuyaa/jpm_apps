<?php

namespace App\Providers;

use App\Contracts\Interfaces\BCASnapRepositoryInterfaces;
use App\Contracts\Interfaces\BCASnapServicesInterfaces;
use App\Repositories\BCASnapRepositories;
use App\Services\BCASnapServices;
use Illuminate\Support\ServiceProvider;

class BCASnapProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->bind(BCASnapRepositoryInterfaces::class, BCASnapRepositories::class);
        $this->app->bind(BCASnapServicesInterfaces::class, BCASnapServices::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
