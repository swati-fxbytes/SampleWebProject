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
use App\Modules\AllergiesTest\Models\ImmunotherapyChart;
use App\Libraries\DateTimeLib;

class ImmunotherapyChartController extends Controller
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
        $this->immunotherapyChartObj = new ImmunotherapyChart();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init exception library object
        $this->dateTimeLibObj = new DateTimeLib();
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

        $immunotherapyChart  = $this->immunotherapyChartObj->getImmunotherapyChartList($requestData);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $immunotherapyChart,
            [],
            trans('AllergiesTest::messages.immunotherapy_chart_list_get_success'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
    * @DateOfCreation        24 Jan 2019
    * @ShortDescription      Get a validator for an incoming Immunotherapy Chart request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function immunotherapyChartValidations($requestData){
        $errors         = [];
        $error          = false;
        $validationData = [];

        // Check the login type is Email or Mobile
        $validationData = [
            'dose_conc_of_antigen'      => 'required',
            'dose_date'                 => 'required',
            'dose'                      => 'required',
            'type'                      => 'required',
        ];

        $validator  = Validator::make($requestData,$validationData);

            if($validator->fails()){
                $error  = true;
                $errors = $validator->errors();
            }
        return ["error" => $error,"errors"=>$errors];
    }

    /* @DateOfCreation        24 Jan 2019
    * @ShortDescription      This function is responsible for insert ImmunotherapyChart data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function store(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;
        $requestData['dose_date'] = !empty($requestData['dose_date']) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['dose_date'],'dd/mm/YY','Y-m-d')['result'] : NULL;
        unset($requestData['user_type']);

        $validate = $this->immunotherapyChartValidations($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }
        $immunotheraphyChartInsertData  = $this->immunotherapyChartObj->doInsertImmunotherapyChart($requestData);
        if($immunotheraphyChartInsertData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $immunotheraphyChartInsertData,
                    [],
                    trans('AllergiesTest::messages.immunotherapy_chart_data_inserted'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_chart_data_not_inserted'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        24 Jan 2019
    * @ShortDescription      This function is responsible for Update Immunotherapy Chart Data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function update(Request $request)
    {
        $requestData        = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

         $requestData['dose_date'] = !empty($requestData['dose_date']) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['dose_date'],'dd/mm/YY','Y-m-d')['result'] : NULL;
        unset($requestData['user_type']);
        $validate = $this->immunotherapyChartValidations($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('AllergiesTest::messages.validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        $immunotherapyChartUpdateData   = $this->immunotherapyChartObj->doUpdateImmunotherapyChart($requestData);
        if($immunotherapyChartUpdateData){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $immunotherapyChartUpdateData,
                [],
                trans('AllergiesTest::messages.immunotherapy_chart_data_updated'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_chart_data_not_updated'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        24 Jan 2019
    * @ShortDescription      This function is responsible for delete ImmunotherapyChart Data
    * @param                 Array $doc_exp_id
    * @return                Array of status and message
    */
    public function destroy(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = ($request->user()->user_type == Config::get('constants.USER_TYPE_DOCTOR')) ? $request->user()->user_id : $request->user()->created_by;

        $primaryKey = $this->immunotherapyChartObj->getTablePrimaryIdColumn();
        $primaryId = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->immunotherapyChartObj->isPrimaryIdExist($primaryId);

        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('AllergiesTest::messages.immunotherapy_not_exist')]],
                trans('AllergiesTest::messages.immunotherapy_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $immunotherapyChartDeleteData   = $this->immunotherapyChartObj->doDeleteImmunotherapyChart($primaryId);
        if($immunotherapyChartDeleteData){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('AllergiesTest::messages.immunotherapy_chart_data_deleted'),
                    $this->http_codes['HTTP_OK']
                );
        }
        return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('AllergiesTest::messages.immunotherapy_chart_data_not_deleted'),
                $this->http_codes['HTTP_OK']
            );
    }

}
