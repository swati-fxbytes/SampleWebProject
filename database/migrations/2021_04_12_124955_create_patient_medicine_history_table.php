<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientMedicineHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('patient_medicine_history'))
        {
            Schema::create('patient_medicine_history', function (Blueprint $table) {
                $table->increments('patient_medicine_history_id')->unsigned()->comment('Primary Key of patient_medicine_history table');
                
                $table->integer('pat_id')->unsigned()->index()->comment('Foreign Key from patient table');

                $table->integer('medicine_id')->unsigned()->index()->comment('Foreign Key from medicine table');
                
                $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type-1 For Web, 2 for Android and 3 for IOS');

                $table->string('ip_address',50)->nullable()->comment('User last login ip'); 
                
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
        Schema::dropIfExists('patient_medicine_history');
    }
}
