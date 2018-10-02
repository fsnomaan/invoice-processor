<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\BankStatementController;
use App\Http\Controllers\OpenInvoiceController;
use App\Http\Controllers\ProcessInvoiceController;
use App\Models\BankStatement;
use App\Models\OpenInvoice;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BankStatementController::class, function () {
            return new BankStatementController(resolve(BankStatement::class));
        });
        resolve(BankStatementController::class);
        
        $this->app->bind(OpenInvoiceController::class, function () {
            return new OpenInvoiceController(resolve(OpenInvoice::class));
        });
        resolve(OpenInvoiceController::class);

        $this->app->bind(ProcessInvoiceController::class, function () {
            return new ProcessInvoiceController(resolve(BankStatement::class), resolve(OpenInvoice::class));
        });
        resolve(ProcessInvoiceController::class);        
    }
}
