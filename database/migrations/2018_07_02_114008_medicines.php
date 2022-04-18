<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Medicines extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('medicines')) {
            Schema::create('medicines', function (Blueprint $table) {
                $table->increments('medicine_id')->comment('Medicine ID');
                $table->string('medicine_name', 255)->nullable()->comment('Medicine Name');                
                $table->tinyInteger('show_in')->nullable()->default(1)->comment('1 for new visit form');                
                $table->tinyInteger('resource_type')->unsigned()->nullable()->comment('Resource type - 1 For Web  , 2 for Android and 3 for IOS');
                $table->string('ip_address',50)->nullable()->comment('User last login ip');
                $table->integer('drug_type_id')->nullable()->comment('drug_type_id is primary id of drug_type table');
                $table->integer('drug_dose_unit_id')->nullable()->comment('drug_dose_unit_id is primary id of drug_dose_unit table');
                $table->string('medicine_dose',255)->nullable()->comment('medicine dose given');
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
        //
    }
}
