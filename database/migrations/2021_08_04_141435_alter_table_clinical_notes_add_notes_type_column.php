<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableClinicalNotesAddNotesTypeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->tinyInteger('notes_type')->default(1)->comment("1=>Clinical Notes, 2=>Public Notes");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->dropColumn('notes_type');
        });
    }
}
