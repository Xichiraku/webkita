<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\MilestoneUpdateController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectActivityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/carakerja', [PageController::class, 'carakerja'])->name('carakerja');
Route::get('/harga', [PageController::class, 'harga'])->name('harga');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');

    Route::get('/orderan', [PageController::class, 'orderan'])->name('orderan');
    Route::get('/orderan/baru', [PageController::class, 'orderBaru'])->name('orderan.baru');
    Route::get('/orderan/{project}', [WorkspaceController::class, 'client'])->whereNumber('project')->name('orderan.show');
    Route::get('/invoice/{project}', [PageController::class, 'invoice'])->name('invoice');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Developer (role developer atau admin): proyek yang ditugaskan kepadanya
    Route::middleware('role:admin,developer')->prefix('developer')->name('developer.')->group(function () {
        Route::get('proyek', [WorkspaceController::class, 'developerIndex'])->name('projects');
        Route::get('proyek/{project}', [WorkspaceController::class, 'developer'])->whereNumber('project')->name('projects.show');
    });

    // Panel admin (admin & developer)
    Route::middleware('role:admin,developer')->prefix('admin')->name('admin.')->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::view('orderan', 'admin.orders')->name('orders');
        Route::view('pengguna', 'admin.users')->name('users');
        Route::view('transaksi', 'admin.transactions')->name('transactions');
        Route::view('paket', 'admin.pricing')->name('pricing');
    });
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| JSON API (sesi login + CSRF), dipanggil dari halaman Blade/Alpine
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    // Harga publik
    Route::get('pricing/public', [PricingController::class, 'publicIndex'])->middleware('throttle:api');
    Route::get('pricing/public/tier/{tier}', [PricingController::class, 'publicTier']);

    // Webhook Midtrans (tanpa login & CSRF; diamankan signature)
    Route::post('payments/notification', [PaymentController::class, 'notification'])->middleware('throttle:120,1');

    Route::middleware(['auth', 'throttle:api'])->group(function () {
        Route::middleware('role:admin,developer')->group(function () {
            Route::get('pricing', [PricingController::class, 'index']);
            Route::post('pricing', [PricingController::class, 'store']);
            Route::get('pricing/{plan}', [PricingController::class, 'show']);
            Route::put('pricing/{plan}', [PricingController::class, 'update']);
            Route::delete('pricing/{plan}', [PricingController::class, 'destroy']);
            Route::patch('pricing/{plan}/toggle-active', [PricingController::class, 'toggleActive']);

            Route::get('users', [UserController::class, 'index']);
        });

        Route::delete('users/{username}', [UserController::class, 'destroy'])->middleware('role:admin');

        // /my dan /user/{id} harus sebelum /{project}
        Route::get('projects/my', [ProjectController::class, 'my']);
        Route::get('projects/user/{userId}', [ProjectController::class, 'byUser'])->whereNumber('userId');
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);
        Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
        Route::get('projects/{project}/activities', [ProjectActivityController::class, 'index']);
        Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
        Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);
        Route::patch('projects/{project}/milestones/order', [MilestoneController::class, 'reorder']);
        Route::patch('milestones/{milestone}', [MilestoneController::class, 'update']);
        Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy']);
        Route::get('milestones/{milestone}/updates', [MilestoneUpdateController::class, 'index']);
        Route::post('milestones/{milestone}/updates', [MilestoneUpdateController::class, 'store'])->middleware('throttle:updates');
        Route::get('projects/{project}/comments', [CommentController::class, 'index']);
        Route::post('projects/{project}/comments', [CommentController::class, 'store'])->middleware('throttle:comments');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
        Route::post('updates/{update}/approve', [ReviewController::class, 'approve']);
        Route::post('updates/{update}/request-revision', [ReviewController::class, 'requestRevision']);
        Route::get('projects/{project}/revisions', [ReviewController::class, 'revisions']);
        Route::patch('projects/{project}/revision-limit', [ReviewController::class, 'setLimit']);
        Route::get('projects/{project}/messages', [ChatController::class, 'index']);
        Route::post('projects/{project}/messages', [ChatController::class, 'store'])->middleware('throttle:chat');
        Route::post('projects/{project}/messages/read', [ChatController::class, 'read']);
        Route::delete('messages/{message}', [ChatController::class, 'destroy']);
        Route::get('chat/unread', [ChatController::class, 'unread']);
        Route::patch('projects/{project}/developer', [ProjectController::class, 'assignDeveloper']);
        Route::patch('projects/{project}/price', [ProjectController::class, 'setPrice']);
        Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

        Route::get('attachments/download/{attachment}', [AttachmentController::class, 'download']);
        Route::get('attachments/{project}', [AttachmentController::class, 'index']);
        Route::post('attachments/{project}', [AttachmentController::class, 'store'])->middleware('throttle:uploads');

        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/status/{orderId}', [PaymentController::class, 'status']);
        Route::post('payments/create/{project}', [PaymentController::class, 'create']);
        Route::get('payments/project/{project}', [PaymentController::class, 'projectStatus']);
        Route::get('payments/invoice/{project}', [PaymentController::class, 'invoice']);
    });
});
