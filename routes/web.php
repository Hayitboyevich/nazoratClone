<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/object-info/{id}', [SiteController::class, 'index'])->name('object-info');
Route::get('/object-elastic', [SiteController::class, 'elastic'])->name('object-elastic');
Route::get('/presentation-pdf/{id}', [SiteController::class, 'presentationPdf']);
Route::get('/presentation-deed-pdf/{id}', [SiteController::class, 'presentationDeedPdf']);
Route::get('/declaration-info/{id}', [SiteController::class, 'declarationPdf'])->name('declaration-pdf');
//Route::get('conclusion', [SiteController::class, 'conclusionPdf'])->name('conclusion');
Route::get('/regulation-info/{id}', [SiteController::class, 'regulation'])->name('regulation-info');
Route::get('/regulation/{id}', [SiteController::class, 'regulationSimple'])->name('regulation');
Route::get('/lab/{id}', [SiteController::class, 'lab'])->name('lab');
Route::get('/object/{id}', [SiteController::class, 'object'])->name('object');
Route::get('/object', [SiteController::class, 'json'])->name('json');
Route::get('claimgov/pdf/{id}', [SiteController::class, 'claimgovPdf'])->name('claimgov.pdf');
Route::get('claimgov/conclusion/{id}', [SiteController::class, 'claimgovConclusionPdf']);
Route::get('claim-gov/organization-pdf/{id}/download', [SiteController::class, 'claimGovPdfOrganizationDownload']);
Route::get('hidden-work-pdf/{id}', [SiteController::class, 'hiddenWorkPdf']);
Route::get('testExcel', [SiteController::class, 'testExcel']);
Route::get('test/{id}', [SiteController::class, 'test']);
Route::post('test-mygov', [SiteController::class, 'testMyGov']);



Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource('roles', RoleController::class);
Route::resource('users', UserController::class);
Route::resource('permissions', PermissionController::class);

require __DIR__.'/auth.php';
