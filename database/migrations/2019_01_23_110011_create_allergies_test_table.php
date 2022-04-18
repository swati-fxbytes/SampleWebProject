<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAllergiesTestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('allergies_test')) {
            Schema::create('allergies_test', function (Blueprint $table) {
                $table->increments('allergy_test_id')->comment('Primary Key of allergies test table');
                $table->integer('user_id')->unsigned()->comment('Foreign key Doctor from users table');
                $table->integer('pat_id')->unsigned()->comment('Foreign key Patient from users table');
                $table->integer('visit_id')->unsigned()->comment('Foreign key Visit Table');
                $table->integer('parent_allergy_id')->unsigned()->comment('Foreign key of parent allergy from allergy table ( allergy_id )');
                $table->integer('sub_parent_allergy_id')->unsigned()->comment('Foreign key of sub parent allergy from allergy table ( allergy_id )');
                $table->integer('allergy_id')->unsigned()->comment('Foreign key of allergy from allergy table ( allergy_id )');
                $table->integer('start_month')->unsigned()->nullable()->comment('Start month 1 to 12 for months 13 for all month');
                $table->integer('end_month')->unsigned()->nullable()->comment('End month 1 to 12 for months 13 for all month');
                
                $table->decimal('percutaneous_start_month_w')->nullable()->comment('Percutaneous weight in MM');
                $table->decimal('percutaneous_start_month_f')->nullable()->comment('percutaneous f');
                $table->decimal('percutaneous_end_month_w')->nullable()->comment('percutaneous_start_month_f');
                $table->decimal('percutaneous_end_month_f')->nullable()->comment('percutaneous_start_month_w');

                $table->tinyInteger('resource_type')->unsigned()->nullable()->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
                $table->string('ip_address',50)->nullable()->comment('User last login ip');
                $table->integer('created_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->integer('updated_by')->unsigned()->nullable()->default(0)->comment('0 for self/by system');
                $table->tinyInteger('is_deleted')->unsigned()->nullable()->default(2)->comment('1 for deleted yes and 2 for deleted no');
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
        Schema::dropIfExists('allergies_test');
    }
}
