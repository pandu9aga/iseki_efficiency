<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\TractorController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Leader\LeaderController;
use App\Http\Controllers\Leader\LeaderMemberController;
use App\Http\Controllers\Leader\LeaderMemberSelectionController;
use App\Http\Controllers\Leader\LeaderReportController;
use App\Http\Controllers\Member\DashboardMemberController;
use App\Http\Controllers\Member\MemberScanController;
use App\Http\Controllers\Member\MemberReportController;

/*
|--------------------------------------------------------------------------
| Guest Routes (belum login)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/', [MainController::class, 'index'])->name('login.form');
    Route::post('/login', [MainController::class, 'login'])->name('login');
    Route::post('/login-member', [MainController::class, 'login_member'])->name('login.member');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (setelah login)
|--------------------------------------------------------------------------
*/

// Logout (bisa diakses oleh siapa saja yang login)
Route::middleware('web')->group(function () {
    Route::get('/logout', [MainController::class, 'logout'])->name('logout');
    Route::get('/logout-member', [MainController::class, 'logout_member'])->name('logout.member');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Id_Type_User == 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('admins')->name('admins.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

    // CRUD User (hanya admin yang boleh kelola user)
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
        Route::put('/{id}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminUserController::class, 'destroy'])->name('destroy');
    });

    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
    // Tambahkan modul lain di sini nanti:

    Route::prefix('tractors')->name('tractors.')->group(function () {
        Route::get('/', [TractorController::class, 'index'])->name('index');   // ✅ ini yang dipakai
        Route::post('/', [TractorController::class, 'store'])->name('store');
        Route::put('/{tractor}', [TractorController::class, 'update'])->name('update');
        Route::delete('/{tractor}', [TractorController::class, 'destroy'])->name('destroy');
        Route::get('/import', [TractorController::class, 'importForm'])->name('import.form');
        Route::post('/import', [TractorController::class, 'import'])->name('import');
    });    // Route::resource('procedures', Admin\ProcedureController::class);

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('index');
    });
});

/*
|--------------------------------------------------------------------------
| Leader Routes (Id_Type_User == 2)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('leaders')->name('leaders.')->group(function () {
    Route::get('/dashboard', [LeaderController::class, 'index'])->name('dashboard');

    // Contoh: Leader hanya bisa lihat user, tidak bisa edit
    // Route::get('/users', [Leader\UserController::class, 'index'])->name('users.index');

    // Tambahkan modul khusus leader di sini nanti
    Route::get('/members', [LeaderMemberController::class, 'index'])->name('members.index');

    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/', [LeaderMemberController::class, 'index'])->name('index');
        Route::get('/select', [LeaderMemberSelectionController::class, 'create'])->name('select');
        Route::post('/select', [LeaderMemberSelectionController::class, 'store'])->name('select.store');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [LeaderReportController::class, 'index'])->name('index');
        Route::post('/report', [LeaderReportController::class, 'storeReport'])->name('report.store');

        // Cost
        Route::post('/cost', [LeaderReportController::class, 'storeCost'])->name('cost.store');
        Route::put('/cost/{cost}', [LeaderReportController::class, 'updateCost'])->name('cost.update');
        Route::delete('/cost/{cost}', [LeaderReportController::class, 'destroyCost'])->name('cost.destroy');

        // Power
        Route::post('/power', [LeaderReportController::class, 'storePower'])->name('power.store');
        Route::put('/power/{power}', [LeaderReportController::class, 'updatePower'])->name('power.update');
        Route::delete('/power/{power}', [LeaderReportController::class, 'destroyPower'])->name('power.destroy');

        // Penanganan
        Route::post('/penanganan', [LeaderReportController::class, 'storePenanganan'])->name('penanganan.store');
        Route::put('/penanganan/{penanganan}', [LeaderReportController::class, 'updatePenanganan'])->name('penanganan.update');
        Route::delete('/penanganan/{penanganan}', [LeaderReportController::class, 'destroyPenanganan'])->name('penanganan.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Member Routes (login via NIK, bukan Id_User)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('members')->name('members.')->group(function () {
    Route::get('/home', [DashboardMemberController::class, 'index'])->name('home');

    Route::get('/scan', [MemberScanController::class, 'index'])->name('scan.index');
    Route::post('/scan/verify', [MemberScanController::class, 'verify'])->name('scan.verify');
    Route::post('/scan', [MemberScanController::class, 'store'])->name('scan.store');

    Route::get('/report', [MemberReportController::class, 'index'])->name('report.index');
});
