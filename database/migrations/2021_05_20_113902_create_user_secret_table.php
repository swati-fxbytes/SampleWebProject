<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSecretTable extends Migration
{
    protected $connection = 'masterdb';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('masterdb')->create('user_secret', function (Blueprint $table) {
            $table->increments('user_secret_id')->unsigned()->comment('User secret primary key');
            //$table->uuid('user_id')->index()->comment('Foreign key from users table');
            $table->longText('client_secret')->comment('Client secret for every user to api calling');
            $table->longText('client_id')->comment('Client id for every user to api calling');
            $table->text('tenant_name')->default('Rxhealth')->comment('Tenant name like - Rxhealth, HBMS');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_secret');
    }
}
