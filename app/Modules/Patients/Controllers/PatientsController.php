<?php

namespace App\Modules\Patients\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use App\Traits\FxFormHandler;
use App\Traits\Notification as NotificationTrait;
use App\Jobs\ProcessPushNotification;
use Config;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\S3Lib;
use App\Libraries\ExceptionLib;
use App\Libraries\DateTimeLib;
use App\Modules\Patients\Models\Patients;
use App\Modules\Region\Models\Country;
use App\Modules\Auth\Models\Auth as Users;
use App\Modules\DoctorProfile\Models\DoctorProfile as DoctorProfile;
use App\Modules\Referral\Models\Referral as Referral;
use App\Modules\PatientGroups\Models\PatientGroups as PatientGroups;
use App\Modules\Patients\Models\PatientVitals as PatientVitals;
use App\Modules\Patients\Models\PatientNotificationSetting;
use App\Modules\Patients\Models\VideoConsulting;
use App\Modules\Auth\Models\UserDeviceToken;
use App\Modules\Patients\Models\DoctorPatientRelation;
use DB, Uuid;
use App\Libraries\FileLib;
use App\Libraries\UtilityLib;
use App\Libraries\ImageLib;
use File, Response;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Modules\Auth\Controllers\AuthController;

use App\Modules\Patients\Models\PatientsAllergies;
use App\Modules\Patients\Models\PatientPreviousPrescription;
use App\Modules\Patients\Models\PatientPreviousPrescriptionMedia;
use App\Modules\Visits\Models\PastMedicationHistory;
use App\Modules\Visits\Models\MedicationHistory;
use App\Modules\Visits\Models\LaboratoryReport;
use App\Modules\DoctorProfile\Models\DoctorMedia;
use App\Modules\Visits\Models\VaccinationHistory;
use App\Modules\Bookings\Models\Bookings;

/**
 * PatientsController
 *
 * @package                ILD INDIA
 * @subpackage             PatientsController
 * @category               Controller
 * @DateOfCreation         13 june 2018
 * @ShortDescription       This controller to handle all the operation related to
                           Patients profile
 */
class PatientsController extends Controller
{

