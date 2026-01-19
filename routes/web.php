<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\PublicScanController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\TractorController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminMemberSelectionController;
use App\Http\Controllers\Admin\AdminJobMemberController;
use App\Http\Controllers\Admin\AdminDailyPlanningController;

use App\Http\Controllers\Leader\LeaderController;
use App\Http\Controllers\Leader\LeaderMemberController;
use App\Http\Controllers\Leader\LeaderMemberSelectionController;
use App\Http\Controllers\Leader\LeaderReportController;
use App\Http\Controllers\Leader\LeaderJobMemberController;
use App\Http\Controllers\Leader\LeaderDailyPlanningController;
use App\Http\Controllers\Member\DashboardMemberController;
use App\Http\Controllers\Member\MemberScanController;
use App\Http\Controllers\Member\MemberReportController;

/*
|--------------------------------------------------------------------------
| Guest Routes (hanya untuk login)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/', [MainController::class, 'index'])->name('login.form');
    Route::post('/login', [MainController::class, 'login'])->name('login');
    Route::post('/login-member', [MainController::class, 'login_member'])->name('login.member');
    Route::post('/login-area', [MainController::class, 'login_area'])->name('login.area');
});

/*
|--------------------------------------------------------------------------
| Logout Routes
|--------------------------------------------------------------------------
*/
Route::middleware('web')->group(function () {
    Route::get('/logout', [MainController::class, 'logout'])->name('logout');
    Route::get('/logout-member', [MainController::class, 'logout_member'])->name('logout.member');
    Route::get('/logout-area', [MainController::class, 'logout_area'])->name('logout.area');
});

