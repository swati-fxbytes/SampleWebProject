<?php

namespace App\Modules\Visits\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use Config;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\S3Lib;
use App\Libraries\ExceptionLib;
use App\Modules\Visits\Models\LaboratoryTest;
use App\Modules\Visits\Models\LaboratoryReport;
use App\Modules\Visits\Models\LaboratoryReportMedia;
use App\Modules\Setup\Models\StaticDataConfig as StaticData;
use DB, Uuid;
use App\Libraries\FileLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use File;
use App\Traits\FxFormHandler;
use Response;

/**
 * LaboratoryTestController
 *
 * @package                ILD INDIA
 * @subpackage             LaboratoryTestController
 * @category               Controller
 * @DateOfCreation         02 July 2018
 * @ShortDescription       This controller to handle all the operation related to
                           Patients Domestic Factors
 */
class LaboratoryTestController extends Controller
{

    use SessionTrait, RestApi,FxFormHandler;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

         // Init LaboratoryTest model object
        $this->laboratoryTestModelObj = new LaboratoryTest();
        $this->laboratoryReportModelObj = new LaboratoryReport();

        // Init Utility Library object
        $this->utilityLibObj = new UtilityLib();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init General staticData Model Object
        $this->staticDataObj = new StaticData();

        // Init File Library object
        $this->FileLib = new FileLib();

        // Init S3 bucket library object
        $this->s3LibObj = new S3Lib();
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getLabortyTestVisitID(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $visitId            = $requestData['visit_id'];
        $patientId          = $requestData['pat_id'];
        $visitId                    = $this->securityLibObj->decrypt($visitId);

        $patientLaboratoryTest  = $this->laboratoryTestModelObj->getPatientLaboratoryTestRecord($visitId);
        $laboratoryTestRecordWithFectorKey = !empty($patientLaboratoryTest) && count($patientLaboratoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientLaboratoryTest),true), 'plt_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataConfigList()['laboratory_test'];
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');

