<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\GiftCodeController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Public product routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{product}', [ProductController::class, 'show']);
});

// Public category routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
});

// Protected user routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // User profile
    Route::get('/users/me', [AuthController::class, 'me']);
    
    // Orders
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::get('/user/{userId}', [OrderController::class, 'userOrders']);
    });
    
    // Payments
    Route::prefix('payment')->group(function () {
        Route::post('/create', [PaymentController::class, 'create']);
        Route::get('/status/{orderId}', [PaymentController::class, 'status']);
    });
    
    // Support tickets
    Route::prefix('tickets')->group(function () {
        Route::post('/', [TicketController::class, 'store']);
        Route::get('/{userId}', [TicketController::class, 'userTickets']);
    });
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    
    // Product management
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
    });
    
    // Category management
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });
    
    // Gift code management
    Route::prefix('giftcodes')->group(function () {
        Route::post('/upload', [GiftCodeController::class, 'upload']);
        Route::get('/', [GiftCodeController::class, 'index']);
        Route::put('/{giftCode}', [GiftCodeController::class, 'update']);
    });
    
    // Order management
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::put('/{order}', [OrderController::class, 'update']);
    });
    
    // Ticket management
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::put('/{ticket}', [TicketController::class, 'update']);
    });
    
    // Settings management
    Route::prefix('settings')->group(function () {
        Route::get('/paghiper', [SettingsController::class, 'getPagHiper']);
        Route::post('/paghiper', [SettingsController::class, 'setPagHiper']);
        Route::post('/paghiper/test', [SettingsController::class, 'testPagHiper']);
    });
});

// Public webhook routes (no authentication)
Route::post('/payment/paghiper/notification', [PaymentController::class, 'webhook']);