<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePreviousPrescriptionMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_previous_prescription_media', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('media_id')->unsigned()->index()->comment('Foreign key from previous prescription table');
            $table->string('media_name', 151);
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
        Schema::dropIfExists('patient_previous_prescription_media');
    }
}
