<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientAppointmentReasonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_appointment_reasons', function (Blueprint $table) {
            $table->increments('id');
            $table->json('appointment_reason');
            $table->bigInteger('booking_id');
            $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
            $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
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
        Schema::dropIfExists('patient_appointment_reasons');
    }
}
