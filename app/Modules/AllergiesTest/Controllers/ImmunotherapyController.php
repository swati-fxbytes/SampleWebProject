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
use App\Modules\AllergiesTest\Models\Immunotherapy;

class ImmunotherapyController extends Controller
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
        $this->immunotherapyObj = new Immunotherapy();

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

        $immunotherapy  = $this->immunotherapyObj->getImmunotherapyList($requestData);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $immunotherapy,
            [],
            trans('AllergiesTest::messages.Immunotherapy_list_get_success'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      Get a validator for an incoming Immunotherapy request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function immunotherapyValidations($requestData){
        $errors         = [];
        $error          = false;
        $validationData = [];
        // Check the login type is Email or Mobile
            $validationData = [
                'parent_allergy_id'         => 'required',
                // 'sub_parent_allergy_id'     => 'required',
                'quantity'                  => 'required',
            ];

        $validator  = Validator::make($requestData,$validationData);

            if($validator->fails()){
                $error  = true;
                $errors = $validator->errors();
            }
        return ["error" => $error,"errors"=>$errors];
    }

   /* @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for insert Immunotherapy data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function store(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        unset($requestData['user_type']);

        $validate = $this->immunotherapyValidations($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }
        $immunotheraphyInsertData           = $this->immunotherapyObj->doInsertImmunotherapy($requestData);
        if($immunotheraphyInsertData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $immunotheraphyInsertData,
                    [],
                    trans('AllergiesTest::messages.immunotherapy_data_inserted'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_data_not_inserted'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for Update Immunotherapy Data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function update(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        $validate = $this->immunotherapyValidations($requestData);
        unset($requestData['user_type']);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $immunotherapyUpdateData   = $this->immunotherapyObj->doUpdateImmunotherapy($requestData);
        if($immunotherapyUpdateData){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $immunotherapyUpdateData,
                [],
                trans('AllergiesTest::messages.immunotherapy_data_updated'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_data_not_updated'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible for delete Immunotherapy Data
    * @param                 Array $doc_exp_id
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        $primaryKey = $this->immunotherapyObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->immunotherapyObj->isPrimaryIdExist($primaryId);

        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('AllergiesTest::messages.immunotherapy_not_exist')]],
                trans('AllergiesTest::messages.immunotherapy_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $immunotherapyDeleteData   = $this->immunotherapyObj->doDeleteImmunotherapy($primaryId);
        if($immunotherapyDeleteData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('AllergiesTest::messages.immunotherapy_data_deleted'),
                    $this->http_codes['HTTP_OK']
                );
        }
        return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_data_not_deleted'),
                $this->http_codes['HTTP_OK']
            );
    }

}
