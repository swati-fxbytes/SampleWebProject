<?php

namespace App\Modules\Accounts\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\RestApi;
use Config;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Modules\Accounts\Models\Accounts;
use App\Modules\PaymentMode\Models\PaymentMode as PaymentMode;
use App\Modules\CheckupType\Models\CheckupType as CheckupType;

/**
 * AccountsController
 *
 * @package                RxHealth
 * @subpackage             AccountsController
 * @category               Controller
 * @DateOfCreation         04 Sep 2018
 * @ShortDescription       This controller to handle all the operation related to
                           Accounts
 **/
class AccountsController extends Controller
{

    use  RestApi;

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
        $this->accountsObj = new Accounts();
        $this->paymentModeObj = new PaymentMode();
        $this->checkupTypeObj = new CheckupType();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();
    }

    /**
    * @DateOfCreation        21 May 2018
    * @ShortDescription      This function is responsible to get the experience list if doctors
    * @param                 Integer $user_id
    * @return                Array of status and message
    */
    public function paymentsHistory(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $request->user()->user_id;

        $paymentsData = [];
        $paymentsHistory  = $this->accountsObj->getPaymentHistory($requestData);
        $paymentsHistory['paymentModes'] = $this->paymentModeObj->getDoctorPaymentModes($requestData['user_id']);
        $paymentsHistory['checkupTypes'] = $this->checkupTypeObj->getDoctorCheckupTypes($requestData['user_id']);
        if($paymentsHistory){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $paymentsHistory,
                [],
                trans('Accounts::messages.payment_history_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Accounts::messages.not_able_to_get_fetch_payments'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        21 May 2018
    * @ShortDescription      This function is responsible to get the experience list if doctor
    * @param                 Integer $user_id
    * @return                Array of status and message
    */
    public function createPayment(Request $request)
    {
        $extra = [];
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $request->user()->user_id;

        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['resource_type'] = Config::get('constants.RESOURCE_TYPE_WEB');

        $validate = $this->PaymentValidator($requestData, $extra);
        if($validate["error"]){
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $validate['errors'],
                    trans('Accounts::messages.payment_validation_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }
        $success  = $this->accountsObj->createPayment($requestData);
        if($success){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $success,
                [],
                trans('Accounts::messages.payment_added_successfully'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Accounts::messages.payment_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        21 May 2018
    * @ShortDescription      This function is responsible to get the experience list if doctors
    * @param                 Integer $user_id
    * @return                Array of status and message
    */
    public function invoicesHistory(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $request->user()->user_id;

        $invoicesHistory  = $this->accountsObj->getInvoiceHistory($requestData);
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $invoicesHistory,
                [],
                trans('Accounts::messages.invoice_history_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
    * @DateOfCreation        06 Dec 2018
    * @ShortDescription      This function is responsible for validating payment data
    * @param                 Array $requestData This contains full request data
    * @param                 Array $extra extra validation rules
    * @return                VIEW
    */
    protected function PaymentValidator(array $requestData, $extra = []) {
        $error = false;
        $errors = [];
        $validationData = [
            'pat_id'            => 'required',
            'amount'            => "required|regex:/^\d*(\.\d{1,2})?$/",
            'checkup_type_id'   => 'required',
            'payment_mode_id'   => 'required',
        ];
        $customMessages = [
            'pat_id.required'           => 'The Patient field is required',
            'amount.required'           => 'The Amount field is required',
            'amount.regex'              => 'The Amount field should be numeric with maximum 2 decimal places',
            'checkup_type_id.required'  => 'The Checkup Type is required',
            'payment_mode.required'     => 'The Payment Mode is required.',
        ];

        $validator = Validator::make(array_filter($requestData), $validationData, $customMessages);
        if($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * Remove the specified Payment from storage.
     *
     * @param $request - Request object for request data
     *
     * @return \Illuminate\Http\Response
     */
    public function deletePayment(Request $request) {
        $requestData = $this->getRequestData($request);
        $primaryId = $this->securityLibObj->decrypt($requestData['payment_id']);

        $paymentDeleted = $this->accountsObj->deletePayment($primaryId);
        if($paymentDeleted) {
            return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        [],
                        [],
                        trans('Accounts::messages.payment_deleted'),
                        $this->http_codes['HTTP_OK']
                    );
        }else{
            return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        [],
                        trans('Accounts::messages.payment_delete_failed'),
                        [],
                        $this->http_codes['HTTP_OK']
                    );
        }
    }
}
