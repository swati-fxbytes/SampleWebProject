<?php
namespace App\Modules\Visits\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\RestApi;
use App\Traits\Encryptable;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use App\Libraries\ExceptionLib;
use Config;
use DB;
use Carbon\Carbon;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Modules\Doctors\Models\ManageDrugs;
use App\Modules\Patients\Models\PatientsActivities;
use App\Modules\Doctors\Models\DrugType;

/**
 * Medication
 *
 * @package                ILD India Registry
 * @subpackage             Medication
 * @category               Model
 * @DateOfCreation         11 june 2018
 * @ShortDescription       This Model to handle database operation of Medication
 **/

class Medication extends Model
{
    use HasApiTokens, Encryptable, RestApi;

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init exception library object
        $this->utilityLibObj = new UtilityLib();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init model static data
        $this->staticDataModelObj = new StaticDataConfig();

        // Init manage drugs Model Object
        $this->manageDrugsModelObj = new ManageDrugs();

        // Init Patients Activities Model Object
        $this->patientActivitiesModelObj = new PatientsActivities();

        // Init Drug Type Model Object
        $this->drugTypeModelObj = new DrugType();
    }

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $table                    = 'patient_medication_history';
    protected $tableMedicines           = 'medicines';
    protected $tableVisits              = 'patients_visits';
    protected $tableDrugType            = 'drug_type';
    protected $tableDrugDoseUnit        = 'drug_dose_unit';
    protected $tableDocMedicineRelation = 'doctor_medicines_relation';
    protected $tablePatMedicineTemplate = 'patient_medicine_templates';

    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
                            'pat_id',
                            'visit_id',
                            'medicine_id',
                            'medicine_start_date',
                            'medicine_end_date',
                            'medicine_dose',
                            'medicine_dose2',
                            'medicine_dose3',
                            'medicine_dose_unit',
                            'medicine_duration',
                            'medicine_duration_unit',
                            'medicine_frequency',
                            'medicine_meal_opt',
                            'medicine_instructions',
                            'ip_address',
                            'resource_type',
                            'is_deleted',
                            'is_discontinued',
                            'medicine_name',
                            'medication_type'
                        ];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'pmh_id';

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get medicine list
     * @param
     * @return                object Array of all medicines
     */
    public function getMedicineListData()
    {
        $queryResult = DB::table($this->tableMedicines)
                        ->select(
                            $this->tableMedicines.'.medicine_id',
                            $this->tableMedicines.'.medicine_name',
                            $this->tableMedicines.'.medicine_dose',
                            $this->tableDrugDoseUnit.'.drug_dose_unit_name'
                            )
                        ->leftJoin($this->tableDrugDoseUnit, function ($join) {
                            $join->on($this->tableMedicines.'.drug_dose_unit_id', '=', $this->tableDrugDoseUnit.'.drug_dose_unit_id')
                                    ->where($this->tableDrugDoseUnit.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                        })
                        ->where($this->tableMedicines.'.is_deleted', Config::get('constants.IS_DELETED_NO'));

        $queryResult = $queryResult->get()
                                    ->map(function ($medicineList) {
                                        if (!empty($medicineList->medicine_id)) {
                                            $medicineList->medicine_id = $this->securityLibObj->encrypt($medicineList->medicine_id);
                                            $medicineList->medicine_name = !empty($medicineList->medicine_dose) ? $medicineList->medicine_name. ' ( '.$medicineList->medicine_dose.' '.$medicineList->drug_dose_unit_name.' )' : $medicineList->medicine_name;
                                        }
                                        return $medicineList;
                                    });
        return $queryResult;
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to prepare query of medication data
     * @param
     * @return                object Array of all medicines
     */
    public function preparedQuery($visitId, $patId)
    {
        $query = DB::table($this->table)
                ->select(
                    $this->tableMedicines.'.medicine_name',
                    $this->tableMedicines.'.medicine_dose as drug_dose',
                    $this->table.'.pmh_id',
                    $this->table.'.medication_type',
                    $this->table.'.pat_id',
                    $this->table.'.visit_id',
                    $this->table.'.medicine_id',
                    $this->table.'.medicine_start_date',
                    $this->table.'.medicine_end_date',
                    $this->table.'.medicine_dose',
                    $this->table.'.medicine_dose2',
                    $this->table.'.medicine_dose3',
                    $this->table.'.medicine_dose_unit',
                    $this->table.'.medicine_duration',
                    $this->table.'.medicine_duration_unit',
                    $this->table.'.medicine_frequency',
                    $this->table.'.medicine_meal_opt',
                    $this->table.'.medicine_instructions',
                    $this->table.'.is_discontinued',
                    $this->table.'.medicine_route',
                    $this->tableDrugType.'.drug_type_name',
                    $this->tableDrugDoseUnit.'.drug_dose_unit_name',
                    $this->tableDrugDoseUnit.'.drug_dose_unit_id'
                    )
                ->leftJoin($this->tableMedicines, function ($join) use ($visitId) {
                    $join->on($this->table.'.medicine_id', '=', $this->tableMedicines.'.medicine_id');
                })
                ->leftJoin($this->tableDrugType, function ($join) {
                    $join->on($this->tableMedicines.'.drug_type_id', '=', $this->tableDrugType.'.drug_type_id')
                            ->where($this->tableDrugType.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                })
                ->leftJoin($this->tableDrugDoseUnit, function ($join) {
                    $join->on($this->table.'.medicine_dose_unit', '=', $this->tableDrugDoseUnit.'.drug_dose_unit_id')
                            ->where($this->tableDrugDoseUnit.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                })
                ->where($this->table.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                ->where($this->table.'.visit_id', $visitId)
                ->where($this->table.'.pat_id', $patId)
                ->where($this->table.'.medication_type', Config::get("constants.MEDICATION_TYPE_EPRESCRIPTION"));
        return $query;
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get medicine list
     * @param
     * @return                object Array of all medicines
     */
    public function getPatientMedicationData($visitId, $patId=null)
    {
        $queryResult = $this->preparedQuery($visitId, $patId);

        $queryResult = $queryResult->orderBy('pmh_id', 'desc')
                                    ->get()
                                    ->map(function ($patientMedication) {
                                        if (!empty($patientMedication->pmh_id)) {
                                            $patientMedication->pmh_id                          = $this->securityLibObj->encrypt($patientMedication->pmh_id);
                                            $patientMedication->pat_id                          = $this->securityLibObj->encrypt($patientMedication->pat_id);
                                            $patientMedication->visit_id                        = $this->securityLibObj->encrypt($patientMedication->visit_id);
                                            $patientMedication->prev_medicine_id                = $this->securityLibObj->encrypt($patientMedication->medicine_id);
                                            $patientMedication->medicine_id                     = $this->securityLibObj->encrypt($patientMedication->medicine_id);
                                            $patientMedication->medicine_start_date             = $patientMedication->medicine_start_date;
                                            $patientMedication->medicine_start_date_formatted   = !empty($patientMedication->medicine_start_date) ? date('d/m/Y', strtotime($patientMedication->medicine_start_date)) : $patientMedication->medicine_start_date;
                                            $patientMedication->medicine_end_date               = $patientMedication->medicine_end_date;
                                            $patientMedication->medicine_end_date_formatted     = !empty($patientMedication->medicine_end_date) ? date('d/m/Y', strtotime($patientMedication->medicine_end_date)) : $patientMedication->medicine_end_date;
                                            $patientMedication->medicine_frequency              = $patientMedication->medicine_frequency;
                                            $patientMedication->medicine_frequencyVal           = $this->staticDataModelObj->getMedicationsFector('medicine_frequency', $patientMedication->medicine_frequency);
                                            $patientMedication->medicine_duration_unitVal       = $this->staticDataModelObj->getMedicationsFector('medicine_duration_unit', $patientMedication->medicine_duration_unit);
                                            $patientMedication->medicine_duration_unit          = $patientMedication->medicine_duration_unit;
                                            $patientMedication->medicine_dose_unitVal           = $patientMedication->drug_dose_unit_name;
                                            $patientMedication->medicine_dose_unit              = $this->securityLibObj->encrypt($patientMedication->medicine_dose_unit);
                                            $patientMedication->drug_dose_unit_id               = $this->securityLibObj->encrypt($patientMedication->drug_dose_unit_id);
                                            $patientMedication->medicine_meal_optVal            = $this->staticDataModelObj->getMedicationsFector('medicine_meal_opt', $patientMedication->medicine_meal_opt);
                                            $patientMedication->medicine_meal_opt               = (string) $patientMedication->medicine_meal_opt;
                                            $patientMedication->is_end_date_past                = (!empty($patientMedication->medicine_end_date) && (strtotime($patientMedication->medicine_end_date) < strtotime(date('Y-m-d')))) ? 1 : 0 ;
                                            $patientMedication->medicine_instructions           = !empty($patientMedication->medicine_instructions) ? $patientMedication->medicine_instructions : "" ;
                                            $patientMedication->medicine_name                   = !empty($patientMedication->drug_dose) ? $patientMedication->medicine_name. ' ( '.$patientMedication->drug_dose.' '.$patientMedication->drug_dose_unit_name.' )' : $patientMedication->medicine_name;
                                        }
                                        return $patientMedication;
                                    });
        return $queryResult;
    }

    public function templatePrepareQuery($visitId, $patId)
    {
        $query = DB::table($this->table)
                ->select(
                    $this->table.'.medicine_id',
                    $this->table.'.medicine_start_date',
                    $this->table.'.medicine_end_date',
                    $this->table.'.medicine_dose',
                    $this->table.'.medicine_dose2',
                    $this->table.'.medicine_dose3',
                    $this->table.'.medicine_dose_unit',
                    $this->table.'.medicine_duration',
                    $this->table.'.medicine_duration_unit',
                    $this->table.'.medicine_frequency',
                    $this->table.'.medicine_meal_opt',
                    $this->table.'.medicine_instructions',
                    $this->table.'.is_discontinued',
                    $this->table.'.medicine_route'
                    )
                ->where($this->table.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                ->where($this->table.'.visit_id', $visitId)
                ->where($this->table.'.pat_id', $patId);
        return $query;
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to save template
     * @param
     * @return                object Array of all medicines
     */
    public function saveMedicationTemplate($requestData)
    {
        $query = $this->templatePrepareQuery($requestData['visit_id'], $requestData['pat_id']);
        $queryResult = $query->get();
        $requestData = [
            'temp_name'=>$requestData['temp_name'],
            'user_id'=>$requestData['user_id'],
            'medication_data'=>json_encode($queryResult)
        ];

        $response  = $this->dbInsert($this->tablePatMedicineTemplate, $requestData);

        if ($response) {
            $id = DB::getPdo()->lastInsertId();
            return $this->getTemplateById($id);
        } else {
            return $response;
        }
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get template
     * @param
     * @return                object Array of all medicines
     */
    public function getTemplateById($pat_med_temp_id)
    {
        $queryResult = DB::table($this->tablePatMedicineTemplate)
            ->select('temp_name', 'pat_med_temp_id')
            ->where(['pat_med_temp_id'=>$pat_med_temp_id, 'is_deleted'=> Config::get('constants.IS_DELETED_NO')])
            ->first();
        if (!empty($queryResult)) {
            $queryResult->pat_med_temp_id  = $this->securityLibObj->encrypt($queryResult->pat_med_temp_id);
            return $queryResult;
        } else {
            return false;
        }
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to save template
     * @param
     * @return                object Array of all medicines
     */
    public function getPatientMedicationTemplate($requestData)
    {
        $queryResult = DB::table($this->tablePatMedicineTemplate)
            ->select('temp_name', 'pat_med_temp_id')
            ->where(['user_id'=>$requestData['user_id'], 'is_deleted'=> Config::get('constants.IS_DELETED_NO')])
            ->get()
            ->map(function ($patientMedication) {
                $patientMedication->pat_med_temp_id  = $this->securityLibObj->encrypt($patientMedication->pat_med_temp_id);
                return $patientMedication;
            });
        if (!empty($queryResult)) {
            return $queryResult;
        } else {
            return false;
        }
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get template
     * @param
     * @return                object medicine template
     */
    public function getMedicationTemplate($requestData)
    {
        $queryResult = DB::table($this->tablePatMedicineTemplate)
            ->select('medication_data')
            ->where(['user_id'=>$requestData['user_id'],'pat_med_temp_id'=>$requestData['pat_med_temp_id'], 'is_deleted'=> Config::get('constants.IS_DELETED_NO')])
            ->first();
        if (!empty($queryResult)) {
            $medicationData = json_decode($queryResult->medication_data);

            return $medicationData;
        } else {
            return false;
        }
    }

    /**
    * @DateOfCreation        14 July 2018
    * @ShortDescription      This function is responsible to update medication Record
    * @param                 Array  $requestData
    * @return                Array of status and message
    */
    public function saveMedicationData($requestData)
    {
        $response  = $this->dbInsert($this->table, $requestData);

        if ($response) {
            $id = DB::getPdo()->lastInsertId();
            return $id;
        } else {
            return $response;
        }
    }

    /**
    * @DateOfCreation        14 July 2018
    * @ShortDescription      This function is responsible to update medication data
    * @param                 Array  $requestData
    * @return                Array of status and message
    */
    public function updateMedicationData($requestData, $phmId)
    {
        $whereData = [ 'pmh_id' => $phmId ];

        // Prepare update query
        $response = $this->dbUpdate($this->table, $requestData, $whereData);

        if ($response) {
            return true;
        }
        return false;
    }

    /**
    * @DateOfCreation        14 July 2018
    * @ShortDescription      This function is responsible to update medication data
    * @param                 Array  $requestData
    * @return                Array of status and message
    */
    public function deletePatientMedicationData($updateData, $whereData)
    {
        // Prepare update query
        $response = $this->dbUpdate($this->table, $updateData, $whereData);

        if ($response) {
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to check if record is exist or not
     * @param                 integer $patId
     * @return                object Array of symptoms records
     */
    public function checkIfFectorExist($vistId, $medicineId)
    {
        $queryResult = DB::table($this->table)
            ->select('pmh_id')
            ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
            ->where('visit_id', $vistId)
            ->where('medicine_id', $medicineId);

        return $queryResult->get()->count();
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to check if record is exist or not
     * @param                 integer $patId
     * @return                object Array of symptoms records
     */
    public function checkDiscontinueStatus($medicationID)
    {
        $queryResult = DB::table($this->table)
            ->select('medicine_end_date', 'is_discontinued')
            ->where('pmh_id', $medicationID);

        return $queryResult->get()->first();
    }

    /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to get the symptoms visit data
     * @param                 integer $patId
     * @return                object Array of symptoms records
     */
    public function patientCurrentMedications($requestData)
    {
        $query = "SELECT
            ".$this->tableMedicines.".medicine_name,
            ".$this->tableMedicines.".medicine_dose as drug_dose,
            ".$this->table.".pmh_id,
            (SELECT MIN(med.medicine_start_date) FROM patient_medication_history as med
                WHERE med.medicine_id = ".$this->table.".medicine_id AND med.pat_id = ".$this->table.".pat_id AND med.created_by = ".$requestData['created_by']."
                GROUP BY med.medicine_id) as medicine_start_date,
            (SELECT MAX(pmed.medicine_end_date) FROM patient_medication_history as pmed
                WHERE pmed.medicine_id = ".$this->table.".medicine_id AND pmed.pat_id = ".$this->table.".pat_id AND pmed.created_by = ".$requestData['created_by']."
                GROUP BY pmed.medicine_id) as medicine_end_date,
            ".$this->table.".medicine_dose,
            ".$this->table.".medicine_dose2,
            ".$this->table.".medicine_dose3,
            ".$this->table.".medicine_start_date AS start_date,
            ".$this->table.".medicine_end_date AS end_date,
            ".$this->table.".medicine_dose_unit,
            ".$this->table.".medicine_route,
            ".$this->table.".pat_id,
            ".$this->table.".visit_id,
            ".$this->table.".medicine_id,
            ".$this->table.".medicine_dose,
            ".$this->table.".medicine_dose2,
            ".$this->table.".medicine_dose3,
            ".$this->table.".medicine_duration,
            ".$this->table.".medicine_duration_unit,
            ".$this->table.".medicine_frequency,
            ".$this->table.".medicine_meal_opt,
            ".$this->table.".medicine_instructions,
            ".$this->table.".is_discontinued,
            ".$this->tableDrugType.".drug_type_name,
            ".$this->tableDrugDoseUnit.".drug_dose_unit_name,
            ".$this->tableDrugDoseUnit.".drug_dose_unit_id
            FROM ".$this->table." 
            JOIN ".$this->tableVisits." ON ".$this->tableVisits.".visit_id=".$this->table.".visit_id AND ".$this->tableVisits.".is_deleted = ".Config::get('constants.IS_DELETED_NO');

        if ($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR')) {
            $query .= "JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id,user_firstname,user_lastname,user_type from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(user_id int,
                user_firstname text,
                user_lastname text,
                user_type int
                )) AS users ON users.user_id= ".$this->table.".created_by AND (users.user_type < ".Config::get('constants.USER_TYPE_DOCTOR')." OR ".$this->table.".created_by =".$requestData['created_by'].")";
        }

        $query .= " LEFT JOIN ".$this->tableMedicines." ON ".$this->tableMedicines.".medicine_id=".$this->table.".medicine_id 
            LEFT JOIN ".$this->tableDrugType." ON ".$this->tableDrugType.".drug_type_id=".$this->tableMedicines.".drug_type_id AND ".$this->tableDrugType.".is_deleted = ".Config::get('constants.IS_DELETED_NO')." 
            LEFT JOIN ".$this->tableDrugDoseUnit." ON ".$this->tableDrugDoseUnit.".drug_dose_unit_id=".$this->tableMedicines.".drug_dose_unit_id AND ".$this->tableDrugDoseUnit.".is_deleted = ".Config::get('constants.IS_DELETED_NO')." 
            WHERE ".$this->table.".is_deleted =".Config::get('constants.IS_DELETED_NO')." 
            AND ".$this->table.".pat_id=".$requestData['pat_id']." 
            AND ".$this->table.".is_discontinued=".Config::get('constants.IS_DISCONTINUED_NO')." ";

        if (!empty($requestData['visit_id'])) {
            $query .= "AND ".$this->table.".visit_id =".$this->securityLibObj->decrypt($requestData['visit_id'])." ";
        }

        /* Condition for Filtering the result */
        if (!empty($requestData['filtered'])) {
            $query .= " AND (";
            foreach ($requestData['filtered'] as $key => $value) {
                $query .= $value['id']." ilike '%".$value['value']."%'";
            }
            $query .= ")";
        }

        /* Condition for Sorting the result */
        if (!empty($requestData['sorted'])) {
            foreach ($requestData['sorted'] as $key => $value) {
                $orderBy = $value['desc'] ? 'desc' : 'asc';
                $query .= " ORDER BY ".$value['id']." ".$orderBy." ";
            }
        }
        if ($requestData['page'] > 0) {
            $offset = $requestData['page']*$requestData['pageSize'];
        } else {
            $offset = 0;
        }

        $withoutPagination = DB::select(DB::raw($query));
        $queryResult['pages'] = ceil(count($withoutPagination)/$requestData['pageSize']);
        $query .= " LIMIT ".$requestData['pageSize']." OFFSET ".$offset.";";
        $list  = DB::select(DB::raw($query));
        $queryResult['result'] = [];
        foreach($list as $patientMedication){
            if (!empty($patientMedication->pmh_id)) {
                $patientMedication->pmh_id                          = $this->securityLibObj->encrypt($patientMedication->pmh_id);
                $patientMedication->medicine_start_date             = $patientMedication->medicine_start_date;
                $patientMedication->medicine_start_date_formatted   = !empty($patientMedication->medicine_start_date) ? date('d/m/Y', strtotime($patientMedication->medicine_start_date)) : '-';
                $patientMedication->medicine_end_date               = $patientMedication->medicine_end_date;
                $patientMedication->medicine_end_date_formatted     = !empty($patientMedication->medicine_end_date) ? date('d/m/Y', strtotime($patientMedication->medicine_end_date)) : '-';
                $patientMedication->medicine_dose_unitVal           = $patientMedication->drug_dose_unit_name;
                $patientMedication->medicine_route                  = !empty($patientMedication->medicine_route) ? $this->staticDataModelObj->getMedicineRoute($patientMedication->medicine_route) : '';

                $dose = $patientMedication->medicine_dose.' '.$patientMedication->medicine_dose_unitVal;
                $dose .= ' - '.$patientMedication->medicine_dose2.' '.$patientMedication->medicine_dose_unitVal;
                $dose .= ' - '.$patientMedication->medicine_dose3.' '.$patientMedication->medicine_dose_unitVal;

                $patientMedication->current_medicine_dose = $dose;
                $patientMedication->pat_id                          = $this->securityLibObj->encrypt($patientMedication->pat_id);
                $patientMedication->visit_id                        = $this->securityLibObj->encrypt($patientMedication->visit_id);
                $patientMedication->prev_medicine_id                = $this->securityLibObj->encrypt($patientMedication->medicine_id);
                $patientMedication->medicine_id                     = $this->securityLibObj->encrypt($patientMedication->medicine_id);
                $patientMedication->medicine_frequency              = $patientMedication->medicine_frequency;
                $patientMedication->medicine_frequencyVal           = $this->staticDataModelObj->getMedicationsFector('medicine_frequency', $patientMedication->medicine_frequency);
                $patientMedication->medicine_duration_unitVal       = $this->staticDataModelObj->getMedicationsFector('medicine_duration_unit', $patientMedication->medicine_duration_unit);
                $patientMedication->medicine_duration_unit          = $patientMedication->medicine_duration_unit;
                $patientMedication->medicine_dose_unit              = $this->securityLibObj->encrypt($patientMedication->medicine_dose_unit);
                $patientMedication->drug_dose_unit_id               = $this->securityLibObj->encrypt($patientMedication->drug_dose_unit_id);
                $patientMedication->medicine_meal_optVal            = $this->staticDataModelObj->getMedicationsFector('medicine_meal_opt', $patientMedication->medicine_meal_opt);
                $patientMedication->medicine_meal_opt               = (string) $patientMedication->medicine_meal_opt;
                $patientMedication->is_end_date_past                = (!empty($patientMedication->medicine_end_date) && (strtotime($patientMedication->medicine_end_date) < strtotime(date('Y-m-d')))) ? 1 : 0 ;
                $patientMedication->medicine_instructions           = !empty($patientMedication->medicine_instructions) ? $patientMedication->medicine_instructions : "" ;
                $patientMedication->medicine_name                   = !empty($patientMedication->drug_dose) ? $patientMedication->medicine_name. ' ( '.$patientMedication->drug_dose.' '.$patientMedication->drug_dose_unit_name.' )' : $patientMedication->medicine_name;
                $queryResult['result'][] = $patientMedication;
            }
        }        

        return $queryResult;
    }

    /**
     * @DateOfCreation        27 March 2021
     * @ShortDescription      This function is responsible to get the symptoms visit data
     * @param                 integer $patId
     * @return                object Array of symptoms records
     */
    public function patientRunningMedications($requestData)
    {
        $today = date('Y-m-d');
        $query = "SELECT
            ".$this->tableMedicines.".medicine_name,
            ".$this->table.".pmh_id,
            ".$this->table.".medicine_start_date,
            ".$this->table.".medicine_end_date,
            ".$this->table.".medicine_dose,
            ".$this->table.".medicine_dose2,
            ".$this->table.".medicine_dose3,
            ".$this->table.".medicine_dose_unit,
            ".$this->table.".medicine_route,
            ".$this->tableDrugType.".drug_type_name,
            ".$this->tableDrugDoseUnit.".drug_dose_unit_name
            FROM ".$this->table." 
            JOIN ".$this->tableVisits." ON ".$this->tableVisits.".visit_id=".$this->table.".visit_id AND ".$this->tableVisits.".is_deleted = ".Config::get('constants.IS_DELETED_NO')." 
            JOIN doctor_patient_relation ON doctor_patient_relation.pat_id=".$this->table.".pat_id ";

        if ($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR')) {
            $query .= "JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id,user_firstname,user_lastname,user_type from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(user_id int,
                user_firstname text,
                user_lastname text,
                user_type int
                )) AS users ON users.user_id= ".$this->table.".created_by AND (users.user_type < ".Config::get('constants.USER_TYPE_DOCTOR')." OR ".$this->table.".created_by =".$requestData['created_by'].")";
        }

        $query .= " LEFT JOIN ".$this->tableMedicines." ON ".$this->tableMedicines.".medicine_id=".$this->table.".medicine_id 
            LEFT JOIN ".$this->tableDrugType." ON ".$this->tableDrugType.".drug_type_id=".$this->tableMedicines.".drug_type_id AND ".$this->tableDrugType.".is_deleted = ".Config::get('constants.IS_DELETED_NO')." 
            LEFT JOIN ".$this->tableDrugDoseUnit." ON ".$this->tableDrugDoseUnit.".drug_dose_unit_id=".$this->tableMedicines.".drug_dose_unit_id AND ".$this->tableDrugDoseUnit.".is_deleted = ".Config::get('constants.IS_DELETED_NO')." 
            WHERE ".$this->table.".is_deleted =".Config::get('constants.IS_DELETED_NO')." 
            AND ".$this->table.".pat_id=".$requestData['pat_id']." 
            AND ".$this->table.".is_discontinued=".Config::get('constants.IS_DISCONTINUED_NO')." 
            AND ".$this->table.".medicine_start_date<='".$today."' 
            AND ( ".$this->table.".medicine_end_date>='".$today."' OR ".$this->table.".medicine_end_date IS NULL) ";

        if (!empty($requestData['visit_id'])) {
            $query .= " AND ".$this->table.".visit_id =".$this->securityLibObj->decrypt($requestData['visit_id'])." ";
        }

        if (array_key_exists('dr_id', $requestData) && !empty($requestData['dr_id'])) {
            $query .= " AND doctor_patient_relation.user_id=".$requestData['dr_id']." ";
        }

        /* Condition for Filtering the result */
        if (!empty($requestData['filtered'])) {
            $query .= " AND (";
            foreach ($requestData['filtered'] as $key => $value) {
                $query .= $value['id']." ilike '%".$value['value']."%'";
            }
            $query .= ")";
        }

        /* Condition for Sorting the result */
        if (!empty($requestData['sorted'])) {
            foreach ($requestData['sorted'] as $key => $value) {
                $orderBy = $value['desc'] ? 'desc' : 'asc';
                $query .= " ORDER BY ".$value['id']." ".$orderBy." ";
            }
        }
        if ($requestData['page'] > 0) {
            $offset = $requestData['page']*$requestData['pageSize'];
        } else {
            $offset = 0;
        }

        $withoutPagination = DB::select(DB::raw($query));
        $queryResult['pages'] = ceil(count($withoutPagination)/$requestData['pageSize']);
        $query .= " LIMIT ".$requestData['pageSize']." OFFSET ".$offset.";";
        $list  = DB::select(DB::raw($query));
        $queryResult['result'] = [];
        foreach($list as $patientMedication){
            if (!empty($patientMedication->pmh_id)) {
                $patientMedication->pmh_id                          = $this->securityLibObj->encrypt($patientMedication->pmh_id);
                $patientMedication->medicine_start_date             = $patientMedication->medicine_start_date;
                $patientMedication->medicine_start_date_formatted   = !empty($patientMedication->medicine_start_date) ? date('d/m/Y', strtotime($patientMedication->medicine_start_date)) : '-';
                $patientMedication->medicine_end_date               = $patientMedication->medicine_end_date;
                $patientMedication->medicine_end_date_formatted     = !empty($patientMedication->medicine_end_date) ? date('d/m/Y', strtotime($patientMedication->medicine_end_date)) : '-';
                $patientMedication->medicine_dose_unitVal           = $patientMedication->drug_dose_unit_name;
                $patientMedication->medicine_dose_unit              = $patientMedication->medicine_dose_unit;
                $patientMedication->medicine_route                  = !empty($patientMedication->medicine_route) ? $this->staticDataModelObj->getMedicineRoute($patientMedication->medicine_route) : '';

                $dose = $patientMedication->medicine_dose.' '.$patientMedication->medicine_dose_unitVal;
                $dose .= ' - '.$patientMedication->medicine_dose2.' '.$patientMedication->medicine_dose_unitVal;
                $dose .= ' - '.$patientMedication->medicine_dose3.' '.$patientMedication->medicine_dose_unitVal;

                $patientMedication->current_medicine_dose = $dose;
                $queryResult['result'][] = $patientMedication;
            }
        } 
        return $queryResult;
    }

    /**
     * @DateOfCreation        27 July 2018
     * @ShortDescription      This function is responsible to insert master Symptom data if Symptom name not exists
     * @param                 Array  $requestData
     * @return                Array of id
     */
    public function createMedicineId($requestData)
    {
        $medicineName   = strpos($requestData['medicine_name'], '(') === false ? $requestData['medicine_name'] : strstr($requestData['medicine_name'], '(', true);
        $medicineName   = trim($medicineName);

        if (!empty($requestData['medicine_strength'])) {
            $medicineDose   = $requestData['medicine_strength'];
        } else {
            $medicineDose   = $requestData['medicine_strength'];
        }
        $requestData['medicine_dose'] = $medicineDose;
        $medicineDoseUnit = $requestData['drug_dose_unit_id'] = $requestData['medicine_dose_unit'];

        if (!empty($requestData['medicine_instructions'])) {
            $requestData['medicine_instructions'] = !empty($requestData['medicine_instructions']) && !is_array($requestData['medicine_instructions']) ? $requestData['medicine_instructions']: "";
        } else {
            $requestData['medicine_instructions'] = null;
        }

        $resultMedicine = $this->checkIfMedicineExist($medicineName, $medicineDose, $medicineDoseUnit);
        if (!empty($resultMedicine) && isset($resultMedicine->medicine_id)) {
            return $resultMedicine->medicine_id;
        } else {
            $filldata  = ['medicine_name','ip_address','resource_type', 'drug_dose_unit_id', 'medicine_dose', 'drug_type_id'];
            $inserData = $this->utilityLibObj->fillterArrayKey($requestData, $filldata);
            $response  = $this->dbInsert($this->tableMedicines, $inserData);
            if ($response) {
                $id = DB::getPdo()->lastInsertId();

                $responseRelation = $this->dbInsert($this->tableDocMedicineRelation, ['user_id' => $requestData['user_id'], 'medicine_id' => $id, 'medicine_instructions' => $requestData['medicine_instructions'] ]);

                return $id;
            } else {
                return $response;
            }
        }
        return false;
    }

    /**
     * @DateOfCreation        1 Oct 2018
     * @ShortDescription      This function is responsible to get the medicine data by medicine name, dose and dose unit
     * @param                 string $medicineName, $medicineDose, $medicineDoseUnit
     * @return                object medicine id
     */
    public function checkIfMedicineExist($medicineName, $medicineDose = null, $medicineDoseUnit = null)
    {
        $medicineName = trim($medicineName);
        $queryResult = DB::table($this->tableMedicines)
                    ->select('medicine_id')
                    ->where('medicine_name', 'ILIKE', $medicineName)
                    ->where('is_deleted', Config::get('constants.IS_DELETED_NO'));

        if (!empty($medicineDose)) {
            $queryResult->where('medicine_dose', '=', $medicineDose);
        }

        if (!empty($medicineDoseUnit)) {
            $queryResult->where('drug_dose_unit_id', '=', $medicineDoseUnit);
        }

        $result = $queryResult->first();
        return $result;
    }

    /**
     * @DateOfCreation        27 July 2018
     * @ShortDescription      This function is responsible to get the medicine data by medicine name
     * @param                 string $medicineName
     * @return                object medicine id
     */
    private function getMedicineDataByMedicineName($medicineName)
    {
        $medicineName = trim($medicineName);
        $queryResult = DB::table($this->tableMedicines)
                    ->select('medicine_id')
                    ->where('medicine_name', 'ILIKE', $medicineName)
                    ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
                    ->first();
        return $queryResult;
    }

    /**
     * @DateOfCreation        22 Aug 2018
     * @ShortDescription      This function is responsible to get medicine data by medicine id and user id
     * @param                 Array  $requestData
     * @return                Array of data
     */
    public function getMedicineData($requestData)
    {
        $selectData = [
                        $this->tableMedicines.'.medicine_id',
                        $this->tableMedicines.'.medicine_name',
                        $this->tableMedicines.'.medicine_dose',
                        $this->tableMedicines.'.drug_type_id',
                        $this->tableMedicines.'.drug_dose_unit_id',
                        $this->tableDrugType.'.drug_type_name',
                        $this->tableDrugDoseUnit.'.drug_dose_unit_name',
                        $this->tableDocMedicineRelation.'.medicine_instructions'
                    ];

        $queryResult = DB::table($this->tableMedicines)
                        ->select($selectData)
                        ->leftJoin($this->tableDrugType, function ($join) {
                            $join->on($this->tableMedicines.'.drug_type_id', '=', $this->tableDrugType.'.drug_type_id')
                                    ->where($this->tableDrugType.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                        })
                        ->leftJoin($this->tableDrugDoseUnit, function ($join) {
                            $join->on($this->tableMedicines.'.drug_dose_unit_id', '=', $this->tableDrugDoseUnit.'.drug_dose_unit_id')
                                    ->where($this->tableDrugDoseUnit.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                        })
                        ->leftJoin($this->tableDocMedicineRelation, function ($join) use ($requestData) {
                            $join->on($this->tableMedicines.'.medicine_id', '=', $this->tableDocMedicineRelation.'.medicine_id')
                                    ->where($this->tableDocMedicineRelation.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and')
                                    ->where($this->tableDocMedicineRelation.'.user_id', '=', $requestData['user_id'], 'and');
                        })
                        ->where($this->tableMedicines.'.medicine_id', '=', $requestData['medicine_id'])
                        ->where($this->tableMedicines.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                        ->get()
                        ->map(function ($medicineData) {
                            if (!empty($medicineData->medicine_id)) {
                                $medicineData->medicine_id           = $this->securityLibObj->encrypt($medicineData->medicine_id);
                                $medicineData->drug_type_id          = $this->securityLibObj->encrypt($medicineData->drug_type_id);
                                $medicineData->drug_dose_unit_id     = $this->securityLibObj->encrypt($medicineData->drug_dose_unit_id);
                                $medicineData->medicine_instructions = !empty($medicineData->medicine_instructions) ? $medicineData->medicine_instructions : "";
                            }
                            return $medicineData;
                        });
        return $queryResult;
    }

    /**
     * @DateOfCreation        22 Aug 2018
     * @ShortDescription      This function is responsible to get the medicine dose unit
     * @return                array dose unit data
     * @param
     */
    public function getDoseUnit()
    {
        $queryResult = DB::table($this->tableDrugDoseUnit)
                    ->select('drug_dose_unit_id', 'drug_dose_unit_name')
                    ->where('is_deleted', Config::get('constants.IS_DELETED_NO'));

        $getResult = $queryResult->get()
                                ->map(function ($medicineDose) {
                                    $medicineDose->drug_dose_unit_id = $this->securityLibObj->encrypt($medicineDose->drug_dose_unit_id);
                                    return $medicineDose;
                                });

        return $getResult;
    }

    /**
    * @DateOfCreation        20 Sept 2018
    * @ShortDescription      This function is responsible to get the Medicine record
    * @param                 String $stateId
    * @return                object Array of city records
    */
    public function searchMedicineRecord($requestData)
    {
        $queryResult = DB::table($this->tableMedicines)
                            ->select(
                                $this->tableMedicines.'.medicine_id',
                                $this->tableMedicines.'.medicine_name',
                                $this->tableMedicines.'.medicine_dose as medicine_strength',
                                $this->tableMedicines.'.drug_type_id',
                                $this->tableMedicines.'.drug_dose_unit_id',
                                $this->tableDrugType.'.drug_type_name',
                                $this->tableDrugDoseUnit.'.drug_dose_unit_name',
                                $this->tableDocMedicineRelation.'.medicine_instructions'
                                )
                            ->leftJoin($this->tableDrugType, function ($join) {
                                $join->on($this->tableMedicines.'.drug_type_id', '=', $this->tableDrugType.'.drug_type_id')
                                    ->where($this->tableDrugType.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                            ->leftJoin($this->tableDrugDoseUnit, function ($join) {
                                $join->on($this->tableMedicines.'.drug_dose_unit_id', '=', $this->tableDrugDoseUnit.'.drug_dose_unit_id')
                                    ->where($this->tableDrugDoseUnit.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                            ->join($this->tableDocMedicineRelation, function ($join) use ($requestData) {
                                $join->on($this->tableMedicines.'.medicine_id', '=', $this->tableDocMedicineRelation.'.medicine_id')
                                                    ->where($this->tableDocMedicineRelation.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and')
                                                    ->where($this->tableDocMedicineRelation.'.user_id', '=', $requestData['user_id'], 'and');
                            })
                            ->where($this->tableMedicines.'.is_deleted', Config::get('constants.IS_DELETED_NO'));

        if (!empty($requestData['medicine_name'])) {
            $queryResult = $queryResult->where('medicine_name', 'ilike', '%'.$requestData['medicine_name'].'%');
        } else {
            $queryResult->limit(10);
        }

        $queryResult = $queryResult
                        ->orderBy($this->tableMedicines.'.medicine_name', 'ASC')
                        ->get()
                        ->map(function ($medicineList) {
                            if (!empty($medicineList->medicine_id)) {
                                $medicineList->medicine_id          = $this->securityLibObj->encrypt($medicineList->medicine_id);
                                $medicineList->drug_type_id         = $this->securityLibObj->encrypt($medicineList->drug_type_id);
                                $medicineList->drug_dose_unit_id    = $this->securityLibObj->encrypt($medicineList->drug_dose_unit_id);
                                $medicineList->medicine_name        = !empty($medicineList->medicine_strength) ? $medicineList->medicine_name. ' ( '.$medicineList->medicine_strength.' '.$medicineList->drug_dose_unit_name.' )' : $medicineList->medicine_name;
                            }
                            return $medicineList;
                        });
        return $queryResult;
    }

    /**
    * @DateOfCreation        10 June 2021
    * @ShortDescription      This function is responsible to get the Medicine record
    * @param                 String $stateId
    * @return                object Array of city records
    */
    public function searchAllMedicineRecord($requestData)
    {
        $queryResult = DB::table($this->tableMedicines)
                            ->select(
                                $this->tableMedicines.'.medicine_id',
                                $this->tableMedicines.'.medicine_name',
                                $this->tableMedicines.'.medicine_dose as medicine_strength',
                                $this->tableMedicines.'.drug_type_id',
                                $this->tableMedicines.'.drug_dose_unit_id',
                                $this->tableDrugType.'.drug_type_name',
                                $this->tableDrugDoseUnit.'.drug_dose_unit_name',
                                $this->tableDocMedicineRelation.'.medicine_instructions'
                                )
                            ->leftJoin($this->tableDrugType, function ($join) {
                                $join->on($this->tableMedicines.'.drug_type_id', '=', $this->tableDrugType.'.drug_type_id')
                                    ->where($this->tableDrugType.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                            ->leftJoin($this->tableDrugDoseUnit, function ($join) {
                                $join->on($this->tableMedicines.'.drug_dose_unit_id', '=', $this->tableDrugDoseUnit.'.drug_dose_unit_id')
                                    ->where($this->tableDrugDoseUnit.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                            ->join($this->tableDocMedicineRelation, function ($join) use ($requestData) {
                                $join->on($this->tableMedicines.'.medicine_id', '=', $this->tableDocMedicineRelation.'.medicine_id')
                                                    ->where($this->tableDocMedicineRelation.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                            ->where($this->tableMedicines.'.is_deleted', Config::get('constants.IS_DELETED_NO'));

        if (!empty($requestData['medicine_name'])) {
            $queryResult = $queryResult->where('medicine_name', 'ilike', '%'.$requestData['medicine_name'].'%');
        } else {
            $queryResult->limit(10);
        }

        $queryResult = $queryResult
                        ->orderBy($this->tableMedicines.'.medicine_name', 'ASC')
                        ->get()
                        ->map(function ($medicineList) {
                            if (!empty($medicineList->medicine_id)) {
                                $medicineList->medicine_id          = $this->securityLibObj->encrypt($medicineList->medicine_id);
                                $medicineList->drug_type_id         = $this->securityLibObj->encrypt($medicineList->drug_type_id);
                                $medicineList->drug_dose_unit_id    = $this->securityLibObj->encrypt($medicineList->drug_dose_unit_id);
                                $medicineList->medicine_name        = !empty($medicineList->medicine_strength) ? $medicineList->medicine_name. ' ( '.$medicineList->medicine_strength.' '.$medicineList->drug_dose_unit_name.' )' : $medicineList->medicine_name;
                            }
                            return $medicineList;
                        });
        return $queryResult;
    }

    public function prepareMultipleMedicationData($requestData, $userId)
    {
        // GET MEDICINE TYPE
        $getAllDrugType = $this->drugTypeModelObj->getAllDrugType();
        $getAllDrugType = json_decode(json_encode($getAllDrugType), true);

        $getDrugTypeKey = !empty($getAllDrugType) ? $this->utilityLibObj->changeArrayKey($getAllDrugType, 'drug_type_id') : [];

        if (!empty($requestData['medicine_type_id'])) {
            $requestData['drug_type_id'] = isset($requestData['medicine_type_id']) && !empty($requestData['medicine_type_id']) ? $this->securityLibObj->decrypt($requestData['medicine_type_id']) : null;

            unset($requestData['medicine_type_id']);
            unset($requestData['medicine_type']);
        } else {
            $requestData['drug_type_id'] = $this->drugTypeModelObj->saveDrugType(['drug_type_name' => $requestData['medicine_type']]);

            unset($requestData['medicine_type_id']);
            unset($requestData['medicine_type']);
        }

        $requestData['medicine_dose_unit']  = isset($requestData['medicine_dose_unit']) && !empty($requestData['medicine_dose_unit']) ? $this->securityLibObj->decrypt($requestData['medicine_dose_unit']) : null;
        $requestData['medicine_id']         = (isset($requestData['medicine_id']) && !empty($requestData['medicine_id']) && $requestData['medicine_id'] != 'undefined') ? $this->securityLibObj->decrypt($requestData['medicine_id']) : $this->createMedicineId($requestData);

        // Make Doctor medicine relation if not exist
        $param  = ['medicine_id' => $requestData['medicine_id'], 'user_id' => $requestData['user_id']];
        $encrypt= true;
        $checkIfMedicineRelationExist = $this->manageDrugsModelObj->getAllMedicinRelation($param, false);
        $checkIfMedicineRelationExist = json_decode(json_encode($checkIfMedicineRelationExist), true);
        if (empty($checkIfMedicineRelationExist)) {
            $newRelation = $this->manageDrugsModelObj->addRequest(['medicine_id' => $requestData['medicine_id'], 'user_id' => $requestData['user_id']]);
        }

        $medicationData = [];
        $medicationData['pat_id']       = $requestData['pat_id'];
        $medicationData['visit_id']     = $requestData['visit_id'];
        $medicationData['medicine_id']  = $requestData['medicine_id'];

        $medicationData['medicine_dose_unit']    = $requestData['medicine_dose_unit'];
        $medicationData['medicine_dose']         = isset($requestData['medicine_dose']) && !empty($requestData['medicine_dose']) ? $requestData['medicine_dose'] : 0;
        $medicationData['medicine_dose2']        = isset($requestData['medicine_dose2']) && !empty($requestData['medicine_dose2']) ? $requestData['medicine_dose2'] : 0;
        $medicationData['medicine_dose3']        = isset($requestData['medicine_dose3']) && !empty($requestData['medicine_dose3']) ? $requestData['medicine_dose3'] : 0;
        $medicationData['medicine_duration']     = isset($requestData['medicine_duration']) && !empty($requestData['medicine_duration']) ? $requestData['medicine_duration'] : 1;
        $medicationData['medicine_duration_unit']= isset($requestData['medicine_duration_unit']) && !empty($requestData['medicine_duration_unit']) ? $requestData['medicine_duration_unit'] : 0;

        $medicationData['medicine_instructions'] = (isset($requestData['medicine_instructions']) && $requestData['medicine_instructions'] != 'undefined' && $requestData['medicine_instructions'] != 'null' && !is_array($requestData['medicine_instructions']) && !empty($requestData['medicine_instructions'])) ? $requestData['medicine_instructions'] : null;
        $medicationData['medicine_instructions'] = !empty($medicationData['medicine_instructions']) ? $medicationData['medicine_instructions'] : null;
        $medicationData['medicine_meal_opt']     = isset($requestData['medicine_meal_opt']) ? $this->utilityLibObj->arrayToStringVal($requestData['medicine_meal_opt']) : null;
        $medicationData['medicine_start_date']   = isset($requestData['medicine_start_date']) && !empty($requestData['medicine_start_date']) ? $requestData['medicine_start_date'] : date('Y-m-d');

        $duration       = $requestData['medicine_duration'];
        $durationUnit   = $requestData['medicine_duration_unit'];
        $dateFormat     =  Carbon::createFromFormat('Y-m-d', $medicationData['medicine_start_date']);
        if ($durationUnit == Config::get('dataconstants.MEDICINE_DURATION_UNIT_WEEKS')) {
            $medicationData['medicine_end_date'] = $dateFormat->addWeek($duration)->toDateString();
        } elseif ($durationUnit == Config::get('dataconstants.MEDICINE_DURATION_UNIT_MONTHS')) {
            $medicationData['medicine_end_date'] = $dateFormat->addMonth($duration)->toDateString();
        } else {
            $medicationData['medicine_end_date'] = $dateFormat->addDays($duration)->toDateString();
        }

        // Unset non usable fields here
        if (isset($medicationData['medicine_name'])) {
            unset($medicationData['medicine_name']);
        }
        if (isset($medicationData['user_id'])) {
            unset($medicationData['user_id']);
        }
        if (isset($medicationData['drug_type_id'])) {
            unset($medicationData['drug_type_id']);
        }

        $response['medicationData'] = $medicationData;
        $response['activityData']   = [ 'pat_id' => $requestData['pat_id'], 'user_id' => $userId, 'activity_table' => 'patient_medication_history', 'visit_id' => $requestData['visit_id'] ];

        return $response;
    }

    public function saveMultipleMedicationData($medicationBatchArray, $activityBatchArray=[])
    {
        $response = $this->dbBatchInsert($this->table, $medicationBatchArray);
        if ($response) {
            $this->patientActivitiesModelObj->insertActivityBatch($activityBatchArray);

            return true;
        }
        return false;
    }
}
