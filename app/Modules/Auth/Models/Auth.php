<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Libraries\SecurityLib;
use Config;
use App\Modules\Doctors\Models\Doctors as Doctors;
use App\Libraries\UtilityLib;
use App\Modules\DoctorProfile\Models\Timing;

use App\Modules\Auth\Models\DefaultSpcializationComponentList;
use App\Modules\AppointmentCategory\Models\AppointmentCategory as AppointmentCategory;
use App\Modules\PatientGroups\Models\PatientGroups as PatientGroups;
use App\Modules\CheckupType\Models\CheckupType as CheckupType;
use App\Modules\PaymentMode\Models\PaymentMode as PaymentMode;
use App\Modules\DoctorProfile\Models\DoctorSpecialisations;
use App\Modules\ConsentForms\Models\ConsentForms as ConsentForms;
use App\Modules\Visits\Models\Visits;

/**
 * Auth
 *
 * @package                Safe Health
 * @subpackage             Auth
 * @category               Model
 * @DateOfCreation         09 May 2018
 * @ShortDescription       This is model which need to perform the options related to
                           users table

 */
class Auth extends Authenticatable {

    use Notifiable,HasApiTokens,Encryptable;
    // use Notifiable,Encryptable;

    protected $connection = 'masterdb';
    protected $tokenTable        = 'access_tokens';
    protected $clientSecretTable = 'user_secret';
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init utility library object
        $this->utilityLibObj = new UtilityLib();

        $this->doctorObj = new Doctors();

        // Init Timing model object
        $this->timingObj = new Timing();

        // Init Visits model object
        $this->visitsModelObj = new Visits();

        // Init AppointmentCategory model object
        $this->appointmentCategoryModelObj = new AppointmentCategory();

        // Init PatientGroups model object
        $this->patientsGroupsModelObj = new PatientGroups();

        // Init CheckupType Model Object
        $this->checkupTypeObj = new CheckupType();

        // Init PaymentMode Model Object
        $this->paymentModeObj = new PaymentMode();

