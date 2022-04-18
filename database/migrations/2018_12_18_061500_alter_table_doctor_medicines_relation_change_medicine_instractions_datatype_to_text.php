<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Modules\Doctors\Models\DoctorMedicinesRelation;

class AlterTableDoctorMedicinesRelationChangeMedicineInstractionsDatatypeToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('doctor_medicines_relation', function(Blueprint $table)
        {
            // 1- add a new column with the desired data type to the table
            // note that after() method is used to order the column and works only with MySQL
            $table->text('medicine_instructions')->nullable()->after('medicine_instractions');
        });

        // 2- fill the new column with the appropriate data
        // note that you may need to use data in the old column as a guide (like in this example)
        $dmr = DoctorMedicinesRelation::all();
        if ($dmr) {
            foreach ($dmr as $relation) {
                $dmr_id = DoctorMedicinesRelation::find($relation->dmr_id);
                $dmr_id->medicine_instractions;
                if(!is_null($dmr_id->medicine_instractions)){
                    $jsonDecode = json_decode($dmr_id->medicine_instractions);
                    $values = array();
                    foreach($jsonDecode as $val){
                        if(!empty($val->text)){
                            $values[] = $val->text;
                        }else{
                            $values[] = '';
                        }
                    }
                    $instructions = join(',', $values);
                    $dmr_id->medicine_instructions = $instructions;
                }
                $dmr_id->save();
            }
        }

        Schema::table('doctor_medicines_relation', function(Blueprint $table)
        {
            // 3- delete the old column
            $table->dropColumn('medicine_instractions');
        });
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
