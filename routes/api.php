<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NectaController;

Route::post('/index-details', [NectaController::class, 'findStudent']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
