<?php

namespace App\Modules\AllergiesTest\Controllers;

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
use App\Modules\AllergiesTest\Models\AllergiesTest;

class AllergiesTestController extends Controller
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

        // Init Doctor experience Model Object
        $this->allergiesTestObj = new AllergiesTest();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        
        $allergiesTest  = $this->allergiesTestObj->getAllergiesTestList($requestData);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $allergiesTest,
            [],
            trans('AllergiesTest::messages.Allergies_test_get_success'),
            $this->http_codes['HTTP_OK']
        );
    }


    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      Get a validator for an incoming allergies test request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function allergiesTestValidations($requestData){
        $errors         = [];
        $error          = false;
        $validationData = [];
        $allergies_validation = '';
        $allergies_validationMessage = '';
        if($requestData['end_month'] < $requestData['start_month']){
            $allergies_validation = 'after';
            $allergies_validationMessage = trans('AllergiesTest::messages.end_month_after');
        }

        // Check the login type is Email or Mobile
        $validationData = [
            'parent_allergy_id'         => 'required',
            'start_month'               => 'required|max:2|min:1',
            'end_month'                 => 'required|max:2|min:1|'.$allergies_validation.':start_month',
            'percutaneous_start_month_w' => 'nullable|numeric',
            'percutaneous_start_month_f' => 'nullable|numeric',
            'percutaneous_end_month_w' => 'nullable|numeric',
            'percutaneous_end_month_f' => 'nullable|numeric',
        ];

        $validationMessage = [
            'end_month.'.$allergies_validation => $allergies_validationMessage
        ];

        $validator  = Validator::make(
            $requestData,
            $validationData,
            $validationMessage
        );
            if($validator->fails()){
                $error  = true;
                $errors = $validator->errors();
            }
        return ["error" => $error,"errors"=>$errors];
    }

   /* @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for insert Allergies test data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function store(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        unset($requestData['user_type']);
        $validate = $this->allergiesTestValidations($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }
        $allegiesInsertData = $this->allergiesTestObj->doInsertAllergiesTest($requestData);
        if($allegiesInsertData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $allegiesInsertData,
                    [],
                    trans('AllergiesTest::messages.allergies_test_data_inserted'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.allergies_test_data_not_inserted'),
                $this->http_codes['HTTP_OK']
            );
        }
    }


    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for Update allergies test Data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function update(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        unset($requestData['user_type']);
        $validate           = $this->allergiesTestValidations($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $allergiesUpdateData   = $this->allergiesTestObj->doUpdateAllergiesTest($requestData);
        if($allergiesUpdateData){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $allergiesUpdateData,
                [],
                trans('AllergiesTest::messages.allergies_test_data_updated'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.allergies_test_data_not_updated'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

     /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for delete allergies test Data
    * @param                 Array $doc_exp_id
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        $primaryKey = $this->allergiesTestObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->allergiesTestObj->isPrimaryIdExist($primaryId);

        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('AllergiesTest::messages.allergies_test_not_exist')]],
                trans('AllergiesTest::messages.allergies_test_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $allergiestestDeleteData = $this->allergiesTestObj->doDeleteAllergiesTest($primaryId);
        if($allergiestestDeleteData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('AllergiesTest::messages.allergies_test_data_deleted'),
                    $this->http_codes['HTTP_OK']
                );
        }
        return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.allergies_test_data_not_deleted'),
                $this->http_codes['HTTP_OK']
            );
    }

}
