<?php

use Illuminate\Support\Facades\Route;

Route::get('/passkeys/register', [\App\Http\Controllers\Api\PasskeyController::class, 'registerOptions']);

Route::get('/passkeys/authenticate', [\App\Http\Controllers\Api\PasskeyController::class, 'requestOptions']);
