<?php

use Illuminate\Support\Facades\Route;

Route::group(
    [
        'prefix' => 'customer-io',
        'middleware' => config('customer-io.all_routes_middleware'),
    ],
    function () {
        Route::post(
            '/submit-email-form',
            \Railroad\CustomerIo\Controllers\CustomerIoController::class.'@submitEmailForm'
        )
            ->name('customer-io.submit-email-form');
    }
);