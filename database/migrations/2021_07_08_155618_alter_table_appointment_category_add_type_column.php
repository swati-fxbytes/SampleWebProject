<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAppointmentCategoryAddTypeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointment_category', function (Blueprint $table) {
            $table->tinyInteger('cat_type')->default(1)->comment("1 => normal, 2=> Direct Clinic visit");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appointment_category', function (Blueprint $table) {
            $table->dropColumn('cat_type');
        });
    }
}
