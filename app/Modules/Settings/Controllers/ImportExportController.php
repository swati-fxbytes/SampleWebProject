<?php
namespace App\Modules\Settings\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Libraries\CsvLib;
use Illuminate\Support\Facades\Validator;
use App\Traits\RestApi;
use Config;
use DB;
use App\Modules\Settings\Models\ImportExport;
use App\Modules\Patients\Models\Patients;
use App\Modules\Doctors\Models\Medicines;
use App\Modules\Visits\Models\Medication;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;

/**
 * ImportExportController Class
 *
 * @package                RxHealth
 * @subpackage             ImportExportController
 * @category               Model
 * @DateOfCreation         13 Nov 2018
 * @ShortDescription       Controller deal with all types of import and export
 */
class ImportExportController extends Controller
{
     /**
     *  use restApi is trait for using function
     */
    use RestApi;
    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->http_codes = $this->http_status_codes();
        // Init Settings model object
        $this->importExportModelObj = new ImportExport();
        // Init security library object
        $this->securityLibObj = new SecurityLib();
        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init utility library object
        $this->utilityLibObj = new UtilityLib();
        $this->dateTimeLibObj = new DateTimeLib();

        // Init Csv library object
        $this->csvLibObj = new CsvLib();

         // Init Patient model object
        $this->patientModelObj = new Patients();

        // Init medicines model object
        $this->medicationModelObj = new Medication();

