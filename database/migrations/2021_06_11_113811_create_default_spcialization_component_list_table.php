<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDefaultSpcializationComponentListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('default_spcialization_component_list', function (Blueprint $table) {
            $table->increments('id');
            $table->string('spicialization_id', "10")->comment('Foreign key from specialisations table');
            $table->json('component');
            $table->json('appointment_category')->nullable();
            $table->json('patient_groups')->nullable();
            $table->json('patient_at_a_glance')->nullable();
            $table->json('checkup_type')->nullable();
            $table->json('payment_mode')->nullable();
            $table->json('clinic_time')->nullable();
            $table->json('consent_form')->nullable();
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
        Schema::dropIfExists('default_spcialization_component_list');
    }
}
