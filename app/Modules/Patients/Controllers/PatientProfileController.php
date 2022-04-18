<?php

namespace App\Modules\Patients\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use Config;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Libraries\DateTimeLib;
use App\Modules\Auth\Models\Auth as Users;
use DB;
use File;
use Response;
use App\Libraries\UtilityLib;
use App\Modules\Patients\Models\Patients;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Modules\Visits\Models\MedicalHistory;
use App\Modules\Visits\Models\Diagnosis;
use App\Modules\Visits\Models\Vitals;
use App\Modules\Visits\Models\PhysicalExaminations;
use App\Modules\Visits\Models\Spirometries;
use App\Libraries\S3Lib;
use App\Modules\Visits\Models\Symptoms as Symptoms;

/**
 * PatientProfileController
 *
 * @package                ILD INDIA
 * @subpackage             PatientProfileController
 * @category               Controller
 * @DateOfCreation         13 june 2018
 * @ShortDescription       This controller to handle all the operation related to
                           Patients profile
 */
class PatientProfileController extends Controller
{

    use SessionTrait, RestApi;

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
    public function __construct(Request $request)
    {
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init Patient model object
        $this->patientModelObj = new Patients();

        // Init User model object
        $this->userModelObj = new Users();

        // Init DateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

         // Init Utility Library object
        $this->UtilityLib = new UtilityLib();

        // S3 library object
        $this->s3LibObj = new S3Lib();

        $this->method = $request->method();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init StaticDataConfig model object
        $this->staticDataObj = new StaticDataConfig();

        // Init MedicalHistory model object
        $this->medicalHistoryObj = new MedicalHistory();

        // Init MedicalHistory model object
        $this->diagnosisObj = new Diagnosis();

        // Init vitals model object
        $this->vitalsObj = new Vitals();

        // Init PhysicalExaminations model object
        $this->physicalExaminationsObj = new PhysicalExaminations();

        // Init Spirometry model object
        $this->spirometryModelObj = new Spirometries();

        // Init Symptoms Model Object
        $this->symptomsObj = new Symptoms();
    }

