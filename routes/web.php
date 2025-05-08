<?php

use App\Http\Controllers\ZohoController;
use App\Services\ZohoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\PulpalPaymentController;
use App\Http\Middleware\InitPayment;
use App\Http\Middleware\ListCustomers;
use App\Http\Middleware\ListDueInvoices;
use App\Http\Middleware\ListInvoices;
use App\Http\Middleware\ListItems;
use App\Http\Middleware\ListRecurringInvoices;
use App\Http\Middleware\ListRepeatInvoices;
use App\Http\Middleware\NewInvoice;
use App\Http\Middleware\PulPalNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;

// https://www.zoho.com/books/api/v3/introduction/#organization-id
Route::group(['prefix' => 'zoho'], function () {
    Route::get('/new-access-and-refresh', [ZohoApiService::class, 'generateNewTokens']);
    Route::get('/customers', [ZohoController::class, 'listCustomers'])
        ->name('zoho-customers')
        ->middleware(ListCustomers::class);
    Route::get('/invoices', [ZohoController::class, 'listInvoices'])
        ->name('zoho-invoices')
        ->middleware(ListInvoices::class);
    Route::get('/items', [ZohoController::class, 'listItems'])
        ->name('zoho-items')
        ->middleware(ListItems::class);

    Route::post('/new-item', [ZohoController::class, 'newItem'])->name('zoho-new-item');
    Route::post('/new-invoice', [ZohoController::class, 'newInvoice'])
        ->name('zoho-new-invoice')
        ->middleware(NewInvoice::class);
    
    Route::get('/new-item', function () {
        return response()->view('new-item');
    });
    Route::get('/new-invoice', function () {
        return response()->view('new-invoice');
    });
    Route::get('/pay-invoice', function () {
        return view('pay-invoice');
    });
});

Route::get('/pulpal/short/', function(Request $request){
    $shorturl = DB::table('shortened_urls')->where([
        'id' => $request->short_id
    ])->first();
    return redirect($shorturl->original);
})->name('pulpal.short');

Route::get('/pulpal/initiate-payment', [PulpalPaymentController::class, 'embedPaymentURL'])
    ->name('pulpal.initiate')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->middleware(ListInvoices::class)
    ->middleware(InitPayment::class);

Route::post('/pulpal/payment-notification', [ZohoController::class, 'payInvoice'])
    ->name('pulpal.payment-notification')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->middleware(ListInvoices::class)
    ->middleware(PulPalNotification::class);
    

// Route::post('/pulpal/payment-notification', function (Request $request) {
//         Log::info($request);
//         return [];
//     })
//     ->name('pulpal.payment-notification')
//     ->withoutMiddleware(ValidateCsrfToken::class);
    