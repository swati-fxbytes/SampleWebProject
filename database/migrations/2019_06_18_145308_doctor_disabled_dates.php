<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DoctorDisabledDates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('doctor_disabled_dates')) {
            Schema::create('doctor_disabled_dates', function (Blueprint $table) {
                $table->increments('disabled_date_id')->comment('Primary Key of disabled dates table');
                $table->integer('user_id')->unsigned()->comment('Foreign key Doctor from users table');
                $table->date('from_date')->nullable()->comment('From disabled date');
                $table->date('to_date')->nullable()->comment('To disabled date');
                $table->tinyInteger('resource_type')->unsigned()->nullable()->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
                $table->string('ip_address', 50)->nullable()->comment('User last login ip');
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes and 2 for deleted no');
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
        Schema::dropIfExists('doctor_disabled_dates');
    }
}
