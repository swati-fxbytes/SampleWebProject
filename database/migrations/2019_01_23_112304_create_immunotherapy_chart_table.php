<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImmunotherapyChartTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('immunotherapy_chart')) {
            Schema::create('immunotherapy_chart', function (Blueprint $table) {
                $table->increments('immunotherapy_chart_id')->comment('Primary Key of immunotherapy table');
                $table->integer('user_id')->unsigned()->comment('Foreign key Doctor from users table');
                $table->integer('pat_id')->unsigned()->comment('Foreign key Patient from users table');
                $table->integer('visit_id')->unsigned()->comment('Foreign key Visit Table');
                $table->date('dose_date')->nullable()->comment('Date of dose need to take');
                $table->string('dose',100)->nullable()->nullable()->comment('Dose');
                $table->string('dose_conc_of_antigen')->nullable()->comment('Conc. of antigen');
                $table->integer('type')->unsigned()->nullable()->default(0)->comment('0 - none, 1 - two injections a week, 2- two injections a week after(I), 3- Once in a week(II), 4- Once in 2 weeks ( After II ), 5- once in 3 weeks (After IV), 6- Once in 4 Weeks( After V), 7- Once in 4 Weeks ( After VI), 8- once in 4 Weeks ( After VII)');
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
        Schema::dropIfExists('immunotherapy_chart');
    }
}
