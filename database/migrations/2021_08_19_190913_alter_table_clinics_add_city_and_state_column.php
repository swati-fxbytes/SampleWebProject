<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableClinicsAddCityAndStateColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->integer('clinic_city')->unsigned()->index()->nullable()->comment('Clinic city');
            $table->tinyInteger('clinic_state')->unsigned()->nullable()->index()->comment('Clinic state');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('clinic_city');
            $table->dropColumn('clinic_state');
        });
    }
}
