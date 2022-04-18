<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VisitSymptoms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('visit_symptoms')) {
            Schema::create('visit_symptoms', function (Blueprint $table) {
                $table->increments('visit_symptom_id')->comment('Patient symtoms ID');
                $table->integer('pat_id')->comment('User ID - Foreign Key From users table');
                $table->integer('visit_id')->comment('Visit ID - Foreign Key From patient_visit table');
                $table->integer('symptom_id')->comment('Refer symptoms.sympotm_id');
                $table->date('since_date')->nullable()->comment('Date since when the symtom');
                $table->text('comment')->nullable()->comment('Comment of doctor');                      
                $table->tinyInteger('resource_type')->unsigned()->nullable()->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
                $table->string('ip_address',50)->nullable()->comment('User last login ip');
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes and 2 for deleted no');
                $table->timestamps();
                // $table->foreign('pat_id')->references('user_id')->on('users')->onUpdate('cascade');
                $table->foreign('visit_id')->references('visit_id')->on('patients_visits')->onUpdate('cascade');
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
       Schema::dropIfExists('visit_symptoms');
    }
}
