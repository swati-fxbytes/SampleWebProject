<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserDeviceTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_device_token', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->bigInteger('user_id');
            $table->string('token');
            $table->string('plateform', 8)->comment('android,ios,web');
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
        Schema::dropIfExists('user_device_token');
    }
}