        $finalCheckupRecords = [];
        $tempData = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $pltTypeIdKey => $pltValue) {
                $temp = [];
                $encryptPltTypeIdKey = $this->securityLibObj->encrypt($pltTypeIdKey);
                $laboratoryTestValuesData = ( array_key_exists($pltTypeIdKey, $laboratoryTestRecordWithFectorKey) ? $laboratoryTestRecordWithFectorKey[$pltTypeIdKey]['plt_value'] : '');
                $temp = [
                'showOnForm'=>true,
                'name' => 'plt_type_'.$encryptPltTypeIdKey,
                'title' => $pltValue['value'],
                'type' => $pltValue['input_type'],
                'value' => $pltValue['input_type'] === 'customcheckbox' ? [(string) $laboratoryTestValuesData] : $laboratoryTestValuesData,
                'cssClasses' => $pltValue['cssClasses'],
                'clearFix' => $pltValue['isClearfix'],

            ];
            if($pltValue['input_type'] === 'date'){
                $temp['format'] =  isset($pltValue['format']) ?  $pltValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
            }
            $tempData['plt_type_'.$encryptPltTypeIdKey.'_data'] = isset($pltValue['input_type_option']) && !empty($pltValue['input_type_option']) ? $this->getOption($pltValue['input_type'],$pltValue['input_type_option']):[] ;

            $finalCheckupRecords['form_'.$pltValue['type']]['fields'][] = $temp;
            $finalCheckupRecords['form_'.$pltValue['type']]['data'] = $tempData;
            $finalCheckupRecords['form_'.$pltValue['type']]['handlers'] = [];
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.laboratory_test_get_data_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        13 june 2018
     * @ShortDescription      This function is responsible for insert Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getOption($inputType = 'text',$inputTypeOption ='')
    {
        $returnResponse = [];
        if(empty($inputTypeOption)){
            return $returnResponse;
        }
        $staticDataKey = $this->staticDataObj->getStaticDataConfigList();
        $requestData = isset($staticDataKey[$inputTypeOption]) ? $staticDataKey[$inputTypeOption] : [];
        if(empty($requestData)){
            return $requestData;
        }
        switch($inputType){
            case 'customcheckbox':
            $returnResponse = array_map(function($tag) {
            return array(
                'value' => (string) $tag['id'],
                'label' => $tag['value']
            );
            }, $requestData);
            break;
            case 'select':
            $returnResponse = array_map(function($tag) {
            return array(
                'value' => $tag['id'],
                'label' => $tag['value']
            );
            }, $requestData);
            break;
        }
        return $returnResponse;
    }

    /**
     * @DateOfCreation        13 june 2018
     * @ShortDescription      This function is responsible for insert Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function addUpdateLabortyTest(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $encryptedVisitId               = $requestData['visit_id'];
        
        $requestData['user_id']         = $request->user()->user_id;

        $requestData['is_deleted']      = Config::get('constants.IS_DELETED_NO');
        $requestData['pat_id']          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['visit_id']        = $this->securityLibObj->decrypt($requestData['visit_id']);
        $visitId                        = $requestData['visit_id'];
        try{
            DB::beginTransaction();
            $patientLaboratoryTest  = $this->laboratoryTestModelObj->getPatientLaboratoryTestRecord($visitId);
            $laboratoryTestRecordWithFectorKey = !empty($patientLaboratoryTest) && count($patientLaboratoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientLaboratoryTest),true), 'plt_type_id'):[];

            $staticDataKey              = $this->staticDataObj->getStaticDataConfigList()['laboratory_test'];
            $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
            $insertData = [];
            $insertDataPlace = [];
            if(!empty($staticDataArrWithCustomKey)){
                foreach ($staticDataArrWithCustomKey as $pltTypeIdKey => $pltValue) {
                    $pltTypeIdEncrypted = $this->securityLibObj->encrypt($pltTypeIdKey);
                    $temp = [];
                    $pltTypeValue = isset($requestData['plt_type_'.$pltTypeIdEncrypted]) ? $requestData['plt_type_'.$pltTypeIdEncrypted] : '';
                    if($pltValue['input_type'] === 'date' && !empty($pltTypeValue)){
                        $dateResponse = $this->dateTimeLibObj->covertUserDateToServerType($pltTypeValue,'dd/mm/YY','Y-m-d');
                        if ($dateResponse["code"] == '5000') {
                                $errorResponseString = $dateResponse["message"];
                                $errorResponseArray = [$pltValue['value'] => [$dateResponse["message"]]];
                                $dataDbStatus = true;
                                break;
                        }
                        $pltTypeValue = $dateResponse['result'];
                    }
                     $temp = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            'plt_type_id'  =>  $pltTypeIdKey,
                            'plt_type'  =>  $pltValue['type'],
                            'plt_value'  =>  $pltTypeValue,
                            'ip_address'  =>  $requestData['ip_address'],
                            'resource_type'  =>  $requestData['resource_type'],
                    ];
                    $pltId = (isset($laboratoryTestRecordWithFectorKey[$pltTypeIdKey]['plt_id']) &&
                                !empty($laboratoryTestRecordWithFectorKey[$pltTypeIdKey]['plt_id']) )
                                ? $this->securityLibObj->decrypt($laboratoryTestRecordWithFectorKey[$pltTypeIdKey]['plt_id']) : '';
                   if(array_key_exists($pltTypeIdKey, $laboratoryTestRecordWithFectorKey) && !empty($pltId)){
                        $whereData =[];
                        $whereData = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            'plt_id'  =>  $pltId,
                        ];
                        $updateData = $this->laboratoryTestModelObj->updateLaboratoryTest($temp,$whereData);
                        if(!$updateData){
                            $dataDbStatus = true;
                            $dbCommitStatus = false;
                            break;
                        }else{
                            $dbCommitStatus = true;
                        }
                   }
                   if(!empty($pltTypeValue) && empty($pltId)){
                        $insertData[] = $temp;
                   }
                }

                if(isset($dataDbStatus) && $dataDbStatus){
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        (isset($errorResponseArray) ? $errorResponseArray:[]),
                        (isset($errorResponseString) ? $errorResponseString :'').trans('Visits::messages.laboratory_test_add_fail'),
                        $this->http_codes['HTTP_OK']
                    );
                }
                if(!empty($insertData)){
                    $addData = $this->laboratoryTestModelObj->addLaboratoryTest($insertData);
                    if(!$addData){
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Visits::messages.laboratory_test_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        DB::commit();
                        $dbCommitStatus = false;
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('Visits::messages.laboratory_test_add_success'),
                            $this->http_codes['HTTP_OK']
                        );
                    }
                }else if(!isset($dbCommitStatus)){
                    $dbCommitStatus = true;
                }

                if(isset($dbCommitStatus) && $dbCommitStatus){
                    DB::commit();
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        [],
                        [],
                        trans('Visits::messages.laboratory_test_add_success'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                 DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('Visits::messages.laboratory_test_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'LaboratoryTestController', 'addUpdateLabortyTest');
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
     * @DateOfCreation        21 May 2018
     * @ShortDescription      This function is responsible to get the WorkEnvironment add
     * @return                Array of status and message
     */
    public function store(Request $request)
    {
        $tableName   = $this->laboratoryReportModelObj->getTableName();
        $primaryKey  = $this->laboratoryReportModelObj->getTablePrimaryIdColumn();
        $requestData = $this->getRequestData($request);

        $user_id = $request->user()->user_id;

        $rules = [
            "visit_id" => "required",
            "pat_id" => "required",
            "lab_report_name" => "required",
            "resource_type" => "required",
            "ip_address" => "required"
        ];
        // If update method call
        if(array_key_exists('lab_report_file', $requestData) && !empty($requestData['lab_report_file'])){
            $rules['lab_report_file.*'] = 'required|max:50000|mimes:'.Config::get('constants.PATIENTS_LABORATORY_MIME_TYPE');
        }

        if(array_key_exists('lr_id', $requestData) && !empty($requestData['lr_id'])){
            $rules['lr_id'] = 'required';
        }

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails()) {
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
        
        try{
            $lab_report_file = [];
            if(array_key_exists('lab_report_file', $requestData) && !empty($requestData['lab_report_file'])){
                foreach ($requestData['lab_report_file'] as $media) {
                    $fileType = $media->getClientOriginalExtension();
                    $randomString = Uuid::generate();
                    $filename = $randomString.'.'.$fileType;
                    $environment = Config::get('constants.ENVIRONMENT_CURRENT');
                    if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                        $filePath = Config::get('constants.PATIENTS_LABORATORY_S3_PATH').$filename;
                        $upload  = $this->s3LibObj->putObject(file_get_contents($media), $filePath, 'public');
                        if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                            $lab_report_file[] = $filename;
                        }
                    }else{
                        $destination = Config::get('constants.PATIENTS_LABORATORY_PATH');
                        $fileUpload = $this->FileLib->fileUpload($media, $destination);
                        $fileType = NULL;
                        if(isset($fileUpload['code']) && $fileUpload['code'] == Config::get('restresponsecode.SUCCESS')){
                            $getFileType = explode('.', $fileUpload['uploaded_file']);
                            $fileType    = $getFileType[1];
                            $lab_report_file[] = $fileUpload['uploaded_file'];
                        }
                    }
                }
                unset($requestData['lab_report_file']);
            }

            $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
            $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
            if(array_key_exists('lr_id', $requestData) && !empty($requestData['lr_id'])){
                $requestData[$primaryKey] = $this->securityLibObj->decrypt($requestData[$primaryKey]);
                $whereData = [
                    'visit_id' => $requestData['visit_id'],
                    'pat_id' => $requestData['pat_id'],
                    $primaryKey => $requestData[$primaryKey]
                ];

                if(isset($requestData['lab_report_date'])){
                    $requestData['lab_report_date'] = date("Y-m-d", strtotime($requestData['lab_report_date']));
                }

                if(!empty($lab_report_file)){
                    $requestData['lab_report_file'] = $lab_report_file[0];
                }

                $storePrimaryId = $this->laboratoryReportModelObj->updateRequest($requestData,$whereData);
                foreach ($lab_report_file as $md){
                    $med = [
                        'lr_media_id' => $requestData['lr_id'],
                        'lr_media_name' => $md,
                        'created_by' => $user_id,
                        "updated_by" => $user_id
                    ];
                    LaboratoryReportMedia::create($med);
                }
                $successMessage = trans('Visits::messages.laboratory_report_update_successfull');
            }else{
                if(isset($requestData['lab_report_date'])){
                    $requestData['lab_report_date'] = date("Y-m-d", strtotime($requestData['lab_report_date']));
                }

                if(!empty($lab_report_file)){
                    $requestData['lab_report_file'] = $lab_report_file[0];
                }
                
                $storePrimaryId = $this->laboratoryReportModelObj->addRequest($requestData);
                foreach ($lab_report_file as $md){
                    $med = [
                        'lr_media_id' => $storePrimaryId,
                        'lr_media_name' => $md,
                        'created_by' => $user_id,
                        "updated_by" => $user_id
                    ];
                    LaboratoryReportMedia::create($med);
                }
                $successMessage = trans('Visits::messages.laboratory_report_add_successfull');
            }

             if($storePrimaryId){
                    $storePrimaryIdEncrypted = $this->securityLibObj->encrypt($storePrimaryId);
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        [$primaryKey => $storePrimaryIdEncrypted],
                        [],
                        $successMessage,
                        $this->http_codes['HTTP_OK']
                    );
                }else{
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        [],
                        trans('Visits::messages.laboratory_report_add_fail'),
                        $this->http_codes['HTTP_OK']
                    );
                }
        } catch (\Exception $ex) {
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'LaboratoryTestController', 'store');
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
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get WorkEnvironment Data by patId and visitId
     * @param                 encrypted integer $patId
     * @param                 encrypted integer $visitId
     * @return                Array of status and message
     */
    public function getListData(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $chartRequestData = $requestData;
        $getListDataResponse = $this->laboratoryReportModelObj->getListData($requestData);
        $chartRequestData['pageSize'] = '-1';
        $getChartDataResponse = $this->laboratoryReportModelObj->getListData($chartRequestData);
        $resultArrayTemp = [];
        $resultArray = [];
        $chartData = [];
        foreach($getChartDataResponse['result'] as $key => $data){
            $data->lab_report_date = date("d-m-Y", strtotime($data->lab_report_date));
            $date = $data->lab_report_date;
            $test = $data->lab_report_name;
            $resultArrayTemp[$date][$test] = $data;
            $resultArray[$date] = [];
        }
        foreach($resultArrayTemp as $k => $t){
            $resultArray[$k] = $t;
        }
        $chartData = (object) $resultArray;
        $getListDataResponse['chartData'] = $chartData;
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $getListDataResponse,
            [],
            trans('Visits::messages.laboratory_report_list_successfull'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * @DateOfCreation        04 June 2021
     * @ShortDescription      This function is responsible for get WorkEnvironment Data by patId and visitId
     * @param                 encrypted integer $patId
     * @param                 encrypted integer $visitId
     * @return                Array of status and message
     */
    public function getLabTestReportDetails(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        // Validate request
        $validator = Validator::make($requestData, [
            'lr_id' => 'required'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Visits::messages.validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }

        $requestData['lr_id'] = $this->securityLibObj->decrypt($requestData['lr_id']);
        
        if($requestData['lr_id'] == ''){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Visits::messages.invalid_lab_test_id_passed'),
                $this->http_codes['HTTP_OK']
            );
        }

        $labReport = $this->laboratoryReportModelObj->getLabReportDetails($requestData);
        if(count($labReport) > 0) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $labReport[0],
                [],
                trans('Visits::messages.data_found'),
                $this->http_codes['HTTP_OK']
            );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Visits::messages.invalid_lab_test_id_passed'),
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
    public function getLabReportMedia($imageName, Request $request)
    {
        $requestData = $this->getRequestData($request);
        $imageName = $this->securityLibObj->decrypt($imageName);
        $defaultPath = storage_path('app/public/'.Config::get('constants.DOCTOR_MEDIA_DEFAULT_PATH'));
        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            $path = Config::get('constants.PATIENTS_LABORATORY_S3_PATH').$imageName;
            if($this->s3LibObj->isFileExist($path)){
                return $response = $this->s3LibObj->getObject($path)['fileObject'];
            }
        }else{
            $imagePath = 'app/public/'.Config::get('constants.PATIENTS_LABORATORY_PATH');
            $path = storage_path($imagePath) ."/". $imageName;

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

    /**
    * @DateOfCreation        11 June 2018
    * @ShortDescription      This function is responsible for delete visit WorkEnvironment Data
    * @param                 Array $wefId
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $primaryKey = $this->laboratoryReportModelObj->getTablePrimaryIdColumn();
        $primaryId = $requestData[$primaryKey];
        $primaryId = $this->securityLibObj->decrypt($primaryId);
        $isPrimaryIdExist = $this->laboratoryReportModelObj->isPrimaryIdExist($primaryId);
        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey => [trans('Visits::messages.laboratory_report_not_exist')]],
                trans('Visits::messages.laboratory_report_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $user_id = $request->user()->user_id;
        $deleteDataResponse   = $this->laboratoryReportModelObj->doDeleteRequest($primaryId);
        if($deleteDataResponse){
            $storedMedia = LaboratoryReportMedia::select("id", "lr_media_name")
                                                            ->where([
                                                                'lr_media_id' => $primaryId,
                                                                'is_deleted' => Config::get('constants.IS_DELETED_NO')
                                                            ])
                                                            ->get();
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                foreach($storedMedia as $md){
                    $oldFilePath = Config::get('constants.PATIENTS_LABORATORY_S3_PATH').$md->lr_media_name;
                    if($this->s3LibObj->isFileExist($oldFilePath)){
                        $this->s3LibObj->deleteFile($oldFilePath);
                    }

                    $deleteMedia = LaboratoryReportMedia::find($md->id);
                    $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                    $deleteMedia->updated_by = $user_id;
                    $deleteMedia->save();
                }
            }else{
                $oldFilePath = storage_path('app/public/'.Config::get('constants.PATIENTS_LABORATORY_PATH'));
                foreach($storedMedia as $md){
                    if(!empty($md) && File::exists($oldFilePath.$md->media_name)) {
                        File::delete($oldFilePath.$md->media_name);
                    }
                    
                    $deleteMedia = LaboratoryReportMedia::find($md->id);
                    $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
                    $deleteMedia->updated_by = $user_id;
                    $deleteMedia->save();
                }
            }
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Visits::messages.laboratory_report_data_deleted'),
                $this->http_codes['HTTP_OK']
            );
        }
        return $this->resultResponse(
            Config::get('restresponsecode.ERROR'),
            [],
            [],
            trans('Visits::messages.laboratory_report_data_not_deleted'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
     * Deleting a selected media from storage.
     *
     * @param  \Illuminate\Http\Request  $doc_media_id
     * @return \Illuminate\Http\Response
     */
    public function deletelaboratoryReportMedia(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $user_id = $request->user()->user_id;

        // Validate request
        $validator = Validator::make($requestData, [
            'lr_media_name' => 'required',
            'lr_media_id' => 'required'
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

        $requestData['lr_media_name'] = $this->securityLibObj->decrypt($requestData['lr_media_name']);
        $requestData['lr_media_id'] = $this->securityLibObj->decrypt($requestData['lr_media_id']);
        
        $storedMedia = LaboratoryReportMedia::select("id", "lr_media_name")
                                                        ->where([
                                                            'lr_media_name' => $requestData['lr_media_name'],
                                                            'lr_media_id' => $requestData['lr_media_id']
                                                        ])
                                                        ->first();
        if(!empty($storedMedia)){
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');
            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
                
                $oldFilePath = Config::get('constants.PATIENTS_LABORATORY_S3_PATH').$storedMedia->lr_media_name;
                if($this->s3LibObj->isFileExist($oldFilePath)){
                    $this->s3LibObj->deleteFile($oldFilePath);
                }
            }else{
                $oldFilePath = storage_path('app/public/'.Config::get('constants.PATIENTS_LABORATORY_PATH'));
                if(!empty($storedMedia) && File::exists($oldFilePath.$storedMedia->lr_media_name)) {
                    File::delete($oldFilePath.$storedMedia->lr_media_name);
                }
            }

            $deleteMedia = LaboratoryReportMedia::find($storedMedia->id);
            $deleteMedia->is_deleted = Config::get('constants.IS_DELETED_YES');
            $deleteMedia->updated_by = $user_id;
            $deleteMedia->save();

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Patients::messages.report_deleted_success'),
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
    * @DateOfCreation        20 Aug 2018
    * @ShortDescription      This function is responsible for view / download laboratory test report files
    * @param                 Array $lr_id
    * @return
    */
    public function downloadFile(Request $request, $lr_id, $requestType='download'){
        $requestData = $this->getRequestData($request);
        $primaryId = $this->securityLibObj->decrypt($lr_id);
        $path = $this->laboratoryReportModelObj->getFilePath($primaryId);

        $path = empty($path) ? Config::get('constants.DEFAULT_IMAGE_NAME'): $path;

        if(!File::exists($path)){
            $path = public_path(Config::get('constants.DEFAULT_IMAGE_PATH'));
        }
        $filenewName    = File::name($path);
        $filenewName    .= '.'.File::extension($path);
        $type           = File::mimeType($path);
        $headers        = ['Content-Type: '.$type];

        if($requestType == 'view'){
            return response()->file($path, $headers);
        }else{
            return response()->download($path,$filenewName,$headers);
        }
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      get the lab templates list
    * @return                list of lab templates
    */
    public function getLabTemplate(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $requestData['user_id'] = $request->user()->user_id;
        $getLabTemplates   = $this->laboratoryReportModelObj->getLabTemplate($requestData);
        if($getLabTemplates){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $getLabTemplates,
                [],
                trans('Visits::messages.laboratory_temp_data_list_success'),
                $this->http_codes['HTTP_OK']
            );
        }
        return $this->resultResponse(
            Config::get('restresponsecode.ERROR'),
            [],
            [],
            trans('Visits::messages.laboratory_temp_data_list_failed'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
    * @DateOfCreation        13 Sep 2018
    * @ShortDescription      get the lab templates list
    * @return                list of lab templates
    */
    public function showLaboratoryReportBySymptoms(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $request->user()->user_id;

        $showLaboratoryReportBySymptoms   = $this->laboratoryReportModelObj->showLaboratoryReportBySymptoms($requestData);
        if($showLaboratoryReportBySymptoms){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $showLaboratoryReportBySymptoms,
                [],
                trans('Visits::messages.laboratory_temp_data_list_success'),
                $this->http_codes['HTTP_OK']
            );
        }
        return $this->resultResponse(
            Config::get('restresponsecode.ERROR'),
            [],
            [],
            trans('Visits::messages.laboratory_temp_data_list_failed'),
            $this->http_codes['HTTP_OK']
        );
    }
}
