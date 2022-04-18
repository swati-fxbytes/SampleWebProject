<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientNotificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_notification', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('booking_id');
            $table->tinyInteger('type')->default(1)->comment("1=>1hour, 2=>before 5 minute");
            $table->string('content', '255')->nullable();
            $table->tinyInteger('status')->default(1)->comment("1=>send, 2=>not sent");
            $table->tinyInteger('is_deleted')->default(2)->comment("1=>Deleted Yes, 2=>Deleted No");
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
        Schema::dropIfExists('patient_notification');
    }
}
