<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use App\Libraries\FileLib;
use App\Libraries\ImageLib;
use App\Libraries\S3Lib;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Modules\Bookings\Models\Bookings;
use App\Modules\Patients\Models\PatientsActivities;
use App\Modules\Patients\Models\PatientsAllergies;
use App\Modules\PatientGroups\Models\PatientGroups;
use App\Modules\Clinics\Models\Clinics;
use App\Modules\DoctorProfile\Models\Timing;
use App\Modules\AppointmentCategory\Models\AppointmentCategory;
use App\Modules\Bookings\Models\PatientAppointmentReason;
use Config;
use File;
use Uuid;
use stdClass;
/**
 * Patients Class
 *
 * @package                ILD INDIA
 * @subpackage             Patients
 * @category               Model
 * @DateOfCreation         13 June 2018
 * @ShortDescription       This is model which need to perform the options related to
                           Patients info
 */
class Patients extends Model {

    use Encryptable;

    protected $connection = 'pgsql';

    // @var string $table
    // This protected member contains table name
    protected $table = 'patients';
    protected $tablePatientRelation  = 'doctor_patient_relation';
    protected $tablePatientsVisits   = 'patients_visits';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'pat_id';

    protected $encryptable = [];

    protected $fillable = ['user_id','pat_title', 'pat_code', 'pat_dob', 'pat_address_line1', 'pat_address_line1',
            'pat_phone_num','pat_locality', 'city_id', 'pat_other_city', 'state_id', 'pat_pincode', 'pat_status', 'ip_address', 'resource_type', 'is_deleted', 'pat_address_line2','pat_blood_group', 'pat_marital_status', 'pat_number_of_children', 'pat_religion', 'pat_informant', 'pat_reliability', 'pat_occupation', 'pat_education', 'doc_ref_id', 'pat_group_id', 'pat_emergency_contact_number', 'pat_age'];

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

        $this->FileLib = new FileLib();

        $this->s3LibObj = new S3Lib();
        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        //Init StaticDataConfig model object
        $this->staticDataConfigObj = new StaticDataConfig();

        // Init Patients Activities Model Object
        $this->patientActivitiesModelObj = new PatientsActivities();

        // Init Patients Allergies Model Object
        $this->patientAllergiesModelObj = new PatientsAllergies();

