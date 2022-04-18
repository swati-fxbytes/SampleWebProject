<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTablePaymentsHistoryAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments_history', function (Blueprint $table) {
            $table->integer('doctor_id')->unsigned()->nullable()->default(0)->after('payment_mode_id')->comment('Doctor id from users table');
            $table->integer('discount_id')->unsigned()->nullable()->default(0)->after('doctor_id')->comment('Discount id from doctor_discount table');
            $table->float('discount_amount')->nullable()->after('discount_id')->comment('Amount after discount');
            $table->tinyInteger('user_payment_status')->unsigned()->nullable()->default(1)->after('discount_amount')->comment('1 for success, 2 for pending, 3 for failed');
            $table->text('user_payment_notes')->nullable()->after('user_payment_status')->comment('Payment notes of user');
            $table->tinyInteger('dr_payment_status')->unsigned()->nullable()->default(1)->after('user_payment_notes')->comment('1 for success, 2 for pending, 3 for failed');
            $table->text('dr_payment_notes')->nullable()->after('dr_payment_status')->comment('Payment notes of doctor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments_history', function (Blueprint $table) {
            $table->dropColumn('doctor_id');
            $table->dropColumn('discount_id');
            $table->dropColumn('discount_amount');
            $table->dropColumn('user_payment_status');
            $table->dropColumn('user_payment_notes');
            $table->dropColumn('dr_payment_status');
            $table->dropColumn('dr_payment_notes');
        });
    }
}
