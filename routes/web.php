<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/cc-images/{filename}', function ($filename) {
    $filePath = storage_path('app/public/cc-images/' . $filename);

    if (!file_exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('filename', '[A-Za-z0-9_\-\.]+');
