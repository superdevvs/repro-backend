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
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DropboxAuthController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working V1'
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

// Test endpoints (remove in production)
Route::get('test/dropbox-config', [App\Http\Controllers\TestDropboxController::class, 'debugConfig']);
Route::get('test/dropbox-curl', [App\Http\Controllers\TestDropboxController::class, 'testWithCurl']);
Route::get('test/dropbox-connection', [App\Http\Controllers\TestDropboxController::class, 'testConnection']);
Route::get('test/dropbox-folder', [App\Http\Controllers\TestDropboxController::class, 'testFolderCreation']);
Route::get('test/folder-structure', [App\Http\Controllers\TestDropboxController::class, 'testFolderStructure']);
Route::get('test/create-shoot', [App\Http\Controllers\TestDropboxController::class, 'createTestShoot']);
Route::post('test/create-shoot-api', [App\Http\Controllers\TestDropboxController::class, 'createTestShootViaAPI']);
Route::get('dropbox/setup-long-lived-token', [App\Http\Controllers\TestDropboxController::class, 'setupLongLivedToken']);

// Address lookup endpoints
Route::prefix('address')->group(function () {
    Route::get('search', [App\Http\Controllers\AddressLookupController::class, 'searchAddresses']);
    Route::get('details', [App\Http\Controllers\AddressLookupController::class, 'getAddressDetails']);
    Route::post('validate', [App\Http\Controllers\AddressLookupController::class, 'validateAddress']);
    Route::post('distance', [App\Http\Controllers\AddressLookupController::class, 'calculateDistance']);
    Route::get('service-area', [App\Http\Controllers\AddressLookupController::class, 'checkServiceArea']);
    Route::get('nearby-photographers', [App\Http\Controllers\AddressLookupController::class, 'getNearbyPhotographers']);
});

// Mail test endpoints (remove in production)
Route::prefix('test/mail')->group(function () {
    Route::get('config', [App\Http\Controllers\TestMailController::class, 'getMailConfig']);
    Route::get('account-created', [App\Http\Controllers\TestMailController::class, 'testAccountCreated']);
    Route::get('shoot-scheduled', [App\Http\Controllers\TestMailController::class, 'testShootScheduled']);
    Route::get('shoot-ready', [App\Http\Controllers\TestMailController::class, 'testShootReady']);
    Route::get('payment-confirmation', [App\Http\Controllers\TestMailController::class, 'testPaymentConfirmation']);
    Route::get('all', [App\Http\Controllers\TestMailController::class, 'testAllEmails']);
});

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

Route::middleware(['auth:sanctum','role:admin,super_admin'])->patch('/admin/users/{id}/role', [UserController::class, 'updateRole']);
Route::middleware(['auth:sanctum'])->post('/admin/users', [UserController::class, 'store']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->get('/admin/clients', [UserController::class, 'getClients']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin,client,superadmin'])->get('/admin/photographers', [UserController::class, 'getPhotographers']);
// Public lightweight list for dropdowns
Route::get('/photographers', [UserController::class, 'simplePhotographers']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::post('/admin/services', [ServiceController::class, 'store']);

    Route::put('/admin/services/{id}', [ServiceController::class, 'update']);

    Route::delete('/admin/services/{id}', [ServiceController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->prefix('admin')->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download']);
    Route::patch('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Shoot management
    Route::get('/shoots', [ShootController::class, 'index']);
    Route::post('/shoots', [ShootController::class, 'store']);
    Route::get('/shoots/{shoot}', [ShootController::class, 'show']);
    // Minimal update endpoint for status/workflow updates
    Route::patch('/shoots/{shoot}', [ShootController::class, 'update']);
    Route::patch('/shoots/{shoot}/notes', [ShootController::class, 'updateNotesSimple']);
    
    // File workflow endpoints
    Route::post('/shoots/{shoot}/upload', [ShootController::class, 'uploadFiles']);
    Route::post('/shoots/{shoot}/files/{file}/move-to-completed', [ShootController::class, 'moveFileToCompleted']);
    Route::post('/shoots/{shoot}/files/{file}/verify', [ShootController::class, 'verifyFile']);
    Route::get('/shoots/{shoot}/workflow-status', [ShootController::class, 'getWorkflowStatus']);
    
    // Enhanced file upload endpoints
    Route::post('/shoots/{shoot}/upload-from-pc', [App\Http\Controllers\FileUploadController::class, 'uploadFromPC']);
    Route::post('/shoots/{shoot}/copy-from-dropbox', [App\Http\Controllers\FileUploadController::class, 'copyFromDropbox']);
    Route::get('/dropbox/browse', [App\Http\Controllers\FileUploadController::class, 'listDropboxFiles']);

    // Finalize a shoot (admin toggle triggers this)
    Route::post('/shoots/{shoot}/finalize', [ShootController::class, 'finalize']);
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

// Public read-only endpoints for client-facing pages
Route::prefix('public/shoots')->group(function () {
    Route::get('{shoot}/branded', [ShootController::class, 'publicBranded']);
    Route::get('{shoot}/mls', [ShootController::class, 'publicMls']);
    Route::get('{shoot}/generic-mls', [ShootController::class, 'publicGenericMls']);
});

// Public client profile
Route::prefix('public')->group(function () {
    Route::get('/clients/{client}/profile', [ShootController::class, 'publicClientProfile']);
});

