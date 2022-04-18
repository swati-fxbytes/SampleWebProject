<?php

namespace App\Modules\Visits\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use Config;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Libraries\UtilityLib;
use App\Modules\Visits\Models\SystemicExamination as SystemicExamination;
use App\Modules\Setup\Models\StaticDataConfigPsych as StaticData;
use App\Modules\Visits\Models\PastPsychiatricMedicalHistory;

/**
 * PsychiatricVisitsController
 *
 * @package                RxHealth
 * @subpackage             PsychiatricVisitsController
 * @category               Controller
 * @DateOfCreation         18 june 2018
 * @ShortDescription       This controller to handle all the operation related to
                           setup PsychiatricHistoryExamination
 **/
class PsychiatricVisitsController extends Controller
{

    use SessionTrait, RestApi;

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

         $this->utilityLibObj = new UtilityLib();

        // Init SystemicExamination Model Object
        $this->systemicExaminationObj = new SystemicExamination();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init exception library object
        $this->staticDataObj = new StaticData();
    }

    public function getPsychiatricVisitsData(Request $request){
        $requestData    = $this->getRequestData($request);
        $formName = $requestData['formName'];
        $result = [];
        switch ($formName) {
            case trans('Visits::messages.psychiatricFormPsychiatricHistoryExamination'):
                $result = $this->getPsychiatricHistoryExaminationDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormMentalStatusExaminationWithAbnormal'):
                $result = $this->getMentalStatusExaminationWithAbnormalDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormPsychiatricHistory'):
                $result = $this->getPsychiatricHistoryDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormFamilyHistory'):
                $result = $this->getFamilyHistoryDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormPersonalHistory'):
                $result = $this->getPersonalHistoryDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormMentalStatus'):
                $result = $this->getMentalStatusDetail($request);
                break;

            case trans('Visits::messages.psychiatricFormPastPsychiatricMedicalHistory'):
                $result = $this->getPastPsychiatricMedicalHistoryDetail($request);
                break;
        }
        return $result;
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getPsychiatricHistoryExaminationDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientPsychiatricHistoryExaminationTest  = $patModelObj->getPatientRecord($visitId);
        $patientPsychiatricHistoryExaminationWithFectorKey = !empty($patientPsychiatricHistoryExaminationTest) && count($patientPsychiatricHistoryExaminationTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientPsychiatricHistoryExaminationTest),true), 'ppmh_type_id'):[];
        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPsychiatricHistoryExamination']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $psychiatricHistoryExaminationTypeIdKey => $psychiatricHistoryExaminationValue) {
                $temp = [];
                $encryptpsychiatricHistoryExaminationTypeIdKey = $this->securityLibObj->encrypt($psychiatricHistoryExaminationTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($psychiatricHistoryExaminationTypeIdKey, $patientPsychiatricHistoryExaminationWithFectorKey) ? $patientPsychiatricHistoryExaminationWithFectorKey[$psychiatricHistoryExaminationTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'psychiatric_history_exam_type_'.$encryptpsychiatricHistoryExaminationTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $psychiatricHistoryExaminationValue['value'],
                    'type' => $psychiatricHistoryExaminationValue['input_type'],
                    'value' => $psychiatricHistoryExaminationValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $psychiatricHistoryExaminationValue) ? [$psychiatricHistoryExaminationValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $psychiatricHistoryExaminationValue) ? $psychiatricHistoryExaminationValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $psychiatricHistoryExaminationValue['cssClasses'],
                    'clearFix' => $psychiatricHistoryExaminationValue['isClearfix'],
                    'fieldName' => (!empty($psychiatricHistoryExaminationValue['field_name'])) ? $psychiatricHistoryExaminationValue['field_name'] : '',
                    'showHideTrigger' => (!empty($psychiatricHistoryExaminationValue['show_hide_trigger'])) ? $psychiatricHistoryExaminationValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($psychiatricHistoryExaminationValue['show_hide_trigger_id'])) ? $psychiatricHistoryExaminationValue['show_hide_trigger_id'] : '',

                ];
                if(isset($psychiatricHistoryExaminationValue['handlers'])  && !empty($psychiatricHistoryExaminationValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $psychiatricHistoryExaminationValue['handlers'];
                            $handler[$fieldName] =  $psychiatricHistoryExaminationValue['handlers'];
                            $handlerName[$fieldName] =  $psychiatricHistoryExaminationValue['field_name'];
                        }
                if($psychiatricHistoryExaminationValue['input_type'] === 'date'){
                    $temp['format'] =  isset($psychiatricHistoryExaminationValue['format']) ?  $psychiatricHistoryExaminationValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['psychiatric_history_exam_type_'.$encryptpsychiatricHistoryExaminationTypeIdKey.'_data'] = isset($psychiatricHistoryExaminationValue['input_type_option']) && !empty($psychiatricHistoryExaminationValue['input_type_option']) ? $psychiatricHistoryExaminationValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$psychiatricHistoryExaminationValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$psychiatricHistoryExaminationValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$psychiatricHistoryExaminationValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$psychiatricHistoryExaminationValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$psychiatricHistoryExaminationValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.psychiatric_history_exam_get_data_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getMentalStatusExaminationWithAbnormalDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientMentalStatusExaminationWithAbnormalTest  = $patModelObj->getPatientRecord($visitId);
        $patientMentalStatusExaminationWithAbnormalWithFectorKey = !empty($patientMentalStatusExaminationWithAbnormalTest) && count($patientMentalStatusExaminationWithAbnormalTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientMentalStatusExaminationWithAbnormalTest),true), 'ppmh_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getMentalStatusExaminationWithAbnormal']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $mentalStatusExaminationWithAbnormalTypeIdKey => $mentalStatusExaminationWithAbnormalValue) {
                $temp = [];
                $encryptmentalStatusExaminationWithAbnormalTypeIdKey = $this->securityLibObj->encrypt($mentalStatusExaminationWithAbnormalTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($mentalStatusExaminationWithAbnormalTypeIdKey, $patientMentalStatusExaminationWithAbnormalWithFectorKey) ? $patientMentalStatusExaminationWithAbnormalWithFectorKey[$mentalStatusExaminationWithAbnormalTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'mental_status_examination_with_abnormal_type_'.$encryptmentalStatusExaminationWithAbnormalTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $mentalStatusExaminationWithAbnormalValue['value'],
                    'type' => $mentalStatusExaminationWithAbnormalValue['input_type'],
                    'value' => $mentalStatusExaminationWithAbnormalValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $mentalStatusExaminationWithAbnormalValue) ? [$mentalStatusExaminationWithAbnormalValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $mentalStatusExaminationWithAbnormalValue) ? $mentalStatusExaminationWithAbnormalValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $mentalStatusExaminationWithAbnormalValue['cssClasses'],
                    'clearFix' => $mentalStatusExaminationWithAbnormalValue['isClearfix'],
                    'fieldName' => (!empty($mentalStatusExaminationWithAbnormalValue['field_name'])) ? $mentalStatusExaminationWithAbnormalValue['field_name'] : '',
                    'showHideTrigger' => (!empty($mentalStatusExaminationWithAbnormalValue['show_hide_trigger'])) ? $mentalStatusExaminationWithAbnormalValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($mentalStatusExaminationWithAbnormalValue['show_hide_trigger_id'])) ? $mentalStatusExaminationWithAbnormalValue['show_hide_trigger_id'] : '',

                ];
                if(isset($mentalStatusExaminationWithAbnormalValue['handlers'])  && !empty($mentalStatusExaminationWithAbnormalValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $mentalStatusExaminationWithAbnormalValue['handlers'];
                            $handler[$fieldName] =  $mentalStatusExaminationWithAbnormalValue['handlers'];
                            $handlerName[$fieldName] =  $mentalStatusExaminationWithAbnormalValue['field_name'];
                        }
                if($mentalStatusExaminationWithAbnormalValue['input_type'] === 'date'){
                    $temp['format'] =  isset($mentalStatusExaminationWithAbnormalValue['format']) ?  $mentalStatusExaminationWithAbnormalValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['mental_status_examination_with_abnormal_type_'.$encryptmentalStatusExaminationWithAbnormalTypeIdKey.'_data'] = isset($mentalStatusExaminationWithAbnormalValue['input_type_option']) && !empty($mentalStatusExaminationWithAbnormalValue['input_type_option']) ? $mentalStatusExaminationWithAbnormalValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$mentalStatusExaminationWithAbnormalValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$mentalStatusExaminationWithAbnormalValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$mentalStatusExaminationWithAbnormalValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$mentalStatusExaminationWithAbnormalValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$mentalStatusExaminationWithAbnormalValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.mental_status_examination_fetch_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getPsychiatricHistoryDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientPsychiatricHistoryTest  = $patModelObj->getPatientRecord($visitId);
        $patientPsychiatricHistoryWithFectorKey = !empty($patientPsychiatricHistoryTest) && count($patientPsychiatricHistoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientPsychiatricHistoryTest),true), 'ppmh_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPsychiatricHistory']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $psychiatricHistoryTypeIdKey => $psychiatricHistoryValue) {
                $temp = [];
                $encryptpsychiatricHistoryTypeIdKey = $this->securityLibObj->encrypt($psychiatricHistoryTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($psychiatricHistoryTypeIdKey, $patientPsychiatricHistoryWithFectorKey) ? $patientPsychiatricHistoryWithFectorKey[$psychiatricHistoryTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'psychiatric_history_type_'.$encryptpsychiatricHistoryTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $psychiatricHistoryValue['value'],
                    'type' => $psychiatricHistoryValue['input_type'],
                    'value' => $psychiatricHistoryValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $psychiatricHistoryValue) ? [$psychiatricHistoryValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $psychiatricHistoryValue) ? $psychiatricHistoryValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $psychiatricHistoryValue['cssClasses'],
                    'clearFix' => $psychiatricHistoryValue['isClearfix'],
                    'fieldName' => (!empty($psychiatricHistoryValue['field_name'])) ? $psychiatricHistoryValue['field_name'] : '',
                    'showHideTrigger' => (!empty($psychiatricHistoryValue['show_hide_trigger'])) ? $psychiatricHistoryValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($psychiatricHistoryValue['show_hide_trigger_id'])) ? $psychiatricHistoryValue['show_hide_trigger_id'] : '',

                ];
                if(isset($psychiatricHistoryValue['handlers'])  && !empty($psychiatricHistoryValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $psychiatricHistoryValue['handlers'];
                            $handler[$fieldName] =  $psychiatricHistoryValue['handlers'];
                            $handlerName[$fieldName] =  $psychiatricHistoryValue['field_name'];
                        }
                if($psychiatricHistoryValue['input_type'] === 'date'){
                    $temp['format'] =  isset($psychiatricHistoryValue['format']) ?  $psychiatricHistoryValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['psychiatric_history_type_'.$encryptpsychiatricHistoryTypeIdKey.'_data'] = isset($psychiatricHistoryValue['input_type_option']) && !empty($psychiatricHistoryValue['input_type_option']) ? $psychiatricHistoryValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$psychiatricHistoryValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$psychiatricHistoryValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$psychiatricHistoryValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$psychiatricHistoryValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$psychiatricHistoryValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.psychiatric_history_get_data_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getFamilyHistoryDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientFamilyHistoryTest  = $patModelObj->getPatientRecord($visitId);
        $patientFamilyHistoryWithFectorKey = !empty($patientFamilyHistoryTest) && count($patientFamilyHistoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientFamilyHistoryTest),true), 'ppmh_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getFamilyHistory']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $familyHistoryTypeIdKey => $familyHistoryValue) {
                $temp = [];
                $encryptfamilyHistoryTypeIdKey = $this->securityLibObj->encrypt($familyHistoryTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($familyHistoryTypeIdKey, $patientFamilyHistoryWithFectorKey) ? $patientFamilyHistoryWithFectorKey[$familyHistoryTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'family_history_type_'.$encryptfamilyHistoryTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $familyHistoryValue['value'],
                    'type' => $familyHistoryValue['input_type'],
                    'value' => $familyHistoryValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $familyHistoryValue) ? [$familyHistoryValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $familyHistoryValue) ? $familyHistoryValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $familyHistoryValue['cssClasses'],
                    'clearFix' => $familyHistoryValue['isClearfix'],
                    'fieldName' => (!empty($familyHistoryValue['field_name'])) ? $familyHistoryValue['field_name'] : '',
                    'showHideTrigger' => (!empty($familyHistoryValue['show_hide_trigger'])) ? $familyHistoryValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($familyHistoryValue['show_hide_trigger_id'])) ? $familyHistoryValue['show_hide_trigger_id'] : '',

                ];
                if(isset($familyHistoryValue['handlers'])  && !empty($familyHistoryValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $familyHistoryValue['handlers'];
                            $handler[$fieldName] =  $familyHistoryValue['handlers'];
                            $handlerName[$fieldName] =  $familyHistoryValue['field_name'];
                        }
                if($familyHistoryValue['input_type'] === 'date'){
                    $temp['format'] =  isset($familyHistoryValue['format']) ?  $familyHistoryValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['family_history_type_'.$encryptfamilyHistoryTypeIdKey.'_data'] = isset($familyHistoryValue['input_type_option']) && !empty($familyHistoryValue['input_type_option']) ? $familyHistoryValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$familyHistoryValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$familyHistoryValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$familyHistoryValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$familyHistoryValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$familyHistoryValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.family_history_get_data_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getPersonalHistoryDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientPersonalHistoryTest  = $patModelObj->getPatientRecord($visitId);
        $patientPersonalHistoryWithFectorKey = !empty($patientPersonalHistoryTest) && count($patientPersonalHistoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientPersonalHistoryTest),true), 'ppmh_type_id'):[];
        
        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPersonalHistory']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $personalHistoryTypeIdKey => $personalHistoryValue) {
                $temp = [];
                $encryptpersonalHistoryTypeIdKey = $this->securityLibObj->encrypt($personalHistoryTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($personalHistoryTypeIdKey, $patientPersonalHistoryWithFectorKey) ? $patientPersonalHistoryWithFectorKey[$personalHistoryTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'personal_history_type_'.$encryptpersonalHistoryTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $personalHistoryValue['value'],
                    'type' => $personalHistoryValue['input_type'],
                    'value' => $personalHistoryValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $personalHistoryValue) ? [$personalHistoryValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $personalHistoryValue) ? $personalHistoryValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $personalHistoryValue['cssClasses'],
                    'clearFix' => $personalHistoryValue['isClearfix'],
                    'fieldName' => (!empty($personalHistoryValue['field_name'])) ? $personalHistoryValue['field_name'] : '',
                    'showHideTrigger' => (!empty($personalHistoryValue['show_hide_trigger'])) ? $personalHistoryValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($personalHistoryValue['show_hide_trigger_id'])) ? $personalHistoryValue['show_hide_trigger_id'] : '',

                ];
                if(isset($personalHistoryValue['handlers'])  && !empty($personalHistoryValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $personalHistoryValue['handlers'];
                            $handler[$fieldName] =  $personalHistoryValue['handlers'];
                            $handlerName[$fieldName] =  $personalHistoryValue['field_name'];
                        }
                if($personalHistoryValue['input_type'] === 'date'){
                    $temp['format'] =  isset($personalHistoryValue['format']) ?  $personalHistoryValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['personal_history_type_'.$encryptpersonalHistoryTypeIdKey.'_data'] = isset($personalHistoryValue['input_type_option']) && !empty($personalHistoryValue['input_type_option']) ? $personalHistoryValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$personalHistoryValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$personalHistoryValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$personalHistoryValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$personalHistoryValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$personalHistoryValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.personal_history_fetched_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getMentalStatusDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);

        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientMentalStatusTest  = $patModelObj->getPatientRecord($visitId);
        $patientMentalStatusWithFectorKey = !empty($patientMentalStatusTest) && count($patientMentalStatusTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientMentalStatusTest),true), 'ppmh_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getMentalStatus']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $mentalStatusTypeIdKey => $mentalStatusValue) {
                $temp = [];
                $encryptmentalStatusTypeIdKey = $this->securityLibObj->encrypt($mentalStatusTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($mentalStatusTypeIdKey, $patientMentalStatusWithFectorKey) ? $patientMentalStatusWithFectorKey[$mentalStatusTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'mental_status_type_'.$encryptmentalStatusTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $mentalStatusValue['value'],
                    'type' => $mentalStatusValue['input_type'],
                    'value' => $mentalStatusValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $mentalStatusValue) ? [$mentalStatusValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $mentalStatusValue) ? $mentalStatusValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $mentalStatusValue['cssClasses'],
                    'clearFix' => $mentalStatusValue['isClearfix'],
                    'fieldName' => (!empty($mentalStatusValue['field_name'])) ? $mentalStatusValue['field_name'] : '',
                    'showHideTrigger' => (!empty($mentalStatusValue['show_hide_trigger'])) ? $mentalStatusValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($mentalStatusValue['show_hide_trigger_id'])) ? $mentalStatusValue['show_hide_trigger_id'] : '',

                ];
                if(isset($mentalStatusValue['handlers'])  && !empty($mentalStatusValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $mentalStatusValue['handlers'];
                            $handler[$fieldName] =  $mentalStatusValue['handlers'];
                            $handlerName[$fieldName] =  $mentalStatusValue['field_name'];
                        }
                if($mentalStatusValue['input_type'] === 'date'){
                    $temp['format'] =  isset($mentalStatusValue['format']) ?  $mentalStatusValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['mental_status_type_'.$encryptmentalStatusTypeIdKey.'_data'] = isset($mentalStatusValue['input_type_option']) && !empty($mentalStatusValue['input_type_option']) ? $mentalStatusValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$mentalStatusValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$mentalStatusValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$mentalStatusValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$mentalStatusValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$mentalStatusValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.mental_status_fetched_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function getPastPsychiatricMedicalHistoryDetail(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $encryptedVisitId   = $requestData['visit_id'];
        $patientId          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $visitId            = $this->securityLibObj->decrypt($encryptedVisitId);
        $patModelObj = new PastPsychiatricMedicalHistory();
        $patientPastPsychiatricMedicalHistoryTest  = $patModelObj->getPatientRecord($visitId);
        $patientPastPsychiatricMedicalHistoryWithFectorKey = !empty($patientPastPsychiatricMedicalHistoryTest) && count($patientPastPsychiatricMedicalHistoryTest)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientPastPsychiatricMedicalHistoryTest),true), 'ppmh_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPastPsychiatricMedicalHistory']);
        $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
        $finalCheckupRecords = [];
        $tempData = [];
        $handlers = [];
        $handler = [];
        $handlerName = [];
        if(!empty($staticDataArrWithCustomKey)){
            foreach ($staticDataArrWithCustomKey as $pastPsychiatricMedicalHistoryTypeIdKey => $pastPsychiatricMedicalHistoryValue) {
                $temp = [];
                $encryptpastPsychiatricMedicalHistoryTypeIdKey = $this->securityLibObj->encrypt($pastPsychiatricMedicalHistoryTypeIdKey);

                $symptomsTestValuesData = ( array_key_exists($pastPsychiatricMedicalHistoryTypeIdKey, $patientPastPsychiatricMedicalHistoryWithFectorKey) ? $patientPastPsychiatricMedicalHistoryWithFectorKey[$pastPsychiatricMedicalHistoryTypeIdKey]['ppmh_value'] : '');
                $fieldName = 'past_psychiatric_medical_history_type_'.$encryptpastPsychiatricMedicalHistoryTypeIdKey;
                $temp = [
                    'showOnForm'=>true,
                    'name' => $fieldName,
                    'title' => $pastPsychiatricMedicalHistoryValue['value'],
                    'type' => $pastPsychiatricMedicalHistoryValue['input_type'],
                    'value' => $pastPsychiatricMedicalHistoryValue['input_type'] === 'customcheckbox' ? ((!empty($symptomsTestValuesData)) ? [(string) $symptomsTestValuesData] : (array_key_exists('default_value', $pastPsychiatricMedicalHistoryValue) ? [$pastPsychiatricMedicalHistoryValue['default_value']] : [(string) $symptomsTestValuesData])) : ((!empty($symptomsTestValuesData)) ? $symptomsTestValuesData : (array_key_exists('default_value', $pastPsychiatricMedicalHistoryValue) ? $pastPsychiatricMedicalHistoryValue['default_value'] : $symptomsTestValuesData)),
                    'cssClasses' => $pastPsychiatricMedicalHistoryValue['cssClasses'],
                    'clearFix' => $pastPsychiatricMedicalHistoryValue['isClearfix'],
                    'fieldName' => (!empty($pastPsychiatricMedicalHistoryValue['field_name'])) ? $pastPsychiatricMedicalHistoryValue['field_name'] : '',
                    'showHideTrigger' => (!empty($pastPsychiatricMedicalHistoryValue['show_hide_trigger'])) ? $pastPsychiatricMedicalHistoryValue['show_hide_trigger'] : '',
                    'showHideTriggerId' => (!empty($pastPsychiatricMedicalHistoryValue['show_hide_trigger_id'])) ? $pastPsychiatricMedicalHistoryValue['show_hide_trigger_id'] : '',

                ];
                if(isset($pastPsychiatricMedicalHistoryValue['handlers'])  && !empty($pastPsychiatricMedicalHistoryValue['handlers']))
                        {
                            $handlers[$fieldName.'_handle'] =  $pastPsychiatricMedicalHistoryValue['handlers'];
                            $handler[$fieldName] =  $pastPsychiatricMedicalHistoryValue['handlers'];
                            $handlerName[$fieldName] =  $pastPsychiatricMedicalHistoryValue['field_name'];
                        }
                if($pastPsychiatricMedicalHistoryValue['input_type'] === 'date'){
                    $temp['format'] =  isset($pastPsychiatricMedicalHistoryValue['format']) ?  $pastPsychiatricMedicalHistoryValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }
                $tempData['past_psychiatric_medical_history_type_'.$encryptpastPsychiatricMedicalHistoryTypeIdKey.'_data'] = isset($pastPsychiatricMedicalHistoryValue['input_type_option']) && !empty($pastPsychiatricMedicalHistoryValue['input_type_option']) ? $pastPsychiatricMedicalHistoryValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$pastPsychiatricMedicalHistoryValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$pastPsychiatricMedicalHistoryValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$pastPsychiatricMedicalHistoryValue['type']]['handlers'] = $handlers;
                $finalCheckupRecords['form_'.$pastPsychiatricMedicalHistoryValue['type']]['handlerData']  = $handler;
                $finalCheckupRecords['form_'.$pastPsychiatricMedicalHistoryValue['type']]['handlerName']  = $handlerName;
            }
        }

        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalCheckupRecords,
                [],
                trans('Visits::messages.past_psychiatric_medical_history_fetched_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        21 May 2018
     * @ShortDescription      This function is responsible to get the WorkEnvironment add
     * @return                Array of status and message
     */
    public function store(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $encryptedVisitId               = $requestData['visit_id'];
        
        $requestData['user_id']         = $request->user()->user_id;
        $requestData['is_deleted']      = Config::get('constants.IS_DELETED_NO');
        $requestData['pat_id']          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['visit_id']        = $this->securityLibObj->decrypt($requestData['visit_id']);
        $visitId                        = $requestData['visit_id'];
        $primayKeyIdName = 'ppmh_id';
        $KeyIdName = 'ppmh_type_id';
        $keyValueName = 'ppmh_value';
        $patModelObj = new PastPsychiatricMedicalHistory();
        try{
            DB::beginTransaction();
            $patientExistsRecord  = $patModelObj->getPatientRecord($visitId);
            $patientExistsRecordWithFectorKey = !empty($patientExistsRecord) && count($patientExistsRecord)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientLaboratoryTest),true), 'ppmh_type_id'):[];

            $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPastPsychiatricMedicalHistory']);
            $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
            $insertData = [];
            $insertDataPlace = [];
            if(!empty($staticDataArrWithCustomKey)){
                foreach ($staticDataArrWithCustomKey as $typeIdKey => $typeValue) {
                    $typeIdEncrypted = $this->securityLibObj->encrypt($typeIdKey);
                    $temp = [];
                    $requestTypeValue = isset($requestData['past_psychiatric_medical_history_type_'.$typeIdEncrypted]) ? $requestData['past_psychiatric_medical_history_type_'.$typeIdEncrypted] : '';
                    if($typeValue['input_type'] === 'date' && !empty($requestTypeValue)){
                        $dateResponse = $this->dateTimeLibObj->covertUserDateToServerType($requestTypeValue,'dd/mm/YY','Y-m-d');
                        if ($dateResponse["code"] == '5000') {
                                $errorResponseString = $dateResponse["message"];
                                $errorResponseArray = [$typeValue['value'] => [$dateResponse["message"]]];
                                $dataDbStatus = true;
                                break;
                        }
                        $requestTypeValue = $dateResponse['result'];
                    }
                     $temp = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            $KeyIdName  =>  $typeIdKey,
                            $keyValueName  =>  $requestTypeValue,
                            'ip_address'  =>  $requestData['ip_address'],
                            'resource_type'  =>  $requestData['resource_type'],
                    ];
                    $primayKeyIdExists = (isset($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) &&
                                !empty($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) )
                                ? $this->securityLibObj->decrypt($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) : '';
                   if(array_key_exists($typeIdKey, $patientExistsRecordWithFectorKey) && !empty($primayKeyIdExists)){
                        $whereData =[];
                        $whereData = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            $primayKeyIdName  =>  $primayKeyIdExists,
                        ];
                        $updateData = $patModelObj->updateRecord($temp,$whereData);
                        if(!$updateData){
                            $dataDbStatus = true;
                            $dbCommitStatus = false;
                            break;
                        }else{
                            $dbCommitStatus = true;
                        }
                   }
                   if(!empty($requestTypeValue) && empty($primayKeyIdExists)){
                        $insertData[] = $temp;
                   }
                }

                if(isset($dataDbStatus) && $dataDbStatus){
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        (isset($errorResponseArray) ? $errorResponseArray:[]),
                        (isset($errorResponseString) ? $errorResponseString :'').trans('Visits::messages.psychiatric_patient_add_fail'),
                        $this->http_codes['HTTP_OK']
                    );
                }
                if(!empty($insertData)){
                    $addData = $patModelObj->addRecord($insertData);
                    if(!$addData){
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Visits::messages.psychiatric_patient_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        DB::commit();
                        $dbCommitStatus = false;
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('Visits::messages.psychiatric_patient_add_success'),
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
                        trans('Visits::messages.psychiatric_patient_add_success'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                 DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('Visits::messages.psychiatric_patient_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PsychiatricVisitsController', 'store');
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
     * @DateOfCreation        02 March 2021
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function savePsychiatricVisitsData(Request $request){
        $requestData    = $this->getRequestData($request);

        $formName = $requestData['formName'];
        $encryptedVisitId               = $requestData['visit_id'];

        $requestData['user_id']         = $request->user()->user_id;

        $requestData['is_deleted']      = Config::get('constants.IS_DELETED_NO');
        $requestData['pat_id']          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['visit_id']        = $this->securityLibObj->decrypt($requestData['visit_id']);
        $visitId                        = $requestData['visit_id'];
        $primayKeyIdName = 'ppmh_id';
        $KeyIdName = 'ppmh_type_id';
        $keyValueName = 'ppmh_value';
        $patModelObj = new PastPsychiatricMedicalHistory();
        try{
            DB::beginTransaction();
            $patientExistsRecord  = $patModelObj->getPatientRecord($visitId);
            $patientExistsRecordWithFectorKey = !empty($patientExistsRecord) && count($patientExistsRecord)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientExistsRecord),true), 'ppmh_type_id'):[];

            switch ($formName) {
                case trans('Visits::messages.psychiatricFormPsychiatricHistoryExamination'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPsychiatricHistoryExamination']);
                    $labelPrefix = 'psychiatric_history_exam_type_';
                    $successMsg = trans('Visits::messages.psychiatric_patient_history_and_exam_success');
                    $errorMsg = trans('Visits::messages.psychiatric_patient_history_and_exam_fail');
                    break;

                case trans('Visits::messages.psychiatricFormMentalStatusExaminationWithAbnormal'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getMentalStatusExaminationWithAbnormal']);
                    $labelPrefix = 'mental_status_examination_with_abnormal_type_';
                    $successMsg = trans('Visits::messages.mental_status_examination_save_success');
                    $errorMsg = trans('Visits::messages.mental_status_examination_save_failed');
                    break;

                case trans('Visits::messages.psychiatricFormPsychiatricHistory'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPsychiatricHistory']);
                    $labelPrefix = 'psychiatric_history_type_';
                    $successMsg = trans('Visits::messages.psychiatric_history_save_success');
                    $errorMsg = trans('Visits::messages.psychiatric_history_save_fail');
                    break;

                case trans('Visits::messages.psychiatricFormFamilyHistory'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getFamilyHistory']);
                    $labelPrefix = 'family_history_type_';
                    $successMsg = trans('Visits::messages.family_history_save_success');
                    $errorMsg = trans('Visits::messages.family_history_save_failed');
                    break;

                case trans('Visits::messages.psychiatricFormPersonalHistory'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPersonalHistory']);
                    $labelPrefix = 'personal_history_type_';
                    $successMsg = trans('Visits::messages.personal_history_save_success');
                    $errorMsg = trans('Visits::messages.personal_history_save_fail');
                    break;

                case trans('Visits::messages.psychiatricFormMentalStatus'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getMentalStatus']);
                    $labelPrefix = 'mental_status_type_';
                    $successMsg = trans('Visits::messages.mental_status_details_save_success');
                    $errorMsg = trans('Visits::messages.mental_status_details_save_fail');
                    break;

                case trans('Visits::messages.psychiatricFormPastPsychiatricMedicalHistory'):
                    $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getPastPsychiatricMedicalHistory']);
                    $labelPrefix = 'past_psychiatric_medical_history_type_';
                    $successMsg = trans('Visits::messages.past_psychiatric_medical_history_save_success');
                    $errorMsg = trans('Visits::messages.past_psychiatric_medical_history_save_fail');
                    break;
            }
            
            $staticDataArrWithCustomKey = $this->utilityLibObj->changeArrayKey($staticDataKey, 'id');
            $insertData = [];
            $insertDataPlace = [];
            if(!empty($staticDataArrWithCustomKey)){
                foreach ($staticDataArrWithCustomKey as $typeIdKey => $typeValue) {
                    $typeIdEncrypted = $this->securityLibObj->encrypt($typeIdKey);
                    $temp = [];
                    $requestTypeValue = isset($requestData[$labelPrefix.$typeIdEncrypted]) ? $requestData[$labelPrefix.$typeIdEncrypted] : '';
                    if($typeValue['input_type'] === 'date' && !empty($requestTypeValue)){
                        $dateResponse = $this->dateTimeLibObj->covertUserDateToServerType($requestTypeValue,'dd/mm/YY','Y-m-d');
                        if ($dateResponse["code"] == '5000') {
                            $errorResponseString = $dateResponse["message"];
                            $errorResponseArray = [$typeValue['value'] => [$dateResponse["message"]]];
                            $dataDbStatus = true;
                            break;
                        }
                        $requestTypeValue = $dateResponse['result'];
                    }
                    $temp = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            $KeyIdName  =>  $typeIdKey,
                            $keyValueName  =>  $requestTypeValue,
                            'ip_address'  =>  $requestData['ip_address'],
                            'resource_type'  =>  $requestData['resource_type'],
                    ];
                    $primayKeyIdExists = (isset($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) &&
                                !empty($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) )
                                ? $this->securityLibObj->decrypt($patientExistsRecordWithFectorKey[$typeIdKey][$primayKeyIdName]) : '';
                    if(array_key_exists($typeIdKey, $patientExistsRecordWithFectorKey) && !empty($primayKeyIdExists)){
                        $whereData = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            $primayKeyIdName  =>  $primayKeyIdExists,
                        ];
                        $updateData = $patModelObj->updateDetails($temp,$whereData);
                        if(!$updateData){
                            $dataDbStatus = true;
                            $dbCommitStatus = false;
                            break;
                        }else{
                            $dbCommitStatus = true;
                        }
                    }
                    if(!empty($requestTypeValue) && empty($primayKeyIdExists)){
                        $insertData[] = $temp;
                    }
                }
                if(isset($dataDbStatus) && $dataDbStatus){
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        (isset($errorResponseArray) ? $errorResponseArray:[]),
                        (isset($errorResponseString) ? $errorResponseString :'').$errorMsg,
                        $this->http_codes['HTTP_OK']
                    );
                }
                if(!empty($insertData)){
                    $addData = $patModelObj->addDetails($insertData);
                    if(!$addData){
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            $errorMsg,
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        DB::commit();
                        $dbCommitStatus = false;
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            $successMsg,
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
                        $successMsg,
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                 DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            $errorMsg,
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PsychiatricVisitsController', 'storePsychiatricHistoryExaminationDetail');
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
