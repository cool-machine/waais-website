<?php

use App\Http\Controllers\Auth\DiscourseSsoController;
use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::get('/auth/email/callback/{user}', [EmailAuthController::class, 'callback'])
    ->middleware('signed')
    ->name('auth.email.callback');

Route::get('/discourse/sso', DiscourseSsoController::class)
    ->name('discourse.sso');
