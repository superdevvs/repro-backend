<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\CategoryController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working'
    ]);
});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->get('/admin/users', [UserController::class, 'index']);

Route::middleware(['auth:sanctum'])->post('/admin/users', [UserController::class, 'store']);

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::post('/admin/services', [ServiceController::class, 'store']);

    Route::put('/admin/services/{id}', [ServiceController::class, 'update']);

    Route::delete('/admin/services/{id}', [ServiceController::class, 'destroy']);
});

Route::get('/services', [ServiceController::class, 'index']);

Route::get('/services/{id}', [ServiceController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']); // Public

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});