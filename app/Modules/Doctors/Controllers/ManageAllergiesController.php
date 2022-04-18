<?php

namespace App\Modules\Doctors\Controllers;

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
use App\Modules\Doctors\Models\ManageAllergies as ManageAllergies;
use App\Traits\FxFormHandler;
use App\Modules\Visits\Models\Allergies;
use App\Modules\Auth\Models\SecondDBUsers as SecondDBUsers;

/**
 * ManageAllergiesController
 *
 * @package                ILD India Registry
 * @subpackage             ManageAllergiesController
 * @category               Controller
 * @DateOfCreation         18 june 2018
 * @ShortDescription       This controller to handle all the operation related to
                           setup ManageAllergies
 **/
class ManageAllergiesController extends Controller
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

        // Init ManageAllergies Model Object
        $this->manageAllergiesObj = new ManageAllergies();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();
        $this->utilityLibObj = new UtilityLib();

        // Init Allergies model object
        $this->allergiesModelObj = new Allergies();
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for get Allergies list
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getAllergiesList(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        $where = array();
        $parentAllergies = [];
        $getAllergiesList = $this->manageAllergiesObj->getAllergiesList($requestData);
        $parentAllergiesResult = $this->manageAllergiesObj->getParentAllergiesList();
        if(!empty($parentAllergiesResult)){
            foreach ($parentAllergiesResult as $key => $allergy) {
                        $parentAllergies[$allergy->allergy_id] = $allergy;
            }
        }

        $allergiesArray = [
            'group_parent'  => $parentAllergies,
            'group_child'   => $getAllergiesList
        ];
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $allergiesArray,
                [],
                trans('Doctors::messages.allergies_list'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for get Allergies list
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getSubParentAllergiesByParentId($parentId)
    {
        $parentId = $this->securityLibObj->decrypt($parentId);
        $result = $this->manageAllergiesObj->getSubParentAllergiesByParentId($parentId);
        $checkEmpty = $this->utilityLibObj->changeObjectToArray($result);
        if(!empty($checkEmpty)){
            $subParentAllergiesList['result'] = $result;
            $subParentAllergiesList['is_sub_parent'] = true;
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $subParentAllergiesList,
                [],
                trans('Doctors::messages.allergies_list'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            $allergiesList = $this->manageAllergiesObj->getAllergiesByParentId($parentId);
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $allergiesList,
                [],
                trans('Doctors::messages.allergies_list'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        15 June 2018
     * @ShortDescription      This function is responsible for get Allergies list
     * @param                 Array $request
     * @return                Array of status and message
     */
    public function getAllergiesByParentId($parentId)
    {
        $allergiesList = $this->manageAllergiesObj->getAllergiesByParentId($parentId);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $allergiesList,
            [],
            trans('Doctors::messages.allergies_list'),
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
        $userId = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        $tableName   = $this->manageAllergiesObj->getTableName();
        $primaryKey  = $this->manageAllergiesObj->getTablePrimaryIdColumn();

        $requestData = $this->getRequestData($request);

        $posConfig =
        [
            $tableName =>[
                $primaryKey=>
                [
                    'type'=>'input',
                    'decrypt'=>true,
                    'isRequired' =>false,
                    'fillable' => true,
                ],
                'parent_allergy_type'=>
                [
                    'type'=>'input',
                    'decrypt'=>false,
                    'isRequired' =>true,
                    'validation'=>'required',
                    'validationRulesMessege' => [
                    'medicine_name.required'   => trans('Doctors::messages.doctors_drugs_validation_parent_allergy_type_reurired'),
                    ],
                    'fillable' => true,
                ],
                'allergy_type'=>
                [
                    'type'=>'input',
                    'decrypt'=>false,
                    'isRequired' =>true,
                    'validation'=>'required',
                    'validationRulesMessege' => [
                    'medicine_dose.required'   => trans('Doctors::messages.doctors_drugs_validation_allergy_type_reurired'),
                    ],
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
            ]
        ];

        $responseValidatorForm = $this->postValidatorForm($posConfig,$request);
        if (!$responseValidatorForm['status']) {
            return $responseValidatorForm['response'];
        }

        if($responseValidatorForm['status']){
            $fillableDataAllergies = $responseValidatorForm['response']['fillable'][$tableName];
            $saveData = [
                'ip_address' => isset($requestData['ip_address']) ? $requestData['ip_address'] : '',
                'resource_type' => isset($requestData['resource_type']) ? $requestData['resource_type'] : '',
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
            $parentId = "";
            if(!empty($requestData['parent_id']) && $requestData['parent_id'] != 'undefined') {
                $parentId = $this->securityLibObj->decrypt($requestData['parent_id']);
            }else if(!empty($requestData['parent_allergy_type'])){
                $parentAllergyResult = $this->manageAllergiesObj->getParentAllergyIdByName($requestData['parent_allergy_type']);
                if(!empty($parentAllergyResult)){
                   $parentId = $parentAllergyResult->allergy_id;
                }
            }
            $childName = $fillableDataAllergies['allergy_type'];
            $saveParentData = $saveChildData = $saveData;
            if(!empty($parentId)){
                if(!empty($requestData['allergy_id'])){
                    try{
                        DB::beginTransaction();
                        $saveChildData['allergy_name'] = $childName;
                        $saveChildData['parent_id'] = $parentId;
                        $allergy_id = $this->securityLibObj->decrypt($requestData['allergy_id']);
                        $whereData = [
                            'allergy_id' => $allergy_id
                        ];
                        $newChildId = $this->manageAllergiesObj->updateRequest($saveChildData, $whereData);
                        if($newChildId){
                            DB::commit();
                            return $this->resultResponse(
                                Config::get('restresponsecode.SUCCESS'),
                                [],
                                [],
                                trans('Doctors::messages.allergies_successfull_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }else{
                            DB::rollback();
                            return $this->resultResponse(
                                Config::get('restresponsecode.ERROR'),
                                [],
                                ['messages'=>[trans('Doctors::messages.allergies_fail_add')]],
                                trans('Doctors::messages.allergies_fail_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }
                    } catch (\Exception $ex) {
                        DB::rollback();
                        $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'ManageAllergiesController', 'store');
                        return $this->resultResponse(
                            Config::get('restresponsecode.EXCEPTION'),
                            [],
                            [],
                            $eMessage,
                            $this->http_codes['HTTP_OK']
                        );
                    }
                }else if(!$this->manageAllergiesObj->isChildAllergyNameExists($childName, $parentId)){
                    try{
                        DB::beginTransaction();
                        $saveChildData['allergy_name'] = $childName;
                        $saveChildData['parent_id'] = $parentId;
                        $newChildId = $this->manageAllergiesObj->addRequest($saveChildData);
                        if($newChildId){
                            DB::commit();
                            return $this->resultResponse(
                                Config::get('restresponsecode.SUCCESS'),
                                [],
                                [],
                                trans('Doctors::messages.allergies_successfull_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }else{
                            DB::rollback();
                            return $this->resultResponse(
                                Config::get('restresponsecode.ERROR'),
                                [],
                                ['messages'=>[trans('Doctors::messages.allergies_fail_add')]],
                                trans('Doctors::messages.allergies_fail_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }
                    } catch (\Exception $ex) {
                        DB::rollback();
                        $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'ManageAllergiesController', 'store');
                        return $this->resultResponse(
                            Config::get('restresponsecode.EXCEPTION'),
                            [],
                            [],
                            $eMessage,
                            $this->http_codes['HTTP_OK']
                        );
                    }
                }else{
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        ['messages'=>[trans('Doctors::messages.allergy_already_exists')]],
                        trans('Doctors::messages.allergy_already_exists'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                try{
                    DB::beginTransaction();
                    $saveParentData['allergy_name'] = $fillableDataAllergies['parent_allergy_type'];
                    $saveParentData['parent_id'] = Config::get('dataconstants.PARENT_ALLERGY_PARENT_ID');
                    $newParentId = $this->manageAllergiesObj->addRequest($saveParentData);
                    if($newParentId){
                        $saveChildData['allergy_name'] = $childName;
                        $saveChildData['parent_id'] = $newParentId;
                        if(!empty($requestData['allergy_id'])){
                            $allergy_id = $this->securityLibObj->decrypt($requestData['allergy_id']);
                            $whereData = [
                                'allergy_id' => $allergy_id
                            ];
                            $newChildId = $this->manageAllergiesObj->updateRequest($saveChildData, $whereData);
                        }else{
                            $newChildId = $this->manageAllergiesObj->addRequest($saveChildData);
                        }
                        
                        if($newChildId){
                            DB::commit();
                            return $this->resultResponse(
                                Config::get('restresponsecode.SUCCESS'),
                                [],
                                [],
                                trans('Doctors::messages.allergies_successfull_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }else{
                            DB::rollback();
                            return $this->resultResponse(
                                Config::get('restresponsecode.ERROR'),
                                [],
                                ['messages'=>[trans('Doctors::messages.allergies_alredy_added')]],
                                trans('Doctors::messages.allergies_fail_add'),
                                $this->http_codes['HTTP_OK']
                            );
                        }
                    }else{
                        DB::rollback();
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('Doctors::messages.allergies_fail_add'),
                            $this->http_codes['HTTP_OK']
                        );
                    }
                } catch (\Exception $ex) {
                    DB::rollback();
                    $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'ManageAllergiesController', 'store');
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
    }
}
