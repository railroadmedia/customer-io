<?php

namespace Railroad\CustomerIo\Tests;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\CustomerIo\Providers\CustomerIoServiceProvider;


class CustomerIoTestCase extends BaseTestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var AuthManager
     */
    protected $authManager;

    /**
     * @var Router
     */
    protected $router;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->artisan('migrate');
        $this->artisan('cache:clear', []);

        $this->faker = $this->app->make(Generator::class);
        $this->databaseManager = $this->app->make(DatabaseManager::class);
        $this->authManager = $this->app->make(AuthManager::class);
        $this->router = $this->app->make(Router::class);

        Carbon::setTestNow(Carbon::now()->startOfSecond());
    }

    /**
     * Define environment setup. (This runs *before* "setUp" method above)
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // setup config for testing
        $defaultConfig = require(__DIR__.'/../config/customer-io.php');

        foreach ($defaultConfig as $defaultConfigKey => $defaultConfigValue) {
            config()->set('customer-io.'.$defaultConfigKey, $defaultConfigValue);
        }

        // db
        config()->set('customer-io.data_mode', 'host');
        config()->set('customer-io.database_connection_name', 'customer_io_sqlite_tests');
        config()->set('database.default', 'customer_io_sqlite_tests');
        config()->set(
            'database.connections.'.'customer_io_sqlite_tests',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );

        // time
        Carbon::setTestNow(Carbon::now());

        // .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
        $dotenv->load();

        if (!empty(env('SANDBOX_CUSTOMER_IO_SITE_ID')) &&
            !empty(env('SANDBOX_CUSTOMER_IO_TRACK_API_KEY')) &&
            !empty(env('SANDBOX_CUSTOMER_IO_TRACK_API_KEY'))) {

            config()->set('customer-io.accounts.musora.site_id', env('SANDBOX_CUSTOMER_IO_SITE_ID'));
            config()->set('customer-io.accounts.musora.track_api_key', env('SANDBOX_CUSTOMER_IO_TRACK_API_KEY'));
            config()->set('customer-io.accounts.musora.app_api_key', env('SANDBOX_CUSTOMER_IO_APP_API_KEY'));
        }

        // service provider
        $app->register(CustomerIoServiceProvider::class);
    }
}