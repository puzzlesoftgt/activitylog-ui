<?php

use Illuminate\Support\Facades\Route;
use Puzzlesoft\ActivitylogUi\Http\Controllers\ActivityController;
use Puzzlesoft\ActivitylogUi\Http\Controllers\AssetController;
use Puzzlesoft\ActivitylogUi\Http\Controllers\HistoryController;

Route::get('/assets/{file}', AssetController::class)
    ->where('file', 'app\.(css|js)')
    ->name('asset');

Route::get('/', [ActivityController::class, 'index'])->name('index');

Route::get('/history', HistoryController::class)->name('history');

Route::get('/{activity}', [ActivityController::class, 'show'])->name('show');
