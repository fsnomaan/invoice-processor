<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\BankStatementController;
use App\Models\BankStatement;

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
    }
}
