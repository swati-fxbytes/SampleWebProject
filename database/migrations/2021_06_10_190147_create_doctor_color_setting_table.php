<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDoctorColorSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_color_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dr_id')->unsigned()->index()->comment('Foreign key from previous prescription table');
            $table->string('primary_color_code', 50);
            $table->string('secondary_color_code', 50);
            $table->tinyInteger('is_deleted')->default(2)->comment('1 for deleted yes and 2 for deleted no');
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
        Schema::dropIfExists('doctor_color_setting');
    }
}
