<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AdminController::class, 'loginForm'])->name('login');
Route::post('/login', [AdminController::class, 'login'])->name('login.submit');

Route::middleware('admin.auth')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard.alias');
    Route::post('/admin/settings/profile', [AdminController::class, 'saveProfile'])->name('admin.settings.profile');
    Route::post('/admin/settings/password', [AdminController::class, 'savePassword'])->name('admin.settings.password');
    Route::post('/admin/settings/app', [AdminController::class, 'saveSettings'])->name('admin.settings.app');
    Route::post('/admin/chats/{id}/message', [AdminController::class, 'sendChatMessage'])->name('admin.chats.message');
    Route::get('/admin/{module}', [AdminController::class, 'index'])->name('admin.module.index');
    Route::get('/admin/{module}/create', [AdminController::class, 'create'])->name('admin.module.create');
    Route::post('/admin/{module}', [AdminController::class, 'store'])->name('admin.module.store');
    Route::get('/admin/{module}/{id}', [AdminController::class, 'show'])->name('admin.module.show');
    Route::get('/admin/{module}/{id}/edit', [AdminController::class, 'edit'])->name('admin.module.edit');
    Route::put('/admin/{module}/{id}', [AdminController::class, 'update'])->name('admin.module.update');
    Route::delete('/admin/{module}/{id}', [AdminController::class, 'destroy'])->name('admin.module.destroy');
});
