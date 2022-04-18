<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideoConsultingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video_consulting', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dr_id')->unsigned()->index()->comment('Foreign key from users table');
            $table->integer('pat_id')->unsigned()->index()->comment('Foreign key from users table');
            $table->integer('booking_id')->unsigned()->index()->comment('Foreign key from bookings table');
            $table->text('video_channel');
            $table->tinyInteger('is_deleted')->default(2)->comment('1 for deleted yes and 2 for deleted no');
            $table->string('ip_address')->comment('User last login ip');
            $table->tinyInteger('resource_type')->unsigned()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
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
        Schema::dropIfExists('video_consulting');
    }
}
