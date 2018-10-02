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

Route::get('/','BankStatementController@index');
Route::get('/bank-statement','BankStatementController@index');
Route::post('/bank-statement','BankStatementController@processBankStatement');

Route::get('/open-invoice','OpenInvoiceController@index');
Route::post('/open-invoice','OpenInvoiceController@processOpenInvoice');

Route::post('/process-invoice','ProcessInvoiceController@processInvoice');