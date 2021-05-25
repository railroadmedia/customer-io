<?php

namespace Railroad\CustomerIo\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class CustomerIoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->publishes(
            [
                __DIR__.'/../../config/customer-io.php' => config_path('customer-io.php'),
            ]
        );

        if (config('customer-io.data_mode') == 'host') {
            $this->loadMigrationsFrom(__DIR__.'/../../migrations');
        }
    }
}
