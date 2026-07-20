<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;

// Endpoint// Public Routes (Bisa diakses siapa saja)
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/locations', [ProductController::class, 'locations']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Endpoint Protected (Butuh Login)
Route::middleware('auth:sanctum')->group(function () {
    
    // Endpoint Get Current User & Logout
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Endpoint Account (Bisa diakses Admin & Marketing)
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);
    
    // Endpoint Account (HANYA Admin)
    Route::middleware('role:admin')->group(function () {
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);
        
        // Nanti untuk CRUD Product (HANYA Admin)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });
});

