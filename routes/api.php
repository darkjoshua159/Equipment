<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\UserController; 

// --- PUBLIC ROUTES ---
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/verify', [UserController::class, 'verify']);
Route::post('/forgot-password', [UserController::class, 'forgotPassword']); 
Route::post('/forgot-verify-otp', [UserController::class, 'forgotVerifyOtp']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);


// --- PROTECTED ROUTES (Combined into one group) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Equipment Routes (Your current setup is fine)
    Route::apiResource('equipment', EquipmentController::class);
    
    // User Profile Routes
    Route::get('/user/profile', [UserController::class, 'profile']); // Get Profile
    Route::post('/logout', [UserController::class, 'logout']); // Logout
    
    // Profile Update/Delete (FIXED LINE for POST override)
    Route::match(['post', 'put', 'patch'], '/user/profile', [UserController::class, 'update']);
    Route::delete('/user/profile', [UserController::class, 'destroy']);
    
});