<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableDoctorSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_settings', function (Blueprint $table) {
            $table->increments('doc_setting_id')->unsigned()->comment('Setting Primary ID');
            $table->integer('user_id')->comment('User id from users table');
            $table->tinyInteger('send_welcome_sms')->default(1)->comment('Whether doctor wants to send a welcome sms');
            $table->string('welcome_sms_content')->index()->nullable()->comment('Welcome sms content');
            $table->tinyInteger('send_birthday_sms')->default(1)->comment('Whether doctor wants to send sms on birthday');
            $table->string('birthday_sms_content')->index()->nullable()->comment('Birthday sms content');
            $table->tinyInteger('send_anniversary_sms')->default(1)->comment('Whether doctor wants to send sms on marriage anniversary');
            $table->string('anniversary_sms_content')->index()->nullable()->comment('Anniversary sms content');
            $table->tinyInteger('send_medicine_reminder_sms')->default(1)->comment('Whether doctor wants to send sms for medicine reminder');
            $table->string('medicine_reminder_sms_content')->index()->nullable()->comment('Medicine reminder sms content');
            $table->string('ip_address',50)->comment('User last login ip'); 
            $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS'); 
            $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('Record created by. 0 for self');
            $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('Record updated by. 0 for self');
            $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes and 2 for deleted no');
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
        Schema::dropIfExists('doctor_settings');
    }
}
