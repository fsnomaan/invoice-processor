<?php
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get(
    '/',
    'ProcessInvoiceController@index'
);

Route::match(
    ['get', 'post'],
    '/process-invoice',
    'ProcessInvoiceController@processInvoice'
);

Route::match(
    ['get', 'post'],
    '/map-company-name',
    'MappingController@mapCompanyName'
);

Route::match(
    ['get', 'post'],
    '/map-bank-number',
    'MappingController@mapBankAccountNumber'
);

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});
Auth::routes();

Route::get('/home', 'ProcessInvoiceController@index')->name('home');

\Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
    Log::info( json_encode($query->sql) );
    Log::info( json_encode($query->bindings) );
    Log::info( json_encode($query->time)   );
});
