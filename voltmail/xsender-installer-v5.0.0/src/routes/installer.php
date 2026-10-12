<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\PurchaseValidation;
use App\Http\Middleware\SoftwareVerification;
use App\Http\Controllers\InstallerController;


    #Install
    Route::controller(InstallerController::class)->prefix("/install")->name('install.')
     ->middleware(['sanitizer'])
     ->withoutMiddleware([SoftwareVerification::class,PurchaseValidation::class])->group(function(){

        Route::get('/','init')->name('init');
        Route::get('/requirement-verification','requirementVerification')->name('requirement.verification');
        Route::get('/envato-verification','envatoVerification')->name('envato.verification');
        Route::post('/purchase-code/verification','purchaseVerification')->name('purchase.code.verification');
        Route::get('/db-setup','dbSetup')->name('db.setup');
        Route::post('/db-store','dbStore')->name('db.store');
        // v4.9 — MySQL compatibility pre-flight. Called by dbSetup form BEFORE
        // POSTing dbStore, so the customer sees actionable errors (wrong storage
        // engine, missing utf8mb4, sql_require_primary_key ON, etc.) before we
        // write .env and run migrations that would otherwise crash mid-flight.
        Route::post('/db-preflight','dbPreflight')->name('db.preflight');
        Route::get('account/setup','accountSetup')->name('account.setup');
        Route::post('account/setup/store','accountSetupStore')->name('account.setup.store');
        Route::get('setup-finished','setupFinished')->name('setup.finished');

        // CSRF Token Refresh endpoint - prevents 419 errors during long installation sessions
        Route::get('/csrf-refresh', function() {
            return response()->json(['token' => csrf_token()]);
        })->name('csrf.refresh');

   });



   Route::get('invalid-license',[InstallerController::class ,'invalidPurchase'])->name('invalid.purchase')->middleware(['sanitizer','firewall.all'])
   ->withoutMiddleware([PurchaseValidation::class]);

   Route::post('verify-purchase',[InstallerController::class ,'verifyPurchase'])->name('verify.puchase')->middleware(['sanitizer','firewall.all'])
   ->withoutMiddleware([PurchaseValidation::class]);