        // Init medicines model object
        $this->medicineModelObj = new Medicines();
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible import data from CSV
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function postImport(Request $request){

        if($request->hasFile('imported_file')){
            $rules = array(
                'imported_file' => 'required|mimes:csv,txt,xlsx,xls|max:40000',
                'import_type'   => 'required',
            );
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return  $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $validator->errors(),
                    [],
                    $this->http_codes['HTTP_OK']
                );
            }else{
                $fileToImport = $request->file('imported_file');
                $data = $this->csvLibObj->importData($fileToImport);
                $doctorUserId = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
                $ip_address   = $request->ip();
                $extraInfo = ['doctorUserId' => $doctorUserId, 'ip_address' => $ip_address];
                if($request->input('import_type') == Config::get('constants.IMPORT_PATIENT')){
                    $response = $this->insertPatients($data['result'], $extraInfo);
                }else if($request->input('import_type') == Config::get('constants.IMPORT_PRESCRIPTION')){
                    $response = $this->insertPrescriptions($data['result'], $extraInfo);
                }else{
                    return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [trans('Settings::messages.select_import_type')],
                            [],
                            $this->http_codes['HTTP_OK']
                        );
                }
                return  $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $response,
                    [],
                    trans('Settings::messages.import_success'),
                    $this->http_codes['HTTP_OK']
                );
            }
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                trans('Settings::messages.import_failed'),
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to update imported Patients data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function updatePatients($data, $extraInfo)
    {
        $doctorUserId = $extraInfo['doctorUserId'];
        $ip_address   = $extraInfo['ip_address'];
        $usersNotInserted = [];
        foreach ($data as $key => $value) {
            $user_gender    = (str_replace("'", "", $value['gender']) == 'M' ? Config::get('constants.USER_GENDER_MALE') : Config::get('constants.USER_GENDER_FEMALE'));
            $external_pat_number = (!empty($value['patient_number']) ? str_replace("'", "", $value['patient_number']) : '' );
            $pat_blood_group = $this->importExportModelObj->getBloodgroup(str_replace("'", "", $value['blood_group']));
            $pat_phone_num  = (!empty($value['secondary_mobile']) ? str_replace("'", "", $value['secondary_mobile']) : 'NA' );
            $pat_dob        = str_replace("'", "", date('Y-m-d',strtotime(str_replace("'", "", $value['date_of_birth']))));
            $patientData = array(
                                    'pat_blood_group'       => $pat_blood_group,
                                    'pat_dob'               => $pat_dob,
                                    'ip_address'            => $ip_address,
                                    'resource_type'         => Config::get('constants.RESOURCE_TYPE_WEB')
                                );
            $user_data = array('user_gender' => $user_gender);
            $patientId     = $this->importExportModelObj->updatePatients($patientData, $user_data, $external_pat_number);
        }
    }


    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to insert imported Patients data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function insertPatients($data, $extraInfo)
    {
        $doctorUserId = $extraInfo['doctorUserId'];
        $ip_address   = $extraInfo['ip_address'];
        $usersNotInserted = [];
        foreach ($data as $key => $value) {
            $patient_name   = explode(' ', $value['patient_name']);
            $user_firstname = (isset($patient_name[0]) ? str_replace("'", "", $patient_name[0]) : '');
            $user_lastname  = (isset($patient_name[1]) ? str_replace("'", "", $patient_name[1]) : '');
            $user_gender    = (str_replace("'", "", $value['gender']) == 'M' ? Config::get('constants.USER_GENDER_MALE') : Config::get('constants.USER_GENDER_FEMALE'));
            $user_mobile    = (!empty($value['mobile_number']) ? substr(str_replace("'", "", $value['mobile_number']), -10) : '' );
            $user_email     = (!empty($value['email_address']) ? str_replace("'", "", $value['email_address']) : '' );
            
            $external_pat_number = (!empty($value['patient_number']) ? str_replace("'", "", $value['patient_number']) : '' );
            $user_country_code = Config::get('constants.INDIA_COUNTRY_CODE');
            $usersData = array(
                                'user_type'             => Config::get('constants.USER_TYPE_PATIENT'),
                                'user_firstname'        => $user_firstname,
                                'user_lastname'         => $user_lastname,
                                'user_gender'           => $user_gender,
                                'user_mobile'           => $user_mobile,
                                'user_email'            => $user_email,
                                'user_country_code'     => $user_country_code,
                                'user_status'           => Config::get('constants.USER_STATUS_ACTIVE'),
                                'is_deleted'            => Config::get('constants.IS_DELETED_NO'),
                                'ip_address'            => $ip_address,
                            );
            $rules = [];
            if($usersData['user_email'] != ''){
                $rules['user_email'] =  'string|email|max:150|unique:users';
            }
            if($usersData['user_email'] != ''){
                $rules['user_mobile'] = 'required|numeric|regex:/[0-9]{10}/|unique:users';
            }
            $validator = Validator::make($usersData, $rules);
            if ($validator->fails()) {
                $usersNotInserted[] = $value;
            }else{
                // Check if the user data has already been imported before
                $patientAlreadyExists = $this->importExportModelObj->getUserIdByExternalPatNumber($external_pat_number);
                if($patientAlreadyExists == NULL){
                    $patientUserId = $this->patientModelObj->createPatientUser('users',$usersData);
                    if($patientUserId){
                        $pat_code = $this->importExportModelObj->getPatientCode($doctorUserId);
                        $pat_blood_group = $this->importExportModelObj->getBloodgroup(str_replace("'", "", $value['blood_group']));
                        $pat_phone_num  = (!empty($value['secondary_mobile']) ? str_replace("'", "", $value['secondary_mobile']) : 'NA' );
                        $pat_dob        = str_replace("'", "", date('Y-m-d',strtotime(str_replace("'", "", $value['date_of_birth']))));
                        $patientData = array(
                                                'user_id'               => $patientUserId,
                                                'pat_code'              => $pat_code,
                                                'pat_blood_group'       => $pat_blood_group,
                                                'pat_phone_num'         => $pat_phone_num,
                                                'pat_dob'               => $pat_dob,
                                                'pat_address_line1'     => str_replace("'", "", $value['address']),
                                                'pat_locality'          => str_replace("'", "", $value['locality']),
                                                'pat_pincode'           => str_replace("'", "", $value['pincode']),
                                                'pat_group_id'          => $this->importExportModelObj->getPatientGroup(str_replace("'", "", $value['groups']), $doctorUserId),
                                                'doc_ref_id'            => $this->importExportModelObj->getPatientRefferedby(str_replace("'", "", $value['referred_by']), $doctorUserId),
                                                'ip_address'            => $ip_address,
                                                'resource_type'         => Config::get('constants.RESOURCE_TYPE_WEB'),
                                                'external_pat_number'   => $external_pat_number,
                                            );
                        $patientId     = $this->patientModelObj->createPatient($patientData, $patientUserId);
                        if($patientId){
                            $this->importExportModelObj->doPatientDefaultEntries($doctorUserId, $patientUserId, $ip_address);
                        }
                    }
                }
            }
        }
        return $usersNotInserted;
    }

    /**
    * @DateOfCreation        14 Nov 2018
    * @ShortDescription      This function is responsible to insert imported Prescriptions data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function insertPrescriptions($data, $extraInfo)
    {
        $doctorUserId = $extraInfo['doctorUserId'];
        $ip_address   = $extraInfo['ip_address'];
        $prescriptionNotInserted = [];
        foreach ($data as $key => $value) {
            $userId         = '';
            $medicineId     = '';
            $medicationData = [];
            $medicineData   = [];
            $visitData      = [];
            $patient_name   = explode(' ', $value['patient_name']);
            $user_firstname = (isset($patient_name[0]) ? str_replace("'", "", $patient_name[0]) : '');
            $user_lastname  = (isset($patient_name[1]) ? str_replace("'", "", $patient_name[1]) : '');

            $medicineData['medicine_name']          = (isset($value['drug_name']) ? str_replace("'", "", $value['drug_name']) : '');
            $medicationData['medicine_start_date']  = (isset($value['date']) ? str_replace("'", "", $value['date']) : '');
            $medicationData['medicine_duration']    = (isset($value['duration']) ? str_replace("'", "", $value['duration']) : NULL);
            $medicationData['medicine_duration_unit']  = (isset($value['duration_unit']) ? $this->importExportModelObj->getMedicineDurationUnitId(str_replace("'", "", $value['duration_unit'])) : NULL);
            $medicationData['medicine_dose']        = (isset($value['morning']) ? str_replace("'", "", $value['morning']) : 0);
            $medicationData['medicine_dose2']       = (isset($value['afternoon']) ? str_replace("'", "", $value['afternoon']) : 0);
            $medicationData['medicine_dose3']       = (isset($value['night']) ? str_replace("'", "", $value['night']) : 0);
            $medicineData['drug_type']              = (isset($value['drug_type']) ? str_replace("'", "", $value['drug_type']) : '');
            $medicineData['dosage']                 = (isset($value['dosage']) ? str_replace("'", "", $value['dosage']) : '');
            $medicineData['dosage_unit']            = (isset($value['dosage_unit']) ? str_replace("'", "", $value['dosage_unit']) : '');

            $before_food            = (isset($value['before_food']) ? str_replace("'", "", $value['before_food']) : 0);
            $after_food             = (isset($value['after_food']) ? str_replace("'", "", $value['after_food']) : 0);
            $external_pat_number    = (!empty($value['patient_number']) ? str_replace("'", "", $value['patient_number']) : '' );
            
            if(!empty($external_pat_number)){
                $userId = $this->importExportModelObj->getUserIdByExternalPatNumber($external_pat_number);
            }
            if(!empty($userId) && !empty($medicationData['medicine_start_date'])){
                if(!empty($medicineData['medicine_name'])){
                    $drugDoseUnitId = $this->importExportModelObj->getDrugDoseUnitIdByName($medicineData['dosage_unit']);
                    $drugTypeId = $this->importExportModelObj->getDrugTypeIdByName($medicineData['drug_type']);
                    $paramMedicine = ['medicine_name'=>$medicineData['medicine_name'],'drug_dose_unit_id'=>$drugDoseUnitId, 'drug_type_id'=>$drugTypeId,'medicine_dose'=>$medicineData['dosage']];
                    $fetchMedicine = $this->medicineModelObj->getAllMedicines($paramMedicine,false);

                    if(count($fetchMedicine)>0){
                        $fetchMedicine = $this->utilityLibObj->changeObjectToArray($fetchMedicine);
                        $medicineId = current($fetchMedicine)['medicine_id'];
                    }else{
                        $medicineId = $this->medicineModelObj->saveMedicines($paramMedicine);
                    }
                    
                    $checkAndSetMedicineRelation = $this->importExportModelObj->checkAndSetMedicineRelation($medicineId,$doctorUserId);
                }

                if($before_food == 1 && $after_food == 0){
                    $medicationData['medicine_meal_opt'] = 1;
                }else if($before_food == 0 && $after_food == 1){
                    $medicationData['medicine_meal_opt'] = 2;
                }else{
                    $medicationData['medicine_meal_opt'] = 0;
                }

                $visitData['patientUserId'] = $this->securityLibObj->encrypt($userId);
                $visitData['user_id']       = $doctorUserId;
                $visitData['resource_type'] = Config::get('constants.RESOURCE_TYPE_WEB');
                $visitData['ip_address']    = $ip_address;
                $visitData['visit_date']    = $medicationData['medicine_start_date'];

                // get the visit id for the medication date if visit already exists for the patient
                $getPatientVisitId         = $this->importExportModelObj->checkVisitExistsForDate($userId,$medicationData['medicine_start_date']);
                if(!empty($getPatientVisitId)){
                    $medicationData['visit_id'] = $getPatientVisitId->visit_id;
                }else{
                    // create a follow up visit for the patient
                    $getPatientVisitId          = $this->importExportModelObj->getPatientFollowUpVisitId($visitData);
                    $medicationData['visit_id'] = $this->securityLibObj->decrypt($getPatientVisitId['visit_id']);
                }
                $medicationData['pat_id']               = $userId;
                $medicationData['medicine_id']          = $medicineId;
                $medicationData['medicine_dose_unit']   = $drugDoseUnitId;
                $medicationData['resource_type']        = Config::get('constants.RESOURCE_TYPE_WEB');
                $medicationData['ip_address']           = $ip_address;

                $rules = [];
                if($medicationData['pat_id'] != ''){
                    $rules['pat_id'] =  'required';
                }
                if($medicationData['medicine_id'] != ''){
                    $rules['medicine_id'] = 'required';
                }
                $validator = Validator::make($medicationData, $rules);
                if ($validator->fails()) {
                    $prescriptionNotInserted[] = $value;
                }else{
                    $pmhId = $this->medicationModelObj->saveMedicationData($medicationData);
                }
            }else{
                $prescriptionNotInserted[] = $value;
            }
        }
        return $prescriptionNotInserted;
	}
}