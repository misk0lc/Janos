<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\Api\AuthController;
use \App\Http\Controllers\Api\EventController;
use \App\Http\Controllers\Api\RegistrationController;
use \App\Http\Controllers\Api\UserController;


//Without authentication
Route::get('/ping', function() {return response()->json(['message' => 'API működik']);});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


//Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);
    Route::post('/logout', [UserController::class, 'logout']);
    
    //Event CRUD
    Route::prefix('events')->group(function(){
        Route::get('/', [EventController::class, 'index']);
        Route::get('/upcoming', [EventController::class, 'upcoming']);
        Route::get('/past', [EventController::class, 'past']);
        Route::get('/filter', [EventController::class, 'filter']);
        
        // Event Admin only CRUD
        Route::post('/', [EventController::class, 'store']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);

        // Registration 
        Route::post('{event}/register', [RegistrationController::class, 'register']);
        Route::delete('{event}/unregister', [RegistrationController::class, 'unregister']);
        Route::delete('{event}/users/{user}', [RegistrationController::class, 'adminRemoveUser']);
    });

    // ---------------------------
    // User CRUD (admin only)
    // ---------------------------
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});

