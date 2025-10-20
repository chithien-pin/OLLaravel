<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CloudflareStreamService;

class CloudflareServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register CloudflareStreamService as singleton
        $this->app->singleton(CloudflareStreamService::class, function ($app) {
            return new CloudflareStreamService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}