        // Init ConsentForms model object
        $this->consentFormsModelObj = new ConsentForms();
    }
    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_firstname', 'user_lastname', 'user_email', 'user_password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    // @var string $table
    // This protected member contains table name
    protected $table = 'users';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'user_id';

    // This public member over ride the password field
    public function getAuthPassword() {
        return $this->user_password;
    }

    public function patients(){
        return $this->hasOne('App\Modules\Patients\Models\Patients', 'user_id');
    }

    /**
    * @DateOfCreation        10 Apr 2018
    * @ShortDescription      This function is responsible for creating new user in DB
    * @param                 Array $data This contains full user input data
    * @return                True/False
    */
    public function createUser($data)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;
        // @var Array $inserData
        // This Array contains insert data for users
        $insertData = array(
            'user_firstname'        => $data['user_firstname'],
            'user_lastname'         => $data['user_lastname'],
            'user_mobile'           => $data['user_mobile'],
            'user_country_code'     => $data['user_country_code'],
            'user_gender'           => $data['user_gender'],
            'user_status'           => $data['user_status'],
            'user_password'         => Hash::make($data['user_password']),
            'user_type'             => $data['user_type'],
            'resource_type'         => $data['resource_type'],
            'ip_address'            => $data['ip_address']
        );
        if(array_key_exists('user_email', $data))
            $inserData['user_email'] = $data['user_email'];
        if(array_key_exists('user_adhaar_number', $data))
            $inserData['user_adhaar_number'] = $data['user_adhaar_number'];
        // Prepair insert query
        $response = $this->dbInsert($this->table, $insertData);
        if($response){
            $id = DB::getPdo()->lastInsertId();
            return $id;
        }else{
            return $response;
        }
    }


    /**
    * @DateOfCreation        18 May 2018
    * @ShortDescription      Get the Aceess token on behalf of user id
    * @return                Array
    */
    public function authAccessToken(){
         return $this->hasMany('App\Modules\Auth\Models\OauthAccessToken','user_id','user_id');
    }

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible for get the user info for user_id
    * @param                 Integer $user_id Currect user ID
    * @return                Array userinfo or False
    */
    public function getUserInfo($authUser)
    {
        $user_id = $authUser->user_id;
        if($authUser->user_type == Config::get('constants.USER_TYPE_PATIENT')){
            $user = DB::connection('masterdb')
                        ->table('users')
                        ->select(
                            'users.user_id',
                            'users.user_firstname',
                            'users.user_lastname',
                            'users.user_email',
                            'users.user_mobile',
                            'users.user_type',
                            'users.user_gender'
                        )
                        ->where('users.user_id',$authUser->user_id)
                        ->first();
            if($user){
                $userDetails = DB::table("patients")
                                    ->select(
                                        'pv.visit_id',
                                        'patients.pat_profile_img',
                                        'patients.pat_id',
                                        'patients.pat_dob',
                                        'patients.pat_address_line1',
                                        'patients.pat_address_line2',
                                        'patients.pat_blood_group'
                                    )
                                    ->join("patients_visits AS pv", "pv.pat_id", "=", "patients.user_id")
                                    ->where([
                                        "pv.pat_id" => $authUser->user_id,
                                        "pv.visit_type" => Config::get('constants.PROFILE_VISIT_TYPE')
                                    ])
                                    ->first();

                $user->user_id = $this->securityLibObj->encrypt($user->user_id);
                $user->visit_id = $this->securityLibObj->encrypt($userDetails->visit_id);
                $user->pat_profile_img = !empty($userDetails->pat_profile_img) ? url('api/patient-profile-thumb-image/small/'.$this->securityLibObj->encrypt($userDetails->pat_profile_img)) : '';
                $user->pat_id = $this->securityLibObj->encrypt($userDetails->pat_id);
                $user->pat_dob = $userDetails->pat_dob;
                $user->pat_address_line1 = $userDetails->pat_address_line1;
                $user->pat_address_line2 = $userDetails->pat_address_line2;
                $user->pat_blood_group = $userDetails->pat_blood_group;
                return $user;
            }else{
                return false;
            }
        }else if($authUser->user_type == Config::get('constants.USER_TYPE_DOCTOR')){
            $joinTableName = "doctors";
            $prefix = "doc_";
            $user = DB::connection('masterdb')
                        ->table('users')
                        ->select(
                            'users.user_id',
                            'users.user_firstname',
                            'users.user_lastname',
                            'users.user_email',
                            'users.user_mobile',
                            'users.user_type'
                        )
                        ->where('users.user_id',$authUser->user_id)
                        ->first();
            if($user){
                $doctor = DB::connection('pgsql')
                            ->table($joinTableName)
                            ->where([
                                'user_id' => $authUser->user_id
                            ])
                            ->first();
                $user->speciality = $this->doctorObj->getDoctorSpecialisation($user->user_id);
                $user->user_id = $this->securityLibObj->encrypt($user->user_id);

                $user->doc_profile_img = !empty($doctor->doc_profile_img) ? url('api/doctor-profile-thumb-image/small/'.$this->securityLibObj->encrypt($doctor->doc_profile_img)) : '';
                return $user;
            }else{
                return false;
            }
        }else if(in_array($authUser->user_type, Config::get('constants.USER_TYPE_STAFF'))){
            $joinTableName = "doctors_staff";
            $prefix = "doc_staff_";
            $user = DB::table('users')
                        ->select(
                            'users.user_id',
                            'users.user_firstname',
                            'users.user_lastname',
                            'users.user_email',
                            'users.user_mobile',
                            'users.user_type'
                        )
                        ->where('users.user_id',$authUser->user_id)
                        ->first();
                if($user){
                    $staff = DB::connection('pgsql')
                                ->table($joinTableName)
                                ->where([
                                    'user_id' => $authUser->user_id
                                ])
                                ->first();
                    $profile_img = $prefix.'profile_image';
                    $user->user_id = $this->securityLibObj->encrypt($user->user_id);
                    if(!empty($staff)){
                        $user->doc_user_id = $this->securityLibObj->encrypt($staff->doc_user_id);
                    }
                    $user->profile_img = !empty($staff->doc_staff_profile_image) ? url('api/profile-image/'.$this->securityLibObj->encrypt($staff->doc_staff_profile_image)) : '';

                    return $user;
                }else{
                    return false;
                }
        }else if($authUser->user_type == Config::get('constants.USER_TYPE_LAB_MANAGER')){
            $joinTableName = "laboratories";
            $user = DB::table('users')
                ->join($joinTableName,$joinTableName.'.user_id', '=', 'users.user_id')
                ->select(
                        'users.user_id',
                        'users.user_firstname',
                        'users.user_lastname',
                        'users.user_email',
                        'users.user_mobile',
                        'users.user_type'
                    )
                    ->where('users.user_id',$authUser->user_id)
                    ->first();
                if($user){
                    $user->user_id = $this->securityLibObj->encrypt($user->user_id);
                    $lab = DB::connection('pgsql')
                                ->table($joinTableName)
                                ->where([
                                    'user_id' => $authUser->user_id
                                ])
                                ->first();
                    if(!empty($lab)){
                        $user->lab_id = $this->securityLibObj->encrypt($lab->lab_id);
                    }
                    $user->lab_featured_image = !empty($lab->lab_featured_image) ? url('api/lab-featured-image/'.$this->securityLibObj->encrypt($lab->lab_featured_image)) : '';

                    return $user;
                }else{
                    return false;
                }
        }
    }


    /**
    * @DateOfCreation        19 July 2018
    * @ShortDescription      Test data inserting function
    * @return                Array
    */
    public function createSpecaility($insertData){
        $response = $this->dbInsert("doctors_specialisations", $insertData);
        return true;
    }

    /**
    * @DateOfCreation        19 July 2018
    * @ShortDescription      Test data inserting function
    * @return                Array
    */
    public function createDoctor($insertData)
    {
        $response = $this->dbInsert("doctors", $insertData);
        return true;
    }

    /**
    * @DateOfCreation        19 July 2018
    * @ShortDescription      Test data inserting function
    * @return                Array
    */
    public function createAward($insertData)
    {
        $response = $this->dbInsert("doctors_awards", $insertData);
        return true;
    }

    /**
     * @DateOfCreation        12 June 2018
     * @ShortDescription      update verifiction email/mobile according to userVerObjType
     * @param                 Integer $userId email/mobile verification user ID
     * @param                 Integer $userVerObjType email/mobile verification type
     * @return                Array
     */
    public function updateUserVerficationData($userId,$userVerObjType)
    {
        $updateData = [];
        $whereData  = ['user_id'=> $userId,'is_deleted'=>  Config::get('constants.IS_DELETED_NO')];
        if( Config::get('constants.USER_VERI_OBJECT_TYPE_EMAIL') == $userVerObjType){
            $updateData = ['user_is_email_verified'=>1];
        }
        if( Config::get('constants.USER_VERI_OBJECT_TYPE_MOBILE') == $userVerObjType){
            $updateData = ['user_is_mob_verified'=>Config::get('constants.USER_MOB_VERIFIED_YES')];
        }
        $result = $this->dbUpdate('users',$updateData,$whereData);
        return $result;
    }

    /**
     * @DateOfCreation        14 June 2018
     * @ShortDescription      function update user data by requested column and condition
     * @param                 array $updateData
     * @param                 array $where
     * @return                Array
     */
    public function userDataUpdate($updateData,$where)
    {
        $result = $this->dbUpdate($this->table, $updateData, $where, 'masterdb');
        return $result;
    }

    public function insertToken($data){
        $token = DB::table($this->tokenTable)->insertGetId($data, 'access_token_id');
        return $token;
    }

    public function updateToken($whereData, $updateData){
        $token = DB::table($this->tokenTable)->where([
                    'user_id'      => $whereData['user_id'],
                    'access_token' => $whereData['access_token'],
                    'device_type'  => $whereData['device_type']
        ])->update(array(
                'access_token' => $updateData['access_token'],
                'expires_at'   => $updateData['expires_at'],
            ));
        return true;
    }

    public function getClientSecret($whereData){
        $clientSecret = DB::connection('masterdb')->table($this->clientSecretTable)->where([
                    'tenant_name' => $whereData['tenant_name']
        ])->get();
        return $clientSecret;
    }

    public function insertClientSecret($insertData){
        $clientSecret = DB::connection('masterdb')->table($this->clientSecretTable)->insertGetId($insertData, 'user_secret_id');
        return $clientSecret;
    }

    //get tenant entry using clientID-secret
    public function getClientSecretUsingIdSecret($whereData){
        $clientSecret = DB::connection('masterdb')->table($this->clientSecretTable)->where([
                    'client_id'     => $whereData['client_id'],
                    'client_secret' => $whereData['client_secret']
        ])->get();
        return $clientSecret;
    }

    /**
    * @DateOfCreation        12 July 2021
    * @ShortDescription      Test data inserting function
    * @Description           This function insert data into users, doctor, clinics, patient, *                        visits table
    * @return                Array
    */
    public function prepareTestData($data)
    {
        /*
        * USER_TYPE_ADMIN - 1
        * USER_TYPE_DOCTOR - 2
        * USER_TYPE_PATIENT - 3
        * USER_TYPE_STAFF - 5,6,7,8
        * USER_TYPE_LAB_MANAGER - 9
        */

        // Insert data array contains info for users
        foreach ($data as $key => $value)
        {
            $insertData = array(
                'user_firstname'        => $value->user_firstname,
                'user_lastname'         => $value->user_lastname,
                'user_mobile'           => $value->user_mobile,
                'user_country_code'     => $value->user_country_code,
                'user_gender'           => $value->user_gender,
                'user_status'           => $value->user_status,
                'user_password'         => Hash::make($value->user_password),
                'user_type'             => $value->user_type,
                'resource_type'         => $value->resource_type,
                'ip_address'            => $value->ip_address,
                'user_email'            => $value->user_email,
                'user_adhaar_number'    => $value->user_adhaar_number
            );

            // Prepair insert query
            $response = $this->dbInsert($this->table, $insertData, 'masterdb');

            if($response)
            {
                $lastInsertId = DB::connection('masterdb')->getPdo()->lastInsertId();

                // Now as per user type we will insert data into other tables
                switch ($value->user_type)
                {
                    // For doctor
                    case '2':
                        $slug = str_slug($value->user_firstname.' '.$value->user_lastname).$this->utilityLibObj->alphabeticString(6);

                        // Insert data into Doctors table
                        $doctorData = array(
                            'user_id'      => $lastInsertId,
                            'doc_slug'     => $slug,
                            'resource_type'=> $value->resource_type,
                            'ip_address'   => $value->ip_address,
                            'created_by'   => $lastInsertId,
                            'updated_by'   => $lastInsertId
                        );
                        $this->createDoctor($doctorData);

                        // Insert data into Clinics table
                        $clinicData = array(
                            'clinic_name'           => $value->user_firstname." ".$value->user_lastname." "."Clinic",
                            'user_id'               => $lastInsertId,
                            'clinic_phone'          => '',
                            'clinic_address_line1'  => '',
                            'clinic_address_line2'  => '',
                            'clinic_landmark'       => '',
                            'clinic_pincode'        => '',
                            'resource_type'         => $value->resource_type,
                            'ip_address'            => $value->ip_address,
                            'created_by'            => 0,
                            'updated_by'            => 0
                        );
                        $this->insertData('clinics', $clinicData);
                        $lastClinicId = DB::connection()->getPdo()->lastInsertId();

                        if($lastClinicId)
                        {
                            // Insert data into Timings table
                            $timingData = array(
                                'appointment_type'     => '1',
                                'clinic_id'            => $lastClinicId,
                                'end_time'             => '1800',
                                'patients_per_slot'    => '1',
                                'slot_duration'        => '15',
                                'start_time'           => '1000',
                                'week_day'             => '0',
                                'user_id'              => $lastInsertId
                            );
                            $this->timingObj->createInitialTimingOnRegister($timingData);
                        }

                        // Create default component part is remain - need to discuss
                        $this->createdefaultComponent($insertData, $lastInsertId);
                    break;
                    // For patient
                    case '3':

                        // Insert data into Patients table
                        $patientData = array(
                            'user_id'      => $lastInsertId,
                            'pat_code'     => $this->utilityLibObj->patientsCodeGenrator(6),
                            'resource_type'=> $value->resource_type,
                            'ip_address'   => $value->ip_address,
                            'created_by'   => $lastInsertId,
                            'updated_by'   => $lastInsertId
                        );
                        $this->insertData('patients', $patientData);

                        // Insert data into Visits table
                        $visitData = array(
                            'user_id'       => Config::get('constants.DEFAULT_USER_VISIT_ID'),
                            'pat_id'        => $lastInsertId,
                            'visit_type'    => Config::get('constants.PROFILE_VISIT_TYPE'),
                            'visit_number'  => Config::get('constants.INITIAL_VISIT_NUMBER'),
                            'resource_type' => $value->resource_type,
                            'ip_address'    => $value->ip_address,
                            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
                            'status'        => Config::get('constants.VISIT_COMPLETED')
                        );
                        $this->insertData('patients_visits', $visitData);
                    break;
                    // For staff
                    case '5':
                        // Insert data into Laboratories table
                        $laboratoryData = array(
                            'user_id'       => $lastInsertId,
                            'resource_type' => $value->resource_type,
                            'ip_address'    => $value->ip_address,
                            'created_by'    => 0,
                            'updated_by'    => 0
                        );
                        $this->insertData('laboratories', $laboratoryData);
                    break;
                }
            }
            else
            {
                return $response;
            }
        }

        if($response)
        {
            $id = DB::getPdo()->lastInsertId();
            return $id;
        }
        else
        {
            return $response;
        }
    }

    /**
    * @DateOfCreation        12 July 2021
    * @ShortDescription      Test data inserting function
    * @Description           This function insert data into clinics, patient, visits table
    * @return                Array
    */
    public function insertData($tableName, $insertData)
    {
        $response = $this->dbInsert($tableName, $insertData);
        return true;
    }

    /**
    * @DateOfCreation        12 July 2021
    * @ShortDescription      This function for insert default component for doctor
    * @return                Array
    */
    public function createdefaultComponent($requestData, $userId)
    {
        if(array_key_exists('doc_spl_id', $requestData) && !empty($requestData['doc_spl_id']))
        {
            $doc_spl_id = $this->securityLibObj->decrypt($requestData['doc_spl_id']);
            $componentList = DefaultSpcializationComponentList::where([
                                                                'spicialization_id' => $doc_spl_id
                                                                ])->first();
            $spic = [
                "user_id"       => $userId,
                "user_type"     => Config::get("constants.USER_TYPE_DOCTOR"),
                "spl_id"        => $doc_spl_id,
                "is_primary"    => Config::get("constants.IS_PRIMARY_YES"),
                "ip_address"    => $requestData['ip_address'],
                "resource_type" => $requestData['resource_type'],
                "created_by"    => $userId,
                "updated_by"    => $userId
            ];
            $spcObj = new DoctorSpecialisations();
            $spcObj->insertOnlySpecialisations($spic);
            if(empty($componentList)){
                $componentList = DefaultSpcializationComponentList::where([
                                                                    'spicialization_id' => "default"
                                                                ])->first();
            }
        }else{
            $componentList = DefaultSpcializationComponentList::where([
                                                                    'spicialization_id' => "default"
                                                                ])->first();
        }

        $component = $componentList->component;
        if(!empty($component)){
            $component = json_decode($component);
            foreach ($component as $cmp) {
                $rqData = [
                    "is_visible" => $cmp->is_visible,
                    "is_visible_in_profile" => $cmp->is_visible_in_profile,
                    "show_in" => $cmp->show_in,
                    "visit_cmp_id" => $cmp->id,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $visitComponents = $this->visitsModelObj->insertDefaultVisitSettingComponent($rqData);
            }
        }

        // Default Categories
        $appointmentCategory = $componentList->appointment_category;
        if(!empty($appointmentCategory)){
            $appointmentCategory = json_decode($appointmentCategory);
            foreach ($appointmentCategory as $cat) {
                $rqData = [
                    "appointment_cat_name" => $cat,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $appointmentCat = $this->appointmentCategoryModelObj->createAppointmentCategory($rqData);
            }
        }

        // Default Patient Groups
        $patientGroups = $componentList->patient_groups;
        if(!empty($patientGroups)){
            $patientGroups = json_decode($patientGroups);
            foreach ($patientGroups as $grp) {
                $rqData = [
                    "pat_group_name" => $grp,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->patientsGroupsModelObj->createPatientGroup($rqData);
            }
        }

        // Default Checkup Types
        $checkupType = $componentList->checkup_type;
        if(!empty($checkupType)){
            $checkupType = json_decode($checkupType);
            foreach ($checkupType as $ck) {
                $rqData = [
                    "checkup_type" => $ck,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->checkupTypeObj->saveCheckupType($rqData);
            }
        }

        // Default Payment Modes
        $paymentMode = $componentList->payment_mode;
        if(!empty($paymentMode)){
            $paymentMode = json_decode($paymentMode);
            foreach ($paymentMode as $pmt) {
                $rqData = [
                    "payment_mode" => $pmt,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->paymentModeObj->savePaymentMode($rqData);
            }
        }

        // Default Patient at a glance
        $patientGlance = $componentList->patient_at_a_glance;
        if(!empty($patientGlance)){
            $patientGlance = json_decode($patientGlance);
            foreach ($patientGlance as $pglance) {
                $rqData = [
                    "patg_cmp_id" => $pglance->id,
                    "is_visible" => $pglance->is_visible,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->visitsModelObj->initialInsertPatgSettingComponent($rqData);
            }
        }

        // Default Consent Form
        $consentForm = $componentList->consent_form;
        if(!empty($consentForm)){
            $consentForm = json_decode($consentForm);
            foreach ($consentForm as $form) {
                $rqData = [
                    "consent_form_id" => "",
                    "consent_form_title" => $form->consent_form_title,
                    "consent_form_content" => $form->consent_form_content,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->consentFormsModelObj->saveConsentForm($rqData);
            }
        }
    }

    /**
    * @DateOfCreation        12 July 2021
    * @ShortDescription      Test data inserting function
    * @Description           This function insert data into country, state, city tables
    * @return                Array
    */
    public function prepareLocationData($tableName, $data)
    {
        // Insert data array contains country info
        foreach ($data as $key => $value)
        {
            switch ($tableName)
            {
                case 'country':
                    $countryData    = array(
                        'country_name' => $value->country_name,
                        'country_code' => $value->country_code
                    );

                    // Prepair insert query
                    $response = DB::table($tableName)->insert($countryData);
                break;

                case 'states':
                    $stateData   = array(
                        'country_id' => $value->country_id,
                        'name'       => $value->name
                    );

                    // Prepair insert query
                    $response = DB::table($tableName)->insert($stateData);
                break;

                case 'cities':
                    $cityData  = array(
                        'state_id' => $value->state_id,
                        'name'     => $value->name
                    );

                    // Prepair insert query
                    $response = DB::table($tableName)->insert($cityData);
                break;
            }
            
        }
        return true;
    }
}