    /**
     * @DateOfCreation        13 june 2018
     * @ShortDescription      This function is responsible for insert Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getDashboard(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['pat_id']     = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['user_id']    = $request->user()->user_id;

        $data['user_type']  = $request->user()->user_type;
        $data['pat_id']     = $requestData['pat_id'];
        $data['user_id']    = $requestData['user_id']; //doctor_ids

        if(empty($data['pat_id']) || empty( $data['user_id'])){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.chart_get_data_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
        try{
            $extraAssociatedDisorder = [];
            $dataAssociatedDisorder = array_merge($data,$extraAssociatedDisorder);
            $getAssociatedDisorder = $this->getAssociatedDisorder($dataAssociatedDisorder);

            $extraFinalDiagnosis = [];
            $dataFinalDiagnosis = array_merge($data,$extraFinalDiagnosis);
            $getFinalDiagnosis = $this->getFinalDiagnosis($dataFinalDiagnosis);

            $extraWeightVitals = [];
            $dataWeightVitals = array_merge($data,$extraWeightVitals);
            $getWeightVitals = $this->getWeightVitals($dataWeightVitals);

            $extraPulseVitals = [];
            $dataPulseVitals = array_merge($data,$extraPulseVitals);
            $getPulseVitals = $this->getPulseVitals($dataPulseVitals);

            $extraTemperatureVitals = [];
            $dataTemperatureVitals = array_merge($data,$extraTemperatureVitals);
            $getTemperatureVitals = $this->getTemperatureVitals($dataTemperatureVitals);

            $extraSPO2Vitals = [];
            $dataSPO2Vitals = array_merge($data,$extraSPO2Vitals);
            $getSPO2Vitals = $this->getSPO2Vitals($dataSPO2Vitals);

            $extraBpSysVitals = [];
            $dataBpSysVitals = array_merge($data,$extraBpSysVitals);
            $getBpSysVitals = $this->getBpSysVitals($dataBpSysVitals);

            $extraBpDiaVitals = [];
            $dataBpDiaVitals = array_merge($data,$extraBpDiaVitals);
            $getBpDiaVitals = $this->getBpDiaVitals($dataBpDiaVitals);

            $extraRespiratoryRateVitals = [];
            $dataRespiratoryRateVitals = array_merge($data,$extraRespiratoryRateVitals);
            $getRespiratoryRateVitals = $this->getRespiratoryRateVitals($dataRespiratoryRateVitals);

            $extraBMIPhysicalExaminations = [];
            $dataBMIPhysicalExaminations = array_merge($data,$extraBMIPhysicalExaminations);
            $getBMIPhysicalExaminations = $this->getBMIPhysicalExaminations($dataBMIPhysicalExaminations);

            $extraVitals = [];
            $extraVitals = array_merge($data,$extraVitals);
            $getVitals = $this->getVitals($extraVitals);

            $extraFEV1Vitals = [];
            $dataFev1Vitals  = array_merge($data,$extraFEV1Vitals);
            $getFev1Vitals   = $this->getFev1Vitals($dataFev1Vitals);

            $extraFVCVitals = [];
            $dataFVCVitals  = array_merge($data,$extraFVCVitals);
            $getFVCVitals   = $this->getFvcVitals($dataFVCVitals);

            $extraFEV1FVCVitals = [];
            $dataFEV1FVCVitals  = array_merge($data,$extraFEV1FVCVitals);
            $getFEV1FVCVitals   = $this->getFev1FvcVitals($dataFEV1FVCVitals);

            $extraSugurLevelVitals = [];
            $dataSugurLevelVitals  = array_merge($data,$extraSugurLevelVitals);
            $getSugarLevelVitals   = $this->getSugarLevelVitals($dataSugurLevelVitals);

            $extraJvpVitals = [];
            $dataJvpVitals  = array_merge($data,$extraJvpVitals);
            $getJvpVitals   = $this->getJvpVitals($dataJvpVitals);

            $extraPedelEdemaVitals = [];
            $dataPedelEdemaVitals  = array_merge($data,$extraPedelEdemaVitals);
            $getPedelEdemaVitals   = $this->getPedelEdemaVitals($dataPedelEdemaVitals);

            $finalData = [
                        'vitals'                => $getVitals,
                        'associated_disorder'   => $getAssociatedDisorder,
                        'final_diagnosis'       => $getFinalDiagnosis,
                        'weight'                => $getWeightVitals,
                        'pulse'                 => $getPulseVitals,
                        'spo2'                  => $getSPO2Vitals,
                        'bp_sys'                => $getBpSysVitals,
                        'bp_dia'                => $getBpDiaVitals,
                        'bmi'                   => $getBMIPhysicalExaminations,
                        'respiratory_rate'      => $getRespiratoryRateVitals,
                        'fev1'                  => $getFev1Vitals,
                        'fvc'                   => $getFVCVitals,
                        'fev1_fvc'              => $getFEV1FVCVitals,
                        'sugar_level'           => $getSugarLevelVitals,
                        'jvp'                   => $getJvpVitals,
                        'pedel_edema'           => $getPedelEdemaVitals,
                        'temperature'           => $getTemperatureVitals
            ];

            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $finalData,
                    [],
                    trans('Patients::messages.chart_get_data_successfull'),
                    $this->http_codes['HTTP_OK']
                );
        } catch (\Exception $ex) {
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PatientProfileController', 'getDashboard');
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
     * @DateOfCreation        23 june 2021
     * @ShortDescription      This function is responsible for insert Patient Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getV1Dashboard(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['pat_id']     = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['user_id']    = $request->user()->user_id;
        
        $data['user_type']  = $request->user()->user_type;
        $data['pat_id']     = $requestData['pat_id'];
        $data['user_id']    = $requestData['user_id']; //doctor_ids

        if(empty($data['pat_id']) || empty( $data['user_id'])){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Patients::messages.chart_get_data_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
        try{
            $extraAssociatedDisorder = [];
            $dataAssociatedDisorder = array_merge($data,$extraAssociatedDisorder);
            $getAssociatedDisorder = $this->getV1AssociatedDisorder($dataAssociatedDisorder);

            $extraFinalDiagnosis = [];
            $dataFinalDiagnosis = array_merge($data,$extraFinalDiagnosis);
            $getFinalDiagnosis = $this->getV1FinalDiagnosis($dataFinalDiagnosis);

            $extraWeightVitals = [];
            $dataWeightVitals = array_merge($data,$extraWeightVitals);
            $getWeightVitals = $this->getV1WeightVitals($dataWeightVitals);

            $extraPulseVitals = [];
            $dataPulseVitals = array_merge($data,$extraPulseVitals);
            $getPulseVitals = $this->getV1PulseVitals($dataPulseVitals);

            $extraTemperatureVitals = [];
            $dataTemperatureVitals = array_merge($data,$extraTemperatureVitals);
            $getTemperatureVitals = $this->getV1TemperatureVitals($dataTemperatureVitals);

            $extraSPO2Vitals = [];
            $dataSPO2Vitals = array_merge($data,$extraSPO2Vitals);
            $getSPO2Vitals = $this->getV1SPO2Vitals($dataSPO2Vitals);

            $extraBpSysVitals = [];
            $dataBpSysVitals = array_merge($data,$extraBpSysVitals);
            $getBpSysVitals = $this->getV1BpSysVitals($dataBpSysVitals);

            $extraBpDiaVitals = [];
            $dataBpDiaVitals = array_merge($data,$extraBpDiaVitals);
            $getBpDiaVitals = $this->getV1BpDiaVitals($dataBpDiaVitals);

            $extraRespiratoryRateVitals = [];
            $dataRespiratoryRateVitals = array_merge($data,$extraRespiratoryRateVitals);
            $getRespiratoryRateVitals = $this->getV1RespiratoryRateVitals($dataRespiratoryRateVitals);

            $extraBMIPhysicalExaminations = [];
            $dataBMIPhysicalExaminations = array_merge($data,$extraBMIPhysicalExaminations);
            $getBMIPhysicalExaminations = $this->getV1BMIPhysicalExaminations($dataBMIPhysicalExaminations);

            $extraVitals = [];
            $extraVitals = array_merge($data,$extraVitals);
            $getVitals = $this->getV1Vitals($extraVitals);

            $extraFEV1Vitals = [];
            $dataFev1Vitals  = array_merge($data,$extraFEV1Vitals);
            $getFev1Vitals   = $this->getV1Fev1Vitals($dataFev1Vitals);

            $extraFVCVitals = [];
            $dataFVCVitals  = array_merge($data,$extraFVCVitals);
            $getFVCVitals   = $this->getV1FvcVitals($dataFVCVitals);

            $extraFEV1FVCVitals = [];
            $dataFEV1FVCVitals  = array_merge($data,$extraFEV1FVCVitals);
            $getFEV1FVCVitals   = $this->getV1Fev1FvcVitals($dataFEV1FVCVitals);

            $extraSugurLevelVitals = [];
            $dataSugurLevelVitals  = array_merge($data,$extraSugurLevelVitals);
            $getSugarLevelVitals   = $this->getV1SugarLevelVitals($dataSugurLevelVitals);

            $extraJvpVitals = [];
            $dataJvpVitals  = array_merge($data,$extraJvpVitals);
            $getJvpVitals   = $this->getV1JvpVitals($dataJvpVitals);

            $extraPedelEdemaVitals = [];
            $dataPedelEdemaVitals  = array_merge($data,$extraPedelEdemaVitals);
            $getPedelEdemaVitals   = $this->getV1PedelEdemaVitals($dataPedelEdemaVitals);

            $finalData = [
                        'vitals'                => $getVitals,
                        'associated_disorder'   => $getAssociatedDisorder,
                        'final_diagnosis'       => $getFinalDiagnosis,
                        'weight'                => $getWeightVitals,
                        'pulse'                 => $getPulseVitals,
                        'spo2'                  => $getSPO2Vitals,
                        'bp_sys'                => $getBpSysVitals,
                        'bp_dia'                => $getBpDiaVitals,
                        'bmi'                   => $getBMIPhysicalExaminations,
                        'respiratory_rate'      => $getRespiratoryRateVitals,
                        'fev1'                  => $getFev1Vitals,
                        'fvc'                   => $getFVCVitals,
                        'fev1_fvc'              => $getFEV1FVCVitals,
                        'sugar_level'           => $getSugarLevelVitals,
                        'jvp'                   => $getJvpVitals,
                        'pedel_edema'           => $getPedelEdemaVitals,
                        'temperature'           => $getTemperatureVitals
            ];

            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $finalData,
                    [],
                    trans('Patients::messages.chart_get_data_successfull'),
                    $this->http_codes['HTTP_OK']
                );
        } catch (\Exception $ex) {
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'PatientProfileController', 'getDashboard');
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
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Associated Disorder found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1AssociatedDisorder($data){
        $extra      = [];
        $patId      = $data['pat_id'];
        $userId     = $data['user_id'];
        $response   = $this->medicalHistoryObj->getPatientMedicalHistoryByPatientIdAndDoctorId($patId,$userId,$extra);
        $response   = count($response)> 0 ? array_pluck($response,'disease_name') : [];
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1FinalDiagnosis($data){
        $extra      = ['user_type' => $data['user_type']];
        $patId      = $data['pat_id'];
        $userId     = $data['user_id'];
        $response   = $this->diagnosisObj->getPatientDiagnosisByPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_pluck($response,'disease_name') : [];
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1WeightVitals($data){
        $extra                      = ['user_type' => $data['user_type'], 'name' =>'weight'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT');
        $response   = $this->physicalExaminationsObj->getV1PatientPhysicalExaminationsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;

        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date'] = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Weight'] = (int) $row['datavalue'];
            $newRow['WeightKey'] = (int) $row['datavalue'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1PulseVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'pulse'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_PULSE');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']          = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pulse']         = (int)$row['datavalue'];
            $newRow['PulseKey']      = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        18 May 2021
     * @ShortDescription      This function is responsible for get information patient temperature
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1TemperatureVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'temperature'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_TEMPERATURE');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']          = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Temperature']         = (int)$row['datavalue'];
            $newRow['TemperatureKey']      = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1SPO2Vitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'spo2'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_SPO2');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']     = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['SpO2']     = (int)$row['datavalue'];
            $newRow['SpO2Key']  = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1BpSysVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'bp_systolic'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_BP_SYS');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;

        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $responseDia = $this->getV1BpDiaVitals($data);
        $dataInChartFormat = [];

        foreach ($response as $key => $value) {
            $dataInChartFormat[$value['created_at']] = [
                    'Date' =>  $value['date'],
                    'created_at' => $value['created_at'],
                    '120'  =>  (int)$value['datavalue'],
                    '80'  =>  NULL
            ];
        }
        foreach ($responseDia as $key => $value) {
            if(isset($dataInChartFormat[$value['created_at']])){
                $dataInChartFormat[$value['created_at']]['80'] = $value['datavalue'];
            }else{
                $dataInChartFormat[$value['created_at']] = [
                    'Date' =>  $value['date'],
                    'created_at' => $value['created_at'],
                    '120'  =>  (int)$value['datavalue'],
                    '80'  =>  NULL
                ];
            }
        }

        $responseResult = ['data'=> array_values($dataInChartFormat)];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1BpDiaVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'bp_diastolic'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_BP_DIA');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        return $response;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1RespiratoryRateVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'respiratory_rate'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_RESPIRATORY_RATE');
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']             = $row['date'];
            $newRow['created_at']       = $row['created_at'];
            $newRow['Respiratory Rate'] = (int)$row['datavalue'];
            $newRow['RespiratoryKey']   = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1BMIPhysicalExaminations($data){
        $extra                      = ['user_type' => $data['user_type'], 'name' => 'bmi'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI');
        $response   = $this->physicalExaminationsObj->getV1PatientPhysicalExaminationsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']     = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['BMI']      = (int)$row['datavalue'];
            $newRow['BMIKey']   = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1Vitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];

        // GET VISIT VITALS DATA
        $response                   = $this->vitalsObj->getPatientVitalsByPatientIdAndDoctorId($patId, $userId, $extra);
        $response                   = count($response)> 0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($response),TRUE),'visit_type') : [];

        // GET SECOND LAST VISIT VITALS DATA
        $responseSecondLast         = $this->vitalsObj->getPatientVitalsByPatientIdAndDoctorId($patId, $userId, ['is_second_last' => true]);
        $responseSecondLast         = count($responseSecondLast)> 0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($responseSecondLast),TRUE),'visit_type') : [];
        $response                   = array_merge($response, $responseSecondLast);

        // GET VITALS STATIC DATA
        $staticData                 = $this->staticDataObj->vitalsFectorData();
        $staticDataArr              = $this->UtilityLib->changeArrayKey($staticData,'id');

        // GET CONSTANTS
        $iniatalVisit               = [];
        $initialVisitNumber         = Config::get('dataconstants.FIRST_FOLLOWUP_VISIT_NUMBER');
        $initialType                = Config::get('constants.INITIAL_VISIT_TYPE');
        $followType                 = Config::get('constants.FOLLOW_VISIT_TYPE');
        $secondLastVisitType        = $followType.'_SECOND_LAST_VISIT';
        $dateType                   = Config::get('constants.USER_VIEW_DATE_FORMAT_CARBON');
        $dbDateType                 = Config::get('constants.DB_SAVE_DATE_FORMAT');
        // $extra['fector_id']         = Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT');

        // GET INITIAL AND LAST VISIT DATE
        $temp = [];
        $temp['title'] = '';
        $temp['initial'] = trans('Patients::messages.chart_initial_visit');
        $dateResponse = !empty($initialVisitData) ? $this->dateTimeLibObj->changeSpecificFormat($initialVisitData->created_at,$dbDateType,$dateType) :'';
        $temp['initialDate'] = !empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';

        $temp['second_last'] = trans('Patients::messages.chart_second_last_visit');
        $dateResponse = !empty($secondLastVisitData) ? $this->dateTimeLibObj->changeSpecificFormat($secondLastVisitData->created_at, $dbDateType, $dateType) :'';
        $temp['secondLastDate'] = !empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';

        $temp['last'] = trans('Patients::messages.chart_last_visit');
        $dateResponse = !empty($followVisitData) ?  $this->dateTimeLibObj->changeSpecificFormat($followVisitData->created_at,$dbDateType,$dateType) :'';
        $temp['lastDate'] =!empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';
        $iniatalVisit[] = $temp;

        // Calculation for WEIGHT
        $weightTemp = $this->calculateVitalsInitialVisitDataByFactorId($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_unit'));
        $iniatalVisit[] = $weightTemp;

        // Calculation for BMI
        $bmiTemp = $this->calculateVitalsInitialVisitDataByFactorId($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_unit'));
        $iniatalVisit[] = $bmiTemp;

        // ASSIGN VITALS RECORD IN ARRAY
        $tempString = '120 / 80';
        $keyBPSys = '';
        $bpTemp = [];
        foreach ($staticDataArr as $key => $value){
            $temp = [];
            $temp['title']      = $value['lable'];
            $temp['initial']    = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
            $temp['second_last']= isset($response[$secondLastVisitType.'_'.$key]) ? $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
            $temp['last']       = isset($response[$followType.'_'.$key]) ? $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';

            // CHANGE BP SYSTOLIC TITLE AND ASSIGN BP 120 / 80
            if($key == Config::get('dataconstants.VISIT_VITALS_BP_SYS')){
                $keyBPSys = $key;
                $temp['title']      = trans('Setup::StaticDataConfigMessage.visit_vitals_label_bp_120_80');
                $temp['initial']    = isset($response[$initialType.'_'.$key]) ? str_replace('120', $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['second_last']= isset($response[$secondLastVisitType.'_'.$key]) ? str_replace('120', $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['last']       = isset($response[$followType.'_'.$key]) ? str_replace('120', $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['key_120_80'] = 100;
            }

            // ASSIGN BP DIASTOLIC to Temp Array variable
            if($key == Config::get('dataconstants.VISIT_VITALS_BP_DIA')){
                $keyBPSys = $key;
                $bpTemp['initial']      = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
                $bpTemp['second_last']  = isset($response[$secondLastVisitType.'_'.$key]) ? $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
                $bpTemp['last']         = isset($response[$followType.'_'.$key]) ? $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';

                continue;
            }
            $iniatalVisit[]     = $temp;
        }

        // Assign BP VISIT_VITALS_BP_DIA to 120 / 80
        if(!empty($iniatalVisit)){
            foreach ($iniatalVisit as $key => $visitData) {
                if(isset($visitData['key_120_80'])){
                    $iniatalVisit[$key]['initial']      = str_replace('80', $bpTemp['initial'], $visitData['initial']);
                    $iniatalVisit[$key]['second_last']  = str_replace('80', $bpTemp['second_last'], $visitData['second_last']);
                    $iniatalVisit[$key]['last']         = str_replace('80', $bpTemp['last'], $visitData['last']);
                    unset($iniatalVisit[$key]['key_120_80']);
                }
            }
        }

        $responseResult = ['data'=>$iniatalVisit];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FEV1 data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1Fev1Vitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FEV1_FACTOR_ID');

        $response   = $this->spirometryModelObj->getV1PatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (int) $row['datavalue'];
            $newRow['Post Value']   = (int) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FVC data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1FvcVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FVC_FACTOR_ID');

        $response   = $this->spirometryModelObj->getV1PatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (int) $row['datavalue'];
            $newRow['Post Value']   = (int) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FEV1 data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1Fev1FvcVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FEV1_FVC_FACTOR_ID');

        $response   = $this->spirometryModelObj->getV1PatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (float) $row['datavalue'];
            $newRow['Post Value']   = (float) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1SugarLevelVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_SUGARLEVEL');
        $extra['name']              = 'sugar_level';
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['Sugar']                  = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1JvpVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_JVP');
        $extra['name']              = 'jvp';
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['JVP']                    = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getV1PedelEdemaVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'pedel_edema'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_PEDELEDEMA');
        $extra['name']              = 'pedel_edema';
        $response   = $this->vitalsObj->getV1PatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['PedelEdema']             = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];

        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Associated Disorder found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getAssociatedDisorder($data){
        $extra      = [];
        $patId      = $data['pat_id'];
        $userId     = $data['user_id'];
        $response   = $this->medicalHistoryObj->getPatientMedicalHistoryByPatientIdAndDoctorId($patId,$userId,$extra);
        $response   = count($response)> 0 ? array_pluck($response,'disease_name') : [];
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getFinalDiagnosis($data){
        $extra      = ['user_type' => $data['user_type']];
        $patId      = $data['pat_id'];
        $userId     = $data['user_id'];
        $response   = $this->diagnosisObj->getPatientDiagnosisByPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_pluck($response,'disease_name') : [];
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getWeightVitals($data){
        $extra                      = ['user_type' => $data['user_type'], 'name' =>'weight'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT');
        $response   = $this->physicalExaminationsObj->getPatientPhysicalExaminationsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;

        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date'] = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Weight'] = (int) $row['datavalue'];
            $newRow['WeightKey'] = (int) $row['datavalue'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getPulseVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'pulse'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_PULSE');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']          = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pulse']         = (int)$row['datavalue'];
            $newRow['PulseKey']      = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        18 May 2021
     * @ShortDescription      This function is responsible for get information patient temperature
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getTemperatureVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'temperature'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_TEMPERATURE');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']          = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Temperature']         = (int)$row['datavalue'];
            $newRow['TemperatureKey']      = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getSPO2Vitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'spo2'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_SPO2');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']     = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['SpO2']     = (int)$row['datavalue'];
            $newRow['SpO2Key']  = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getBpSysVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'bp_systolic'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_BP_SYS');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;

        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $responseDia = $this->getBpDiaVitals($data);

        $dataInChartFormat = [];

        foreach ($response as $key => $value) {
            $dataInChartFormat[$value['date']] = [
                    'Date' =>  $value['date'],
                    'created_at' => $value['created_at'],
                    '120'  =>  (int)$value['datavalue'],
                    '80'  =>  NULL
            ];
        }
        foreach ($responseDia as $key => $value) {
            if(isset($dataInChartFormat[$value['date']])){
                $dataInChartFormat[$value['date']]['80'] = $value['datavalue'];
            }else{
                $dataInChartFormat[$value['date']] = [
                    'Date' =>  $value['date'],
                    'created_at' => $value['created_at'],
                    '120'  =>  (int)$value['datavalue'],
                    '80'  =>  NULL
                ];
            }
        }

        $responseResult = ['data'=> array_values($dataInChartFormat)];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getBpDiaVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'bp_diastolic'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_BP_DIA');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        return $response;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getRespiratoryRateVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'respiratory_rate'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_RESPIRATORY_RATE');
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId,$userId,$extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']             = $row['date'];
            $newRow['created_at']       = $row['created_at'];
            $newRow['Respiratory Rate'] = (int)$row['datavalue'];
            $newRow['RespiratoryKey']   = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getVitals($data){
        //echo'getVitals';exit;
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];

        // GET VISIT VITALS DATA
        $response                   = $this->vitalsObj->getPatientVitalsByPatientIdAndDoctorId($patId, $userId, $extra);
        $response                   = count($response)> 0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($response),TRUE),'visit_type') : [];

        // GET SECOND LAST VISIT VITALS DATA
        $responseSecondLast         = $this->vitalsObj->getPatientVitalsByPatientIdAndDoctorId($patId, $userId, ['is_second_last' => true]);
        $responseSecondLast         = count($responseSecondLast)> 0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($responseSecondLast),TRUE),'visit_type') : [];
        $response                   = array_merge($response, $responseSecondLast);

        // GET VITALS STATIC DATA
        $staticData                 = $this->staticDataObj->vitalsFectorData();
        $staticDataArr              = $this->UtilityLib->changeArrayKey($staticData,'id');

        // GET CONSTANTS
        $iniatalVisit               = [];
        $initialVisitNumber         = Config::get('dataconstants.FIRST_FOLLOWUP_VISIT_NUMBER');
        $initialType                = Config::get('constants.INITIAL_VISIT_TYPE');
        $followType                 = Config::get('constants.FOLLOW_VISIT_TYPE');
        $secondLastVisitType        = $followType.'_SECOND_LAST_VISIT';
        $dateType                   = Config::get('constants.USER_VIEW_DATE_FORMAT_CARBON');
        $dbDateType                 = Config::get('constants.DB_SAVE_DATE_FORMAT');

        // GET INITIAL AND LAST VISIT DATE
        $temp = [];
        $temp['title'] = '';
        $temp['initial'] = trans('Patients::messages.chart_initial_visit');
        $dateResponse = !empty($initialVisitData) ? $this->dateTimeLibObj->changeSpecificFormat($initialVisitData->created_at,$dbDateType,$dateType) :'';
        $temp['initialDate'] = !empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';

        $temp['second_last'] = trans('Patients::messages.chart_second_last_visit');
        $dateResponse = !empty($secondLastVisitData) ? $this->dateTimeLibObj->changeSpecificFormat($secondLastVisitData->created_at, $dbDateType, $dateType) :'';
        $temp['secondLastDate'] = !empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';

        $temp['last'] = trans('Patients::messages.chart_last_visit');
        $dateResponse = !empty($followVisitData) ?  $this->dateTimeLibObj->changeSpecificFormat($followVisitData->created_at,$dbDateType,$dateType) :'';
        $temp['lastDate'] =!empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';
        $iniatalVisit[] = $temp;

        // Calculation for WEIGHT
        $weightTemp = $this->calculateVitalsInitialVisitDataByFactorId($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_unit'));
        $iniatalVisit[] = $weightTemp;

        // Calculation for BMI
        $bmiTemp = $this->calculateVitalsInitialVisitDataByFactorId($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_unit'));
        $iniatalVisit[] = $bmiTemp;

        // ASSIGN VITALS RECORD IN ARRAY
        $tempString = '120 / 80';
        $keyBPSys = '';
        $bpTemp = [];
        foreach ($staticDataArr as $key => $value){
            $temp = [];
            $temp['title']      = $value['lable'];
            $temp['initial']    = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
            $temp['second_last']= isset($response[$secondLastVisitType.'_'.$key]) ? $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
            $temp['last']       = isset($response[$followType.'_'.$key]) ? $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';

            // CHANGE BP SYSTOLIC TITLE AND ASSIGN BP 120 / 80
            if($key == Config::get('dataconstants.VISIT_VITALS_BP_SYS')){
                $keyBPSys = $key;
                $temp['title']      = trans('Setup::StaticDataConfigMessage.visit_vitals_label_bp_120_80');
                $temp['initial']    = isset($response[$initialType.'_'.$key]) ? str_replace('120', $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['second_last']= isset($response[$secondLastVisitType.'_'.$key]) ? str_replace('120', $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['last']       = isset($response[$followType.'_'.$key]) ? str_replace('120', $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                $temp['key_120_80'] = 100;
            }

            // ASSIGN BP DIASTOLIC to Temp Array variable
            if($key == Config::get('dataconstants.VISIT_VITALS_BP_DIA')){
                $keyBPSys = $key;
                $bpTemp['initial']      = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
                $bpTemp['second_last']  = isset($response[$secondLastVisitType.'_'.$key]) ? $response[$secondLastVisitType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
                $bpTemp['last']         = isset($response[$followType.'_'.$key]) ? $response[$followType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';

                continue;
            }
            $iniatalVisit[]     = $temp;
        }

        // Assign BP VISIT_VITALS_BP_DIA to 120 / 80
        if(!empty($iniatalVisit)){
            foreach ($iniatalVisit as $key => $visitData) {
                if(isset($visitData['key_120_80'])){
                    $iniatalVisit[$key]['initial']      = str_replace('80', $bpTemp['initial'], $visitData['initial']);
                    $iniatalVisit[$key]['second_last']  = str_replace('80', $bpTemp['second_last'], $visitData['second_last']);
                    $iniatalVisit[$key]['last']         = str_replace('80', $bpTemp['last'], $visitData['last']);
                    unset($iniatalVisit[$key]['key_120_80']);
                }
            }
        }

        $responseResult = ['data'=>$iniatalVisit];
        return $responseResult;
    }

    /**
     * @DateOfCreation        13 June 2018
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getBMIPhysicalExaminations($data){
        $extra                      = ['user_type' => $data['user_type'], 'name' => 'bmi'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI');
        $response   = $this->physicalExaminationsObj->getPatientPhysicalExaminationsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];
        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']     = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['BMI']      = (int)$row['datavalue'];
            $newRow['BMIKey']   = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FEV1 data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getFev1Vitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FEV1_FACTOR_ID');

        $response   = $this->spirometryModelObj->getPatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (int) $row['datavalue'];
            $newRow['Post Value']   = (int) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FVC data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getFvcVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FVC_FACTOR_ID');

        $response   = $this->spirometryModelObj->getPatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (int) $row['datavalue'];
            $newRow['Post Value']   = (int) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        3 Oct 2018
     * @ShortDescription      This function is responsible for get information patient Spirometries FEV1 data
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getFev1FvcVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.SPIROMETRY_FEV1_FVC_FACTOR_ID');

        $response   = $this->spirometryModelObj->getPatientSpirometryByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $response   = count($response)> 0 ? array_reverse(json_decode(json_encode($response),TRUE)) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']         = $row['date'];
            $newRow['created_at']     = $row['created_at'];
            $newRow['Pre Value']    = (float) $row['datavalue'];
            $newRow['Post Value']   = (float) $row['data_post_value'];
            return $newRow;
        },$response) : $response;

        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateImage(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $user_id = $request->user()->user_id;

        $uploadedImage = $this->patientModelObj->updateProfileImage($requestData,$user_id);

        // validate, is query executed successfully
        if($uploadedImage){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $uploadedImage,
                [],
                trans('Patients::messages.patients_updated_image_successfull'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.profile_image_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        22 May 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getProfileImage($imageName, Request $request)
    {
        $requestData = $this->getRequestData($request);
        $imageName = $this->securityLibObj->decrypt($imageName);
        $imagePath =  'app/public/'.Config::get('constants.PATIENTS_PROFILE_IMG_PATH');
        $imageName = empty($imageName) ? Config::get('constants.DEFAULT_IMAGE_NAME'):$imageName;
        $path = 'patients/'.$imageName;
        if($this->s3LibObj->isFileExist($path)){
            return $response = $this->s3LibObj->getObject($path)['fileObject'];
        }
        $path = public_path(Config::get('constants.DEFAULT_IMAGE_PATH'));
        $file = File::get($path);
        $type = File::mimeType($path);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * @DateOfCreation        22 May 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getThumbProfileImage($type='small', $imageName, Request $request)
    {
        $requestData = $this->getRequestData($request);
        $defaultPath = ($type == 'small' ? public_path(Config::get('constants.DEFAULT_SMALL_PATH')) : public_path(Config::get('constants.DEFAULT_MEDIUM_PATH')));
        $imageName = $this->securityLibObj->decrypt($imageName);
        $path_name = ($type == 'small' ? Config::get('constants.PATIENTS_PROFILE_STHUMB_IMG_PATH') : Config::get('constants.PATIENTS_PROFILE_MTHUMB_IMG_PATH'));
        $imageName = empty($imageName) ? Config::get('constants.DEFAULT_IMAGE_NAME'):$imageName;
        $path = Config::get('constants.PATIENT_PROFILE_S3_PATH').$imageName;

        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            if($this->s3LibObj->isFileExist($path)){
                 return $response = $this->s3LibObj->getObject($path)['fileObject'];
            }
        }else{
            $imagePath =  'app/public/'.$path_name;
            $imageName = empty($imageName) ? Config::get('constants.DEFAULT_IMAGE_NAME'):$imageName;
            $path = storage_path($imagePath) . $imageName;
            if(!File::exists($path)){
                $path = $defaultPath;
            }
            $file = File::get($path);
            $type = File::mimeType($path);
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
     * @DateOfCreation        22 May 2018
     * @ShortDescription      This function is calculate the vitals data
     * @param                 String $patId
                              String $userId,$extra, $factorId, $title, $unit
     * @return                response
     */
    function calculateVitalsInitialVisitDataByFactorId($patId, $userId, $extra, $factorId, $title, $unit){

        // GET CONSTANTS
        $initialVisitNumber         = Config::get('dataconstants.FIRST_FOLLOWUP_VISIT_NUMBER');
        $initialType                = Config::get('constants.INITIAL_VISIT_TYPE');
        $followType                 = Config::get('constants.FOLLOW_VISIT_TYPE');
        $secondLastVisitType        = $followType.'_SECOND_LAST_VISIT';
        $dateType                   = Config::get('constants.USER_VIEW_DATE_FORMAT_CARBON');
        $dbDateType                 = Config::get('constants.DB_SAVE_DATE_FORMAT');
        $extra['fector_id']         = $factorId;

        $initialVisitData           = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $initialType, $extra); // INITIAL VISIT DATA
        $initialVisitId             = !empty($initialVisitData) ? $initialVisitData->visit_id :'';

