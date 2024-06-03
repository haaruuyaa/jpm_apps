<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBcaTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bca_transaction', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id')->index();
            $table->string('ref_no')->index()->nullable();
            $table->string('external_id');
            $table->decimal('amount',16);
            $table->string('currency',3);
            $table->string('source_account_no')->index();
            $table->string('beneficiary_account_no')->index();
            $table->timestamp('transaction_dt');
            $table->json('additional_info')->nullable();
            $table->enum('type',['transfer-intrabank','transfer-interbank','transfer-va,top-up'])->default('transfer-interbank');
            $table->enum('status',['pending','approved','failed','success'])->default('pending');
            $table->string('remark_1')->nullable();
            $table->string('remark_2')->nullable();
            $table->string('customer_reference',50)->nullable();
            $table->string('response_code', 7)->nullable();
            $table->string('response_message', 150)->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bca_transaction');
    }
}
