<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('import.index'));

Route::prefix('import')->name('import.')->controller(ImportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/upload', 'upload')->name('upload');
    Route::get('/{batch}/map', 'map')->name('map');
    Route::post('/{batch}/map', 'saveMapping')->name('saveMapping');
    Route::post('/{batch}/validate', 'validateBatch')->name('validate');
    Route::get('/{batch}/preview', 'preview')->name('preview');
    Route::post('/{batch}/commit', 'commit')->name('commit');
    Route::get('/{batch}/status', 'status')->name('status');
    Route::get('/{batch}/status-json', 'statusJson')->name('status.json');
});

Route::get('/records', [ImportController::class, 'records'])->name('records');
