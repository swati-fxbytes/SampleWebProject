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
use App\Libraries\ExceptionLib;
use App\Libraries\DateTimeLib;
use App\Modules\Visits\Models\SocialAddiction;
use App\Modules\Visits\Models\SocialAddictionUse;
use App\Modules\Setup\Models\StaticDataConfig as StaticData;
use DB;
use App\Libraries\FileLib;
use App\Libraries\UtilityLib;
use File;

/**
 * SocialAddictionController
 *
 * @package                ILD INDIA
 * @subpackage             SocialAddictionController
 * @category               Controller
 * @DateOfCreation         02 July 2018
 * @ShortDescription       This controller to handle all the operation related to
                           Social Addiction
 */
class SocialAddictionController extends Controller
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

        // Init SocialAddiction model object
        $this->socialAddictionModelObj = new SocialAddiction();

        // Init SocialAddictionUse model object
        $this->socialAddictionUseModelObj = new SocialAddictionUse();

        // Init Utility Library object
        $this->utilityLibObj = new UtilityLib();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init General staticData Model Object
        $this->staticDataObj = new StaticData();

        // Init DateTime library object
        $this->dateTimeLibObj = new DateTimeLib();
    }

    /**
     * @DateOfCreation        30 March 2021
     * @ShortDescription      This function is responsible to get the Domestic factor field value
     * @return                Array of status and message
     */
    public function postSocialAddictionVisitID(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $visitId                                = $this->securityLibObj->decrypt($requestData['visitId']);
        $encryptVisitId                         = $this->securityLibObj->encrypt($visitId);
        $patientSocialAddictionData             = $this->socialAddictionModelObj->getPatientSocialAddictionRecord($visitId);
        $patientSocialAddictionUseData          = $this->socialAddictionUseModelObj->getPatientSocialAddictionUseRecord($visitId);
        $staticData                             = $this->staticDataObj->getStaticDataConfigList();;
        $socialAddictionKeyData                 = $staticData['social_addiction_key'];
        $socialAddictionUseTypeData             = $staticData['social_addiction_use_type'];
        $patientsocialAddictionCustomKey        = !empty($patientSocialAddictionData) && count($patientSocialAddictionData)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientSocialAddictionData),true), 'sa_key'):[];
        $patientsocialAddictionUseCustomKey     = !empty($patientSocialAddictionUseData) && count($patientSocialAddictionUseData)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientSocialAddictionUseData),true), 'sau_type'):[];

        $data = [];
        if(count($socialAddictionKeyData)>0){
            foreach ($socialAddictionKeyData as $key => $socialAddictionValue) {
                $temp = [];
                $socialAddictionId  = $socialAddictionValue['id'];
                $encryptSocialAddictionId  = $this->securityLibObj->encrypt($socialAddictionId);
                $temp['id'] = 'sa_key_'.$encryptSocialAddictionId;
                $temp['value'] = isset($patientsocialAddictionCustomKey[$socialAddictionId]) ?  $patientsocialAddictionCustomKey[$socialAddictionId]['sa_value'] : '';
                $temp['type']  = 'customcheckbox';
                $temp['optionValue'] = isset($staticData[$socialAddictionValue['input_type_option']]) ? $this->typeConversion($staticData[$socialAddictionValue['input_type_option']]) : [];
                $temp['name'] = $socialAddictionValue['value'];
                $data['socialAddictionKey'][] =$temp;
            }
        }

        if(count($socialAddictionUseTypeData)>0){
            $data['sa_labels'] = ['starting_date' => trans('Visits::messages.social_addiction_label_starting_date'), 'stopping_date' => trans('Visits::messages.social_addiction_label_stopping_date'), 'quantitiy' => trans('Visits::messages.social_addiction_label_quantity'), 'pack_year' => trans('Visits::messages.social_addiction_label_pack_year')];
            foreach ($socialAddictionUseTypeData as $key => $socialAddictionUseValue) {
                $temp = [];
                $socialAddictionUseId  = $socialAddictionUseValue['id'];
                $encryptSocialAddictionUseId  = $this->securityLibObj->encrypt($socialAddictionUseId);
                $temp['id'] = 'starting_sau_type_'.$encryptSocialAddictionUseId;
                $temp['value'] = isset($patientsocialAddictionUseCustomKey[$socialAddictionUseId]) && !empty($patientsocialAddictionUseCustomKey[$socialAddictionUseId]['starting_date']) ?  $patientsocialAddictionUseCustomKey[$socialAddictionUseId]['starting_date'] : '';

                $temp['name'] = $socialAddictionUseValue['value'];
                $temp['type'] = 'text';
                $data['socialAddictionKeyUse']['starting_date'][] = $temp;
                unset($temp['value']);
                unset($temp['id']);
                 $temp['id'] = 'stopping_sau_type_'.$encryptSocialAddictionUseId;
                $temp['value'] = isset($patientsocialAddictionUseCustomKey[$socialAddictionUseId]) && !empty($patientsocialAddictionUseCustomKey[$socialAddictionUseId]['stopping_date']) ?  $patientsocialAddictionUseCustomKey[$socialAddictionUseId]['stopping_date'] : '';
                $data['socialAddictionKeyUse']['stopping_date'][] = $temp;
                unset($temp['value']);
                unset($temp['id']);
                 $temp['id'] = 'quantitiy_sau_type_'.$encryptSocialAddictionUseId;
                 $temp['type']  = 'text';
                $temp['value'] = isset($patientsocialAddictionUseCustomKey[$socialAddictionUseId]) ?  $patientsocialAddictionUseCustomKey[$socialAddictionUseId]['quantitiy'] : '';
                $data['socialAddictionKeyUse']['quantitiy'][] = $temp;
                unset($temp['value']);
                unset($temp['id']);
                 $temp['id'] = 'pack_year_sau_type_'.$encryptSocialAddictionUseId;
                 $temp['type']  = 'text';
                $temp['value'] = isset($patientsocialAddictionUseCustomKey[$socialAddictionUseId]) ?  $patientsocialAddictionUseCustomKey[$socialAddictionUseId]['pack_year'] : '';
                $data['socialAddictionKeyUse']['pack_year'][] = $temp;
                unset($temp['value']);
                unset($temp['id']);
                $temp['id'] = 'sau_type_'.$encryptSocialAddictionUseId;
                $data['socialAddictionKeyUse']['headerData'][] = $temp;
            }
        }
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $data,
                [],
                trans('Visits::messages.social_addiction_get_data_successfull'),
                $this->http_codes['HTTP_OK']
            );
    }

    public function typeConversion($data){
        return array_map(function($row){
            if(isset($row['id'])){
                $row['id'] = (string) $row['id'];
            }
            return $row;
        }, $data);
    }

    /**
     * @DateOfCreation        13 june 2018
     * @ShortDescription      This function is responsible for insert SocialAddiction Data
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function addUpdateSocialAddiction(Request $request){
        $requestData = $this->getRequestData($request);
        $encryptedVisitId               = $requestData['visit_id'];
        
        $requestData['user_id']         = $request->user()->user_id;
        $requestData['is_deleted']      = Config::get('constants.IS_DELETED_NO');
        $requestData['pat_id']          = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['visit_id']        = $this->securityLibObj->decrypt($requestData['visit_id']);
        $visitId                        = $requestData['visit_id'];
        try{
            DB::beginTransaction();
            $patientSocialAddictionData             = $this->socialAddictionModelObj->getPatientSocialAddictionRecord($visitId);
            $patientSocialAddictionUseData          = $this->socialAddictionUseModelObj->getPatientSocialAddictionUseRecord($visitId);
            $staticData                             = $this->staticDataObj->getStaticDataConfigList();;
            $socialAddictionKeyData                 = $staticData['social_addiction_key'];
            $socialAddictionUseTypeData             = $staticData['social_addiction_use_type'];
            $patientsocialAddictionCustomKey        = !empty($patientSocialAddictionData) && count($patientSocialAddictionData)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientSocialAddictionData),true), 'sa_key'):[];
            $patientsocialAddictionUseCustomKey     = !empty($patientSocialAddictionUseData) && count($patientSocialAddictionUseData)>0 ? $this->utilityLibObj->changeArrayKey(json_decode(json_encode($patientSocialAddictionUseData),true), 'sau_type'):[];

            $insertData = [];
            $insertDataUse = [];
            if(count($socialAddictionKeyData)>0){
                foreach ($socialAddictionKeyData as $key => $socialAddictionValue) {
                    $temp = [];
                    $socialAddictionId  = $socialAddictionValue['id'];
                    $encryptSocialAddictionId  = $this->securityLibObj->encrypt($socialAddictionId);

                    $value = isset($requestData['sa_key_'.$encryptSocialAddictionId]) ? $requestData['sa_key_'.$encryptSocialAddictionId] : '';
                    $temp = [
                            'pat_id'        =>  $requestData['pat_id'],
                            'visit_id'      =>  $requestData['visit_id'],
                            'sa_key'        =>  $socialAddictionId,
                            'sa_value'      =>  $value,
                            'ip_address'    =>  $requestData['ip_address'],
                            'resource_type' =>  $requestData['resource_type'],
                    ];
                    $sa_id = (isset($patientsocialAddictionCustomKey[$socialAddictionId]['sa_id']) &&
                                !empty($patientsocialAddictionCustomKey[$socialAddictionId]['sa_id']) )
                                ? $this->securityLibObj->decrypt($patientsocialAddictionCustomKey[$socialAddictionId]['sa_id']) : '';
                   if(array_key_exists($socialAddictionId, $patientsocialAddictionCustomKey) && !empty($sa_id)){
                        $whereData =[];
                        $whereData = [
                            'pat_id'    =>  $requestData['pat_id'],
                            'visit_id'  =>  $requestData['visit_id'],
                            'sa_id'     =>  $sa_id,
                        ];
                        $updateData = $this->socialAddictionModelObj->updateSocialAddiction($temp,$whereData);
                        if(!$updateData){
                            $dataDbStatus = true;
                            $dbCommitStatus = false;
                            break;
                        }else{
                            $dbCommitStatus = true;
                        }
                   }
                   if(!empty($value) && empty($sa_id)){
                        $insertData[] = $temp;
                   }
                }

                if(count($socialAddictionUseTypeData)>0 && !isset($dataDbStatus)){
                    foreach ($socialAddictionUseTypeData as $key => $socialAddictionUseValue) {
                        $temp = [];
                        $socialAddictionTypeId  = $socialAddictionUseValue['id'];
                        $encryptSocialAddictionTypeId  = $this->securityLibObj->encrypt($socialAddictionTypeId);
                        $starting_date_value = isset($requestData['starting_sau_type_'.$encryptSocialAddictionTypeId]) && !empty($requestData['starting_sau_type_'.$encryptSocialAddictionTypeId]) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['starting_sau_type_'.$encryptSocialAddictionTypeId],'dd/mm/YY','Y-m-d')['result'] : NULL;
                        $stopping_date_value = isset($requestData['stopping_sau_type_'.$encryptSocialAddictionTypeId]) && !empty($requestData['stopping_sau_type_'.$encryptSocialAddictionTypeId]) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['stopping_sau_type_'.$encryptSocialAddictionTypeId],'dd/mm/YY','Y-m-d')['result'] : NULL;
                        $quantitiy_value = isset($requestData['quantitiy_sau_type_'.$encryptSocialAddictionTypeId]) ? $requestData['quantitiy_sau_type_'.$encryptSocialAddictionTypeId] : '';
                        $pack_year_value = isset($requestData['pack_year_sau_type_'.$encryptSocialAddictionTypeId]) ? $requestData['pack_year_sau_type_'.$encryptSocialAddictionTypeId] : '';
                        $temp = [
                                'pat_id'        =>  $requestData['pat_id'],
                                'visit_id'      =>  $requestData['visit_id'],
                                'sau_type'      =>  $socialAddictionTypeId,
                                'starting_date'  =>  $starting_date_value,
                                'stopping_date'  =>  $stopping_date_value,
                                'quantitiy'     =>  $quantitiy_value,
                                'pack_year'     =>  $pack_year_value,
                                'ip_address'    =>  $requestData['ip_address'],
                                'resource_type' =>  $requestData['resource_type'],
                        ];

                        $sau_id = (isset($patientsocialAddictionUseCustomKey[$socialAddictionTypeId]['sau_id']) &&
                                    !empty($patientsocialAddictionUseCustomKey[$socialAddictionTypeId]['sau_id']) )
                                    ? $this->securityLibObj->decrypt($patientsocialAddictionUseCustomKey[$socialAddictionTypeId]['sau_id']) : '';
                        if(array_key_exists($socialAddictionTypeId, $patientsocialAddictionUseCustomKey) && !empty($sau_id)){
                            $whereData =[];
                            $whereData = [
                                'pat_id'    =>  $requestData['pat_id'],
                                'visit_id'  =>  $requestData['visit_id'],
                                'sau_id'     =>  $sau_id,
                            ];
                            $updateData = $this->socialAddictionUseModelObj->updateSocialAddiction($temp,$whereData);
                            if(!$updateData){
                                $dataDbStatus = true;
                                $dbCommitStatus = false;
                                break;
                            }else{
                                $dbCommitStatus = true;
                            }

                        }
                        if((!empty($starting_date_value) || !empty($stopping_date_value) || !empty($quantitiy_value)) && empty($sau_id)){
                            $insertDataUse[] = $temp;
                        }
                    }
                }

                if(isset($dataDbStatus) && $dataDbStatus){
                    DB::rollback();
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        [],
                        trans('Visits::messages.social_addiction_add_fail'),
                        $this->http_codes['HTTP_OK']
                    );
                }

                if(!empty($insertDataUse)){
                    $addData = $this->socialAddictionUseModelObj->addSocialAddiction($insertDataUse);
                    if(!$addData){
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Visits::messages.social_addiction_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        $dbCommitStatus = true;
                    }
                }
                if(!empty($insertData)){
                    $addData = $this->socialAddictionModelObj->addSocialAddiction($insertData);
                    if(!$addData){
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Visits::messages.social_addiction_add_fail'),
                            $this->http_codes['HTTP_OK']
                        );
                    }else{
                        DB::commit();
                        $dbCommitStatus = false;
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('Visits::messages.social_addiction_add_success'),
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
                        trans('Visits::messages.social_addiction_add_success'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }

        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'SocialAddictionController', 'addUpdateSocialAddiction');
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
