<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Http\Controllers\SteamLoginController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SteamLoginController::class, function() {
            return new SteamLoginController;
        });
        
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
