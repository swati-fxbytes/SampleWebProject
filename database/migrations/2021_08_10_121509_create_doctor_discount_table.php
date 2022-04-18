<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorDiscountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('doctor_discount')) {
            Schema::create('doctor_discount', function (Blueprint $table) {
                $table->increments('doctor_discount_id')->unsigned()->comment('Primary Key of doctor_discount table');
                $table->integer('doctor_id')->unsigned()->nullable()->default(0)->comment('Doctor id from users table');
                $table->text('coupon_name')->nullable()->comment('Coupon name');
                $table->text('coupon_image')->nullable()->comment('Coupon image');
                $table->tinyInteger('discount_type')->unsigned()->nullable()->default(1)->comment('1 for direct discount, 2 for percentage discount, 3 for coupon discount');
                $table->date('discount_start_date')->nullable()->comment('Discount start date');
                $table->date('discount_end_date')->nullable()->comment('Discount end date');
                $table->integer('discount_usage')->unsigned()->nullable()->default(0)->comment('How many times user can use discount');
                $table->tinyInteger('discount_availability')->unsigned()->nullable()->default(1)->comment('1 for first booking, 2 for all bookings');
                $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('1 For Web, 2 for Android, 3 for IOS');
                $table->string('ip_address',50)->comment('User last login ip');
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('Record created by. 0 for self');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('Record updated by. 0 for self');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes, 2 for deleted no');
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
        Schema::dropIfExists('doctor_discount');
    }
}
