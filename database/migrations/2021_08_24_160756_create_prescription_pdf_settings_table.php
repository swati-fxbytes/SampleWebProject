<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrescriptionPdfSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prescription_pdf_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->tinyInteger('pre_type')->default(1)->comment('1 -> custom header, 2 -> Image header');
            $table->string('pre_header_image', 255)->nullable();
            $table->string('pre_logo', 255)->nullable();
            $table->text('pre_footer')->nullable();
            $table->tinyInteger('is_deleted')->default(2)->comment('1 for deleted yes and 2 for deleted no');
            $table->string('ip_address',50)->comment('User last login ip'); 
            $table->tinyInteger('resource_type')->unsigned()->nullable()->default(1)->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
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
        Schema::dropIfExists('prescription_pdf_settings');
    }
}