    use SessionTrait, RestApi, FxFormHandler, NotificationTrait;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    // Store Post Method
    protected $method = '';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request, AuthController $authController)
    {
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init Patient model object
        $this->patientModelObj = new Patients();

        // Init User model object
        $this->userModelObj = new Users();

        $this->doctorProfileModelObj = new DoctorProfile();

        // Init DateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init File Library object
        $this->FileLib = new FileLib();

        // Init Utility Library object
        $this->UtilityLib = new UtilityLib();

        $this->method = $request->method();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init country model object
        $this->countrytModelObj = new Country();

        // Init referral model object
        $this->referralModelObj = new Referral();

        // Init patient groups model object
        $this->patientGroupsModelObj = new PatientGroups();

        //init auth controller object
        $this->authControllerObject = $authController;

        // Init patient vitals model object
        $this->patientVitalsModelObj = new PatientVitals();

        // Init Patient allergy Model Object
        $this->patientsAllergiesObj = new PatientsAllergies();

        // Init Past Medication History Model Object
        $this->pastMedicationHistoryObj = new PastMedicationHistory();

        // Init MedicationHistory Model Object
        $this->medicationHistoryObj = new MedicationHistory();

        // Init LaboratoryTest model object
        $this->laboratoryReportModelObj = new LaboratoryReport();

        // Init Doctor Media model object
        $this->doctorMedia = new DoctorMedia();

        // Init Vaccination History Model Object
        $this->vaccinationHistoryModelObj = new VaccinationHistory();

        // Init Previous prescription Model Object
        $this->patPrevPrescriptionModelObj = new PatientPreviousPrescription();

        // Init Previous prescription media Model Object
        $this->patPrevPrescriptionMediaModelObj = new PatientPreviousPrescriptionMedia();

        // Init Image Library object
        $this->ImageLib = new ImageLib();

        // Init S3 bucket library object
        $this->s3LibObj = new S3Lib();

        // Init Bookings model object
        $this->bookingsModelObj = new Bookings();
    }

    /**
    * @DateOfCreation        13 June 2018
    * @ShortDescription      Get a validator for an incoming Patients request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function patientsValidations(array $requestData, $extra = [], $type = 'insert'){
        $errors         = [];
        $error          = false;
        $rules = [];

        // Check the required validation rule
        switch($this->method)
        {
            case 'POST':
            {
                $rules = [
                            'user_email'            => 'required|string|email|max:150|unique:masterdb.users,user_email',
                            'user_firstname'        => 'required|string|max:100',
                            'user_lastname'         => 'required|string|max:100',
                            'user_mobile'           => 'required|numeric|regex:/[0-9]{10}/||unique:users',
                            'user_gender'           => 'required|numeric',
                        ];
            }
            case 'PUT':
            {
                $rules = [
                            'user_email'            => 'required|string|email|max:150|unique:masterdb.users,user_email,'.$requestData['user_id'].',user_id',
                            'user_firstname'        => 'required|string|max:100',
                            'user_lastname'         => 'required|string|max:100',
                            'user_mobile'           => 'required|numeric|regex:/[0-9]{10}/||unique:masterdb.users,user_mobile,'.$requestData['user_id'].',user_id',
                            'user_gender'           => 'required|numeric',
                        ];
            }
            default:break;
        }

        $rules = array_merge($rules, $extra);

        $validationMessageData = [
            'user_gender.numeric'   => trans('Patients::messages.patient_validation_gender'),
            'user_gender.numeric'   => trans('Patients::messages.patient_validation_title'),
            'pat_pincode.numeric'   => trans('Patients::messages.patient_validation_pat_pincode'),
        ];

        $validator = Validator::make($requestData, $rules, $validationMessageData);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * @DateOfCreation        13 june 2018
     * @ShortDescription      This function is responsible for insert Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function store(Request $request)
    {
        $requestData = $request->only('city_id','pat_other_city','doc_ref_id','doc_ref_name','pat_group_id','pat_group_name', 'user_email');

        $requestData['user_id'] = $doctorUserId = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        $tenant_id = $request->user()->tenant_id;
        try{
            DB::beginTransaction();
            $doc_ref_id = NULL;
            if(!empty($requestData['doc_ref_id']) && $requestData['doc_ref_id'] != 'undefined') {
                $doc_ref_id = $this->securityLibObj->decrypt($requestData['doc_ref_id']);
            }else if(!empty($requestData['doc_ref_name'])){
                $referralResult = $this->referralModelObj->getReferralIdByName($requestData['doc_ref_name']);
                if(!empty($referralResult)){
                   $doc_ref_id = $referralResult->doc_ref_id;
                }else{
                    $refferalData = ['doc_ref_name' => $requestData['doc_ref_name'], 'user_id' => $requestData['user_id']];
                    $referal = $this->referralModelObj->createReferral($refferalData);
                    if(!empty($referal->doc_ref_id)){
                        $doc_ref_id = $this->securityLibObj->decrypt($referal->doc_ref_id);
                    }else{
                        $doc_ref_id = NULL;
                    }
                }
            }

            $pat_group_id = NULL;
            if(!empty($requestData['pat_group_id']) && $requestData['pat_group_id'] != 'undefined') {
                $pat_group_id = $this->securityLibObj->decrypt($requestData['pat_group_id']);
            }else if(isset($requestData['pat_group_name']) && !empty($requestData['pat_group_name'])){
                $patGroupResult = $this->patientGroupsModelObj->getPatientGroupIdByName($requestData['pat_group_name']);
                if(!empty($patGroupResult)){
                   $pat_group_id = $patGroupResult->pat_group_id;
                }else{
                    $groupData = ['pat_group_name' => $requestData['pat_group_name'], 'user_id' => $requestData['user_id']];
                    $patientGroup = $this->patientGroupsModelObj->createPatientGroup($groupData);
                    if(!empty($patientGroup->pat_group_id)){
                        $pat_group_id = $this->securityLibObj->decrypt($patientGroup->pat_group_id);
                    }else{
                        $pat_group_id = NULL;
                    }
                }
            }

            $requestData['city_id']  = $this->securityLibObj->decrypt($requestData['city_id']);

            $posConfig =
            [
                'users'=>
                [
                    'user_firstname'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'validation'=>'required|string|max:100',
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'user_lastname'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'validation'=>'required|string|max:100',
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'user_mobile'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'validation'=>'required|numeric|regex:/[0-9]{10}/',
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'user_adhaar_number'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'user_gender'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'validation'=>'required',
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'resource_type'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'decrypt'=>false,
                        'validation'=>'required',
                        'fillable' => true,
                    ],
                    'ip_address'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'decrypt'=>false,
                        'validation'=>'required',
                        'fillable' => true,
                    ]
                ],
                'patients'=>[
                    'pat_title'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'validation'=>'required',
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'pat_address_line1'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'city_id'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>true,
                        'fillable' => true,
                    ],
                    'state_id'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>true,
                        'fillable' => true,
                    ],
                    'pat_dob'=>
                    [
                        'type'=>'date',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                        'currentDateFormat' => 'dd/mm/YY',
                    ],
                    'pat_address_line1'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'pat_address_line2'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'pat_locality'=>
                    [
                        'type'=>'input',
                        'decrypt'=>false,
                        'isRequired' =>false,
                        'fillable' => true,
                    ],
                    'pat_mobile_num'=>
                    [
                        'type'=>'input',
                        'decrypt'=>false,
                        'isRequired' =>false,
                        'fillable' => true,
                    ],
                    'pat_pincode'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'pat_other_city'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'validation' =>'string|max:100',
                        'validationRulesMessege' => [
                        'pat_other_city.string' => trans('Patients::messages.pat_other_city_string'),
                        ],
                        'decrypt'=>false,
                        'fillable' => true,
                    ],
                    'resource_type'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'decrypt'=>false,
                        'validation'=>'required',
                        'fillable' => true,
                    ],
                    'ip_address'=>
                    [
                        'type'=>'input',
                        'isRequired' =>true,
                        'decrypt'=>false,
                        'validation'=>'required',
                        'fillable' => true,
                    ],
                    'pat_emergency_contact_number'=>
                    [
                        'type'=>'input',
                        'isRequired' =>false,
                        'decrypt'=>false,
                        'fillable' => true,
                    ]
                ]
            ];

            // Email Validation check if user email is not empty in post data
            if(isset($requestData['user_email']) && !empty($requestData['user_email'])){
                $posConfig['users']['user_email'] = [
                                                        'type'          => 'input',
                                                        'isRequired'    => true,
                                                        'validation'    => 'required|string|email|max:150',
                                                        'decrypt'       => false,
                                                        'fillable'      => true,
                                                    ];

                $emailExists = Users::where([
                                    'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                    'user_email' => $requestData['user_email'],
                                    'tenant_id' => $tenant_id
                                ])
                                ->first();
                if(!empty($emailExists)){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        ["user_email"=> [
                            trans('Patients::messages.email_already_connected')
                        ]],
                        trans('Patients::messages.email_already_connected'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }

            if($requestData['city_id'] === '0'){
                $posConfig['patients']['pat_other_city']['isRequired'] = true;
            }else{
                if(isset($posConfig['patients']['pat_other_city']['validation'])) {
                    unset($posConfig['patients']['pat_other_city']['validation']);
                }
               $posConfig['patients']['pat_other_city']['valueOverwrite'] = '';
            }

           $responseValidatorForm = $this->postValidatorForm($posConfig, $request);

           if (!$responseValidatorForm['status']) {
                return $responseValidatorForm['response'];
           }

            if($responseValidatorForm['status']){
                $destination    = Config::get('constants.PATIENTS_MEDIA_PATH');
                $storagPath     = Config::get('constants.STORAGE_MEDIA_PATH');
                $patientsData   = $responseValidatorForm['response']['fillable']['patients'];
                $usersData      = $responseValidatorForm['response']['fillable']['users'];

                // Check mobile number
                $mobileExists = Users::where([
                                        'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                        'user_mobile' => $usersData['user_mobile'],
                                        'tenant_id' => $tenant_id
                                    ])
                                    ->first();
                if(!empty($mobileExists)){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        ["user_mobile"=> [
                            trans('Patients::messages.mobile_already_connected')
                        ]],
                        trans('Patients::messages.mobile_already_connected'),
                        $this->http_codes['HTTP_OK']
                    );
                }

                $patientsData['doc_ref_id']     = $doc_ref_id;
                $patientsData['pat_group_id']   = $pat_group_id;

                //file uploade path
                if(!empty($patientsData['state_id'])){
                    $countryDetailes = $this->countrytModelObj->getCountryDetailsByStateId($patientsData['state_id']);
                    $usersData['user_country_code'] = $countryDetailes->country_code;
                }else{
                    $usersData['user_country_code'] = Config::get('constants.INDIA_COUNTRY_CODE');
                }
                $usersData['user_type'] = Config::get('constants.USER_TYPE_PATIENT');

                $isEmailSet = true;
                if(empty($usersData['user_email'])){
                    $isEmailSet = false;
                }

                if(array_key_exists('user_mobile', $usersData)){
                    $usersData['user_password'] = Hash::make($usersData['user_mobile']);
                }
                $usersData['user_status'] = Config::get('constants.USER_STATUS_ACTIVE');
                $usersData['tenant_id'] = $tenant_id;

                $patientUserId = $this->patientModelObj->createPatientUser('users',$usersData, 'masterdb');
                if($patientUserId){
                    $doctorPatCodePrefix = $this->doctorProfileModelObj->getPatCodePrefix($doctorUserId);
                    $patCodePrefix = (isset($doctorPatCodePrefix) && !empty($doctorPatCodePrefix->pat_code_prefix)) ? $doctorPatCodePrefix->pat_code_prefix : Config::get('constants.PATIENT_CODE_PREFIX_DEFAULT');
                    if(!empty($this->patientModelObj->getPatientsRegistrationNumberByDoctorId($doctorUserId)->pat_code)){
                        $lastPatientCode    = $this->patientModelObj->getPatientsRegistrationNumberByDoctorId($doctorUserId)->pat_code;
                        $validLastCode      = explode($patCodePrefix, $lastPatientCode);
                        $newPatientCode     = array_key_exists('1', $validLastCode) ? $validLastCode[1]+1 : Config::get('constants.FIRST_PATIENT_CODE_DEFAULT');

                        if(strlen($newPatientCode) == 1){
                            $validCodePrefix = '000';
                        } else if(strlen($newPatientCode) == 2){
                            $validCodePrefix = '00';
                        } else if(strlen($newPatientCode) == 3){
                            $validCodePrefix = '0';
                        } else {
                            $validCodePrefix = '';
                        }

                        $patientsData['pat_code'] = $patCodePrefix.($validCodePrefix.$newPatientCode);
                        if(isset($patientsData['pat_dob']) && !empty($patientsData['pat_dob'])){
                            $age = $this->UtilityLib->calculateAge($patientsData['pat_dob']);
                            $patientsData['pat_age'] = $age;
                        }
                    } else {
                        $patientsData['pat_code'] = $patCodePrefix.Config::get('constants.FIRST_PATIENT_CODE_DEFAULT');
                    }
                    $patientsData['user_id']  = $patientUserId;

                    $patId = $this->patientModelObj->createPatientUser('patients',$patientsData);
                    if($patId){
                        $relationData = [
                            'user_id'       => (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id,
                            'pat_id'        => $patientUserId,
                            'assign_by_doc' => $request->user()->user_id,
                            'resource_type' => Config::get('constants.RESOURCE_TYPE_WEB'),
                            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
                            'ip_address'    => $request->ip()
                        ];
                        $this->patientModelObj->createPatientDoctorRelation('doctor_patient_relation',$relationData);

                        $defaultVisitData = [
                            'user_id'       => Config::get('constants.DEFAULT_USER_VISIT_ID'),
                            'pat_id'        => $patientUserId,
                            'visit_type'    => Config::get('constants.PROFILE_VISIT_TYPE'),
                            'visit_number'  => Config::get('constants.INITIAL_VISIT_NUMBER'),
                            'resource_type' => Config::get('constants.RESOURCE_TYPE_WEB'),
                            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
                            'status'        => Config::get('constants.VISIT_COMPLETED'),
                            'ip_address'    => $request->ip()
                        ];
                        $this->patientModelObj->createPatientDoctorVisit('patients_visits',$defaultVisitData);


                        if($isEmailSet){
                            $verificationLinkData = [
                                'user_firstname'=> $usersData['user_firstname'],
                                'user_lastname' => $usersData['user_lastname'],
                                'user_email'    => $usersData['user_email'],
                                'resource_type' => Config::get('constants.RESOURCE_TYPE_WEB'),
                                'ip_address'    => $request->ip(),
                                'user_type'     => Config::get('constants.USER_TYPE_PATIENT')
                            ];
                            $this->authControllerObject->sendVerificationLink($verificationLinkData, $patientUserId, $resetType = 'patientPassword');
                        }

                        DB::commit();

                        // SEND THANK YOU MESSAGE TO REFFERAL CONTACT NUMBER HERE
                        if(!empty($doc_ref_id)){
                            $referralResult = $this->referralModelObj->getReferralById($doc_ref_id);

                            if(!empty($referralResult->doc_ref_mobile)){
                                $referralContactNumber = $referralResult->doc_ref_mobile;
                            }
                        }
                        // SEND THANK YOU MESSAGE TO REFFERAL CONTACT NUMBER HERE

                        $createdPatientIdEncrypted = $this->securityLibObj->encrypt($patientUserId);
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            ['user_id' => $createdPatientIdEncrypted],
                            [],
                            trans('Patients::messages.patients_add_successfull'),
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Patients::messages.patients_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
                    }
                }else{
                    DB::rollback();

                    //user pat_consent_file unlink
                    if(!empty($pdfPath) && file_exists($pdfPath)){
                        unlink($pdfPath);
                    }
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        [],
                        trans('Patients::messages.patients_add_fail'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }
       } catch (\Exception $ex) {
           //user pat_consent_file unlink
           if(!empty($pdfPath) && file_exists($pdfPath)){
               unlink($pdfPath);
           }
           $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PatientsController', 'store');
           return $this->resultResponse(
               Config::get('restresponsecode.EXCEPTION'),
               [],
               [],
               $eMessage,
               $this->http_codes['HTTP_OK']
           );
       }
    }

    /**
     * @DateOfCreation        30 March 2021
     * @ShortDescription      This function is responsible for get Patient Data by id
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function editPatientDetails(Request $request)
    {
       $requestData = $this->getRequestData($request);
       $patientID = $this->securityLibObj->decrypt($requestData['id']);

       $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

       $requestData['user_type'] = $request->user()->user_type;

        $patientProfileData = $this->patientModelObj->getPatientProfileData($requestData, $patientID);
        if($patientProfileData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $patientProfileData,
                    [],
                    trans('Patients::messages.patient_profile_data'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                    Config::get('restresponsecode.NOT_FOUND'),
                    [],
                    ['user'=> trans('Patients::messages.patient_not_available')],
                    trans('Patients::messages.patient_not_available'),
                    $this->http_codes['HTTP_OK']
                  );
        }
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for update Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function update(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_type'] = $request->user()->user_type;


        $extra = [];
        $requestData['city_id']             = $this->securityLibObj->decrypt($requestData['city_id']);
        $requestData['state_id']            = $this->securityLibObj->decrypt($requestData['state_id']);
        $requestData['pat_dob']             = isset($requestData['pat_dob']) && !empty($requestData['pat_dob']) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['pat_dob'],'dd/mm/YY','Y-m-d')['result'] : NULL;
        $requestData['user_country_code']   = $this->securityLibObj->decrypt($requestData['user_country_code']);
        $requestData['resource_type']       = Config::get('constants.RESOURCE_TYPE_WEB');
        $requestData['is_deleted']          = Config::get('constants.IS_DELETED_NO');
        $requestData['user_id']             = $this->securityLibObj->decrypt($requestData['user_id']);
        $requestData['pat_marital_status']  = count($requestData['pat_marital_status']) > 0 ? $requestData['pat_marital_status'][0] : null;

        if(isset($requestData['pat_number_of_children'])){
            $pat_number_of_children = $requestData['pat_number_of_children'];
        }else{
            $pat_number_of_children = 0;
        }

        $requestData['pat_number_of_children']  = $requestData['pat_marital_status'] == Config::get('dataconstants.MARITAL_STATUS_MARRIED') ? $pat_number_of_children : null;

        if($requestData['city_id'] === '0'){
            $extra['pat_other_city'] =  'string|max:100';
        }else{
           $requestData['pat_other_city']  = '';
        }

        if(empty($requestData['user_email'])){
            $isEmailSet = false;
            $requestData['user_email'] = strtolower(str_replace(" ", "_", $requestData['user_firstname']).'_'.str_replace(" ", "_", $requestData['user_lastname']).'_'.time()).Config::get('constants.DEFAULT_EMAIL_ADDRESS_SUFFIX');
        }

        $validate = $this->patientsValidations($requestData, $extra, 'update');
        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Patients::messages.patients_add_validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }

        // DOCTOR REFERANCE
        $loggedInUserId = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        $doc_ref_id = NULL;
        if(!empty($requestData['doc_ref_id']) && $requestData['doc_ref_id'] != 'undefined') {
            $doc_ref_id = $this->securityLibObj->decrypt($requestData['doc_ref_id']);
        }else if(!empty($requestData['doc_ref_name'])){
            $referralResult = $this->referralModelObj->getReferralIdByName($requestData['doc_ref_name']);

            if(!empty($referralResult)){
               $doc_ref_id = $referralResult->doc_ref_id;
            }else{
                $refferal_data = [
                                    "doc_ref_name"  => $requestData['doc_ref_name'],
                                    "user_id"       => $loggedInUserId,
                                    "ip_address"    => $requestData['ip_address'],
                                    "resource_type" => $requestData['resource_type'],
                                    "is_deleted"    => $requestData['is_deleted'],
                                ];
                $referal = $this->referralModelObj->createReferral($refferal_data);
                if(!empty($referal->doc_ref_id)){
                    $doc_ref_id = $this->securityLibObj->decrypt($referal->doc_ref_id);
                }else{
                    $doc_ref_id = NULL;
                }
            }
        }

        // PATIENT GROUP
        $pat_group_id = NULL;
        if(!empty($requestData['pat_group_id']) && $requestData['pat_group_id'] != 'undefined') {
            $pat_group_id = $this->securityLibObj->decrypt($requestData['pat_group_id']);
        }else if(isset($requestData['pat_group_name']) && !empty($requestData['pat_group_name'])){
            $patGroupResult = $this->patientGroupsModelObj->getPatientGroupIdByName($requestData['pat_group_name']);
            if(!empty($patGroupResult)){
               $pat_group_id = $patGroupResult->pat_group_id;
            }else{
                $group_data = [
                                    "pat_group_name"  => $requestData['pat_group_name'],
                                    "user_id"       => $loggedInUserId,
                                    "ip_address"    => $requestData['ip_address'],
                                    "resource_type" => $requestData['resource_type'],
                                    "is_deleted"    => $requestData['is_deleted'],
                                ];
                $patientGroup = $this->patientGroupsModelObj->createPatientGroup($group_data);
                if(!empty($patientGroup->pat_group_id)){
                    $pat_group_id = $this->securityLibObj->decrypt($patientGroup->pat_group_id);
                }else{
                    $pat_group_id = NULL;
                }
            }
        }

        try{
            $pat_id  = $this->securityLibObj->decrypt(($request->user()->user_type == Config::get('constants.USER_TYPE_PATIENT')) ? $request->user()->user_id : $requestData['pat_id']);
            $user_id = $requestData['user_id'];

            $whereData = ['user_id' => $user_id];

            if(!empty($requestData['state_id'])){
                    $countryDetailes = $this->countrytModelObj->getCountryDetailsByStateId($requestData['state_id']);
                    $requestData['user_country_code'] = $countryDetailes->country_code;
            }else{
                    $requestData['user_country_code'] = Config::get('constants.INDIA_COUNTRY_CODE');
                }
            $userData = ['user_email'        => $requestData['user_email'],
                        'user_mobile'        => $requestData['user_mobile'],
                        'user_adhaar_number' => $requestData['user_adhaar_number'],
                        'user_firstname'     => $requestData['user_firstname'],
                        'user_lastname'      => $requestData['user_lastname'],
                        'user_country_code'  => $requestData['user_country_code'],
                        'user_gender'        => $requestData['user_gender']
                        ];
            if(empty($requestData['state_id'])){
                unset($requestData['state_id']);
            }

            if(empty($requestData['city_id'])){
                unset($requestData['city_id']);
            }
            unset($requestData['user_email']);
            unset($requestData['user_mobile']);
            unset($requestData['user_adhaar_number']);
            unset($requestData['user_country_code']);
            unset($requestData['user_gender']);

            $requestData['doc_ref_id']   = $doc_ref_id;
            $requestData['pat_group_id'] = $pat_group_id;
            $updatePatientData  = $this->patientModelObj->updatePatientData($requestData, $whereData);
            $updateUserData     = $this->userModelObj->userDataUpdate($userData, $whereData);

            // validate, is query executed successfully
            if($updatePatientData){
                $updatePatientDataDetails = $this->patientModelObj->getPatientProfileData($requestData, $user_id);
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $updatePatientDataDetails,
                    [],
                    trans('Patients::messages.patients_updated_successfull'),
                    $this->http_codes['HTTP_OK']
                );

            }else{
                DB::rollback();
                //user image unlink
                if(!empty($imagePath) && file_exists($imagePath)){
                    unlink($imagePath);
                }
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Patients::messages.patients_update_fail'),
                    $this->http_codes['HTTP_OK']
                );
            }
        } catch (\Exception $ex) {

            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PatientsController', 'update');
            return $this->resultResponse(
                Config::get('restresponsecode.EXCEPTION'),
                [],
                [],
                $eMessage,
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for get Patient list
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getPatientList(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        $tenant_id = $request->user()->tenant_id;

        $getPatientList = $this->patientModelObj->getPatientList($requestData);
        
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $getPatientList,
            [],
            trans('Patients::messages.patient_list_data'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get Patient visit id
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getPatientVisitId(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $getPatientVisitId = $this->patientModelObj->getPatientVisitId($requestData);

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $getPatientVisitId,
                [],
                trans('Patients::messages.patient_visit_id_fetced_success'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
    * @DateOfCreation        21 June 2018
    * @ShortDescription      This function is responsible for get Patient visit id
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function createPatientFollowUpVisitId(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $getPatientVisitId = $this->patientModelObj->getPatientFollowUpVisitId($requestData);

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $getPatientVisitId,
                [],
                trans('Patients::messages.patient_visit_id_fetced_success'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        3 Sept 2018
     * @ShortDescription      This function is responsible for get Patient's activity history record
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getPatientActivityHistory(Request $request)
    {
        $requestData            = $this->getRequestData($request);

        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $requestData['pat_id']  = $this->securityLibObj->decrypt($requestData['pat_id']);

        $getPatientActivityHistoryRecord = $this->patientModelObj->getPatientActivityHistory($requestData);

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $getPatientActivityHistoryRecord,
                [],
                trans('Patients::messages.patient_activity_history_fetced_success'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
    * @DateOfCreation        22 Nov 2018
    * @ShortDescription      This function is responsible for delete patient doctor relationship
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $primaryKey = $this->patientModelObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $pat_user_id = $this->securityLibObj->decrypt($requestData['pat_user_id']);
        $isPrimaryIdExist = $this->patientModelObj->isPrimaryIdExist($primaryId);
        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('Patients::messages.patient_not_exist')]],
                trans('Patients::messages.patient_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $patientDeleteData   = $this->patientModelObj->doDeletePatient($pat_user_id, $requestData['user_id']);
        if($patientDeleteData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Patients::messages.patient_data_deleted'),
                    $this->http_codes['HTTP_OK']
                );
        }
        return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.patient_data_not_deleted'),
                $this->http_codes['HTTP_OK']
            );

    }

    /**
     * @DateOfCreation        20 Sept 2018
     * @ShortDescription      This function is responsible to get patient's current Medication record
     * @return                Array of medicines and message
     */
    public function searchPatients(Request $request){
        $requestData    = $this->getRequestData($request);
        
        $requestData['user_id']      = $request->user()->user_id;
        $requestData['patient_name']= $requestData['patient_name'];

        $data = [];
        $patientDetails = $this->patientModelObj->searchPatientRecord($requestData);

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $patientDetails,
                [],
                trans('Visits::messages.patient_data_fetched_successfully'),
                $this->http_codes['HTTP_OK']
            );
    }

    public function updateAgeFromDateOfBirth(){
        $i=1;
        $updatePatients = $this->patientModelObj->updateAgeFromDateOfBirth();
        $patients = $this->patientModelObj->getPatientsWithDateOfBirth();

            echo '<div style="color:red; width:1500px; float:left; margin:0 auto;"><div style="width:50px; float:left;">S No.</div><div style="width:100px; float:left;">Pat Code</div><div style="width:300px; float:left;">Name</div><div style="solid black; width:200px; float:left;">DOB</div><div style="width:200px; float:left;">Age</div><div style="width:200px; float:left;">Is Deleted</div><br>';
        foreach($patients as $p){
            if(!empty($p->pat_dob)){
                echo '<div style="color:blue; width:1500px; float:left; margin:0 auto;">';
            }else{
                echo '<div style="color:black; width:1500px; float:left; margin:0 auto;">';
            }
                echo '<div style="width:50px; float:left;">'.$i.'</div><div style="solid black; width:100px; float:left;">'.(!empty($p->pat_code) ? $p->pat_code : '-').'</div><div style="width:300px; float:left;">'.$p->user_firstname.' '.$p->user_lastname.'</div><div style="solid black; width:200px; float:left;">'.(!empty($p->pat_dob) ? $p->pat_dob : '-').'</div><div style="width:200px; float:left;">'.(!empty($p->age_after) ? $p->age_after : '-').'</div><div style="width:200px; float:left;">'.(!empty($p->is_deleted) ? $p->is_deleted : '-').'</div><br>';
                $i++;
        }
    }

    /**
     * @DateOfCreation        17 Sep 2020
     * @ShortDescription      This function is responsible to store the patient vitals add
     * @return                Array of status and message
     */
    public function storePatientVitals(Request $request)
    {
        $userId = ($request->user()->user_type == Config::get('constants.USER_TYPE_PATIENT')) ? $request->user()->user_id : $request->user()->created_by;
        $tableName   = $this->patientVitalsModelObj->getTableName();
        $primaryKey  = $this->patientVitalsModelObj->getTablePrimaryIdColumn();
        $requestData = $this->getRequestData($request);

        $posConfig =
            [$tableName =>
            [
                $primaryKey =>
                [
                    'type' => 'input',
                    'decrypt' => true,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'resource_type' =>
                [
                    'type' => 'input',
                    'isRequired' => true,
                    'decrypt' => false,
                    'validation' => 'required',
                    'fillable' => true,
                ],
                'ip_address' =>
                [
                    'type' => 'input',
                    'isRequired' => true,
                    'decrypt' => false,
                    'validation' => 'required',
                    'fillable' => true,
                ],
                'temperature' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'pulse' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'bp_systolic' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'bp_diastolic' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'spo2' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'respiratory_rate' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'sugar_level' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'height' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'weight' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'jvp' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
                'pedel_edema' =>
                [
                    'type' => 'input',
                    'decrypt' => false,
                    'isRequired' => false,
                    'fillable' => true,
                ],
            ],];

        $responseValidatorForm = $this->postValidatorForm($posConfig, $request);
        if (!$responseValidatorForm['status']) {
            return $responseValidatorForm['response'];
        }

        if ($responseValidatorForm['status']) {
            $fillableDataPayment                        = $responseValidatorForm['response']['fillable']['patient_vitals'];
            $fillableDataPayment['pat_id']              = $userId;
            
            try {
                DB::beginTransaction();
                $patient_vitals_id = $fillableDataPayment['patient_vitals_id'];
                if(!empty($fillableDataPayment['weight']) && !empty($fillableDataPayment['height'])){
                    $heightInMeter = $fillableDataPayment['height']/100;
                    $heightInMeter = $heightInMeter*$heightInMeter;
                    $fillableDataPayment['bmi'] = round($fillableDataPayment['weight']/$heightInMeter, 2);
                }else if(!empty($fillableDataPayment['weight']) && empty($fillableDataPayment['height'])){
                    $getHeight = PatientVitals::where([
                                                    'pat_id' => $userId
                                                ])
                                                ->where('height', '>', 0)
                                                ->orderBy('created_at', "DESC")
                                                ->first();
                    if($getHeight){
                        $heightInMeter = $getHeight->height/100;
                        $heightInMeter = $heightInMeter*$heightInMeter;
                        $fillableDataPayment['bmi'] = round($fillableDataPayment['weight']/$heightInMeter, 2);
                    }else{
                        $fillableDataPayment['bmi'] = Null;
                    }
                }else if(empty($fillableDataPayment['weight']) && !empty($fillableDataPayment['height'])){
                    $getHeight = PatientVitals::where([
                                                    'pat_id' => $userId
                                                ])
                                                ->where('weight', '>', 0)
                                                ->orderBy('created_at', "DESC")
                                                ->first();
                    if($getHeight){
                        $heightInMeter = $fillableDataPayment['height']/100;
                        $heightInMeter = $heightInMeter*$heightInMeter;
                        $fillableDataPayment['bmi'] = round($getHeight->weight/$heightInMeter, 2);
                    }else{
                        $fillableDataPayment['bmi'] = Null;
                    }
                }else{
                    $fillableDataPayment['bmi'] = Null;
                }
                
                $paramPatientVitals = ['patient_vitals_id' => $fillableDataPayment['patient_vitals_id'], 'temperature' => $fillableDataPayment['temperature'], 'pulse' => $fillableDataPayment['pulse'], 'bp_systolic' => $fillableDataPayment['bp_systolic'], 'bp_diastolic' => $fillableDataPayment['bp_diastolic'], 'spo2' => $fillableDataPayment['spo2'], 'sugar_level' => $fillableDataPayment['sugar_level'], 'respiratory_rate' => $fillableDataPayment['respiratory_rate'], 'jvp' => $fillableDataPayment['jvp'], 'pedel_edema' => $fillableDataPayment['pedel_edema'], 'height' => $fillableDataPayment['height'], 'weight' => $fillableDataPayment['weight'], 'bmi' => $fillableDataPayment['bmi']];
                if (!empty($patient_vitals_id)) {
                    $whereData = [];
                    $whereData[$primaryKey]  = $patient_vitals_id;
                    $storePrimaryId = $this->patientVitalsModelObj->updateRequest($paramPatientVitals, $whereData);
                    $message = '_update';
                } else {
                    $storePrimaryId = $this->patientVitalsModelObj->savePatientVitals($fillableDataPayment);
                    $message = '_add';
                    if (!$storePrimaryId) {
                        $dberror = true;
                    }
                }

                if (isset($dberror) && $dberror) {
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        [],
                        trans('Patients::messages.patient_vitals_fail_add'),
                        $this->http_codes['HTTP_OK']
                    );
                }

                if ($storePrimaryId) {
                    DB::commit();
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $storePrimaryId,
                        [],
                        trans('Patients::messages.patient_vitals_successfull' . $message),
                        $this->http_codes['HTTP_OK']
                    );
                } else {
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        ['messages' => [trans('Patients::messages.patient_vitals_fail' . $message)]],
                        trans('Patients::messages.patient_vitals_fail' . $message),
                        $this->http_codes['HTTP_OK']
                    );
                }
            } catch (\Exception $ex) {
                DB::rollback();
                $eMessage = $this->exceptionLibObj->reFormAndLogException($ex, 'PatientsController', 'storePatientVitals');
                return $this->resultResponse(
                    Config::get('restresponsecode.EXCEPTION'),
                    [],
                    [],
                    $eMessage,
                    $this->http_codes['HTTP_OK']
                );
            }
        }
    }

    /**
     * @DateOfCreation        18 Sep 2020
     * @ShortDescription      This function is responsible for delete patient vitals WorkEnvironment Data
     * @param                 Array $wefId
     * @return                Array of status and message
     */
    public function destroyPatientVitals(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $primaryKey = $this->patientVitalsModelObj->getTablePrimaryIdColumn();
        $primaryId = $requestData[$primaryKey];
        $primaryId = $this->securityLibObj->decrypt($primaryId);
        $isPrimaryIdExist = $this->patientVitalsModelObj->isPrimaryIdExist($primaryId);
        if (!$isPrimaryIdExist) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey => [trans('Patients::messages.patient_vitals_not_exist')]],
                trans('Patients::messages.patient_vitals_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $deleteDataResponse   = $this->patientVitalsModelObj->doDeleteRequest($primaryId);
        if ($deleteDataResponse) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.patient_vitals_deleted'),
                $this->http_codes['HTTP_OK']
            );
        }
        return $this->resultResponse(
            Config::get('restresponsecode.ERROR'),
            [],
            [],
            trans('Patients::messages.patient_vitals_not_deleted'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        18 Sep 2020
     * @ShortDescription      This function is responsible for get Patient vitals list
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getPatientVitalsList(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['pat_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_PATIENT')) ? $request->user()->user_id : $request->user()->created_by;

        $getPatientVitalsList = $this->patientVitalsModelObj->getPatientVitalsList($requestData);

        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $getPatientVitalsList,
            [],
            trans('Patients::messages.patient_vitals_list'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        13 April 2021
     * @ShortDescription      This function is responsible for add edit patient notification setting
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function savePatientNotificationSetting(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $patId = $request->user()->user_id;

        $whereData = ['pat_id' => $patId];
        $requestData['created_by'] = $patId;
        $requestData['updated_by'] = $patId;
        $notificationUpdate = PatientNotificationSetting::updateOrCreate($whereData, $requestData);

        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $notificationUpdate,
            [],
            trans('Patients::messages.notif_setting_saved_successfully'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        13 April 2021
     * @ShortDescription      This function is responsible for get patient notification setting
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getPatientNotificationSetting(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $patId = $request->user()->user_id;

        if(!empty($patId)){
            $type = $request->user()->user_type;
            if($type != Config::get('constants.USER_TYPE_PATIENT')){
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Patients::messages.valid_patient_alert'),
                    $this->http_codes['HTTP_OK']
                );
            }

            $setting = PatientNotificationSetting::where([
                            'pat_id' => $patId,
                            'is_deleted' => Config::get("constants.IS_DELETED_NO")
                        ])->first();
            if(empty($setting)){
                $setting = [];
            }
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $setting,
                [],
                trans('Patients::messages.notif_setting_fetched_successfully'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.patient_not_found'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    public function getProfileCompletePercantage(Request $request){
        $requestData = $this->getRequestData($request);
        $user = Auth::user();
        
        $requestData['user_id'] = $user->user_id;
        $patientID = $user->user_id;
        $requestData['user_type'] = $user->user_type;
        $percantage = 100;
        $perTab = 100/7;
        $completed = 0;

        $patientProfileData = $this->patientModelObj->getPatientProfileData($requestData, $patientID);
        if(!empty($patientProfileData)){
            $completed += $perTab;
        }

        $getAllergiesData = $this->patientsAllergiesObj->getPatientAllergiesListCount($patientID);
        if(!empty($getAllergiesData) && $getAllergiesData > 0){
            $completed += $perTab;
        }

        $getPastMedicationHistoryData = $this->pastMedicationHistoryObj->getPatientMedicationHistoryDataCount($patientID);
        if(!empty($getPastMedicationHistoryData) && $getPastMedicationHistoryData > 0){
            $completed += $perTab;
        }

        $getMedicationHistoryData = $this->medicationHistoryObj->getMedicationHistoryListCount($patientID);
        if(!empty($getMedicationHistoryData) && $getMedicationHistoryData > 0){
            $completed += $perTab;
        }

        $getLabTestData = $this->laboratoryReportModelObj->getLabTestDataCount($patientID);
        if(!empty($getLabTestData) && $getLabTestData > 0){
            $completed += $perTab;
        }

        $patientPrescriptionData = $this->doctorMedia->getPatientMediaCount($patientID);
        if(!empty($patientPrescriptionData) && $patientPrescriptionData > 0){
            $completed += $perTab;
        }

        $patientVacHistoryData = $this->vaccinationHistoryModelObj->getVaccinationHistoryDataCount($patientID);
        if(!empty($patientVacHistoryData) && $patientVacHistoryData > 0){
            $completed += $perTab;
        }

        $completed = Round($completed);
        if($completed){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    ["Completed" => $completed],
                    [],
                    trans('Patients::messages.patient_profile_complete_percentage_fetch_success'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                    Config::get('restresponsecode.NOT_FOUND'),
                    [],
                    ['user'=> trans('Patients::messages.patient_not_available')],
                    trans('Patients::messages.patient_not_available'),
                    $this->http_codes['HTTP_OK']
                  );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadPreviousPrescription(Request $request){

        $requestData = $this->getRequestData($request);
        
        $user_id   = $this->securityLibObj->decrypt($requestData['patient_id']);
        $user_type = Config::get('constants.USER_TYPE_PATIENT');

        // Validate request
        $validate = $this->PreviousPrescriptionMediaValidator($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Patients::messages.pre_prescription_upload_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $mediaData = [];
        $uploaded_doc_media_file = [];
        if(array_key_exists('doc_media_file', $requestData) && !empty($requestData['doc_media_file'])){
            foreach ($requestData['doc_media_file'] as $media) {
                $fileType = $media->getClientOriginalExtension();
                $randomString = Uuid::generate();
                $filename = $randomString.'.'.$fileType;
                $environment = Config::get('constants.ENVIRONMENT_CURRENT');
                if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                    $filePath = Config::get('constants.PATIENT_PRESCRIPTION_S3_PATH').$filename;
                    $upload  = $this->s3LibObj->putObject(file_get_contents($media), $filePath, 'public');
                    if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                        $uploaded_doc_media_file[] = $filename;
                    }
                }else{
                    $destination = Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MEDIA_PATH');
                    $fileUpload = $this->FileLib->fileUpload($media, $destination);
                    $fileType = NULL;
                    if(isset($fileUpload['code']) && $fileUpload['code'] == Config::get('restresponsecode.SUCCESS')){
                        $getFileType = explode('.', $fileUpload['uploaded_file']);
                        $fileType    = $getFileType[1];
                    }

                    $thumbGenerate = [];
                    if($fileType && $fileType != 'pdf'){
                        $thumbPath =  Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MTHUMB_PATH');
                        $thumb = [];
                        $thumbName = $fileUpload['uploaded_file'];
                        $thumb = array(['thumb_name' => $thumbName,'thumb_path' => $thumbPath,'width' => 350 , 'height' => 250]);
                        $thumbGenerate = $this->ImageLib->genrateThumbnail($destination.$fileUpload['uploaded_file'],$thumb);
                    }else if($fileType && $fileType == 'pdf'){
                        $thumbGenerate[0]['code'] = Config::get('restresponsecode.SUCCESS');
                        $thumbGenerate[0]['uploaded_file'] = $fileUpload['uploaded_file'];
                    }

                    if((isset($thumbGenerate[0]) && $thumbGenerate[0]['code']) && $thumbGenerate[0]['code'] == 1000) {
                        $uploaded_doc_media_file[] = $thumbGenerate[0]['uploaded_file'];
                    }
                }
            }
        }

        if(array_key_exists('pre_prescription_id', $requestData) && !empty($requestData['pre_prescription_id'])){
            $preId   = $this->securityLibObj->decrypt($requestData['pre_prescription_id']);
            if(!empty($preId)){
                $getDetails = PatientPreviousPrescription::find($preId);
                if($getDetails){
                    try {
                        DB::beginTransaction();
                        $getDetails->doctor_name = $requestData['doctor_name'];
                        $getDetails->prescription_date = $requestData['prescription_date'];
                        $getDetails->updated_by = $user_id;
                        if(array_key_exists('doc_media_file', $requestData) && !empty($requestData['doc_media_file'])){
                            $getDetails->doc_media_file = $uploaded_doc_media_file[0];
                            foreach ($uploaded_doc_media_file as $md){
                                $med = [
                                    'media_id' => $preId,
                                    'media_name' => $md,
                                    'created_by' => $user_id,
                                    "updated_by" => $user_id
                                ];
                                PatientPreviousPrescriptionMedia::create($med);
                            }
                        }
                        $getDetails->save();
                        $preId = $this->securityLibObj->encrypt($preId);
                        $doc_media_file = $this->securityLibObj->encrypt($getDetails->doc_media_file);
                        $user_id = $this->securityLibObj->encrypt($user_id);
                        $mediaData = [
                            "pre_prescription_id" => $preId,
                            "user_id" => $user_id,
                            "doc_media_file" => $doc_media_file,
                            'user_type'  => $user_type,
                            'doctor_name' => $getDetails->doctor_name,
                            'prescription_date' => $getDetails->prescription_date,
                            "created_by" => $user_id,
                            "updated_by" => $user_id,
                            "ip_address" => $requestData['ip_address']
                        ];
                        DB::commit();
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            $mediaData,
                            [],
                            trans('DoctorProfile::messages.media_upload_success'),
                            $this->http_codes['HTTP_OK']
                        );
                    } catch (\Exception $e) {
                        DB::rollback();
                        if(array_key_exists('doc_media_file', $requestData) && !empty($requestData['doc_media_file'])){
                            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
                            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                                foreach ($uploaded_doc_media_file as $md){
                                    if($this->s3LibObj->isFileExist($md)){
                                        $this->s3LibObj->deleteFile($md);
                                    }
                                }
                            }else{
                                foreach ($uploaded_doc_media_file as $md){                            
                                    if(File::exists($destination.$md)){
                                        File::delete($destination.$md);
                                    }

                                    if(File::exists($thumbPath.$md)){
                                        File::delete($thumbPath.$md);
                                    }
                                }
                            }
                        }
                    }
                }else{
                    return $this->resultResponse(
                        Config::get('restresponsecode.NOT_FOUND'),
                        [],
                        ['user'=> trans('Patients::messages.patient_not_available')],
                        trans('Patients::messages.patient_not_available'),
                        $this->http_codes['HTTP_OK']
                    );
                }             
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $validate['errors'],
                    trans('Patients::messages.invalid_prescription_id_passed'),
                    $this->http_codes['HTTP_OK']
                );
            }
        }else{
            try{
                DB::beginTransaction();
                $patId   = $this->securityLibObj->decrypt($requestData['patient_id']);
                $mediaData = [
                    "user_id" => $patId,
                    "doc_media_file" => $uploaded_doc_media_file[0],
                    'user_type'  => $user_type,
                    'doctor_name' => $requestData['doctor_name'],
                    'prescription_date' => $requestData['prescription_date'],
                    "created_by" => $user_id,
                    "updated_by" => $user_id,
                    "ip_address" => $requestData['ip_address']
                ];
                $isMediaAdded = $this->patPrevPrescriptionModelObj->insertPrescription($mediaData);
                if($isMediaAdded){    
                    foreach ($uploaded_doc_media_file as $md){
                        $med = [
                            'media_id' => $isMediaAdded,
                            'media_name' => $md,
                            'created_by' => $user_id,
                            "updated_by" => $user_id
                        ];
                        PatientPreviousPrescriptionMedia::create($med);
                    }

                    $user_id = $this->securityLibObj->encrypt($user_id);
                    $mediaData['pre_prescription_id']   = $this->securityLibObj->encrypt($isMediaAdded);
                    $mediaData['doc_media_file'] = $this->securityLibObj->encrypt($mediaData['doc_media_file']);
                    $mediaData['user_id'] = $user_id;
                    $mediaData['created_by'] = $user_id;
                    $mediaData['updated_by'] = $user_id;
                    DB::commit();
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $mediaData,
                        [],
                        trans('Patients::messages.pre_prescription_upload_success'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            } catch (\Exception $e) {
                print_r($e->getMessage());die;
                DB::rollback();
                if(array_key_exists('doc_media_file', $requestData) && !empty($requestData['doc_media_file'])){
                    $environment = Config::get('constants.ENVIRONMENT_CURRENT');
                    if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                        if($this->s3LibObj->isFileExist($filePath)){
                            $this->s3LibObj->deleteFile($filePath);
                        }
                    }else{              
                        if(File::exists($destination.$fileUpload['uploaded_file'])){
                            File::delete($destination.$fileUpload['uploaded_file']);
                        }

                        if(File::exists($thumbPath.$fileUpload['uploaded_file'])){
                            File::delete($thumbPath.$fileUpload['uploaded_file']);
                        }
                    }
                }
            }
        }
    }

    /**
     * @DateOfCreation        10 May 2021
     * @ShortDescription      This function is responsible for validating blog data
     * @param                 Array $data This contains full doctor media input data
     * @return                VIEW
     */
    protected function PreviousPrescriptionMediaValidator(array $data)
    {
        $error = false;
        $errors = [];

        $rules = [
            'doctor_name' => 'required|max:150',
            'patient_id' => 'required',
            'prescription_date' => 'required|max:50',
        ];

        if(empty($data['pre_prescription_id'])){
            $rules['doc_media_file.*'] = 'required|max:50000|mimes:png,jpg,jpeg,pdf';
        }else if(array_key_exists('pre_prescription_id', $data) && !empty($data['pre_prescription_id']) && !empty($data['doc_media_file'])){            
            $rules['doc_media_file.*'] = 'required|max:50000|mimes:png,jpg,jpeg,pdf';
        }

        $validator = Validator::make($data, $rules);

        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * Getting all medias.
     *
     * @param  \Illuminate\Http\Request  $doctorId
     * @return \Illuminate\Http\Response
     */
    public function getAllPreviousPrescription(Request $request) {
        $requestData = $this->getRequestData($request);

        // Validate request
        $validator = Validator::make($requestData, [
            'patient_id' => 'required'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Patients::messages.pre_prescription_upload_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $requestData['patientId'] = $this->securityLibObj->decrypt($requestData['patient_id']);
        if(array_key_exists('dr_id', $requestData)){
            $requestData['dr_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        }
        
        if($requestData['patientId'] == ''){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.invalid_prescription_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }

        $prePrescription = $this->patPrevPrescriptionModelObj->getAllPreviousPrescription($requestData);

        if(count($prePrescription) > 0) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $prePrescription,
                [],
                trans('Patients::messages.data_found'),
                $this->http_codes['HTTP_OK']
            );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.data_found'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * Getting all medias.
     *
     * @param  \Illuminate\Http\Request  $doctorId
     * @return \Illuminate\Http\Response
     */
    public function getPreviousPrescriptionDetails(Request $request) {
        $requestData = $this->getRequestData($request);

        // Validate request
        $validator = Validator::make($requestData, [
            'pre_prescription_id' => 'required'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Patients::messages.pre_prescription_upload_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $requestData['pre_prescription_id'] = $this->securityLibObj->decrypt($requestData['pre_prescription_id']);
        
        if($requestData['pre_prescription_id'] == ''){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.invalid_prescription_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }

        $prePrescription = $this->patPrevPrescriptionModelObj->getPreviousPrescriptionDetails($requestData);
        if(count($prePrescription) > 0) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $prePrescription[0],
                [],
                trans('Patients::messages.data_found'),
                $this->http_codes['HTTP_OK']
            );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.invalid_prescription_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * Deleting a media from storage.
     *
     * @param  \Illuminate\Http\Request  $doc_media_id
     * @return \Illuminate\Http\Response
     */
    public function deletePreviousPrescription(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $user_id = $request->user()->user_id;

        $primaryKey = $this->patPrevPrescriptionModelObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->patPrevPrescriptionModelObj->isPrimaryIdExist($primaryId);
        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('Patients::messages.invalid_prescription_id_passed')]],
                trans('Patients::messages.invalid_prescription_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $isExist = $this->patPrevPrescriptionModelObj->getDetails($primaryId);
        $isMediaDeleted = $this->patPrevPrescriptionModelObj->deleteMedia($primaryId);
        if($isMediaDeleted){
            $storedMedia = PatientPreviousPrescriptionMedia::select("id", "media_name")
                                                            ->where([
                                                                'media_id' => $primaryId,
                                                                'is_deleted' => Config::get('constants.IS_DELETED_NO')
                                                            ])
                                                            ->get();
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                foreach($storedMedia as $md){
                    $oldFilePath = Config::get('constants.DOCTOR_MEDIA_S3_PATH').$md->media_name;
                    if($this->s3LibObj->isFileExist($oldFilePath)){
                        $this->s3LibObj->deleteFile($oldFilePath);
                    }

                    $deleteMedia = PatientPreviousPrescriptionMedia::find($md->id);
                    $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                    $deleteMedia->updated_by = $user_id;
                    $deleteMedia->save();
                }
            }else{
                $oldFilePath = storage_path('app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MEDIA_PATH'));
                foreach($storedMedia as $md){
                    if(!empty($md) && File::exists($oldFilePath.$md->media_name)) {
                        File::delete($oldFilePath.$md->media_name);
                    }
                    $oldFileThumbPath = storage_path('app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MTHUMB_PATH'));
                    if(!empty($md) && File::exists($oldFileThumbPath.$md->media_name)) {
                        File::delete($oldFileThumbPath.$md->media_name);
                    }

                    $deleteMedia = PatientPreviousPrescriptionMedia::find($md->id);
                    $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                    $deleteMedia->updated_by = $user_id;
                    $deleteMedia->save();
                }
            }
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.pre_prescription_delete_success'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * Deleting a selected media from storage.
     *
     * @param  \Illuminate\Http\Request  $doc_media_id
     * @return \Illuminate\Http\Response
     */
    public function deletePreviousPrescriptionMedia(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $user_id = $request->user()->user_id;

        // Validate request
        $validator = Validator::make($requestData, [
            'media_name' => 'required',
            'pre_prescription_id' => 'required'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Patients::messages.pre_prescription_upload_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $requestData['media_name'] = $this->securityLibObj->decrypt($requestData['media_name']);
        $requestData['media_id'] = $this->securityLibObj->decrypt($requestData['pre_prescription_id']);

        $storedMedia = PatientPreviousPrescriptionMedia::select("id", "media_name")
                                                        ->where([
                                                            'media_name' => $requestData['media_name'],
                                                            'media_id' => $requestData['media_id']
                                                        ])
                                                        ->first();
        if(!empty($storedMedia)){
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                
                $oldFilePath = Config::get('constants.DOCTOR_MEDIA_S3_PATH').$storedMedia->media_name;
                if($this->s3LibObj->isFileExist($oldFilePath)){
                    $this->s3LibObj->deleteFile($oldFilePath);
                }

                $deleteMedia = PatientPreviousPrescriptionMedia::find($storedMedia->id);
                $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                $deleteMedia->updated_by = $user_id;
                $deleteMedia->save();
            }else{
                $oldFilePath = storage_path('app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MEDIA_PATH'));
                    if(!empty($storedMedia) && File::exists($oldFilePath.$storedMedia->media_name)) {
                        File::delete($oldFilePath.$storedMedia->media_name);
                    }
                    $oldFileThumbPath = storage_path('app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MTHUMB_PATH'));
                    if(!empty($storedMedia) && File::exists($oldFileThumbPath.$storedMedia->media_name)) {
                        File::delete($oldFileThumbPath.$md->media_name);
                    }

                    $deleteMedia = PatientPreviousPrescriptionMedia::find($storedMedia->id);
                    $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                    $deleteMedia->updated_by = $user_id;
                    $deleteMedia->save();
            }
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.pre_prescription_delete_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.invalid_prescription_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        22 May 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imagePath
     * @return                response
     */
    public function getPrePrescriptionMedia($imageType = 0, $imageName, Request $request)
    {
        $requestData = $this->getRequestData($request);
        $imageName = $this->securityLibObj->decrypt($imageName);
        $defaultPath = storage_path('app/public/'.Config::get('constants.DOCTOR_MEDIA_DEFAULT_PATH'));
        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            $path = Config::get('constants.PATIENT_PRESCRIPTION_S3_PATH').$imageName;
            if($this->s3LibObj->isFileExist($path)){
                 return $response = $this->s3LibObj->getObject($path)['fileObject'];
            }
        }else{
            $imagePath = ($imageType ==  0) ? 'app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MEDIA_PATH') : 'app/public/'.Config::get('constants.PATIENTS_PRE_PRESCRIPTION_MTHUMB_PATH');
            $path = storage_path($imagePath) . $imageName;

            if(!File::exists($path)){
                $path = $defaultPath;
            }

            $file = File::get($path);
            $type = File::mimeType($path);

            if($type == 'pdf'){
                $headers = ['Content-Type: '.$type];
                return response()->file($path, $headers);
            }

            $response = Response::make($file, 200);
            $response->header("Content-Type", $type);
            return $response;
        }

        $file = File::get($defaultPath);
        $type = File::mimeType($defaultPath);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    public function sendpushNotificaiton(Request $request){
        $requestData = $this->getRequestData($request);
        $userToken = UserDeviceToken::whereIn('user_id', [$requestData['id']])
                                    ->where([ 'is_deleted' =>  Config::get('constants.IS_DELETED_NO') ])
                                    ->get();
        if($userToken){
            $tokens = [];
            foreach($userToken as $tk){
                $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
            }
            $message = "This is a test notification from BE.";
            $notifData = [
                "tokens" => $tokens,
                "title" => 'Rxhealth',
                "body" => $message,
                "extra" => [ 
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                    "title" => 'Rxhealth',
                    "body" => $message
                ]
            ];
            ProcessPushNotification::dispatch($notifData);
        }
    }

    public function startVideoCall(Request $request){
        $requestData = $this->getRequestData($request);
        $rules = [
            "pat_id"=>"required",
            "dr_id"=>"required",
            "booking_id"=>"required",
            "video_channel"=>"required",
        ];
        
        $validator = Validator::make($requestData, $rules);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Patients::messages.patients_add_validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $booking_id = $requestData['booking_id'];
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['dr_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        $requestData['booking_id'] = $this->securityLibObj->decrypt($requestData['booking_id']);
        $storeDetails = VideoConsulting::where([
                                            'dr_id' => $requestData['dr_id'],
                                            'pat_id' => $requestData['pat_id'],
                                            'booking_id' => $requestData['booking_id']
                                        ])
                                        ->first();
        if(empty($storeDetails)){
            $storeDetails = VideoConsulting::create([
                                'dr_id' => $requestData['dr_id'],
                                'pat_id' => $requestData['pat_id'],
                                'booking_id' => $requestData['booking_id'],
                                'video_channel' => $requestData['video_channel'],
                                'ip_address' => empty($requestData['ip_address'])?'NA':$requestData['ip_address']
                            ]);
        }
        
        if($storeDetails){
            //this fn get all the required details using booking ID
            $bookingDetails = $this->bookingsModelObj->getDataForStartVideo($requestData);

            $doctor = Users::where('user_id', $requestData['dr_id'])->first();
            $patient = Users::where('user_id', $requestData['pat_id'])->first();
            $dr_name = $doctor->user_firstname." ".$doctor->user_lastname;
            $pt_name = $patient->user_firstname." ".$patient->user_lastname;
            $userToken = UserDeviceToken::whereIn('user_id', [$requestData['pat_id']])
                                        ->where([ 'is_deleted' =>  Config::get('constants.IS_DELETED_NO') ])
                                        ->get();
            if($userToken){
                $tokens = [];
                foreach($userToken as $tk){
                    $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
                }
                $message = "Hi ".$pt_name.", Your video call is started with Dr. ".$dr_name.". Please join the call.";
                $notifData = [
                    "tokens" => $tokens,
                    "title" => $dr_name,
                    "body" => $message,
                    "extra" => [ 
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        "title" => $dr_name,
                        "body" => $message,
                        "booking_id" => $booking_id,
                        "video_channel" => $requestData['video_channel'],
                        "type" => "video-call-started",
                        "sound"=> "alert.mp3",

                        "booking_id"=> $bookingDetails[0]->booking_id,
                        "booking_date"=> $bookingDetails[0]->booking_date,
                        "booking_time"=> $bookingDetails[0]->booking_time,
                        "user_id"=> $bookingDetails[0]->user_id,
                        "pat_id"=> $bookingDetails[0]->pat_id,
                        "booking_status"=> $bookingDetails[0]->booking_status,
                        "patient_extra_notes"=> $bookingDetails[0]->patient_extra_notes,
                        "patient_appointment_status"=> $bookingDetails[0]->patient_appointment_status,
                        "clinic_id"=> $bookingDetails[0]->clinic_id,
                        "clinic_address_line1"=> $bookingDetails[0]->clinic_address_line1,
                        "clinic_address_line2"=> $bookingDetails[0]->clinic_address_line2,
                        "clinic_landmark"=> $bookingDetails[0]->clinic_landmark,
                        "clinic_pincode"=> $bookingDetails[0]->clinic_pincode,
                        "visit_id"=> $bookingDetails[0]->visit_id,
                        "visit_status"=> $bookingDetails[0]->visit_status,
                        "booking_reason"=> $bookingDetails[0]->booking_reason,
                        "pat_code"=> $bookingDetails[0]->pat_code,
                        "created_at"=> $bookingDetails[0]->created_at,
                        "visit_number"=> $bookingDetails[0]->visit_number,
                        "doc_profile_img"=> $bookingDetails[0]->doc_profile_img,
                        "pat_profile_img"=> $bookingDetails[0]->pat_profile_img,
                        "patient_appointment_status"=> $bookingDetails[0]->patient_appointment_status,
                        "appointment_type"=> $bookingDetails[0]->appointment_type,
                        "video_channel"=> $bookingDetails[0]->video_channel,
                        "appointment_reason"=> $bookingDetails[0]->appointment_reason,
                        "user_mobile"=> $bookingDetails[0]->user_mobile,
                        "doc_name"=> $bookingDetails[0]->doc_name,
                        "pat_name"=> $bookingDetails[0]->pat_name,
                        "doc_id"=> $bookingDetails[0]->doc_id,
                        "doctor_firstname"=> $bookingDetails[0]->doctor_firstname,
                        "doctor_lastname"=> $bookingDetails[0]->doctor_lastname,
                        "doctor_mobile"=> $bookingDetails[0]->doctor_mobile
                    ]
                ];
                ProcessPushNotification::dispatch($notifData);
            }
            $storeDetails->pat_id = $this->securityLibObj->encrypt($storeDetails->pat_id);
            $storeDetails->dr_id = $this->securityLibObj->encrypt($storeDetails->dr_id);
            $storeDetails->booking_id = $this->securityLibObj->encrypt($storeDetails->booking_id);
            $storeDetails->id = $this->securityLibObj->encrypt($storeDetails->id);
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $storeDetails,
                [],
                trans('Patients::messages.video_call_notification_send'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.video_call_notification_not_send'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    public function startDirectVideoCall(Request $request){
        $requestData = $this->getRequestData($request);
        $rules = [
            "pat_id"=>"required",
            "dr_id"=>"required",
            "video_channel"=>"required",
        ];
        
        $validator = Validator::make($requestData, $rules);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Patients::messages.patients_add_validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['dr_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        $storeDetails = DoctorPatientRelation::where([
                                            'user_id' => $requestData['dr_id'],
                                            'pat_id' => $requestData['pat_id'],
                                            'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                            'user_channel' => $requestData['video_channel']
                                        ])
                                        ->first();
        if(empty($storeDetails)){
            $update = DoctorPatientRelation::where([
                                'user_id' => $requestData['dr_id'],
                                'pat_id' => $requestData['pat_id'],
                                'is_deleted' => Config::get('constants.IS_DELETED_NO')
                            ])->update(['user_channel' => $requestData['video_channel']]);
            $storeDetails = DoctorPatientRelation::where([
                                            'user_id' => $requestData['dr_id'],
                                            'pat_id' => $requestData['pat_id'],
                                            'is_deleted' => Config::get('constants.IS_DELETED_NO')
                                        ])
                                        ->first();
        }
        
        if($storeDetails){
            $doctor = Users::where('user_id', $requestData['dr_id'])->first();
            $patient = Users::where('user_id', $requestData['pat_id'])->first();
            $dr_name = $doctor->user_firstname." ".$doctor->user_lastname;
            $pt_name = $patient->user_firstname." ".$patient->user_lastname;
            $userToken = UserDeviceToken::whereIn('user_id', [$requestData['pat_id']])
                                        ->where([ 'is_deleted' =>  Config::get('constants.IS_DELETED_NO') ])
                                        ->get();
            if($userToken){
                $tokens = [];
                foreach($userToken as $tk){
                    $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
                }
                $message = "Hi ".$pt_name.", Your video call is started with Dr. ".$dr_name.". Please join the call.";
                $notifData = [
                    "tokens" => $tokens,
                    "title" => $dr_name,
                    "body" => $message,
                    "extra" => [ 
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        "title" => $dr_name,
                        "body" => $message,
                        "video_channel" => $requestData['video_channel'],
                        "type" => "direct-video-call-started",
                        "sound"=> "alert.mp3"
                    ]
                ];
                ProcessPushNotification::dispatch($notifData);
            }
            $storeDetails->pat_id = $this->securityLibObj->encrypt($storeDetails->pat_id);
            $storeDetails->user_id = $this->securityLibObj->encrypt($storeDetails->user_id);
            $storeDetails->rel_id = $this->securityLibObj->encrypt($storeDetails->rel_id);
            $storeDetails->created_by = $this->securityLibObj->encrypt($storeDetails->created_by);
            $storeDetails->updated_by = $this->securityLibObj->encrypt($storeDetails->updated_by);
            $storeDetails->assign_by_doc = $this->securityLibObj->encrypt($storeDetails->assign_by_doc);
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.video_call_notification_send'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.video_call_notification_not_send'),
                $this->http_codes['HTTP_OK']
            );
        }
    }
}