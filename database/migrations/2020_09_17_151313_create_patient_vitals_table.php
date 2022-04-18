<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientVitalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_vitals', function (Blueprint $table) {
            $table->increments('patient_vitals_id')->comment('Patient Vitals id');
            $table->integer('pat_id')->comment('User ID - Foreign Key From users table');
            $table->string('temperature',255)->nullable()->comment('Temperature value');
            $table->string('pulse',255)->nullable()->comment('Pulse value');
            $table->string('bp_systolic',255)->nullable()->comment('BP Systolic value');
            $table->string('bp_diastolic',255)->nullable()->comment('BP Diastolic value');
            $table->string('spo2',255)->nullable()->comment('SpO2 value');
            $table->string('respiratory_rate',255)->nullable()->comment('Respiratory Rate value');
            $table->string('sugar_level',255)->nullable()->comment('Sugar Level value');
            $table->string('jvp',255)->nullable()->comment('JVM value');
            $table->string('pedel_edema',255)->nullable()->comment('Pedel Edema value');
            $table->string('height', 50)->nullable();
            $table->string('weight', 50)->nullable();
            $table->string('bmi', 50)->nullable();
            $table->tinyInteger('resource_type')->unsigned()->nullable()->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
            $table->string('ip_address',50)->nullable()->comment('User last login ip');
            $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
            $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
            $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes and 2 for deleted no');
            $table->timestamps();
            // $table->foreign('pat_id')->references('user_id')->on('users')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_vitals');
    }
}
