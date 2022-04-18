<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientNotificationSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('patient_notification_setting'))
        {
            Schema::create('patient_notification_setting', function (Blueprint $table) {
                $table->increments('patient_notification_id')->unsigned()->comment('Primary Key of patient_notification table');
                
                $table->integer('pat_id')->unsigned()->index()->comment('Foreign Key from patient table');
                
                $table->tinyInteger('medicine_notification')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');
                
                $table->tinyInteger('vital_notification')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                $table->tinyInteger('lab_test_notification')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                //default time for this notification is - 8 AM
                $table->tinyInteger('morning_medicine_notification_before_breakfast')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');
                
                //default time for this notification is - 10 AM
                $table->tinyInteger('morning_medicine_notification_after_breakfast')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                //default time for this notification is - 12 PM
                $table->tinyInteger('afternoon_medicine_notification_before_lunch')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                //default time for this notification is - 2 PM
                $table->tinyInteger('afternoon_medicine_notification_after_lunch')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                //default time for this notification is - 7 PM
                $table->tinyInteger('night_medicine_notification_before_dinner')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');

                //default time for this notification is - 9 PM
                $table->tinyInteger('night_medicine_notification_after_dinner')->unsigned()->nullable()->default(1)->comment('Default-1, incase notification off then-0');
                
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
        Schema::dropIfExists('patient_notification_setting');
    }
}
