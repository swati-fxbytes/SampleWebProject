<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Modules\Patients\Models\PatientMedicationHistory;

class AlterTablePatientMedicationHistoryChangeMedicineInstractionsDatatypeToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patient_medication_history', function (Blueprint $table) {
            // 1- add a new column with the desired data type to the table
            // note that after() method is used to order the column and works only with MySQL
            $table->text('medicine_instructions')->nullable()->after('medicine_instractions');
        });

        // 2- fill the new column with the appropriate data
        // note that you may need to use data in the old column as a guide (like in this example)
        $pmh = PatientMedicationHistory::all();
        if ($pmh) {
            foreach ($pmh as $row) {
                $pmh_id = PatientMedicationHistory::find($row->pmh_id);
                $pmh_id->medicine_instractions;
                if (!is_null($pmh_id->medicine_instractions)) {
                    $jsonDecode = json_decode($pmh_id->medicine_instractions);
                    $values = array();
                    if (!empty($jsonDecode)) {
                        foreach ($jsonDecode as $val) {
                            if (isset($val->text)) {
                                $values[] = $val->text;
                            }
                        }
                        $instructions = join(',', $values);
                        $pmh_id->medicine_instructions = $instructions;
                    }
                }
                $pmh_id->save();
            }
        }

        Schema::table('patient_medication_history', function (Blueprint $table) {
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
