<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\API\ShootController;
use App\Http\Controllers\PhotographerAvailabilityController;
use App\Http\Controllers\PhotographerShootController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DropboxAuthController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working'
    ]);
});

Route::prefix('dropbox')->name('dropbox.')->group(function () {
    // Auth
    Route::get('connect', [DropboxAuthController::class, 'connect'])->name('connect');
    Route::get('callback', [DropboxAuthController::class, 'callback'])->name('callback');
    Route::post('disconnect', [DropboxAuthController::class, 'disconnect'])->name('disconnect');

    // User Info
    Route::get('user', [DropboxAuthController::class, 'getUserAccount'])->name('user');

    // File Operations
    Route::get('files/list', [DropboxAuthController::class, 'listFiles'])->name('files.list');
    Route::post('files/upload', [DropboxAuthController::class, 'uploadFile'])->name('files.upload');
    Route::get('files/download', [DropboxAuthController::class, 'downloadFile'])->name('files.download');
    Route::post('files/delete', [DropboxAuthController::class, 'deleteFile'])->name('files.delete');

    // Webhook (can be in api.php if it's stateless)
    Route::match(['get', 'post'], 'webhook', [DropboxAuthController::class, 'webhook'])->name('webhook');
});

// Route::post('/shoots/{shoot}/create-payment-link', [PaymentController::class, 'createCheckoutLink']);

Route::post('webhooks/square', [PaymentController::class, 'handleWebhook'])
    ->middleware('square.webhook') // Verifies the request is genuinely from Square
    ->name('webhooks.square');

// Group of routes that require user authentication (e.g., using Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Creates a checkout link for a specific photography shoot.
    // The {shoot} parameter is a route model binding.
    // e.g., POST /api/shoots/123/create-checkout-link
    Route::post('shoots/{shoot}/create-checkout-link', [PaymentController::class, 'createCheckoutLink'])
        ->name('api.shoots.payment.create-link');

    // Initiates a refund for a given payment.
    // The Square Payment ID should be sent in the request body.
    // e.g., POST /api/payments/refund
    Route::post('payments/refund', [PaymentController::class, 'refundPayment'])
        ->name('api.payments.refund');

});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->get('/admin/users', [UserController::class, 'index']);

Route::middleware(['auth:sanctum'])->post('/admin/users', [UserController::class, 'store']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->get('/admin/clients', [UserController::class, 'getClients']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin,client'])->get('/admin/photographers', [UserController::class, 'getPhotographers']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::post('/admin/services', [ServiceController::class, 'store']);

    Route::put('/admin/services/{id}', [ServiceController::class, 'update']);

    Route::delete('/admin/services/{id}', [ServiceController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/shoots', [ShootController::class, 'index']);
    Route::post('/shoots', [ShootController::class, 'store']);
});

Route::get('/services', [ServiceController::class, 'index']);

Route::get('/services/{id}', [ServiceController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']); // Public

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

Route::prefix('photographer/availability')->group(function () {
    // Get all availability for a photographer
    Route::get('/{photographerId}', [PhotographerAvailabilityController::class, 'index']);

    // Add single availability
    Route::post('/', [PhotographerAvailabilityController::class, 'store']);

    // Bulk add availability (weekly schedule)
    Route::post('/bulk', [PhotographerAvailabilityController::class, 'bulkStore']);

    // Update availability
    Route::put('/{id}', [PhotographerAvailabilityController::class, 'update']);

    // Delete single availability
    Route::delete('/{id}', [PhotographerAvailabilityController::class, 'destroy']);

    // Clear all availability for a photographer
    Route::delete('/clear/{photographerId}', [PhotographerAvailabilityController::class, 'clearAll']);

    // Check availability for a specific date (for one photographer)
    Route::post('/check', [PhotographerAvailabilityController::class, 'checkAvailability']);

    // Find all photographers available for given date & time
    Route::post('/available-photographers', [PhotographerAvailabilityController::class, 'availablePhotographers']);
});

// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/photographer/shoots', [PhotographerShootController::class, 'index']);
});

Route::middleware('auth:sanctum')->post('/shoots/{shoot}/upload', [ShootController::class, 'uploadFiles'])->name('shoots.upload');