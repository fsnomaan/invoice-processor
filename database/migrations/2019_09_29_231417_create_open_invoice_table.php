<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOpenInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('open_invoice', function (Blueprint $table) {
            $table->increments('id');
            $table->longText("customer_account");
            $table->longText("name");
            $table->longText("voucher");
            $table->longText("invoice");
            $table->longText("trans_date");
            $table->longText("description");
            $table->longText("currency");
            $table->longText("amount_transaction_currency");
            $table->longText("bank_account");
            $table->longText("business_unit");
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('open_invoice');
    }
}
