<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBankStatementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_statement', function (Blueprint $table) {
            $table->increments('id');
            $table->longText("trans_date");
            $table->longText("valuta");
            $table->longText("datev_account_number");
            $table->longText("amount");
            $table->longText("currency");
            $table->longText("account_statement_number");
            $table->longText("booking_text");
            $table->longText("purpose_of_use");
            $table->longText("company_customer");
            $table->longText("gvc");
            $table->longText("receivers");
            $table->longText("customer_reference");
            $table->longText("account_number");
            $table->longText("bic");
            $table->longText("bankname");
            $table->longText("account_holder");
            $table->longText("bank_reference");
            $table->longText("original_amount");
            $table->longText("original_currency");
            $table->longText("valuta_original");
            $table->longText("mandate_reference");
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
        Schema::dropIfExists('bank_statement');
    }
}
