<?php

use App\Http\Controllers\Dev\BrevoMailTestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route de diagnostic reservee au developpement : jamais exposee en production,
// meme si le token BREVO_TEST_TOKEN venait a fuiter.
if (! app()->isProduction()) {
    Route::get('/dev/test-brevo-mail', BrevoMailTestController::class);
}
