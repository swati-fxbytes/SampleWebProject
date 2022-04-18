<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPaymentModeAddNotesColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_mode', function (Blueprint $table) {
            $table->text('payment_notes')->nullable()->comment('Notes and QR code HTML');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_mode', function (Blueprint $table) {
            $table->dropColumn('payment_notes');
        });
    }
}