        // Init Patients Groups Model Object
        $this->patientGroupsModelObj = new PatientGroups();
    }

    public function patientGroup(){
        return $this->HasOne('App\Modules\PatientGroups\Models\PatientGroups', 'pat_group_id', 'pat_group_id');
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for creating new Patient in DB
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function createPatient($data, $userId)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // @var Array $inserData
        // This Array contains insert data for users
        $inserData = array(
            'user_id'               => $userId,
            'pat_code'              => $data['pat_code'],
            'resource_type'         => $data['resource_type'],
            'ip_address'            => $data['ip_address'],
            'created_by'            => $userId,
            'updated_by'            => $userId
        );

        if(isset($data['external_pat_number']) && !empty($data['external_pat_number'])){
            $inserData['external_pat_number']   = $data['external_pat_number'];
        }
        // Prepair insert query
        $response = $this->dbInsert($this->table, $inserData);
        return $response;
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for update Patient Records
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function updatePatientData($requestData, $whereData)
    {
        // This Array contains update data for Patient
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);

        // Prepare update query
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        return $response;
    }

    /**
     * @DateOfCreation        22 Nov 2018
     * @ShortDescription      This function is to get the Primary key name
     * @return                integer primary key name id
    */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        22 Nov 2018
     * @ShortDescription      This function is responsible to check the primary value exist in the system or not
     * @param                 integer $primaryId
     * @return                boolean
     */
    public function isPrimaryIdExist($primaryId){
        $primaryIdExist = DB::table($this->table)
                        ->where($this->primaryKey, $primaryId)
                        ->exists();
        return $primaryIdExist;
    }

    /**
    * @DateOfCreation        22 Nov 2018
    * @ShortDescription      This function is responsible to Delete doctor patient relation
    * @param                 Array $pat_id
    * @return                Array of status and message
    */
    public function doDeletePatient($pat_user_id, $user_id)
    {
        $updateData = array(
                        'is_deleted' => Config::get('constants.IS_DELETED_YES')
                        );
        $whereData = array( $this->primaryKey => $pat_user_id, 'user_id' => $user_id );

        $queryResult =  $this->dbUpdate($this->tablePatientRelation, $updateData, $whereData);
        if($queryResult){
            return true;
        }
        return false;
    }

    /**
    * @DateOfCreation        01 June 2021
    * @ShortDescription      This function is responsible to check doctor patient relation
    * @param                 Array $pat_id
    * @return                Array of status and message
    */
    public function isMobileExists($number, $user_id)
    {
        $checkUser = DB::table('users')
                        ->select('users.user_id', 'user_mobile')
                        ->join('doctor_patient_relation as dpr', 'dpr.pat_id', '=', 'users.user_id')
                        ->where([
                            'dpr.user_id' => $user_id,
                            'user_mobile' => $number,
                            'dpr.is_deleted' => Config::get('constants.IS_DELETED_NO')
                        ])
                        ->first();
        if(!empty($checkUser)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for update Patient Records
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getPatientProfileData($requestData, $patientId)
    {
        $whereProfileData  = [
            $this->table.'.user_id' => $patientId,
            'patients_visits.visit_type' => Config::get('constants.PROFILE_VISIT_TYPE'),
            $this->table.'.is_deleted' => Config::get('constants.IS_DELETED_NO')
        ];
        $allergyListData   = $this->patientAllergiesModelObj->getListData(['patId' => $this->securityLibObj->encrypt($patientId), 'page' => 0, 'pageSize' => -1, 'sort' => [], 'filtered' => [] ]);

        $patientAllergyData = [];
        if(!empty($allergyListData['result'])){
            foreach ($allergyListData['result'] as $allergyData) {
                $patientAllergyData[] = $allergyData->allergy_type_value;
            }
        }
        $user = DB::connection('masterdb')
                    ->table('users')
                    ->select(
                        'user_id',
                        'user_mobile',
                        'user_email',
                        'user_gender',
                        'user_country_code',
                        'user_firstname',
                        'user_lastname',
                        'user_adhaar_number'
                    )
                    ->where([
                        'user_id' => $patientId,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    ])
                    ->first();
        
        $response = DB::table($this->table)
                    ->select($this->table.'.pat_id',
                        'pat_title',
                        'pat_profile_img',
                        'pat_phone_num',
                        'pat_code',
                        'pat_dob',
                        'pat_address_line1',
                        'pat_address_line2',
                        'pat_locality',
                        'city_id',
                        'pat_other_city',
                        $this->table.'.state_id',
                        'pat_pincode',
                        'pat_status',
                        'states.country_id',
                        'pat_blood_group',
                        'states.name as state_name',
                        'country_name',
                        'cities.name as city_name',
                        'pat_marital_status',
                        'pat_number_of_children',
                        'pat_religion',
                        'pat_informant',
                        'pat_reliability',
                        'pat_occupation',
                        'pat_education',
                        $this->table.'.doc_ref_id',
                        $this->table.'.pat_group_id',
                        'patient_groups.pat_group_name',
                        'doctor_referral.doc_ref_name',
                        'pat_emergency_contact_number',
                        'patients_visits.visit_id',
                        'pat_age'
                     );
                    if($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR') || in_array($requestData['user_type'], Config::get('constants.USER_TYPE_STAFF'))){
                        $response = $response->join($this->tablePatientRelation,function($join) use($patientId) {
                                $join->on($this->tablePatientRelation.'.pat_id', '=', "patients.user_id")
                                    ->where($this->tablePatientRelation.'.is_deleted', Config::get('constants.IS_DELETED_NO'), 'and')
                                    ->where($this->tablePatientRelation.'.pat_id', $patientId, 'and');
                            });
                    }
                    $response = $response->leftJoin('states', $this->table.'.state_id', '=' ,'states.id')
                    ->leftJoin('cities', $this->table.'.city_id', '=' ,'cities.id')
                    ->leftJoin('country', 'states.country_id', '=' ,'country.country_id')
                    ->leftJoin('patient_groups', $this->table.'.pat_group_id', '=' ,'patient_groups.pat_group_id')
                    ->leftJoin('doctor_referral', $this->table.'.doc_ref_id', '=' ,'doctor_referral.doc_ref_id')
                    ->leftJoin('patients_visits', 'patients_visits.pat_id', '=', $this->table.'.user_id')
                    ->where($whereProfileData)
                    ->get()
                    ->map(function($patientProfileData) use($patientAllergyData){
                        $patientProfileData->allergy_type_value     = !empty($patientAllergyData) ? implode(', ', $patientAllergyData) : '';
                         //$patientProfileData->user_id                = $this->securityLibObj->encrypt($patientProfileData->user_id);
                        $patientProfileData->pat_id                 = $this->securityLibObj->encrypt($patientProfileData->pat_id);
                        $patientProfileData->visit_id               = $this->securityLibObj->encrypt($patientProfileData->visit_id);
                        $patientProfileData->doc_ref_id             = !empty($patientProfileData->doc_ref_id) ? $this->securityLibObj->encrypt($patientProfileData->doc_ref_id) : '';
                        $patientProfileData->pat_group_id           = !empty($patientProfileData->pat_group_id) ? $this->securityLibObj->encrypt($patientProfileData->pat_group_id) : '';
                        $patientProfileData->doc_ref_name           = !empty($patientProfileData->doc_ref_name) ? $patientProfileData->doc_ref_name : '';
                        $patientProfileData->pat_group_name         = !empty($patientProfileData->pat_group_name) ? $patientProfileData->pat_group_name : '';
                        $patientProfileData->pat_profile_img        =
                        !empty($patientProfileData->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($patientProfileData->pat_profile_img)) : '';
                        $patientProfileData->country_id             = $this->securityLibObj->encrypt($patientProfileData->country_id);
                        $patientProfileData->city_id                = $this->securityLibObj->encrypt($patientProfileData->city_id);
                        $patientProfileData->state_id               = $this->securityLibObj->encrypt($patientProfileData->state_id);
                        $patientProfileData->pat_dob                = !empty($patientProfileData->pat_dob) ? date(Config::get('constants.DB_SAVE_DATE_FORMAT'), strtotime($patientProfileData->pat_dob)) : '';
                        $patientProfileData->pat_blood_group_name   = $this->staticDataConfigObj->getBloodGroupNameById($patientProfileData->pat_blood_group);
                        $patientProfileData->pat_aadhar_no_formatted= !empty($patientProfileData->user_adhaar_number) ? chunk_split($patientProfileData->user_adhaar_number, 4, ' ') : '';
                        $patientProfileData->pat_marital_status     = !empty($patientProfileData->pat_marital_status) ? [(string) $patientProfileData->pat_marital_status] : [];
                        $patientProfileData->pat_religion           = $patientProfileData->pat_religion;
                        $patientProfileData->pat_age                = ($patientProfileData->pat_age == Config::get('constants.AGE_BELOW_ONE')) ? Config::get('constants.AGE_BELOW_ONE_TEXT') : $patientProfileData->pat_age;

                        $patientFullAddress = '';
                        if(!empty($patientProfileData->pat_address_line1)) $patientFullAddress .= $patientProfileData->pat_address_line1;
                        if(!empty($patientProfileData->pat_address_line2)) $patientFullAddress .= ', '.$patientProfileData->pat_address_line2;
                        $patientProfileData->pat_full_address_line1 = $patientFullAddress;

                        $patientFullAddressCity = '';
                        if(!empty($patientProfileData->pat_other_city)) {
                            $patientFullAddressCity .= $patientProfileData->pat_other_city;
                        }else{
                            $patientFullAddressCity .= $patientProfileData->city_name;
                        }

                        if(!empty($patientProfileData->state_name)) $patientFullAddressCity .= ', '.$patientProfileData->state_name;
                        if(!empty($patientProfileData->pat_pincode)) $patientFullAddressCity .= ', '.$patientProfileData->pat_pincode;
                        $patientProfileData->pat_full_address_line2 = $patientFullAddressCity;
                        return $patientProfileData;
                    })->first();
        
        if(empty($response))
        {
            $response = new stdClass();
        }
        $response->user_id = $this->securityLibObj->encrypt($user->user_id);
        $response->user_mobile = $user->user_mobile;
        $response->user_email = $user->user_email;
        $response->user_gender = $user->user_gender;
        $response->user_country_code = $user->user_country_code;
        $response->user_firstname = $user->user_firstname;
        $response->user_lastname = $user->user_lastname;
        $response->user_adhaar_number = $user->user_adhaar_number;

        return $response;
    }

    /**
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get all Patients Records by user_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getPatientList($requestData){
        $user_id = $requestData['user_id'];
        $recentVisits = isset($requestData['recent_visits']) && !empty($requestData['recent_visits']) ? $requestData['recent_visits'] : 1;

        $query = "SELECT 
                    user_firstname,
                    user_lastname,
                    users.user_id,
                    user_gender,
                    user_mobile,
                    user_email,
                    user_country_code,
                    CONCAT(user_firstname, ' ', user_lastname) AS user_fullname,
                    tb2.*,
                    allergies.allergy_type_value
                    FROM users
                    JOIN ( SELECT * FROM 
                    dblink('user=".Config::get('database.connections.pgsql.username')." password=".Config::get('database.connections.pgsql.password')." dbname=".Config::get('database.connections.pgsql.database')."',
                    'SELECT dpr.rel_id,
                    patients.user_id,
                    patients.pat_id,
                    patients.pat_code,
                    pat_phone_num,
                    pat_profile_img,
                    pat_locality,
                    pat_pincode,
                    pat_title,
                    pat_dob,
                    pat_emergency_contact_number,
                    pv.visit_id,
                    pg.pat_group_name,
                    pv.created_at,
                    dpr.user_channel,
                    patients.pat_group_id
                    FROM patients 
                    JOIN doctor_patient_relation AS dpr on dpr.pat_id = patients.user_id 
                    JOIN patients_visits AS pv on pv.pat_id=dpr.pat_id AND pv.visit_type = ".Config::get('constants.PROFILE_VISIT_TYPE')."
                    LEFT JOIN patient_groups AS pg on pg.pat_group_id = patients.pat_group_id AND pg.is_deleted=".Config::get('constants.IS_DELETED_NO')."
                    where dpr.user_id = ".$user_id." AND dpr.is_deleted=".Config::get('constants.IS_DELETED_NO');

            if(!empty($recentVisits) && $recentVisits == 2){
                // $query .= " AND pv.created_at = '2021-03-05' ";
            }
            $query .="') AS patients( rel_id int,
                    user_id int,
                    pat_id int,
                    pat_code text,
                    pat_phone_num text,
                    pat_profile_img text,
                    pat_locality text,
                    pat_pincode text,
                    pat_title int,
                    pat_dob date,
                    pat_emergency_contact_number text,
                    visit_id int,
                    pat_group_name text,
                    created_at timestamp,
                    user_channel text,
                    pat_group_id int)) AS tb2 ON tb2.user_id= users.user_id 
                    LEFT JOIN (SELECT * FROM 
                    dblink('user=".Config::get('database.connections.pgsql.username')." password=".Config::get('database.connections.pgsql.password')." dbname=".Config::get('database.connections.pgsql.database')."',
                    'SELECT array_agg(allergies.allergy_name) AS allergy_type_value, pat_id AS patient_id FROM allergies JOIN patient_allergies AS pa ON pa.allergy_type=allergies.allergy_id Group by pa.pat_id') AS allergies( allergy_type_value text, patient_id int )) AS allergies ON allergies.patient_id= users.user_id 
                    where ";
        $outerfilter='';
        if(!empty($requestData['filtered'])){
            foreach ($requestData['filtered'] as $key => $value) {
                if(!empty($value['value'])){
                    $outerfilter .= "user_email ilike '%".$value['value']."%' 
                    or CAST(user_mobile AS TEXT) ilike '%".$value['value']."%'
                     or CAST(CONCAT(user_firstname, ' ', user_lastname) AS TEXT) ilike '%".$value['value']."%'
                      or pat_locality ilike '%".$value['value']."%' 
                      or pat_group_name ilike '%".$value['value']."%' 
                      or CAST(pat_pincode AS TEXT) ilike '%".$value['value']."%' 
                      or CAST(pat_code AS TEXT) ilike '%".$value['value']."%'";
                }
            }
            if(!empty($outerfilter)){            
                $query .= "(".$outerfilter.") AND ";
            }
        }
        $query .= "users.user_type=".Config::get('constants.USER_TYPE_PATIENT')." AND users.is_deleted=".Config::get('constants.IS_DELETED_NO');

        if(!empty($requestData['sorted'])){
            foreach ($requestData['sorted'] as $sortKey => $sortValue) {
                $orderBy = $sortValue['desc'] ? 'desc' : 'asc';
                // $listQuery->orderBy($sortValue['id'], $orderBy);
                $query .= " order by ".$sortValue['id']." ".$orderBy." ";
            }
        } else {
            if(!empty($recentVisits) && $recentVisits == 2){
                $query .= " order by created_at desc";
            }else{
                $query .= " order by users.user_id desc";
            }
        }
        
        $withoutpagination = DB::connection('masterdb')
                                ->select(DB::raw($query));
        $patientList['total'] = count($withoutpagination);
        $patientList['pages']   = ceil($patientList['total']/$requestData['pageSize']);
        if($requestData['page'] > 0){
            $offset = $requestData['page'] * $requestData['pageSize'];
        }else{
            $offset = 0;
        }
        $query .= " limit ".$requestData['pageSize']." offset ".$offset.";";
        $list  = DB::connection('masterdb')
                    ->select(DB::raw($query));
        $patientList['result'] = [];
        foreach($list as $lt){
            $lt->user_fullname = $lt->user_firstname.' '.$lt->user_lastname;
            $lt->pat_profile_img = (!empty($lt->pat_profile_img) ? $lt->pat_profile_img : '');
            if(!empty($lt->allergy_type_value)){
                $lt->allergy_type_value = str_replace('{', '[', str_replace('}',']', $lt->allergy_type_value));
                $lt->allergy_type_value = json_decode($lt->allergy_type_value);
                if(!empty($lt->allergy_type_value)){
                    $lt->allergy_type_value = implode(', ', array_unique($lt->allergy_type_value));
                }
            }
            $lt->rel_id = $this->securityLibObj->encrypt($lt->rel_id);
            $lt->pat_id = $this->securityLibObj->encrypt($lt->pat_id);
            $lt->user_id = $this->securityLibObj->encrypt($lt->user_id);
            $lt->visit_id = $this->securityLibObj->encrypt($lt->visit_id);
            $lt->pat_profile_img = !empty($lt->pat_profile_img) ? $this->securityLibObj->encrypt($lt->pat_profile_img) : 'default';
            $lt->pat_profile_img_name = !empty($lt->pat_profile_img) ? $this->securityLibObj->decrypt($lt->pat_profile_img) : 'default';
            $patientList['result'][] = $lt;
        }
        return $patientList;
    }

    /**
     * @DateOfCreation        20 June 2018
     * @ShortDescription      This function is responsible for patient list query from user and patient tables
     * @param                 Array $data This contains full Patient user input data
     * @return                Array of patients
     */
    public function patientListQuery($userId, $recentlyVisited = 1)
    {
        $query = "SELECT
                    users.user_firstname,
                    users.user_lastname,
                    users.user_id,
                    users.user_gender,
                    users.user_mobile,
                    users.user_email,
                    users.user_country_code,
                    patients.pat_phone_num,
                    patients.pat_id,
                    patients.pat_code,
                    patients.pat_profile_img,
                    patients.pat_locality,
                    patients.pat_pincode,
                    patients.pat_title,
                    patients.pat_dob,
                    patients.pat_emergency_contact_number,
                    pv.visit_id,
                    pg.pat_group_name
                FROM patients
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                'SELECT user_id,
                user_firstname,
                user_lastname,
                user_gender,
                user_email,
                user_mobile,
                user_country_code FROM users WHERE users.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                AND users.user_type=".Config::get('constants.USER_TYPE_PATIENT')."') AS users (user_id int,
                user_firstname text,
                user_lastname text,
                user_gender int,
                user_email text,
                user_mobile text,
                user_country_code text
                )) AS users ON users.user_id = patients.user_id
                JOIN doctor_patient_relation AS dpr on dpr.pat_id = patients.user_id
                JOIN patients_visits AS pv on pv.pat_id = patients.user_id
                LEFT JOIN patient_groups AS pg ON pg.pat_group_id = patients.pat_group_id AND pg.is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                WHERE patients.is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                AND dpr.user_id = ".$userId."
                AND dpr.is_deleted = ".Config::get('constants.IS_DELETED_NO');

        if($recentlyVisited == 1){
            $query .= " AND pv.visit_type = ".Config::get('constants.PROFILE_VISIT_TYPE');
        }
        return DB::select(DB::raw($query));
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get patient's visit id
     * @param                 Array $data This contains full Patient user input data
     * @return                String {patient visit id}
     */
    public function getPatientVisitId($requestData)
    {
        $patientUserId = $this->securityLibObj->decrypt($requestData['patientUserId']);
        $userId = $requestData['user_id'];
        $visitIdQuery = $this->checkPatientVisitId($patientUserId,$userId);

        if( !empty($visitIdQuery) )
        {
            $visitId = $visitIdQuery->visit_id;
        } else {

            // Insert New Visit
            $inserData = [
                            'user_id'       => $userId,
                            'pat_id'        => $patientUserId,
                            'visit_type'    => Config::get('constants.PROFILE_VISIT_TYPE'),
                            'visit_number'  => Config::get('constants.INITIAL_VISIT_NUMBER')
                        ];
            $newVisit = $this->dbInsert('patients_visits', $inserData);
            if($newVisit){
                $visitId = DB::getPdo()->lastInsertId();
            } else {
                $visitId = 0;
            }
        }
        return $this->securityLibObj->encrypt($visitId);
    }

    /**
     * @DateOfCreation        29 June 2018
     * @ShortDescription      This function is responsible for creating new Patient in DB
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function createPatientUser($tablename,$insertData, $db='pgsql')
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // Prepare insert query
        $response = $this->dbInsert($tablename, $insertData, $db);
        if($response){
            $id = DB::connection($db)->getPdo()->lastInsertId();
            return $id;
        }else{
            return $response;
        }
    }

    /**
     * @DateOfCreation        2 aug 2018
     * @ShortDescription      This function is responsible for creating doctor patient in visit
                              on new Patient in DB
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function createPatientDoctorVisit($tablename,$insertData)
    {
        $queryResult = $this->dbInsert($tablename, $insertData);
        if($queryResult){
            return $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
        }
        return false;
    }


    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get patient's visit id
     * @param                 Array $data This contains full Patient user input data
     * @return                String {patient visit id}
     */
    public function getPatientFollowUpVisitId($requestData)
    {
        //Init StaticDataConfig model object
        $this->bookingsObj = new Bookings();

        $patientUserId = $this->securityLibObj->decrypt($requestData['patientUserId']);
        $patientBookingId = !empty($requestData['patientBookingId']) ? $this->securityLibObj->decrypt($requestData['patientBookingId']) : '';
        $userId = $requestData['user_id'];

        $checkVisit = $this->checkInProgressVisitExist($patientUserId, $userId);
        if(!empty($checkVisit)){
            $resourceType   = $requestData['resource_type'];
            $ipAddress      = $requestData['ip_address'];
            $visitData = [
                'status'                          => Config::get('constants.VISIT_COMPLETED'),
                'ip_address'                      => $ipAddress,
                'resource_type'                   => $resourceType,
                'pat_id'                          => $patientUserId,
                'visit_id'                        => $checkVisit->visit_id,
            ];
            $whereCheck = ['pat_id' => $patientUserId, 'visit_id' => $checkVisit->visit_id, 'is_deleted' => Config::get('constants.IS_DELETED_NO')];
            $response = $this->dbUpdate($this->tablePatientsVisits, $visitData, $whereCheck);
        }

        $visitIdQuery = $this->checkPatientVisitId($patientUserId, $userId);
        if( !empty($visitIdQuery) )
        {
            $visitId = $visitIdQuery->visit_id;
            $insertData = ['user_id'         => $userId,
                          'pat_id'          => $patientUserId,
                          'visit_type'      => Config::get('constants.FOLLOW_VISIT_TYPE'),
                          'visit_number'    => $visitIdQuery->visit_number+1,
                          'resource_type'   => $requestData['resource_type'],
                          'ip_address'       => $requestData['ip_address']
                        ];
            $visitType = Config::get('constants.FOLLOW_VISIT_TYPE');
        } else {
            // Insert New Visit
            $insertData = ['user_id'      => $requestData['user_id'],
                          'pat_id'       => $patientUserId,
                          'visit_type'   => Config::get('constants.INITIAL_VISIT_TYPE'),
                          'visit_number' => Config::get('constants.INITIAL_VISIT_NUMBER'),
                          'resource_type'   => $requestData['resource_type'],
                          'ip_address'       => $requestData['ip_address']
                        ];
            $visitType = Config::get('constants.INITIAL_VISIT_TYPE');
        }
        if(array_key_exists('visit_date', $requestData) && !empty($requestData['visit_date'])){
            $insertData['visit_date'] = $requestData['visit_date'];
        }

        try{
            DB::beginTransaction();
            $newVisit = $this->dbInsert('patients_visits', $insertData);
            $bookingId = 0;
            $visitId = 0;
            if($newVisit){
                $visitId = DB::getPdo()->lastInsertId();
                if(empty($patientBookingId)){
                    $doctorClinic = Clinics::select("clinic_id")
                                            ->where([
                                                "user_id" => $requestData['user_id'],
                                                "is_deleted" => Config::get('constants.IS_DELETED_NO')
                                            ])
                                            ->first();
                    if(!empty($doctorClinic)){
                        $booking['clinic_id'] = $doctorClinic->clinic_id;
                        $day_of_week = date('N', strtotime('Today'));
                        $timeSlots = Timing::select("timing_id")
                                            ->where([
                                                "user_id" => $requestData['user_id'],
                                                "is_deleted" => Config::get('constants.IS_DELETED_NO'),
                                                "clinic_id" => $booking['clinic_id'],
                                                "week_day" => $day_of_week
                                            ])
                                            ->first();
                        if(!empty($timeSlots)){
                            $booking['timing_id'] = $timeSlots->timing_id;
                        }else{
                            $timeSlots = Timing::select("timing_id")
                                            ->where([
                                                "user_id" => $requestData['user_id'],
                                                "is_deleted" => Config::get('constants.IS_DELETED_NO'),
                                                "clinic_id" => $doctorClinic->clinic_id
                                            ])
                                            ->first();
                            if(!empty($timeSlots)){
                                $booking['timing_id'] = $timeSlots->timing_id;
                            }else{
                                $booking['timing_id'] = 0;
                            }
                        }

                        $bookingReason = AppointmentCategory::where([
                                                                "cat_type" => 2,
                                                                "is_deleted" => Config::get('constants.IS_DELETED_NO')
                                                            ])
                                                            ->first();
                        if(!empty($bookingReason)){
                            $booking['booking_reason'] = $bookingReason->appointment_cat_id;
                            $bookingReasonArray = [ ["id" => $bookingReason->appointment_cat_id, "reason" => $bookingReason->appointment_cat_name] ];
                        }else{
                            $booking['booking_reason'] = 0;
                        }
                        $booking['booking_date'] = Date("Y-m-d");
                        $booking['booking_time'] = Date("Hi");
                        $booking['user_id'] = $userId;
                        $booking['pat_id'] = $patientUserId;
                        $booking['is_profile_visible'] = Config::get("constants.IS_VISIBLE_YES");
                        $booking['booking_status'] = Config::get("constants.BOOKING_IN_PROGRESS");
                        $booking['resource_type'] = Config::get('constants.RESOURCE_TYPE_WEB');
                        $booking['created_by'] = $userId;
                        $booking['updated_by'] = $userId;
                        $booking['patient_appointment_status'] = Config::get("constants.PATIENT_STATUS_GOING");
                        $this->dbInsert('bookings', $booking);
                        $patientBookingId = DB::getPdo()->lastInsertId();
                    }
                }

                if(!empty($patientBookingId)){
                    if(!empty($bookingReasonArray)){
                        PatientAppointmentReason::create([
                            "appointment_reason" => json_encode($bookingReasonArray),
                            "booking_id" => $patientBookingId,
                            "created_by" => $userId,
                            "updated_by" => $userId
                        ]);
                    }
                    $bookingVisitRelationData = ['visit_id' => $visitId,
                                                 'booking_id' => $patientBookingId
                                                ];
                    $bookingVisitRelation = $this->dbInsert('booking_visit_relation', $bookingVisitRelationData);
                    $bookingInProgress = $this->bookingsObj->updateBookingState($patientBookingId, Config::get('constants.BOOKING_IN_PROGRESS'));
                    $bookingId = $this->securityLibObj->encrypt($patientBookingId);
                }
            }
            DB::commit();
            return ['visit_id'=> $this->securityLibObj->encrypt($visitId),'booking_id'=> $bookingId,'visit_type'=> $visitType, 'is_pending' => false];    
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $ex->getMessage();
            echo $eMessage;die;
        }
        
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get patient's last visit id
     * @param                 $user id, $doctor id
     * @return                array
     */
    public function checkPatientVisitId($patientUserId,$doctorUserId){
        $visitIdQuery = DB::table('patients_visits')
                        ->select(['visit_id', 'visit_number'])
                        ->where([
                                'user_id'    => $doctorUserId,
                                'pat_id'     => $patientUserId,
                                'is_deleted' => Config::get('constants.IS_DELETED_NO')]
                        )
                        ->where('visit_type', '!=', Config::get('constants.PROFILE_VISIT_TYPE'))
                        ->orderBy('visit_id', 'desc')
                        ->first();
        return $visitIdQuery;
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get any in progress visit exist or not
     * @param                 $patientUserId, $doctorUserId
     * @return                array
     */
    private function checkInProgressVisitExist($patientUserId, $doctorUserId){
        $visitIdQuery = DB::table('patients_visits')
                        ->select(['patients_visits.visit_id', 'patients_visits.visit_number', 'booking_visit_relation.booking_id'])
                        ->leftJoin('booking_visit_relation', 'patients_visits.visit_id', '=', 'booking_visit_relation.visit_id')
                        ->where([
                                'patients_visits.user_id'       => $doctorUserId,
                                'patients_visits.pat_id'        => $patientUserId,
                                'patients_visits.is_deleted'    => Config::get('constants.IS_DELETED_NO')]
                        )
                        ->where('patients_visits.visit_type', Config::get('constants.FOLLOW_VISIT_TYPE'))
                        ->where('patients_visits.status', '!=' ,Config::get('constants.PROFILE_VISIT_TYPE'))
                        ->orderBy('patients_visits.visit_id', 'desc')
                        ->first();
        return $visitIdQuery;
    }

    /**
     * @DateOfCreation        2 Aug 2018
     * @ShortDescription      This function is responsible to create relation between doctor ans patient
     * @param                 $tablename - insertion table name
     * @param                 Array $insertData
     * @return                String {doctor patient relatrion id}
     */
    public function createPatientDoctorRelation($tablename, $insertData){
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // Prepare insert query
        $result = DB::table($tablename)
                        ->select(['rel_id'])
                        ->where([
                                'user_id'       => $insertData['user_id'],
                                'pat_id'        => $insertData['pat_id'],
                                'is_deleted'    => Config::get('constants.IS_DELETED_NO')]
                        )
                        ->first();
        if(!empty($result->rel_id)){
            return $result->rel_id;
        }
        $response = $this->dbInsert($tablename, $insertData);
        if($response){
            $relId = DB::getPdo()->lastInsertId();
            return $relId;
        }else{
            return $response;
        }
    }

    protected function generateThumbWithImage($mainImage)
    {
        $imageLibObj = new ImageLib();
        $thumb = [];
        $thumb = array(
            ['thumb_name' => $mainImage,'thumb_path' => Config::get('constants.PATIENTS_PROFILE_MTHUMB_IMG_PATH'),'width' => Config::get('constants.MEDIUM_THUMB_SIZE') , 'height' => Config::get('constants.MEDIUM_THUMB_SIZE')],
            ['thumb_name' => $mainImage,'thumb_path' => Config::get('constants.PATIENTS_PROFILE_STHUMB_IMG_PATH'),'width' => Config::get('constants.SMALL_THUMB_SIZE') , 'height' => Config::get('constants.SMALL_THUMB_SIZE')],
        );
        $thumbGenerate = $imageLibObj->genrateThumbnail(Config::get('constants.PATIENTS_PROFILE_IMG_PATH').$mainImage,$thumb);
        return $thumbGenerate;
    }

    /**
     * Update doctor image with regarding details
     *
     * @param array $data image data and patent id
     * @return array profile image
     */
    public function updateProfileImage($requestData, $loggedInUserId)
    {
        $pat_id = $this->securityLibObj->decrypt($requestData['pat_id']);
        $isExist = DB::table('patients')->select('pat_profile_img')->where('user_id', $pat_id)->first();
        $destination = storage_path('app/public/'.Config::get('constants.PATIENTS_PROFILE_IMG_PATH'));
        $data = $requestData['pat_profile_img'];

        // To convert base64 to image file
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);
        $randomString = Uuid::generate();
        $filename = $randomString.'.png';
        // To convert base64 to image file

        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            $filePath = Config::get('constants.PATIENT_PROFILE_S3_PATH').$filename;

            $upload  = $this->s3LibObj->putObject($data, $filePath, 'public');
            $imageData = array();
            if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                $imageData = array(
                    "pat_profile_img"   => $filename,
                    "created_by"        => $loggedInUserId,
                    "updated_by"        => $loggedInUserId,
                );
                try {
                    DB::beginTransaction();
                    $isUpdated = DB::table('patients')->where('user_id', $pat_id)->update($imageData);

                    if($isUpdated){
                        DB::commit();
                        if(!empty($isExist->pat_profile_img)){
                            $oldFilePath = Config::get('constants.PATIENT_PROFILE_S3_PATH').$isExist->pat_profile_img;
                            if($this->s3LibObj->isFileExist($oldFilePath)){
                                $this->s3LibObj->deleteFile($oldFilePath);
                            }
                        }
                        return url('api/patient-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($filename));
                    }else{
                        if($this->s3LibObj->isFileExist($filePath)){
                            $this->s3LibObj->deleteFile($filePath);
                        }
                    }
                } catch (\Exception $e) {
                    if($this->s3LibObj->isFileExist($filePath)){
                        $this->s3LibObj->deleteFile($filePath);
                    }
                  DB::rollback();
                }
            }
        }else{
            $this->FileLib->createDirectory($destination);
            $data = $this->FileLib->base64ToPng($requestData['pat_profile_img'], $destination, 'png');
            $imageData = array();
            $uploadImage = $data['uploaded_file'];
            if(!empty($data['uploaded_file'])) {
                $imageData = array(
                    "pat_profile_img"   => $data['uploaded_file'],
                    "created_by"        => $loggedInUserId,
                    "updated_by"        => $loggedInUserId,
                );
                try {
                    DB::beginTransaction();
                    $thumbStatus = $this->generateThumbWithImage($data['uploaded_file']);
                    if($thumbStatus[0]['code'] == Config::get('restresponsecode.SUCCESS')){
                        $isUpdated = DB::table('patients')->where('user_id', $pat_id)->update($imageData);
                        if(!empty($isUpdated)){
                            if(!empty($isExist->pat_profile_img) && File::exists($destination.$isExist->pat_profile_img)) {
                                File::delete($destination.$isExist->pat_profile_img);
                            }
                            DB::commit();
                            return url('api/patient-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($data['uploaded_file']));
                          }
                    }else{
                        if(File::exists($destination.$uploadImage)){
                            File::delete($destination.$uploadImage);
                        }
                        return false;
                    }
                } catch (\Exception $e) {
                    if(File::exists($destination.$uploadImage)){
                        File::delete($destination.$uploadImage);
                    }
                  DB::rollback();
                }
            }
        }
    }

    /**
     * @DateOfCreation        3 Sept 2018
     * @ShortDescription      This function is responsible for get Patient Activity History
     * @param                 $patientUserId, $doctorUserId
     * @return                array
     */
    public function getPatientActivityHistory($requestData)
    {
        $getActivityVisits = $this->patientActivitiesModelObj->getActivityRecords($requestData);

        $getSymptoms = $getVitals = $getDiagnosis = $getClinicalNotes = $getPrescribedMedicine = [];
        if(!empty($getActivityVisits))
        {
            $visitIdArr = explode(',', $getActivityVisits->visits);

            // GET SYMPTOMS
            $getSymptoms            = $this->getVisitSymptoms($visitIdArr);
            $getSymptoms            = !empty($getSymptoms) ? $this->utilityLibObj->changeMultidimensionalArrayKey($getSymptoms, 'visit_id') : [];
            if(!empty($getSymptoms)){
                foreach ($getSymptoms as $symptomsVisitKey => $diagnosis) {
                    $getSymptoms[$symptomsVisitKey] = !empty($diagnosis)  ? $this->utilityLibObj->changeMultidimensionalArrayKey($diagnosis, 'created_at') : [];
                }
            }

            // GET VITALS
            $getVitals              = $this->getVisitVitals($visitIdArr);
            $getVitals              = !empty($getVitals) ? $this->utilityLibObj->changeMultidimensionalArrayKey($getVitals, 'visit_id') : [];

            // GET DIAGNOSIS
            $getDiagnosis           = $this->getVisitDiagnosis($visitIdArr);
            $getDiagnosis           = !empty($getDiagnosis) ? $this->utilityLibObj->changeMultidimensionalArrayKey($getDiagnosis, 'visit_id') : [];
            if(!empty($getDiagnosis)){
                foreach ($getDiagnosis as $diagnosisVisitKey => $diagnosis) {
                    $getDiagnosis[$diagnosisVisitKey] = !empty($diagnosis)  ? $this->utilityLibObj->changeMultidimensionalArrayKey($diagnosis, 'created_at') : [];
                }
            }

            // GET CLINICAL NOTES
            $getClinicalNotes       = $this->getVisitClinicalNotes($visitIdArr);
            $getClinicalNotes       = !empty($getClinicalNotes)  ? $this->utilityLibObj->changeArrayKey($getClinicalNotes, 'visit_id') : [];

            // GET PRESCRIBED MEDICINES
            $getPrescribedMedicine  = $this->getVisitPrescribedMedicine($visitIdArr);
            $getPrescribedMedicine  = !empty($getPrescribedMedicine) ? $this->utilityLibObj->changeMultidimensionalArrayKey($getPrescribedMedicine, 'visit_id') : [];
            if(!empty($getPrescribedMedicine)){
                foreach ($getPrescribedMedicine as $medicineVisitKey => $medicines) {
                    $getPrescribedMedicine[$medicineVisitKey] = !empty($medicines)  ? $this->utilityLibObj->changeMultidimensionalArrayKey($medicines, 'created_at') : [];
                }
            }
        }
        $activityHistory = DB::table('patient_activity')
                        ->select(DB::raw("user_id, pat_id, activity_table, visit_id, Date(created_at) as created_at"))
                        ->where([
                                'patient_activity.user_id'    => $requestData['user_id'],
                                'patient_activity.pat_id'     => $requestData['pat_id'],
                                'patient_activity.is_deleted' => Config::get('constants.IS_DELETED_NO')
                            ])
                        ->where('patient_activity.activity_table', '!=', 'patients_visits')
                        ->orderBy('patient_activity', 'desc')
                        ->get()
                        ->map(function($activityRecord) use($getSymptoms, $getVitals, $getDiagnosis, $getClinicalNotes, $getPrescribedMedicine){
                            $activityRecord->visit_id = $this->securityLibObj->encrypt($activityRecord->visit_id);
                            if($activityRecord->activity_table == 'visit_symptoms' && array_key_exists($activityRecord->visit_id, $getSymptoms)){
                                if(array_key_exists($activityRecord->created_at, $getSymptoms[$activityRecord->visit_id])){
                                    $activityRecord->symptoms_data = $getSymptoms[$activityRecord->visit_id][$activityRecord->created_at];
                                }
                            }
                            if($activityRecord->activity_table == 'vitals' && array_key_exists($activityRecord->visit_id, $getVitals)){
                                $activityRecord->vitals_data = $getVitals[$activityRecord->visit_id];
                            }
                            if($activityRecord->activity_table == 'patients_visit_diagnosis' && array_key_exists($activityRecord->visit_id, $getDiagnosis)){
                                if(array_key_exists($activityRecord->created_at, $getDiagnosis[$activityRecord->visit_id])){
                                    $activityRecord->diagnosis_data = $getDiagnosis[$activityRecord->visit_id][$activityRecord->created_at];
                                }
                            }
                            if($activityRecord->activity_table == 'clinical_notes' && array_key_exists($activityRecord->visit_id, $getClinicalNotes)){
                                $activityRecord->clinicalNotes_data = $getClinicalNotes[$activityRecord->visit_id];
                            }
                            if($activityRecord->activity_table == 'patient_medication_history' && array_key_exists($activityRecord->visit_id, $getPrescribedMedicine)){
                                if(array_key_exists($activityRecord->created_at, $getPrescribedMedicine[$activityRecord->visit_id])){
                                    $activityRecord->prescribedMedicine_data = $getPrescribedMedicine[$activityRecord->visit_id][$activityRecord->created_at];
                                }
                            }
                            $activityRecord->created_at = date('d M, Y', strtotime($activityRecord->created_at));
                            return $activityRecord;
                        });
        return $activityHistory;
    }

    private function getVisitSymptoms($visitIdArray=[]){
        $visitIdArray = !empty(array_filter($visitIdArray)) ? $visitIdArray : [0];
        return DB::table('visit_symptoms as vs')
                    ->select(DB::raw("symptoms.symptom_name, vs.since_date, vs.comment, vs.visit_id, Date(vs.created_at) as created_at"))
                    ->join('symptoms',function($join) {
                        $join->on('symptoms.symptom_id', '=', 'vs.symptom_id')
                            ->where('symptoms.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                    })
                    ->where('vs.is_deleted', Config::get('constants.IS_DELETED_NO'))
                    ->whereIn('vs.visit_id', $visitIdArray)
                    ->orderBy('visit_symptom_id', 'desc')
                    ->get()
                    ->map(function($symptomsData){
                        $symptomsData->visit_id = $this->securityLibObj->encrypt($symptomsData->visit_id);
                        return $symptomsData;
                    });
    }
    private function getVisitVitals($visitIdArray=[]){
        $visitIdArray = !empty(array_filter($visitIdArray)) ? $visitIdArray : [0];
        return DB::table('vitals')
                ->select(DB::raw("vitals.visit_id, vitals.fector_id as vitals_factor_id, vitals.fector_value as vitals_factor_value, pe.fector_value as physical_weight, pe.fector_id as physical_fector_id, Date(vitals.created_at) as created_at"))
                ->leftJoin('physical_examinations as pe',function($join) {
                    $join->on('pe.visit_id', '=', "vitals.visit_id")
                        ->where('pe.fector_id', Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'), 'and');
                })
                ->whereIn('vitals.visit_id', $visitIdArray)
                ->where('vitals.is_deleted', Config::get('constants.IS_DELETED_NO'))
                ->get()
                ->map(function($vitalsData){
                    if($vitalsData->vitals_factor_id == Config::get('dataconstants.VISIT_VITALS_PULSE')){
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_pulse');
                    } else if($vitalsData->vitals_factor_id == Config::get('dataconstants.VISIT_VITALS_BP_SYS')){
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_bp_sys');
                    } else if($vitalsData->vitals_factor_id == Config::get('dataconstants.VISIT_VITALS_BP_DIA')){
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_bp_dia');
                    } else if($vitalsData->vitals_factor_id == Config::get('dataconstants.VISIT_VITALS_SPO2')){
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_spo2_lable');
                    } else if($vitalsData->vitals_factor_id == Config::get('dataconstants.VISIT_VITALS_RESPIRATORY_RATE')){
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_respiratory_rate');
                    } else{
                        $vitalsData->title = trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight');
                    }
                    $vitalsData->vitals_factor_id   = $this->securityLibObj->encrypt($vitalsData->vitals_factor_id);
                    $vitalsData->physical_fector_id = $this->securityLibObj->encrypt($vitalsData->physical_fector_id);
                    $vitalsData->visit_id = $this->securityLibObj->encrypt($vitalsData->visit_id);
                    return $vitalsData;
                });
    }

    private function getVisitDiagnosis($visitIdArray=[]){
        $visitIdArray = !empty(array_filter($visitIdArray)) ? $visitIdArray : [0];
        return DB::table('patients_visit_diagnosis as pvd')
                ->select(DB::raw("pvd.date_of_diagnosis, diseases.disease_name, pvd.visit_id, Date(pvd.created_at) as created_at"))
                ->join('diseases',function($join) {
                    $join->on('diseases.disease_id', '=', 'pvd.disease_id')
                        ->where('diseases.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                })
                ->where('pvd.is_deleted', Config::get('constants.IS_DELETED_NO'))
                ->whereIn('pvd.visit_id', $visitIdArray)
                ->orderBy('visit_diagnosis_id', 'desc')
                ->get()
                ->map(function($diagnosisData){
                    $diagnosisData->visit_id = $this->securityLibObj->encrypt($diagnosisData->visit_id);
                    return $diagnosisData;
                });
    }

    private function getVisitClinicalNotes($visitIdArray=[]){
        $visitIdArray = !empty(array_filter($visitIdArray)) ? $visitIdArray : [0];
        return DB::table('clinical_notes')
                ->select(DB::raw("clinical_notes, Date(created_at) as created_at, visit_id"))
                ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
                ->whereIn('visit_id', $visitIdArray)
                ->get()
                ->map(function($clinicalNote){
                    $clinicalNote->clinical_notes   = !empty($clinicalNote->clinical_notes) ? json_decode($clinicalNote->clinical_notes) : $clinicalNote->clinical_notes;
                    $clinicalNote->visit_id         = $this->securityLibObj->encrypt($clinicalNote->visit_id);
                    return $clinicalNote;
                });
    }

    private function getVisitPrescribedMedicine($visitIdArray=[]){
        $visitIdArray = !empty(array_filter($visitIdArray)) ? $visitIdArray : [0];
        $result = DB::table( 'patient_medication_history' )
                ->select(
                        DB::raw("
                        medicines.medicine_name,
                        medicines.medicine_dose as drug_dose,
                        patient_medication_history.pmh_id,
                        patient_medication_history.pat_id,
                        patient_medication_history.visit_id,
                        patient_medication_history.medicine_id,
                        patient_medication_history.medicine_start_date,
                        patient_medication_history.medicine_end_date,
                        patient_medication_history.medicine_dose,
                        patient_medication_history.medicine_dose2,
                        patient_medication_history.medicine_dose3,
                        patient_medication_history.medicine_dose_unit,
                        patient_medication_history.medicine_duration,
                        patient_medication_history.medicine_duration_unit,
                        patient_medication_history.medicine_frequency,
                        patient_medication_history.medicine_meal_opt,
                        patient_medication_history.medicine_instructions,
                        patient_medication_history.is_discontinued,
                        patient_medication_history.medicine_route,
                        Date(patient_medication_history.created_at) as created_at,
                        drug_type.drug_type_name,
                        drug_dose_unit.drug_dose_unit_name
                        ")
                    )
                ->leftJoin('medicines', function($join) {
                        $join->on('patient_medication_history.medicine_id', '=', 'medicines.medicine_id');
                    })
                ->leftJoin('drug_type', function($join) {
                        $join->on('medicines.drug_type_id', '=', 'drug_type.drug_type_id')
                            ->where('drug_type.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                    })
                ->leftJoin('drug_dose_unit', function($join) {
                        $join->on('medicines.drug_dose_unit_id', '=', 'drug_dose_unit.drug_dose_unit_id')
                            ->where('drug_dose_unit.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                    })
                ->where( 'patient_medication_history.is_deleted',  Config::get('constants.IS_DELETED_NO') )
                ->whereIn( 'patient_medication_history.visit_id',  $visitIdArray)
                ->orderBy('pmh_id', 'desc')
                ->get()
                ->map(function($patientMedication){
                    $patientMedication->pmh_id                          = $this->securityLibObj->encrypt($patientMedication->pmh_id);
                    $patientMedication->pat_id                          = $this->securityLibObj->encrypt($patientMedication->pat_id);
                    $patientMedication->visit_id                        = $this->securityLibObj->encrypt($patientMedication->visit_id);
                    $patientMedication->medicine_id                     = $this->securityLibObj->encrypt($patientMedication->medicine_id);
                    $patientMedication->medicine_start_date             = $patientMedication->medicine_start_date;
                    $patientMedication->medicine_start_date_formatted   = !empty($patientMedication->medicine_start_date) ? date('d/m/Y', strtotime($patientMedication->medicine_start_date)) : $patientMedication->medicine_start_date;
                    $patientMedication->medicine_end_date               = $patientMedication->medicine_end_date;
                    $patientMedication->medicine_end_date_formatted     = !empty($patientMedication->medicine_end_date) ? date('d/m/Y', strtotime($patientMedication->medicine_end_date)) : $patientMedication->medicine_end_date;
                    $patientMedication->medicine_frequency              = $patientMedication->medicine_frequency;
                    $patientMedication->medicine_frequencyVal           = $this->staticDataConfigObj->getMedicationsFector('medicine_frequency', $patientMedication->medicine_frequency);
                    $patientMedication->medicine_duration_unitVal       = $this->staticDataConfigObj->getMedicationsFector('medicine_duration_unit', $patientMedication->medicine_duration_unit);
                    $patientMedication->medicine_duration_unit          = $patientMedication->medicine_duration_unit;
                    $patientMedication->medicine_dose_unitVal           = $patientMedication->drug_dose_unit_name;
                    $patientMedication->medicine_dose_unit              = $patientMedication->medicine_dose_unit;
                    $patientMedication->medicine_meal_optVal            = $this->staticDataConfigObj->getMedicationsFector('medicine_meal_opt', $patientMedication->medicine_meal_opt);
                    $patientMedication->medicine_meal_opt               = (string) $patientMedication->medicine_meal_opt;
                    $patientMedication->is_end_date_past                = (!empty($patientMedication->medicine_end_date) && (strtotime($patientMedication->medicine_end_date) < strtotime(date('Y-m-d')))) ? 1 : 0 ;
                    $patientMedication->medicine_instructions           = !empty($patientMedication->medicine_instructions) ? $patientMedication->medicine_instructions : "" ;
                    return $patientMedication;
                });
                return $result;
    }

    // FUNCTION WILL BE USED FOR PATIENT LIST FILTERING FROM REPORTS COMPONENT
    public function getPatientListForReportFilter($requestData){

        $query = "SELECT
                    count(*) OVER() AS total,
                    users.created_at,
                    users.user_firstname,
                    users.user_lastname,
                    users.user_id,
                    users.user_gender,
                    users.user_mobile,
                    users.user_email,
                    users.user_country_code,
                    patients.pat_phone_num,
                    patients.pat_id,
                    patients.pat_code,
                    patients.pat_profile_img,
                    patients.pat_locality,
                    patients.pat_pincode,
                    pv.visit_id,
                    patients.pat_title,
                    patients.pat_dob,
                    pg.pat_group_name,
                    patients.pat_emergency_contact_number,
                    patients.pat_age
                    FROM patients
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                    'SELECT user_id,user_firstname,user_lastname,user_mobile,user_email,user_country_code,user_gender,created_at from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')." AND user_type =".Config::get('constants.USER_TYPE_PATIENT')."') AS users(
                    user_id int,
                    user_firstname text,
                    user_lastname text,
                    user_mobile text,
                    user_email text,
                    user_country_code text,
                    user_gender int,
                    created_at timestamp
                    )) AS users ON users.user_id= patients.user_id
                    JOIN doctor_patient_relation as dpr on dpr.pat_id = users.user_id 
                    JOIN patients_visits AS pv on pv.pat_id = users.user_id
                    LEFT JOIN patient_groups AS pg on pg.pat_group_id = patients.pat_group_id AND pg.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                    LEFT JOIN visit_symptoms AS vs on vs.pat_id = users.user_id AND vs.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                    LEFT JOIN patients_visit_diagnosis AS pvd on pvd.pat_id = users.user_id AND pvd.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                    WHERE patients.is_deleted =".Config::get('constants.IS_DELETED_NO')." 
                    AND dpr.user_id = ".$requestData['user_id']." 
                    AND dpr.is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                    AND pv.visit_type = ".Config::get('constants.PROFILE_VISIT_TYPE');

        if(!empty($requestData['filtered'])){
            $query .= " AND (";
            foreach ($requestData['filtered'] as $key => $value) {

                $whereGender = $value['value'];
                if(stripos($value['value'], 'male') !== false)
                {
                    $whereGender = 1;
                } else if( stripos($value['value'], 'female' !== false) ) {
                    $whereGender = 2;
                } else if( stripos($value['value'], 'other') !== false ){
                    $whereGender = 3;
                }

                if(!empty($value['value'])){
                    $query .= " user_email ilike '%".$value['value']."%' 
                                OR pat_locality ilike '%".$value['value']."%'
                                OR pg.pat_group_name ilike '%".$value['value']."%'
                                OR CAST(user_mobile AS TEXT) ilike '%".$value['value']."%'
                                OR CAST(pat_pincode AS TEXT) ilike '%".$value['value']."%'
                                OR CAST(pat_code AS TEXT) ilike '%".$value['value']."%'
                                OR CAST(user_gender AS TEXT) ilike '%".$whereGender."%'
                                OR CAST(pat_age AS TEXT) ilike '%".$value['value']."%'
                                OR CAST(CONCAT(user_firstname, ' ', user_lastname) AS TEXT) ilike '%".$value['value']."%'";
                }
            }
            $query .= ") ";
        }

        if(isset($requestData['state_id']) && !empty($requestData['state_id'])){
            $query .= " AND patients.state_id=".$this->securityLibObj->decrypt($requestData['state_id']);
        }
        if(isset($requestData['city_id']) && !empty($requestData['city_id'])){
            $query .= " AND patients.city_id=".$this->securityLibObj->decrypt($requestData['city_id']);
        }
        if(isset($requestData['group_id']) && !empty($requestData['group_id'])){
            $query .= " AND patients.pat_group_id=".$this->securityLibObj->decrypt($requestData['group_id']);
        }
        if(isset($requestData['doc_ref_id']) && !empty($requestData['doc_ref_id'])){
            $query .= " AND patients.doc_ref_id=".$this->securityLibObj->decrypt($requestData['doc_ref_id']);
        }
        if(isset($requestData['symptoms_id']) && !empty($requestData['symptoms_id'])){
            $query .= " AND vs.symptom_id=".$this->securityLibObj->decrypt($requestData['symptoms_id']);
        }
        if(isset($requestData['diagnosis_id']) && !empty($requestData['diagnosis_id'])){
            $query .= " AND pvd.visit_diagnosis_id=".$this->securityLibObj->decrypt($requestData['diagnosis_id']);
        }
        if(isset($requestData['from_age']) && !empty($requestData['from_age'])){
            $query .= " AND patients.pat_age >='".$requestData['from_age']."'";
        }
        if(isset($requestData['to_age']) && !empty($requestData['to_age'])){
            $query .= " AND patients.pat_age <='".$requestData['to_age']."'";
        }
        if(!empty($requestData['from_age']) && !empty($requestData['to_age'])){
            $query .= " AND CAST(patients.pat_age AS INT) BETWEEN ".$requestData['from_age']." AND ".$requestData['to_age'];
        }

        $fromDate = NULL;
        $toDate   = NULL;
        $today    = Carbon::now();
        if(isset($requestData['from_date']) && !empty($requestData['from_date'])){
            $fromDate = Carbon::parse($requestData['from_date'])->format('Y-m-d 00:00:00'); // date('Y-m-d 00:00:00', strtotime());
        }

        if(isset($requestData['to_date']) && !empty($requestData['to_date'])){
            $toDate = Carbon::parse($requestData['to_date'])->format('Y-m-d 23:59:59');
        }

        if(isset($requestData['from_date']) && !empty($requestData['from_date']) && empty($requestData['to_date']))
        {
            $toDate = Carbon::parse($today)->format('Y-m-d 23:59:59');
        }

        if(isset($requestData['to_date']) && !empty($requestData['to_date']) && empty($requestData['from_date']))
        {
            $fromDate = Carbon::parse($toDate)->subMonth()->format('Y-m-d 00:00:00');
        }

        if(!empty($fromDate) && !empty($toDate)){
            $query .= " AND users.created_at BETWEEN '".$fromDate."' AND '".$toDate."'";
        }

        // $query .= " GROUP BY users.created_at, patients.pat_id,users.user_id,pv.visit_id,pg.pat_group_name";
        if(!empty($requestData['sorted'])){
            foreach ($requestData['sorted'] as $sortKey => $sortValue) {
                $orderBy = $sortValue['desc'] ? 'desc' : 'asc';

                if($sortValue['id'] == 'created_at'){
                    $query .= " ORDER BY users.created_at ".$orderBy;
                } else {
                    $query .= " ORDER BY ".$sortValue['id']." ".$orderBy;
                }
            }
        } else {
            $query .= " ORDER BY users.user_id DESC";
        }
        // echo $query;die;
        $withoutpagination = DB::select(DB::raw($query));
        $patientList['count'] = count($withoutpagination);
        $patientList['pages']   = ceil($patientList['count']/$requestData['pageSize']);
        $patientList['pageSize']= $requestData['pageSize'];
        if($requestData['page'] > 0){
            $offset = $requestData['page'] * $requestData['pageSize'];
        }else{
            $offset = 0;
        }
        $query .= " limit ".$requestData['pageSize']." offset ".$offset.";";
        $list  = DB::select(DB::raw($query));
        $patientList['result'] = [];
        foreach($list as $patientListData){
            $patientListData->pat_profile_img   = (!empty($patientListData->pat_profile_img) ? $patientListData->pat_profile_img : '');
            $patientListData->pat_id            = $this->securityLibObj->encrypt($patientListData->pat_id);
            $patientListData->user_id           = $this->securityLibObj->encrypt($patientListData->user_id);
            $patientListData->visit_id          = $this->securityLibObj->encrypt($patientListData->visit_id);
            $patientListData->pat_profile_img   = !empty($patientListData->pat_profile_img) ? $this->securityLibObj->encrypt($patientListData->pat_profile_img) : 'default';
            $patientListData->user_firstname    = $patientListData->user_firstname.' '.$patientListData->user_lastname;
            $patientListData->pat_age           = ($patientListData->pat_age == Config::get('constants.AGE_BELOW_ONE')) ? Config::get('constants.AGE_BELOW_ONE_TEXT') : $patientListData->pat_age;
            $patientList['result'][] = $patientListData;
        }
        return $patientList;
    }

    /**
    * @DateOfCreation        14 Nov 2018
    * @ShortDescription      This function is responsible to get the Last inserted Patient Code for the doctor
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getPatientsRegistrationNumberByDoctorId($doctorId = NULL){
        if(!empty($doctorId)){
            $selectData = [DB::raw('MAX(pat_code) AS pat_code')];
            $whereData = array(
                        'patients.is_deleted'               => Config::get('constants.IS_DELETED_NO'),
                        'created_by'   => $doctorId,
                    );

            $listQuery = DB::table('patients')
                            ->select($selectData)
                            ->where($whereData)
                            ->first();
            return $listQuery;
        }
    }

    public function updateAgeFromDateOfBirth(){
        $output = [];
        $patientsWithDateOfBirth = DB::table('patients as p')
                            ->select('p.pat_id', 'p.pat_code', 'p.pat_dob', 'p.pat_age as age_before', 'p.pat_age as age_after', 'u.user_firstname', 'u.user_lastname', 'p.is_deleted')
                            ->join('users as u', 'u.user_id', '=', 'p.user_id')
                            ->where('p.pat_dob', '!=', NULL)
                            // ->orderby('p.pat_dob', 'asc')
                            ->get()->toArray();
        if(!empty($patientsWithDateOfBirth)){
            $requestData = [];
            $whereData = [];
            foreach($patientsWithDateOfBirth as $key => $patient){
                $whereData = ['pat_id' => $patient->pat_id];
                $patientAge = $this->utilityLibObj->calculateAge($patient->pat_dob);
                $patientAge = ($patientAge >= 1) ? $patientAge : Config::get('constants.AGE_BELOW_ONE');
                if(!empty($patient->pat_dob)){
                    $requestData = ['pat_age' => $patientAge];
                }
                $updated = DB::table('patients')
                                    ->where($whereData)
                                    ->update($requestData);
            }
        }
        return $patientsWithDateOfBirth;

    }

    public function getPatientsWithDateOfBirth(){

        $patientsWithDateOfBirthAfterAgeUpdate = DB::table('patients as p')
                            ->select('p.pat_id', 'p.pat_code', 'p.pat_dob', 'p.pat_age as age_after', 'u.user_firstname', 'u.user_lastname', 'p.is_deleted')
                            ->join('users as u', 'u.user_id', '=', 'p.user_id')
                            ->where('p.pat_dob', '!=', NULL)
                            ->get()->toArray();
        return $patientsWithDateOfBirthAfterAgeUpdate;
    }
}