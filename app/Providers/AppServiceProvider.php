<?php

namespace App\Providers;

use App\Http\Controllers\MappingController;
use App\Models\BankAccount;
use App\Models\CompanyName;
use App\Models\InvoiceImporter;
use App\Models\InvoiceProcessor;
use App\Models\StatementImporter;
use Illuminate\Support\ServiceProvider;
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
        $this->app->bind(StatementImporter::class, function () {
            return new StatementImporter(resolve(BankStatement::class));
        });
        resolve(StatementImporter::class);
        
        $this->app->bind(InvoiceImporter::class, function () {
            return new InvoiceImporter(resolve(OpenInvoice::class));
        });
        resolve(InvoiceImporter::class);

        $this->app->bind(MappingController::class, function () {
            return new MappingController(resolve(CompanyName::class), resolve(BankAccount::class));
        });
        resolve(MappingController::class);

        $this->app->bind(InvoiceProcessor::class, function () {
            return new InvoiceProcessor(
                resolve(BankStatement::class),
                resolve(OpenInvoice::class),
                resolve(CompanyName::class),
                resolve(BankAccount::class)
            );
        });
        resolve(InvoiceProcessor::class);

        $this->app->bind(ProcessInvoiceController::class, function () {
            return new ProcessInvoiceController(
                resolve(StatementImporter::class),
                resolve(InvoiceImporter::class),
                resolve(CompanyName::class),
                resolve(BankAccount::class),
                resolve(InvoiceProcessor::class)
            );
        });
        resolve(InvoiceProcessor::class);
    }
}
