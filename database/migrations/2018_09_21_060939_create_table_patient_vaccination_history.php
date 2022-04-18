<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePatientVaccinationHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('patient_vaccination_history')) {
            Schema::create('patient_vaccination_history', function (Blueprint $table) {
                $table->increments('vaccination_id')->unsigned()->comment('Vaccination history ID');
                $table->integer('pat_id')->unsigned()->index()->comment('Foreign key from users table');
                $table->integer('visit_id')->unsigned()->nullable()->comment('Foreign key from patient_visit table');
                
                $table->string('vaccine_name',255)->nullable()->comment('Name of Vaccine ');
                $table->date('vaccine_date')->nullable()->comment('Date of Vaccination');
                
                // $table->foreign('pat_id')->references('user_id')->on('users')->onUpdate('cascade');
                $table->string('ip_address',50)->nullable()->comment('User last login ip'); 
                $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS'); 
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('Record created by. 0 for self');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('Record updated by. 0 for self');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->index()->default(2)->comment('1 for yes, 2 for no');
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
        //
    }
}
