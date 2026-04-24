<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
|
| All auth routes in this application are registered by Laravel Fortify
| via FortifyServiceProvider. This file is intentionally left as a stub.
|
| Fortify registers:
|   POST   /login                → login
|   POST   /logout               → logout
|   POST   /forgot-password      → password.email
|   POST   /reset-password       → password.reset (token-based)
|   PUT    /user/password        → password.update (profile password change)
|   PUT    /user/profile-info    → user-profile-information.update
|   GET    /two-factor-challenge → two-factor.login
|   etc.
|
| Do not register Breeze-style auth routes here — they will conflict with Fortify.
|
*/

