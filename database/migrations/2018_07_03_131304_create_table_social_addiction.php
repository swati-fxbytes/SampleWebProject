<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableSocialAddiction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('social_addiction')) {
            Schema::create('social_addiction', function (Blueprint $table) {
                $table->increments('sa_id')->unsigned()->comment('Patients social_addiction unique id');
                $table->integer('pat_id')->unsigned()->index()->comment('Foreign key from users table');
                $table->integer('visit_id')->unsigned()->index()->comment('Foreign key from patient_visit table');
                
                $table->integer('sa_key')->comment('sa_key from static config data');
                $table->string('sa_value',255)->nullable()->comment('sa_value ');
                
                $table->string('ip_address',50)->nullable()->comment('User last login ip'); 
                $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS'); 
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('Record created by. 0 for self');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('Record updated by. 0 for self');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->index()->default(2)->comment('1 for yes, 2 for no');
                // $table->foreign('pat_id')->references('user_id')->on('users')->onUpdate('cascade');
                $table->foreign('visit_id')->references('visit_id')->on('patients_visits')->onUpdate('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_addiction');
    }
}
