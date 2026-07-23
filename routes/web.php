<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\OpportunityController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/privacidade', [SiteController::class, 'privacy'])->name('privacy');
Route::get('/cookies', [SiteController::class, 'cookies'])->name('cookies');

Route::post('/contato', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::post('/webhooks/telegram/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.telegram');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::post('/admin/login/telegram/start', [LoginController::class, 'startTelegram'])
        ->middleware('throttle:10,1')
        ->name('login.telegram.start');
    Route::get('/admin/login/telegram/status/{token}', [LoginController::class, 'telegramStatus'])
        ->middleware('throttle:60,1')
        ->name('login.telegram.status');
    Route::get('/admin/login/telegram/complete/{token}', [LoginController::class, 'completeTelegram'])
        ->middleware('throttle:20,1')
        ->name('login.telegram.complete');
    Route::get('/admin/login/telegram/callback', [LoginController::class, 'telegramWidget'])
        ->middleware('throttle:20,1')
        ->name('login.telegram.callback');
});

Route::post('/admin/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('profile.update');
    Route::put('/perfil/foto', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:20,1')
        ->name('profile.avatar');
    Route::delete('/perfil/sessoes/outras', [ProfileController::class, 'destroyOtherSessions'])
        ->name('profile.sessions.destroy-others');
    Route::delete('/perfil/sessoes/{session}', [ProfileController::class, 'destroySession'])
        ->name('profile.sessions.destroy');

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['show'])
        ->names('users');
    Route::put('/usuarios/{user}/foto', [UserController::class, 'updateAvatar'])
        ->name('users.avatar');
    Route::patch('/usuarios/{user}/ativo', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');

    Route::get('/integracoes', [IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integracoes', [IntegrationController::class, 'update'])->name('integrations.update');

    Route::get('/mensagens', [ContactMessageController::class, 'index'])->name('messages.index');
    Route::get('/mensagens/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
    Route::post('/mensagens/{message}/vincular-contato', [ContactMessageController::class, 'linkContact'])
        ->name('messages.link-contact');
    Route::delete('/mensagens/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy');

    Route::resource('contatos', AdminContactController::class)
        ->parameters(['contatos' => 'contact'])
        ->names('contacts');
    Route::post('/contatos/{contact}/atividades', [AdminContactController::class, 'storeActivity'])
        ->name('contacts.activities.store');
    Route::delete('/contatos/{contact}/atividades/{activity}', [AdminContactController::class, 'destroyActivity'])
        ->name('contacts.activities.destroy');
    Route::post('/contatos/{contact}/projetos', [AdminContactController::class, 'attachProject'])
        ->name('contacts.projects.attach');
    Route::delete('/contatos/{contact}/projetos/{project}', [AdminContactController::class, 'detachProject'])
        ->name('contacts.projects.detach');

    Route::resource('oportunidades', OpportunityController::class)
        ->parameters(['oportunidades' => 'opportunity'])
        ->except(['show'])
        ->names('opportunities');

    Route::resource('projetos', ProjectController::class)
        ->parameters(['projetos' => 'project'])
        ->except(['show'])
        ->names('projects');

    Route::get('/tarefas', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tarefas', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tarefas/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tarefas/{task}/google', [TaskController::class, 'syncGoogle'])->name('tasks.google');
    Route::delete('/tarefas/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::post('/anexos/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['contacts', 'opportunities', 'tasks', 'projects', 'users'])
        ->whereNumber('id')
        ->middleware('throttle:30,1')
        ->name('attachments.store');
    Route::get('/anexos/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');
    Route::delete('/anexos/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');
    Route::post('/anexos/{attachment}/restaurar', [AttachmentController::class, 'restore'])
        ->withTrashed()
        ->name('attachments.restore');
    Route::delete('/anexos/{attachment}/eliminar', [AttachmentController::class, 'purge'])
        ->withTrashed()
        ->name('attachments.purge');

    Route::get('/configuracoes', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingController::class, 'update'])->name('settings.update');
});