/*
|--------------------------------------------------------------------------
| Area Authenticated Routes — DIPERBAIKI: tanpa middleware area.auth
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->group(function () {
    Route::get('/area/scan', [PublicScanController::class, 'index'])->name('area.scan');
    Route::post('/area/scan/verify', [PublicScanController::class, 'verify'])->name('area.scan.verify');
    Route::post('/area/scan', [PublicScanController::class, 'store'])->name('area.scan.store');
    Route::get('/area/report', [PublicReportController::class, 'index'])->name('area.report');
});

/*
|--------------------------------------------------------------------------
| Member Routes (login via NIK)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('members')->name('members.')->group(function () {
    Route::get('/home', [DashboardMemberController::class, 'index'])->name('home');
    Route::get('/scan', [MemberScanController::class, 'index'])->name('scan.index');
    Route::post('/scan/verify', [MemberScanController::class, 'verify'])->name('scan.verify');
    Route::post('/scan', [MemberScanController::class, 'store'])->name('scan.store');
    Route::get('/report', [MemberReportController::class, 'index'])->name('report.index');
});

/*
|--------------------------------------------------------------------------
| Leader Routes (Id_Type_User == 2)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('leaders')->name('leaders.')->group(function () {
    Route::get('/dashboard', [LeaderController::class, 'index'])->name('dashboard');
    Route::get('/dashboard-fullscreen', [LeaderController::class, 'fullscreen'])->name('dashboard.fullscreen');
    Route::get('/dashboard/export', [LeaderController::class, 'exportExcel'])->name('dashboard.export');

    Route::get('/members', [LeaderMemberController::class, 'index'])->name('members.index');

    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/', [LeaderMemberController::class, 'index'])->name('index');
        Route::get('/select', [LeaderMemberSelectionController::class, 'create'])->name('select');
        Route::post('/select', [LeaderMemberSelectionController::class, 'store'])->name('select.store');
    });

    // 🔥 Route delete scan untuk Leader (di luar grup reports)
    Route::delete('/scan/{scan}', [LeaderReportController::class, 'destroyScan'])->name('scan.destroy');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [LeaderReportController::class, 'index'])->name('index');
        Route::post('/report', [LeaderReportController::class, 'storeReport'])->name('report.store');

        Route::post('/cost', [LeaderReportController::class, 'storeCost'])->name('cost.store');
        Route::put('/cost/{cost}', [LeaderReportController::class, 'updateCost'])->name('cost.update');
        Route::delete('/cost/{cost}', [LeaderReportController::class, 'destroyCost'])->name('cost.destroy');

        Route::post('/power', [LeaderReportController::class, 'storePower'])->name('power.store');
        Route::put('/power/{power}', [LeaderReportController::class, 'updatePower'])->name('power.update');
        Route::delete('/power/{power}', [LeaderReportController::class, 'destroyPower'])->name('power.destroy');

        Route::post('/penanganan', [LeaderReportController::class, 'storePenanganan'])->name('penanganan.store');
        Route::put('/penanganan/{penanganan}', [LeaderReportController::class, 'updatePenanganan'])->name('penanganan.update');
        Route::delete('/penanganan/{penanganan}', [LeaderReportController::class, 'destroyPenanganan'])->name('penanganan.destroy');
        // ❌ HAPUS BARIS INI YANG SALAH:
        // Route::delete('/scan/{scan}', [AdminReportController::class, 'destroyScan'])->name('scan.destroy');
    });

    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::get('/', [LeaderJobMemberController::class, 'index'])->name('manage');
        Route::post('/', [LeaderJobMemberController::class, 'store'])->name('store');
        Route::put('/{jobMember}', [LeaderJobMemberController::class, 'update'])->name('update');
        Route::delete('/{jobMember}', [LeaderJobMemberController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('planning')->name('planning.')->group(function () {
        Route::get('/', [LeaderDailyPlanningController::class, 'create'])->name('create');
        Route::post('/', [LeaderDailyPlanningController::class, 'store'])->name('store');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Id_Type_User == 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->prefix('admins')->name('admins.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/dashboard-fullscreen', [AdminController::class, 'fullscreen'])->name('dashboard.fullscreen');
    Route::get('/dashboard/export', [AdminController::class, 'export'])->name('dashboard.export');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
        Route::put('/{id}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminUserController::class, 'destroy'])->name('destroy');
    });

    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');

    Route::prefix('tractors')->name('tractors.')->group(function () {
        Route::get('/', [TractorController::class, 'index'])->name('index');
        Route::post('/', [TractorController::class, 'store'])->name('store');
        Route::put('/{tractor}', [TractorController::class, 'update'])->name('update');
        Route::delete('/{tractor}', [TractorController::class, 'destroy'])->name('destroy');
        Route::get('/import', [TractorController::class, 'importForm'])->name('import.form');
        Route::post('/import', [TractorController::class, 'import'])->name('import');
    });

    // 🔥 GRUP REPORTS ADMIN — TAMBAHKAN ROUTE SCAN DESTROY DI SINI
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('index');
        Route::post('/report', [AdminReportController::class, 'storeReport'])->name('report.store');

        Route::post('/cost', [AdminReportController::class, 'storeCost'])->name('cost.store');
        Route::put('/cost/{cost}', [AdminReportController::class, 'updateCost'])->name('cost.update');
        Route::delete('/cost/{cost}', [AdminReportController::class, 'destroyCost'])->name('cost.destroy');

        Route::post('/power', [AdminReportController::class, 'storePower'])->name('power.store');
        Route::put('/power/{power}', [AdminReportController::class, 'updatePower'])->name('power.update');
        Route::delete('/power/{power}', [AdminReportController::class, 'destroyPower'])->name('power.destroy');

        Route::post('/penanganan', [AdminReportController::class, 'storePenanganan'])->name('penanganan.store');
        Route::put('/penanganan/{penanganan}', [AdminReportController::class, 'updatePenanganan'])->name('penanganan.update');
        Route::delete('/penanganan/{penanganan}', [AdminReportController::class, 'destroyPenanganan'])->name('penanganan.destroy');

        // ✅ TAMBAHKAN INI:
        Route::delete('/scan/{scan}', [AdminReportController::class, 'destroyScan'])->name('scan.destroy');
    });

    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/', [AdminMemberController::class, 'index'])->name('index');
        Route::get('/select', [AdminMemberSelectionController::class, 'create'])->name('select');
        Route::post('/select', [AdminMemberSelectionController::class, 'store'])->name('select.store');
    });

    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::get('/', [AdminJobMemberController::class, 'index'])->name('manage');
        Route::post('/', [AdminJobMemberController::class, 'store'])->name('store');
        Route::put('/{jobMember}', [AdminJobMemberController::class, 'update'])->name('update');
        Route::delete('/{jobMember}', [AdminJobMemberController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('planning')->name('planning.')->group(function () {
        Route::get('/', [AdminDailyPlanningController::class, 'create'])->name('create');
        Route::post('/', [AdminDailyPlanningController::class, 'store'])->name('store');
    });
});
