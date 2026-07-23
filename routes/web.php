<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IdeaNoteController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\OpportunityController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectStepController;
use App\Http\Controllers\Admin\GoogleOAuthController;
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

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/ideias', [IdeaNoteController::class, 'store'])->name('idea-notes.store');
    Route::put('/ideias/{ideaNote}', [IdeaNoteController::class, 'update'])->name('idea-notes.update');
    Route::patch('/ideias/{ideaNote}/cor', [IdeaNoteController::class, 'updateColor'])->name('idea-notes.color');
    Route::delete('/ideias/{ideaNote}', [IdeaNoteController::class, 'destroy'])->name('idea-notes.destroy');

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

    Route::resource('empresas', CompanyController::class)
        ->parameters(['empresas' => 'company'])
        ->names('companies');
    Route::post('/empresas/{company}/projetos', [CompanyController::class, 'attachProject'])
        ->name('companies.projects.attach');
    Route::delete('/empresas/{company}/projetos/{project}', [CompanyController::class, 'detachProject'])
        ->name('companies.projects.detach');
    Route::post('/empresas/{company}/contatos', [CompanyController::class, 'storeContact'])
        ->name('companies.contacts.store');

    Route::post('/contatos/atividades/lote', [AdminContactController::class, 'storeBulkActivity'])
        ->name('contacts.activities.bulk');
    Route::resource('contatos', AdminContactController::class)
        ->parameters(['contatos' => 'contact'])
        ->names('contacts');
    Route::post('/contatos/{contact}/atividades', [AdminContactController::class, 'storeActivity'])
        ->name('contacts.activities.store');
    Route::get('/contatos/{contact}/atividades/{activity}/editar', [AdminContactController::class, 'editActivity'])
        ->name('contacts.activities.edit');
    Route::put('/contatos/{contact}/atividades/{activity}', [AdminContactController::class, 'updateActivity'])
        ->name('contacts.activities.update');
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
    Route::patch('/oportunidades/{opportunity}/stage', [OpportunityController::class, 'updateStage'])
        ->name('opportunities.stage');

    Route::resource('projetos', ProjectController::class)
        ->parameters(['projetos' => 'project'])
        ->except(['show'])
        ->names('projects');
    Route::patch('/projetos/{project}/status', [ProjectController::class, 'updateStatus'])
        ->name('projects.status');
    Route::get('/projetos/{project}/contrato', [ProjectController::class, 'downloadContract'])
        ->name('projects.contract');
    Route::post('/projetos/{project}/etapas', [ProjectStepController::class, 'store'])
        ->name('projects.steps.store');
    Route::put('/projetos/{project}/etapas/{step}', [ProjectStepController::class, 'update'])
        ->name('projects.steps.update');
    Route::patch('/projetos/{project}/etapas/{step}/toggle', [ProjectStepController::class, 'toggle'])
        ->name('projects.steps.toggle');
    Route::delete('/projetos/{project}/etapas/{step}', [ProjectStepController::class, 'destroy'])
        ->name('projects.steps.destroy');

    Route::get('/tarefas', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tarefas/exportar.ics', [TaskController::class, 'export'])->name('tasks.export');
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

    Route::get('/google/connect', [GoogleOAuthController::class, 'redirect'])->name('google.connect');
    Route::get('/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleOAuthController::class, 'disconnect'])->name('google.disconnect');
    Route::post('/google/test', [GoogleOAuthController::class, 'test'])->name('google.test');
});
