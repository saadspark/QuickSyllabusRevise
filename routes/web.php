<?php

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pdf');
});

Route::post('/process-pdf', [PdfController::class, 'handlePdf'])->name('handle.pdf');
