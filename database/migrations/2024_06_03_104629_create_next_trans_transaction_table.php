<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNextTransTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('next_trans_transaction', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no',50);
            $table->string('bank_code',10);
            $table->integer('amount');
            $table->string('beneficiary_name',50);
            $table->string('beneficiary_account',30);
            $table->string('description',100)->nullable();
            $table->string('trx_id',40)->index('trx_id_idx')->nullable();
            $table->string('ref_id',40)->index('ref_id_idx')->nullable();
            $table->string('disburse_id',40)->index('disburse_id_idx')->nullable();
            $table->string('type',30)->nullable();
            $table->string('status',30)->nullable();
            $table->string('trans_code',50)->nullable();
            $table->integer('transfer_fee')->nullable();
            $table->string('reason',100)->nullable();
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
        Schema::dropIfExists('next_trans_transaction');
    }
}
