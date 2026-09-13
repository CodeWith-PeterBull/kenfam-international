<?php

use App\Http\Controllers\Admin\CommunicationTemplateController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InstitutionDetailController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SystemActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ContentManager\DashboardController as ContentManagerDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Editor\DashboardController as EditorDashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Viewer\DashboardController as ViewerDashboardController;
use App\Support\CmsPermission;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('redirect.role.dashboard')
        ->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::controller(AdminDashboardController::class)
            ->middleware('role:system-admin')
            ->group(function () {
                Route::get('/dashboard', 'index')->name('dashboard');
                Route::get('/blank', 'blank')->name('blank');
            });
        Route::get('/system-activity', [SystemActivityController::class, 'index'])
            ->middleware('permission:'.CmsPermission::VIEW_SYSTEM_ACTIVITIES)
            ->name('system-activity.index');
        Route::get('/institution-details', [InstitutionDetailController::class, 'index'])
            ->middleware('permission:'.CmsPermission::VIEW_INSTITUTION_DETAILS)
            ->name('institution-details.index');
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:'.CmsPermission::VIEW_USERS)
            ->name('users.index');
        Route::get('/roles-and-permissions', [RolePermissionController::class, 'index'])
            ->middleware('permission:'.CmsPermission::VIEW_ROLES_AND_PERMISSIONS)
            ->name('roles-and-permissions.index');

        Route::controller(CommunicationTemplateController::class)
            ->prefix('communication-templates')
            ->name('communication-templates.')
            ->group(function () {
                Route::middleware('permission:'.CmsPermission::PREVIEW_COMMUNICATION_TEMPLATES)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::get('/mail-preview', 'mailPreview')->name('mail-preview');
                        Route::get('/report-preview/{orientation}', 'reportPreview')
                            ->where('orientation', 'portrait|landscape')
                            ->name('report-preview');
                        Route::get('/report-download/{orientation}', 'reportDownload')
                            ->where('orientation', 'portrait|landscape')
                            ->name('report-download');
                    });

                Route::post('/test-mail', 'sendTestMail')
                    ->middleware([
                        'permission:'.CmsPermission::SEND_TEST_NOTIFICATIONS,
                        'throttle:communication-test-mail',
                    ])
                    ->name('test-mail');
            });
    });

    Route::controller(ContentManagerDashboardController::class)
        ->middleware('role:content-manager')
        ->prefix('content')
        ->name('content-manager.')
        ->group(function () {
            Route::get('/dashboard', 'index')->name('dashboard');
            Route::get('/queue', 'queue')->name('queue');
            Route::get('/calendar', 'calendar')->name('calendar');
        });

    Route::controller(EditorDashboardController::class)
        ->middleware('role:editor')
        ->prefix('editor')
        ->name('editor.')
        ->group(function () {
            Route::get('/dashboard', 'index')->name('dashboard');
            Route::get('/drafts', 'drafts')->name('drafts');
            Route::get('/reviews', 'reviews')->name('reviews');
        });

    Route::controller(ViewerDashboardController::class)
        ->middleware('role:viewer')
        ->prefix('viewer')
        ->name('viewer.')
        ->group(function () {
            Route::get('/dashboard', 'index')->name('dashboard');
            Route::get('/library', 'library')->name('library');
            Route::get('/saved', 'saved')->name('saved');
        });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
