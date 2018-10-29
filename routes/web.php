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

Route::get('/','ProcessInvoiceController@index');

Route::match(['get', 'post'], '/process-invoice','ProcessInvoiceController@processInvoice');
Route::match(['get', 'post'], '/update-map','CompanyNameController@updateMap');