        $extra['is_second_last']    = true;
        $secondLastVisitData        = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $followType, $extra); // Second Last VISIT DATA
        $secondLastVisitId          = !empty($secondLastVisitData) ? $secondLastVisitData->visit_id :'';
        unset($extra['is_second_last']);

        $followVisitData            = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $followType, $extra); // FOLLOWUP VISIT DATA
        $followVisitId              = !empty($followVisitData) ? $followVisitData->visit_id :'';

        $visitIds                   =  array_filter([$initialVisitId, $secondLastVisitId, $followVisitId]);
        $extra['visit_id']          = !empty($visitIds) ? $visitIds : ['0'];

        // GET WEIGHT FROM VISITs
        $physicalExaminationsData = $this->physicalExaminationsObj->getPatientPhysicalExaminationsByFactorIdPatientIdAndDoctorIdAndVisitIds($patId, $userId, $extra);
        $physicalExaminationsData = count($physicalExaminationsData) > 0 ? $this->UtilityLib->changeArrayKey($physicalExaminationsData,'visit_id') : [];

        // GET INITIAL AND LAST VISIT WEIGHT
        $temp = [];
        $temp['title'] = $title;
        $temp['initial']        = !empty($physicalExaminationsData) && isset($physicalExaminationsData[$initialVisitId]) && isset($physicalExaminationsData[$initialVisitId]['datavalue']) ? $physicalExaminationsData[$initialVisitId]['datavalue'].' '.$unit : '-';
        $temp['second_last']    = !empty($physicalExaminationsData) && isset($physicalExaminationsData[$secondLastVisitId]) && isset($physicalExaminationsData[$secondLastVisitId]['datavalue']) ? $physicalExaminationsData[$secondLastVisitId]['datavalue'].' '.$unit : '-';
        $temp['last']           = !empty($physicalExaminationsData) && isset($physicalExaminationsData[$followVisitId]) && isset($physicalExaminationsData[$followVisitId]['datavalue']) ? $physicalExaminationsData[$followVisitId]['datavalue'].' '.$unit : '-';

        return $temp;
    }

    /**
     * @DateOfCreation        21 Jan 2018
     * @ShortDescription      This function is responsible to trasnfer all data from folder to S3
     * @return                upload status
     */
    public function transferAllPatientProfileImages(){
        $directory = storage_path('app/public/'.Config::get('constants.PATIENTS_PROFILE_IMG_PATH'));
        $S3filePath = 'patients/patientsprofile/';
        return $this->s3LibObj->folderToS3Bucket($directory, $S3filePath);
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getSugarLevelVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_SUGARLEVEL');
        $extra['name']              = 'sugar_level';
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['Sugar']                  = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getJvpVitals($data){
        $extra                      = ['user_type' => $data['user_type']];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_JVP');
        $extra['name']              = 'jvp';
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['JVP']                    = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];
        return $responseResult;
    }

    /**
     * @DateOfCreation        21 Sep 2020
     * @ShortDescription      This function is responsible for get information patient Final Diagnosis found
     * @param                 Array $data  pat_id and doctor_id
     * @return                Array of result record
     */
    public function getPedelEdemaVitals($data){
        $extra                      = ['user_type' => $data['user_type'],'name' => 'pedel_edema'];
        $patId                      = $data['pat_id'];
        $userId                     = $data['user_id'];
        $extra['fector_id']         = Config::get('dataconstants.VISIT_VITALS_PEDELEDEMA');
        $extra['name']              = 'pedel_edema';
        $response   = $this->vitalsObj->getPatientVitalsByFactorIdPatientIdAndDoctorId($patId, $userId, $extra);
        $temp = [];
        foreach($response as $value){
            $temp[] = $value;
        }
        $response = $temp;
        $response   = count($response)> 0 ? json_decode(json_encode($response),TRUE) : [];

        $response = !empty($response) ? array_map(function($row){
            $newRow = [];
            $newRow['Date']                   = $row['date'];
            $newRow['created_at']             = $row['created_at'];
            $newRow['PedelEdema']             = (int)$row['datavalue'];
            return $newRow;
        },$response) : $response;
        $responseResult = ['data'=>$response];

        return $responseResult;
    }

    /**
    * @DateOfCreation        16 August 2021
    * @ShortDescription      This function is responsible for provide data for current visit
    * @param                 $visitId
    * @return                array
    */
    public function currentVisitAtGlance(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['pat_id']   = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['user_id']  = $this->securityLibObj->decrypt($requestData['user_id']);
        $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);

        $patId   = $requestData['pat_id'];
        $userId  = $requestData['user_id'];
        $visitId = $requestData['visit_id'];
        $extra   = [];

        // GET VISIT VITALS DATA
        $response = $this->vitalsObj->getPatientVitalsByPatientIdAndDoctorIdAndVisitId($patId, $userId, $visitId);

        $finalRecords = [];

        if(!empty($response))
        {
            $response = count($response)> 0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($response),TRUE),'visit_type') : [];

            // GET VITALS STATIC DATA
            $staticData                 = $this->staticDataObj->vitalsFectorData();
            $staticDataArr              = $this->UtilityLib->changeArrayKey($staticData,'id');

            // GET CONSTANTS
            $iniatalVisit               = [];
            $initialVisitNumber         = Config::get('dataconstants.FIRST_FOLLOWUP_VISIT_NUMBER');
            $initialType                = Config::get('constants.INITIAL_VISIT_TYPE');
            $followType                 = Config::get('constants.FOLLOW_VISIT_TYPE');
            $secondLastVisitType        = $followType.'_SECOND_LAST_VISIT';
            $dateType                   = Config::get('constants.USER_VIEW_DATE_FORMAT_CARBON');
            $dbDateType                 = Config::get('constants.DB_SAVE_DATE_FORMAT');

            // GET INITIAL AND LAST VISIT DATE
            $temp = [];
            $temp['title'] = '';
            $temp['initial'] = trans('Patients::messages.chart_initial_visit');
            $dateResponse = !empty($initialVisitData) ? $this->dateTimeLibObj->changeSpecificFormat($initialVisitData->created_at,$dbDateType,$dateType) :'';
            $temp['initialDate'] = !empty($dateResponse) && $dateResponse['code'] == Config::get('restresponsecode.SUCCESS') ? $dateResponse['result'] : '';

            // Calculation for WEIGHT
            $weightTemp = $this->calculateVitalsInitialVisitDataForGlance($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_weight_unit'));
            $iniatalVisit[] = $weightTemp;

            // Calculation for BMI
            $bmiTemp = $this->calculateVitalsInitialVisitDataForGlance($patId, $userId, $extra, Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_lable'), trans('Setup::StaticDataConfigMessage.visit_vitals_label_bmi_unit'));
            $iniatalVisit[] = $bmiTemp;

            // ASSIGN VITALS RECORD IN ARRAY
            $tempString = '120 / 80';
            $keyBPSys = '';
            $bpTemp = [];
            foreach ($staticDataArr as $key => $value){
                $temp = [];
                $temp['title']      = $value['lable'];
                $temp['initial']    = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';

                // CHANGE BP SYSTOLIC TITLE AND ASSIGN BP 120 / 80
                if($key == Config::get('dataconstants.VISIT_VITALS_BP_SYS')){
                    $keyBPSys = $key;
                    $temp['title']      = trans('Setup::StaticDataConfigMessage.visit_vitals_label_bp_120_80');
                    $temp['initial']    = isset($response[$initialType.'_'.$key]) ? str_replace('120', $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'], $tempString) :'-';
                    $temp['key_120_80'] = 100;
                }

                // ASSIGN BP DIASTOLIC to Temp Array variable
                if($key == Config::get('dataconstants.VISIT_VITALS_BP_DIA')){
                    $keyBPSys = $key;
                    $bpTemp['initial']      = isset($response[$initialType.'_'.$key]) ? $response[$initialType.'_'.$key]['fector_value'].' '.$value['unit'] :'-';
                    continue;
                }

                $iniatalVisit[]     = $temp;
            }

            // Assign BP VISIT_VITALS_BP_DIA to 120 / 80
            if(!empty($iniatalVisit)){
                foreach ($iniatalVisit as $key => $visitData) {
                    if(isset($visitData['key_120_80'])){
                        $iniatalVisit[$key]['initial']      = str_replace('80', $bpTemp['initial'], $visitData['initial']);
                        unset($iniatalVisit[$key]['key_120_80']);
                    }
                }
            }

            //now add presenting complaints array also in result
            $patientSymptomsDetail = $this->patientSymptomsDetail($visitId);

            //now add symptoms data array also in result
            $symptomsData             = [];
            $symptomsData['page']     = "0";
            $symptomsData['pageSize'] = "10";
            $symptomsData['patId']    = $this->securityLibObj->encrypt($requestData['pat_id']);
            $symptomsData['visitId']  = $this->securityLibObj->encrypt($requestData['visit_id']);
            $patientSymptomsVisitData = $this->symptomsObj->getSymptomsDataByPatientIdAndVistId($symptomsData);

            $finalRecords['iniatalVisit']          = $iniatalVisit;
            $finalRecords['patientSymptomsDetail'] = $patientSymptomsDetail;
            $finalRecords['patientSymptomsData']   = $patientSymptomsVisitData;

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalRecords,
                [],
                trans('Visits::messages.initial_visit_result'),
                $this->http_codes['HTTP_OK']
            );
        }
        else
        {
            //now add presenting complaints array also in result
            $patientSymptomsDetail = $this->patientSymptomsDetail($visitId);

            $finalRecords['iniatalVisit']          = [];
            $finalRecords['patientSymptomsDetail'] = $patientSymptomsDetail;

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $finalRecords,
                [],
                trans('Visits::messages.initial_visit_result'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        16 August 2021
     * @ShortDescription      This function is calculate the vitals data
     * @param                 String $patId
                              String $userId,$extra, $factorId, $title, $unit
     * @return                response
     */
    function calculateVitalsInitialVisitDataForGlance($patId, $userId, $extra, $factorId, $title, $unit)
    {
        // GET CONSTANTS
        $initialVisitNumber         = Config::get('dataconstants.FIRST_FOLLOWUP_VISIT_NUMBER');
        $initialType                = Config::get('constants.INITIAL_VISIT_TYPE');
        $followType                 = Config::get('constants.FOLLOW_VISIT_TYPE');
        $secondLastVisitType        = $followType.'_SECOND_LAST_VISIT';
        $dateType                   = Config::get('constants.USER_VIEW_DATE_FORMAT_CARBON');
        $dbDateType                 = Config::get('constants.DB_SAVE_DATE_FORMAT');
        $extra['fector_id']         = $factorId;

        $initialVisitData           = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $initialType, $extra); // INITIAL VISIT DATA
        $initialVisitId             = !empty($initialVisitData) ? $initialVisitData->visit_id :'';

        $extra['is_second_last']    = true;
        $secondLastVisitData        = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $followType, $extra); // Second Last VISIT DATA
        $secondLastVisitId          = !empty($secondLastVisitData) ? $secondLastVisitData->visit_id :'';
        unset($extra['is_second_last']);

        $followVisitData            = $this->vitalsObj->getPatientVisitByPatientIdAndDoctorId($patId, $userId, $followType, $extra); // FOLLOWUP VISIT DATA
        $followVisitId              = !empty($followVisitData) ? $followVisitData->visit_id :'';

        $visitIds                   =  array_filter([$initialVisitId, $secondLastVisitId, $followVisitId]);
        $extra['visit_id']          = !empty($visitIds) ? $visitIds : ['0'];

        // GET WEIGHT FROM VISITs
        $physicalExaminationsData = $this->physicalExaminationsObj->getPatientPhysicalExaminationsByFactorIdPatientIdAndDoctorIdAndVisitIds($patId, $userId, $extra);
        $physicalExaminationsData = count($physicalExaminationsData) > 0 ? $this->UtilityLib->changeArrayKey($physicalExaminationsData,'visit_id') : [];

        // GET INITIAL AND LAST VISIT WEIGHT
        $temp = [];
        $temp['title'] = $title;
        $temp['initial']        = !empty($physicalExaminationsData) && isset($physicalExaminationsData[$initialVisitId]) && isset($physicalExaminationsData[$initialVisitId]['datavalue']) ? $physicalExaminationsData[$initialVisitId]['datavalue'].' '.$unit : '-';
        return $temp;
    }

    /**
     * @DateOfCreation        2 July 2018
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function patientSymptomsDetail($visitId)
    {
        $patientSymptomsTest  = $this->symptomsObj->getPatientSymptomsTestRecord($visitId);
        $symptomsTestRecordWithFectorKey = !empty($patientSymptomsTest) && count($patientSymptomsTest)>0 ? $this->UtilityLib->changeArrayKey(json_decode(json_encode($patientSymptomsTest),true), 'hopi_type_id'):[];

        $staticDataKey              = $this->staticDataObj->getStaticDataFunction(['getsymptomsTestData']);
        $staticDataArrWithCustomKey = $this->UtilityLib->changeArrayKey($staticDataKey, 'id');

        $finalCheckupRecords = [];
        $tempData = [];

        if(!empty($staticDataArrWithCustomKey))
        {
            foreach ($staticDataArrWithCustomKey as $hopiTypeIdKey => $hopiValue)
            {
                $temp = [];
                $encrypthopiTypeIdKey = $this->securityLibObj->encrypt($hopiTypeIdKey);
                $symptomsTestValuesData = ( array_key_exists($hopiTypeIdKey, $symptomsTestRecordWithFectorKey) ? $symptomsTestRecordWithFectorKey[$hopiTypeIdKey]['hopi_value'] : '');
                $temp = [
                    'showOnForm'=>true,
                    'name' => 'hopi_type_'.$encrypthopiTypeIdKey,
                    'title' => $hopiValue['value'],
                    'type' => $hopiValue['input_type'],
                    'value' => $hopiValue['input_type'] === 'customcheckbox' ? [(string) $symptomsTestValuesData] : $symptomsTestValuesData,
                    'cssClasses' => $hopiValue['cssClasses'],
                    'clearFix' => $hopiValue['isClearfix'],

                ];
                if($hopiValue['input_type'] === 'date')
                {
                    $temp['format'] =  isset($hopiValue['format']) ?  $hopiValue['format'] : Config::get('constants.REACT_WEB_DATE_FORMAT');
                }

                $tempData['hopi_type_'.$encrypthopiTypeIdKey.'_data'] = isset($hopiValue['input_type_option']) && !empty($hopiValue['input_type_option']) ? $hopiValue['input_type_option']:[] ;

                $finalCheckupRecords['form_'.$hopiValue['type']]['fields'][] = $temp;
                $finalCheckupRecords['form_'.$hopiValue['type']]['data'] = $tempData;
                $finalCheckupRecords['form_'.$hopiValue['type']]['handlers'] = [];

                if(isset($hopiValue['formName']))
                {
                    $finalCheckupRecords['form_'.$hopiValue['type']]['formName'] = $hopiValue['formName'];
                }
            }
        }

        return $finalCheckupRecords;
    }
}
