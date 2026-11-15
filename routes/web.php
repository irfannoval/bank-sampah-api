<?php

use Illuminate\Support\Facades\Route;

// Semua alamat selain API, health check, dan berkas statis diserahkan ke aplikasi Vue.
Route::view('/{any?}', 'app')->where('any', '^(?!api/|up$|build/|storage/|icons/).*$');
