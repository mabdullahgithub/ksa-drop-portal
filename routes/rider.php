<?php

use App\Http\Controllers\Rider\RiderAppController;
use App\Http\Controllers\Rider\RiderAuthController;
use App\Http\Controllers\Rider\RiderParcelController;
use App\Http\Controllers\Rider\RiderProfileController;
use App\Http\Controllers\Rider\RiderStockController;
use App\Http\Middleware\AuthenticateRider;
use App\Http\Middleware\EnsureRiderRole;
use App\Models\Rider;
use Illuminate\Support\Facades\Route;

/*
| KSA Express rider app. Registered under the `rider` middleware group
| (bootstrap/app.php): cookies but no session and no CSRF token — see
| RiderAuthService and VerifyRiderOrigin.
*/

Route::prefix('rider')->name('rider.')->group(function () {
    Route::redirect('/', RiderAppController::START_URL);
    Route::get('/app', [RiderAppController::class, 'app'])->name('app');
    Route::get('/manifest.webmanifest', [RiderAppController::class, 'manifest'])->name('manifest');
    Route::get('/activate/{token}', [RiderAppController::class, 'activate'])->middleware('throttle:30,1')->name('activate');
    Route::get('/activate/{token}/manifest.webmanifest', [RiderAppController::class, 'activationManifest'])->name('activate.manifest');

    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/activate', [RiderAuthController::class, 'activate'])->middleware('throttle:20,1')->name('activate');
        Route::post('/login', [RiderAuthController::class, 'login'])->middleware('throttle:rider-login')->name('login');

        Route::middleware(AuthenticateRider::class)->group(function () {
            Route::get('/me', [RiderParcelController::class, 'me'])->name('me');
            Route::get('/me/photo', [RiderProfileController::class, 'photo'])->name('photo');
            Route::post('/me/photo', [RiderProfileController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('photo.update');

            // Taking and delivering parcels: riders only.
            Route::middleware(EnsureRiderRole::class . ':' . Rider::ROLE_RIDER)->group(function () {
                Route::get('/cash', [RiderParcelController::class, 'cash'])->name('cash');
                Route::get('/parcels', [RiderParcelController::class, 'parcels'])->name('parcels');
                Route::get('/history', [RiderParcelController::class, 'history'])->name('history');
                Route::get('/scan', [RiderParcelController::class, 'scan'])->middleware('throttle:120,1')->name('scan');
                Route::post('/claim', [RiderParcelController::class, 'claim'])->middleware('throttle:240,1')->name('claim');
                Route::post('/shipments/{shipment}/events', [RiderParcelController::class, 'record'])->name('shipments.events');
            });

            // Scanning parcels OUT of and IN to the warehouse: inventory managers only.
            Route::middleware(EnsureRiderRole::class . ':' . Rider::ROLE_INVENTORY_MANAGER)->prefix('stock')->name('stock.')->group(function () {
                Route::get('/', [RiderStockController::class, 'index'])->name('index');
                Route::get('/scans', [RiderStockController::class, 'scans'])->name('scans');
                Route::post('/scan', [RiderStockController::class, 'scan'])->middleware('throttle:240,1')->name('scan');
            });

            Route::post('/presence', [RiderProfileController::class, 'presence'])->middleware('throttle:30,1')->name('presence');
            Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');
        });
    });
});
