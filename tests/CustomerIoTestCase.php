<?php

namespace Railroad\CustomerIo\Tests;

use Carbon\Carbon;
use Exception;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\LeadTracker\Providers\CustomerIoServiceProvider;


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

    protected function setUp()
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
    }

    /**
     * Define environment setup. (This runs *before* "setUp" method above)
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // setup config for testing
        $defaultConfig = require(__DIR__ . '/../config/customer-io.php');

        // db
        config()->set('lead-tracker.data_mode', 'host');
        config()->set('lead-tracker.database_connection_name', 'leadtracker_sqlite_tests');
        config()->set('database.default', 'leadtracker_sqlite_tests');
        config()->set(
            'database.connections.' . 'leadtracker_sqlite_tests',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );

        if (empty(config('lead-tracker.charset'))) {
            config()->set('lead-tracker.charset', 'utf8mb4');
        }

        if (empty(config('lead-tracker.collation'))) {
            config()->set('lead-tracker.collation', 'utf8mb4_unicode_ci');
        }


        // time
        Carbon::setTestNow(Carbon::now());

        // service provider
        $app->register(CustomerIoServiceProvider::class);
    }

    /**
     * We don't want to use mockery so this is a reimplementation of the mockery version.
     *
     * @param array|string $events
     * @return $this
     */
    public function expectsEvents($events)
    {
        $events = is_array($events) ? $events : func_get_args();

        $mock =
            $this->getMockBuilder(Dispatcher::class)
                ->setMethods(['fire', 'dispatch'])
                ->getMockForAbstractClass();

        $mock->method('fire')
            ->willReturnCallback(
                function ($called) {
                    $this->firedEvents[] = $called;
                }
            );

        $mock->method('dispatch')
            ->willReturnCallback(
                function ($called) {
                    $this->firedEvents[] = $called;
                }
            );

        $this->app->instance('events', $mock);

        $this->beforeApplicationDestroyed(
            function () use ($events) {
                $fired = $this->getFiredEvents($events);
                if ($eventsNotFired = array_diff($events, $fired)) {
                    throw new Exception(
                        'These expected events were not fired: [' . implode(', ', $eventsNotFired) . ']'
                    );
                }
            }
        );

        return $this;
    }
}