<?php

use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');

Route::post('/contato', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1');
});

Route::post('/admin/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/mensagens', [ContactMessageController::class, 'index'])->name('messages.index');
    Route::get('/mensagens/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
    Route::delete('/mensagens/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy');

    Route::resource('projetos', ProjectController::class)
        ->parameters(['projetos' => 'project'])
        ->except(['show'])
        ->names('projects');

    Route::get('/tarefas', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tarefas', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tarefas/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tarefas/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/configuracoes', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingController::class, 'update'])->name('settings.update');
});
