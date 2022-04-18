<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUsersTableAddTanentIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('masterdb')->table('users', function (Blueprint $table) {
            $table->tinyInteger('tenant_id')->unsigned()->nullable()->default(1)->comment('Foreign key from user secret table');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('masterdb')->table('users', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
}
