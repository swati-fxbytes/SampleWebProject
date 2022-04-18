<?php

namespace App\Modules\Settings\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use Illuminate\Support\Facades\Validator;
use App\Traits\RestApi;
use Config;
use DB, Uuid, File;
use App\Libraries\FileLib;
use App\Modules\Settings\Models\Settings as Settings;
use App\Modules\Settings\Models\PrescriptionPdfSettings;

/**
 * SettingsController Class
 *
 * @package                SettingsController
 * @subpackage             Doctor SettingsController
 * @category               Model
 * @DateOfCreation         7 june 2018
 * @ShortDescription       This is controller which need to perform the options related to 
                           Setting of doctors
 */
class SettingsController extends Controller
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
        $this->settingsModelObj = new Settings();
        // Init security library object
        $this->securityLibObj = new SecurityLib();  
        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init File Library object
        $this->FileLib = new FileLib();
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      This function is responsible to get the templates list  
    * @return                Array of status and message
    */
    public function getLabtemplatesList(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = $request->user()->user_id;

        $settingsList =  $this->settingsModelObj->getList($requestData);
        if($settingsList)
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $settingsList,  
                [],
                trans('Settings::messages.lab_templates_list_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                [],
                trans('Settings::messages.lab_templates_list_failed'), 
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get medicine list
     * @return                Array of medicines and message
     */
    public function getMedicineListData(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $userId = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $medicationList['medicine_data'] = $this->settingsModelObj->getMedicineListData();
        $medicationList['dose_unit'] = $this->settingsModelObj->getDoseUnit();
        $medicationList['drug_type'] = $this->settingsModelObj->getAllDrugType();

        
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $medicationList, 
                [],
                trans('Visits::messages.medication_medicine_list_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get patient Medication record
     * @return                Array of medicines and message
     */
    public function getMedicineTemplate(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $requestData['user_id']= $request->user()->user_id;

        try{
            DB::beginTransaction();

            $templateRecord = $this->settingsModelObj->getMedicineTemplate($requestData);
            
            if($templateRecord){
                DB::commit();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'), 
                    $templateRecord, 
                    [],
                    trans('Settings::messages.medicine_templates_list_success'), 
                    $this->http_codes['HTTP_OK']
                );
            }else{
                DB::rollback();

                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'), 
                    [], 
                    [],
                    trans('Settings::messages.medicine_templates_list_failed'), 
                    $this->http_codes['HTTP_OK']
                );
            }                   
        } catch (\Exception $ex) {
            //user pat_consent_file unlink
            
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'SettingsController', 'getMedicineTemplate');
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
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to get patient Medication record
     * @return                Array of medicines and message
     */
    public function getMedicineTemplateList(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $requestData['user_id']= $request->user()->user_id;

        $requestData['pat_med_temp_id'] = $this->securityLibObj->decrypt($requestData['pat_med_temp_id']);
        $templateRecord = $this->settingsModelObj->getMedicineTemplateList($requestData);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'), 
            $templateRecord, 
            [],
            trans('Settings::messages.medicine_templates_list_success'), 
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        22 July 2018
     * @ShortDescription      This function is responsible to get patient's current Medication record
     * @return                Array of medicines and message
     */
    public function getMedicineData(Request $request){
        $requestData    = $this->getRequestData($request);
       
        $requestData['user_id']    = $request->user()->user_id;
        $requestData['user_type']  = $request->user()->user_type;
        $requestData['medicine_id']= $this->securityLibObj->decrypt($requestData['medicine_id']);

        $data = [];
        $medicineDetails = $this->medicationObj->getMedicineData($requestData);

        $data['medicine_data'] = isset($medicineDetails[0]) ? $medicineDetails[0]: $medicineDetails;
        $data['dose_unit'] = $this->medicationObj->getDoseUnit();

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $data, 
                [],
                trans('Visits::messages.medicine_data_fetched_successfully'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      This function is responsible to save templates  
    * @return                Array of status and message
    */
    public function store(Request $request)
    {
        $requestData = $this->getRequestData($request);
        unset($requestData['user_type']);
        unset($requestData['lab_temp_id']);
        
        // Validate request
        $validate = $this->LabTemplatesValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                $validate['errors'],
                trans('Settings::messages.lab_templates_store_failed'), 
                $this->http_codes['HTTP_OK']
            ); 
        }
        $requestData['user_id'] = $request->user()->user_id;

        if(isset($requestData['symptoms_data']) 
            && $requestData['symptoms_data'] != 'undefined' 
            && $requestData['symptoms_data'] != 'null') 
        { 
            $requestData['symptoms_data'] = $this->settingsModelObj->checkDataExist($requestData['symptoms_data'],'symptoms_data');
            $requestData['symptoms_data'] = json_encode($requestData['symptoms_data']);
        }else{ 
            $requestData['symptoms_data'] = NULL; 
        }
         
        if(isset($requestData['diagnosis_data']) 
            && $requestData['diagnosis_data'] != 'undefined' 
            && $requestData['diagnosis_data'] != 'null') 
        { 
            $requestData['diagnosis_data'] = $this->settingsModelObj->checkDataExist($requestData['diagnosis_data'],'diagnosis_data');
            $requestData['diagnosis_data'] = json_encode($requestData['diagnosis_data']);
        }else{ 
            $requestData['diagnosis_data'] = NULL;
        }
        
        if(isset($requestData['laboratory_test_data']) 
            && $requestData['laboratory_test_data'] != 'undefined' 
            && $requestData['laboratory_test_data'] != 'null') 
        {
            $requestData['laboratory_test_data'] = $this->settingsModelObj->checkDataExist($requestData['laboratory_test_data'],'laboratory_test_data',$requestData['user_id']);
            $requestData['laboratory_test_data'] = json_encode($requestData['laboratory_test_data']);
        }else{
            $requestData['laboratory_test_data'] = NULL;  
        }
        $templateCreated = $this->settingsModelObj->createLabTemplates($requestData);
        if(!empty($templateCreated))
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $templateCreated, 
                [],
                trans('Settings::messages.lab_templates_store_success'), 
                $this->http_codes['HTTP_OK']
            );

        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                [],
                trans('Settings::messages.lab_templates_store_failed'), 
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      Get a validator for an incoming Lab templates request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    private function medicineTemplatesValidator(array $data)
    {
        $error      = false;
        $errors     = [];
        $rules      = ['temp_name'     => 'required|unique:patient_medicine_templates,temp_name,null,pat_med_temp_id,is_deleted,'.Config::get('constants.IS_DELETED_NO') ];
        $validator = Validator::make($data, $rules); 
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      Get a validator for an incoming Lab templates request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    private function LabTemplatesValidator(array $data)
    {
        $error      = false;
        $errors     = [];
        $rules      = ['temp_name'     => 'required'];
        $validator = Validator::make($data, $rules); 
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      This function is responsible for Update template Data 
    * @param                 Array $request   
    * @return                Array of status and message
    */
    public function update(Request $request)
    {       
        $requestData = $this->getRequestData($request);
        unset($requestData['user_type']);
        
        // Validate request
        $validate = $this->LabTemplatesValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                $validate['errors'],
                trans('Settings::messages.lab_templates_store_failed'), 
                $this->http_codes['HTTP_OK']
            ); 
        }
        $requestData['user_id']     = $request->user()->user_id;

        // Update service detail in database 
        if(isset($requestData['symptoms_data']) 
            && $requestData['symptoms_data'] != 'undefined' 
            && $requestData['symptoms_data'] != 'null') 
        { 
            $requestData['symptoms_data'] = $this->settingsModelObj->checkDataExist($requestData['symptoms_data'],'symptoms_data');
            $requestData['symptoms_data'] = json_encode($requestData['symptoms_data']);
        }else{ 
            $requestData['symptoms_data'] = NULL; 
        }
         
        if(isset($requestData['diagnosis_data']) 
            && $requestData['diagnosis_data'] != 'undefined' 
            && $requestData['diagnosis_data'] != 'null') 
        { 
            $requestData['diagnosis_data'] = $this->settingsModelObj->checkDataExist($requestData['diagnosis_data'],'diagnosis_data');
            $requestData['diagnosis_data'] = json_encode($requestData['diagnosis_data']);
        }else{ 
            $requestData['diagnosis_data'] = NULL;
        }
        
        if(isset($requestData['laboratory_test_data']) 
            && $requestData['laboratory_test_data'] != 'undefined' 
            && $requestData['laboratory_test_data'] != 'null') 
        {
            $requestData['laboratory_test_data'] = $this->settingsModelObj->checkDataExist($requestData['laboratory_test_data'],'laboratory_test_data',$requestData['user_id']);
            $requestData['laboratory_test_data'] = json_encode($requestData['laboratory_test_data']);
        }else{
            $requestData['laboratory_test_data'] = NULL;  
        }
        $isTemplatesUpdate = $this->settingsModelObj->updateLabTemplates($requestData);

        // validate, is query executed successfully 
        if(!empty($isTemplatesUpdate))
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $isTemplatesUpdate, 
                [],
                trans('Settings::messages.lab_templates_update_success'), 
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                [],
                trans('Settings::messages.lab_templates_update_failed'), 
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      This function is responsible for delete template Data 
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {   
        $requestData = $this->getRequestData($request);
        $primaryKey = $this->settingsModelObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->settingsModelObj->isPrimaryIdExist($primaryId);
        
        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [], 
                [$primaryKey=> [trans('Settings::messages.lab_template_not_found')]],
                trans('Settings::messages.lab_template_not_found'), 
                $this->http_codes['HTTP_OK']
            ); 
        }
        $isLabTemplateDeleted=$this->settingsModelObj->deleteLabTemplate($primaryId);
        // validate, is query executed successfully 
        if($isLabTemplateDeleted === true)
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                [], 
                [],
                trans('Settings::messages.lab_templates_delete_success'), 
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                trans('Settings::messages.lab_templates_delete_failed'), 
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to save patient Medication record
     * @return                Array of medicines and message
     */
    public function saveMedicineTemplate(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $requestData['resource_type']   = Config::get('constants.RESOURCE_TYPE_WEB');   
        $requestData['user_id']         = $request->user()->user_id;

        $validate = $this->medicineTemplatesValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                $validate['errors'],
                trans('Settings::messages.medicine_templates_exist'), 
                $this->http_codes['HTTP_OK']
            ); 
        }

        try{
            DB::beginTransaction();

            $templateRecord = $this->settingsModelObj->saveMedicineTemplate($requestData);
            
            if($templateRecord){
                DB::commit();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'), 
                    $templateRecord, 
                    [],
                    trans('Settings::messages.medicine_templates_store_success'), 
                    $this->http_codes['HTTP_OK']
                );
            }else{
                DB::rollback();

                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'), 
                    [], 
                    [],
                    trans('Settings::messages.medicine_templates_store_failed'), 
                    $this->http_codes['HTTP_OK']
                );
            }                   
        } catch (\Exception $ex) {
            //user pat_consent_file unlink
            
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'SettingsController', 'saveMedicationTemplate');
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
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to save patient Medication record
     * @return                Array of medicines and message
     */
    public function updateMedicineTemplate(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $requestData['resource_type']   = Config::get('constants.RESOURCE_TYPE_WEB');   
        $requestData['user_id']         = $request->user()->user_id;
        try{
            DB::beginTransaction();

            $templateRecord = $this->settingsModelObj->updateMedicineTemplate($requestData);
            
            if($templateRecord){
                DB::commit();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'), 
                    $templateRecord, 
                    [],
                    trans('Settings::messages.medicine_templates_update_success'), 
                    $this->http_codes['HTTP_OK']
                );
            }else{
                DB::rollback();

                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'), 
                    [], 
                    [],
                    trans('Settings::messages.medicine_templates_update_failed'), 
                    $this->http_codes['HTTP_OK']
                );
            }                   
        } catch (\Exception $ex) {
            //user pat_consent_file unlink
            
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'SettingsController', 'updateMedicationTemplate');
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
     * @DateOfCreation        14 July 2018
     * @ShortDescription      This function is responsible to save patient Medication record
     * @return                Array of medicines and message
     */
    public function deleteMedicineTemplate(Request $request)
    {
        $requestData    = $this->getRequestData($request);

        $requestData['resource_type']   = Config::get('constants.RESOURCE_TYPE_WEB');   
        $requestData['user_id']         = $request->user()->user_id;
        try{
            DB::beginTransaction();

            $templateRecord = $this->settingsModelObj->deleteMedicineTemplate($requestData);
            
            if($templateRecord){
                DB::commit();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'), 
                    $templateRecord, 
                    [],
                    trans('Settings::messages.medicine_templates_delete_success'), 
                    $this->http_codes['HTTP_OK']
                );
            }else{
                DB::rollback();

                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'), 
                    [], 
                    [],
                    trans('Settings::messages.medicine_templates_delete_failed'), 
                    $this->http_codes['HTTP_OK']
                );
            }                   
        } catch (\Exception $ex) {
            //user pat_consent_file unlink
            
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'SettingsController', 'deleteMedicineTemplate');
            return $this->resultResponse(
                Config::get('restresponsecode.EXCEPTION'), 
                [], 
                [],
                $eMessage, 
                $this->http_codes['HTTP_OK']
            );
        }

    }

    public function updatePdfPrescriptionSetting(Request $request){
        echo public_path(Config::get('constants.DOCTOR_MEDIA_DEFAULT_PATH'));die;
        $requestData    = $this->getRequestData($request);
        $user_id = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        $rules      = [
            'pre_type'     => 'required'
        ];

        $validator = Validator::make($requestData, $rules); 
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                $errors,
                trans('Settings::messages.validation_error'), 
                $this->http_codes['HTTP_OK']
            );
        }

        $findExistingSetting = PrescriptionPdfSettings::where('user_id', $user_id)->first();

        if(array_key_exists('pre_header_image', $requestData) && !empty($requestData['pre_header_image'])){
            $media = $requestData['pre_header_image'];
            $fileType = $media->getClientOriginalExtension();
            $randomString = Uuid::generate();
            $filename = $randomString.'.'.$fileType;
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                $filePath = Config::get('constants.DOCTER_PRESCRIPRION_PATH').$filename;
                $upload  = $this->s3LibObj->putObject(file_get_contents($media), $filePath, 'public');
                if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                    $lab_report_file[] = $filename;
                }
            }else{
                $destination = Config::get('constants.DOCTER_PRESCRIPRION_PATH_LOCAL');
                $fileUpload = $this->FileLib->fileUpload($media, $destination);
                $fileType = NULL;
                if(isset($fileUpload['code']) && $fileUpload['code'] == Config::get('restresponsecode.SUCCESS')){
                    $getFileType = explode('.', $fileUpload['uploaded_file']);
                    $fileType    = $getFileType[1];
                    $requestData['pre_header_image'] = $fileUpload['uploaded_file'];
                    if($findExistingSetting && !empty($findExistingSetting->pre_header_image)){
                        unlink(storage_path('app/public/'.$destination.'/'.$findExistingSetting->pre_header_image));
                    }
                }
            }
        }

        if(array_key_exists('pre_logo', $requestData) && !empty($requestData['pre_logo'])){
            $media = $requestData['pre_logo'];
            $fileType = $media->getClientOriginalExtension();
            $randomString = Uuid::generate();
            $filename = $randomString.'.'.$fileType;
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                $filePath = Config::get('constants.DOCTER_PRESCRIPRION_PATH').$filename;
                $upload  = $this->s3LibObj->putObject(file_get_contents($media), $filePath, 'public');
                if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                    $lab_report_file[] = $filename;
                }
            }else{
                $destination = Config::get('constants.DOCTER_PRESCRIPRION_PATH_LOCAL');
                $fileUpload = $this->FileLib->fileUpload($media, $destination);
                $fileType = NULL;
                if(isset($fileUpload['code']) && $fileUpload['code'] == Config::get('restresponsecode.SUCCESS')){
                    $getFileType = explode('.', $fileUpload['uploaded_file']);
                    $fileType    = $getFileType[1];
                    $requestData['pre_logo'] = $fileUpload['uploaded_file'];
                    if($findExistingSetting && !empty($findExistingSetting->pre_logo)){
                        unlink(storage_path('app/public/'.$destination.'/'.$findExistingSetting->pre_logo));
                    }
                }
            }
        }

        $check = PrescriptionPdfSettings::updateOrCreate(['user_id' => $user_id], $requestData);
        if($check){
            if($check->user_id)
                $check->user_id = $this->securityLibObj->encrypt($check->user_id);
            if($check->id)
                $check->id = $this->securityLibObj->encrypt($check->id);

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $check, 
                [],
                trans('Settings::messages.pdf_prescription_setting_success'), 
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                [],
                trans('Settings::messages.pdf_prescription_setting_failed'), 
                $this->http_codes['HTTP_OK']
            );
        }  
    }

    public function getPdfPrescriptionSetting(Request $request){
        $requestData    = $this->getRequestData($request);
        $user_id = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        
        if(empty($user_id)){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'), 
                [], 
                $errors,
                trans('Settings::messages.validation_error'), 
                $this->http_codes['HTTP_OK']
            );
        }

        $findExistingSetting = PrescriptionPdfSettings::where('user_id', $user_id)->first()->toArray();

        if($findExistingSetting){
            
            if($findExistingSetting['user_id'])
                $findExistingSetting['user_id'] = $this->securityLibObj->encrypt($findExistingSetting['user_id']);
            if($findExistingSetting['id'])
                $findExistingSetting['id'] = $this->securityLibObj->encrypt($findExistingSetting['id']);

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                $findExistingSetting, 
                [],
                trans('Settings::messages.pdf_prescription_setting_success'), 
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'), 
                [], 
                [],
                trans('Settings::messages.pdf_prescription_setting_success'), 
                $this->http_codes['HTTP_OK']
            );
        }  
    }
}