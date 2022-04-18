<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImmunotherapyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('immunotherapy')) {
            Schema::create('immunotherapy', function (Blueprint $table) {
                $table->increments('immunotherapy_id')->comment('Primary Key of immunotherapy table');
                $table->integer('user_id')->unsigned()->comment('Foreign key Doctor from users table');
                $table->integer('pat_id')->unsigned()->comment('Foreign key Patient from users table');
                $table->integer('visit_id')->unsigned()->comment('Foreign key Visit Table');
                $table->integer('parent_allergy_id')->unsigned()->comment('Foreign key of parent allergy from allergy table ( allergy_id )');
                $table->integer('sub_parent_allergy_id')->unsigned()->comment('Foreign key of sub parent allergy from allergy table ( allergy_id )');
                $table->integer('allergy_id')->unsigned()->comment('Foreign key of allergy from allergy table ( allergy_id )');
                $table->decimal('quantity')->nullable()->comment('Quantity Of drug');
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
        Schema::dropIfExists('immunotherapy');
    }
}
