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
            $table->unsignedInteger('user_id');
            $table->mediumText("trans_date");
            $table->mediumText("valuta");
            $table->mediumText("datev_account_number");
            $table->mediumText("amount");
            $table->mediumText("currency");
            $table->mediumText("account_statement_number");
            $table->mediumText("booking_text");
            $table->mediumText("purpose_of_use");
            $table->mediumText("company_customer");
            $table->mediumText("gvc");
            $table->mediumText("receivers");
            $table->mediumText("customer_reference");
            $table->mediumText("account_number");
            $table->mediumText("bic");
            $table->mediumText("bankname");
            $table->mediumText("account_holder");
            $table->mediumText("bank_reference");
            $table->mediumText("original_amount");
            $table->mediumText("original_currency");
            $table->mediumText("valuta_original");
            $table->mediumText("mandate_reference");
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
            $table->foreign('user_id')->references('user_id')->on('users');
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
