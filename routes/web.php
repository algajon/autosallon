<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\InStockController;
use Illuminate\Support\Facades\Route;

Route::get('/search',        [VehicleController::class, 'search'])->name('vehicles.search');       // full page
Route::get('/search/suggest',[VehicleController::class, 'suggest'])->name('vehicles.suggest');     // live suggestions (AJAX)
// Auth
Route::get('/', [VehicleController::class, 'publicIndex'])->name('index');      // list page
Route::get('/listing/{vehicle}', [VehicleController::class, 'publicShow'])
    ->whereNumber('vehicle')
    ->name('listing.show');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/toggle/{vehicle}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::delete('/favorites/{vehicle}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
});

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/vehicles',                [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create',         [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles',               [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}',      [VehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}',      [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}',   [VehicleController::class, 'destroy'])->name('vehicles.destroy');

        // InStock (admin-only)
        Route::get('/instocks',             [InStockController::class, 'index'])->name('instocks.index');
        Route::get('/instocks/create',      [InStockController::class, 'create'])->name('instocks.create');
        Route::post('/instocks',            [InStockController::class, 'store'])->name('instocks.store');
        Route::get('/instocks/{instock}/edit', [InStockController::class, 'edit'])->name('instocks.edit');
        Route::put('/instocks/{instock}',   [InStockController::class, 'update'])->name('instocks.update');
        Route::delete('/instocks/{instock}',[InStockController::class, 'destroy'])->name('instocks.destroy');
    });
