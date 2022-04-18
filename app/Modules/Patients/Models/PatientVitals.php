<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use Config;
use App\Libraries\UtilityLib;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Libraries\DateTimeLib;

/**
 * PatientVitals Class
 *
 * @package                ILD INDIA
 * @subpackage             PatientVitals
 * @category               Model
 * @DateOfCreation         17 Sep 2020
 * @ShortDescription       This is model which need to perform the options related to
                           PatientVitals info
 */
class PatientVitals extends Model
{
    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'patient_vitals';
    protected $tablePatientRelation  = 'doctor_patient_relation';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'patient_vitals_id';

    protected $encryptable = [];

    protected $fillable = ['pat_id', 'temperature', 'pulse', 'bp_systolic', 'bp_diastolic', 'spo2', 'respiratory_rate', 'sugar_level', 'jvp', 'pedel_edema', 'resource_type', 'ip_address', 'height', 'weight', 'bmi'];

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init exception library object
        $this->utilityLibObj = new UtilityLib();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to get table name
     * @return                string Patient Table Name
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to save record for the Patient Vitals
     * @param                 array $requestData
     * @return                integer Patient Vitals id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to check the Primary Id exist in the system or not
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function isPrimaryIdExist($primaryId)
    {
        $primaryIdExist = DB::table($this->table)
            ->where($this->primaryKey, $primaryId)
            ->exists();
        return $primaryIdExist;
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to save record for the Patient Vitals
     * @param                 array $requestData
     * @return                integer auto increment id
     */
    public function savePatientVitals($inserData)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // @var Array $inserData
        // This Array contains insert data for Patient
        $inserData = $this->utilityLibObj->fillterArrayKey($inserData, $this->fillable);

        // Prepair insert query
        $response = $this->dbInsert($this->table, $inserData);
        if ($response) {
            $patientVitals = $this->getPatiemtVitalsById(DB::getPdo()->lastInsertId());
            // Encrypt the ID
            $patientVitals->patient_vitals_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $patientVitals;
        } else {
            return $response;
        }
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to update Patient Vitals Record
     * @param                 Array  $requestData
     * @return                Array of status and message
     */
    public function updateRequest($requestData, $whereData)
    {
        $patient_vitals_id = $requestData['patient_vitals_id'];
        unset($requestData['patient_vitals_id']);
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        if ($response) {
            $updatePatientVitals = $this->getPatiemtVitalsById($patient_vitals_id);
            // Encrypt the ID
            $updatePatientVitals->patient_vitals_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $updatePatientVitals;
        }
        return false;
    }

    /**
     * @DateOfCreation        18 Sep 2020
     * @ShortDescription      This function is responsible to Delete Patient Vitals data
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function doDeleteRequest($primaryId)
    {
        $queryResult = $this->dbUpdate(
            $this->table,
            ['is_deleted' => Config::get('constants.IS_DELETED_YES')],
            [$this->primaryKey => $primaryId]
        );

        if ($queryResult) {
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        18 Sep 2020
     * @ShortDescription      This function is responsible for get all Patients Vitals Records by pat_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getPatientVitalsList($requestData)
    {
        $listQuery = $this->patiemtVitalsListQuery($requestData['pat_id']);

        if (!empty($requestData['filtered'])) {
            foreach ($requestData['filtered'] as $key => $value) {

                if (!empty($value['value'])) {
                    $listQuery = $listQuery->where(function ($listQuery) use ($value) {
                        $listQuery
                            ->where('temperature', 'ilike', "%" . $value['value'] . "%")
                            ->orWhere('pulse', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('bp_systolic', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('bp_diastolic', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('spo2', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('respiratory_rate', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('sugar_level', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('jvp', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('pedel_edema', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('height', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('weight', 'ilike', '%' . $value['value'] . '%')
                            ->orWhere('bmi', 'ilike', '%' . $value['value'] . '%');
                    });
                }
            }
        }

        if (!empty($requestData['sorted'])) {
            foreach ($requestData['sorted'] as $sortKey => $sortValue) {
                $orderBy = $sortValue['desc'] ? 'desc' : 'asc';
                $listQuery->orderBy($sortValue['id'], $orderBy);
            }
        }

        if ($requestData['page'] > 0) {
            $offset = $requestData['page'] * $requestData['pageSize'];
        } else {
            $offset = 0;
        }

        $list['pages']   = ceil($listQuery->count() / $requestData['pageSize']);

        $list['result']  = $listQuery
            ->offset($offset)
            ->limit($requestData['pageSize'])
            ->get()
            ->map(function ($listData) {
                $listData->patient_vitals_id = $this->securityLibObj->encrypt($listData->patient_vitals_id);
                return $listData;
            });
        return $list;
    }

    /**
     * @DateOfCreation        18 Sep 2020
     * @ShortDescription      This function is responsible for patient vitals list query from user and patient tables
     * @param                 Array $data This contains full Patient user input data
     * @return                Array of patients
     */
    public function patiemtVitalsListQuery($userId)
    {
        $selectData = [$this->table . '.patient_vitals_id', $this->table . '.temperature', $this->table . '.pulse', $this->table . '.bp_systolic', $this->table . '.bp_diastolic', $this->table . '.spo2', $this->table . '.respiratory_rate', $this->table . '.sugar_level', $this->table . '.jvp', $this->table . '.pedel_edema', $this->table . '.height', $this->table . '.weight', $this->table . '.bmi'];

        $whereData = array(
            $this->table . '.is_deleted'      => Config::get('constants.IS_DELETED_NO'),
            $this->table . '.pat_id'         => $userId
        );
        $listQuery = DB::table($this->table)
            ->select($selectData)
            ->where($whereData);
        return $listQuery;
    }

    /**
    * @DateOfCreation        25 Sep 2020
    * @ShortDescription      This function is responsible to get the service by id
    * @param                 String $patient_vitals_id   
    * @return                Array of service
    */
    public function getPatiemtVitalsById($patient_vitals_id)
    {   
    	$selectData = ['patient_vitals_id', 'temperature','pulse', 'bp_systolic','bp_diastolic', 'spo2','respiratory_rate', 'sugar_level','jvp', 'pedel_edema', 'height', 'weight', 'bmi'];
        $whereData = array(
                        'patient_vitals_id' =>  $patient_vitals_id, 
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    );
        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }
}
