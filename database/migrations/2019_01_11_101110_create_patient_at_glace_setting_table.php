<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientAtGlaceSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_at_glace_setting', function (Blueprint $table) {
            $table->increments('patg_cmp_set_id')->unsigned()->comment('Setting Primary ID');
            $table->integer('patg_cmp_id')->unsigned()->comment('Foreign key of component from patient_at_glace_components table');
            $table->tinyInteger('is_visible')->unsigned()->index()->default(1)->comment('Component visibility - 1 for Hide, 2  For show');
            $table->integer('user_id')->unsigned()->nullable()->default(0)->comment('User id from users table');
            $table->string('ip_address',50)->comment('User last login ip'); 
            $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS'); 
            $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('Record created by. 0 for self');
            $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('Record updated by. 0 for self');
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
        Schema::dropIfExists('patient_at_glace_setting');
    }
}
