<?php

namespace App\Modules\Auth\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Modules\Auth\Models\Auth as Users;
use App\Modules\Auth\Models\DefaultSpcializationComponentList;
use App\Modules\AppointmentCategory\Models\AppointmentCategory as AppointmentCategory;
use App\Modules\PatientGroups\Models\PatientGroups as PatientGroups;
use App\Modules\CheckupType\Models\CheckupType as CheckupType;
use App\Modules\PaymentMode\Models\PaymentMode as PaymentMode;
use App\Modules\DoctorProfile\Models\DoctorSpecialisations;
use App\Modules\ConsentForms\Models\ConsentForms as ConsentForms;
use App\Modules\Auth\Models\PasswordReset;
use App\Modules\Auth\Models\SecondDBUsers as SecondDBUsers;
use Auth;
use App\Traits\RestApi;
use Config;
use Session;
use App\Libraries\SecurityLib;
use App\Libraries\EmailLib;
use App\Libraries\ExceptionLib;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use App\Modules\Auth\Models\UserVerification;
use App\Modules\Patients\Models\DoctorPatientRelation;
use App\Modules\Auth\Models\UserDeviceToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Sabberworm\CSS\Value\URL;
use App\Modules\Doctors\Models\Doctors;
use App\Modules\Clinics\Models\Clinics;
use App\Modules\Patients\Models\Patients;
use App\Modules\DoctorProfile\Models\Timing;
use App\Modules\Visits\Models\Visits;
use App\Traits\Encryptable;
use App\Jobs\ProcessEmail;
use Spatie\Activitylog\Models\Activity;
use App\Modules\Laboratories\Models\Laboratories;
use Lcobucci\JWT\Parser;
use File, ArrayObject;
use Illuminate\Support\Str;
use App\Modules\DoctorProfile\Models\DoctorProfile;

/**
 * AuthController
 *
 * @package                SafeHealth
 * @subpackage             AuthController
 * @category               Controller
 * @DateOfCreation         09 May 2018
 * @ShortDescription       This class is responsiable for login, register, forgot password
 */
class AuthController extends Controller
{
    use RestApi;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    // @var Array $hasher
    // This protected member used for forgot password token
    protected $hasher;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(HasherContract $hasher)
    {
        $this->hasher = $hasher;
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init utility library object
        $this->utilityLibObj = new UtilityLib();

        // Init Datetime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init Auth model object
        $this->authModelObj = new Users();

        // Init SecondDB model object
        $this->secondDBModelObj = new SecondDBUsers();

        // Init UserVerification model object
        $this->userVerificationObj = new UserVerification();

        // Init Exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init Doctor model object
        $this->doctorModelObj = new Doctors();

        // Init Clinics model object
        $this->clinicsModelObj = new Clinics();

        // Init Patient model object
        $this->patientModelObj = new Patients();

        // Init Timing model object
        $this->timingObj = new Timing();

        // Init Visits model object
        $this->visitsModelObj = new Visits();

        // Init Laboratories model object
        $this->labModelObj = new Laboratories();

        // Init empty array object
        $this->emptyArrayObject = new ArrayObject();

        $this->profileModelObj = new DoctorProfile();

        // Init AppointmentCategory model object
        $this->appointmentCategoryModelObj = new AppointmentCategory();

        // Init PatientGroups model object
        $this->patientsGroupsModelObj = new PatientGroups();

        // Init CheckupType Model Object
        $this->checkupTypeObj = new CheckupType();

        // Init PaymentMode Model Object
        $this->paymentModeObj = new PaymentMode();

        // Init ConsentForms model object
        $this->consentFormsModelObj = new ConsentForms();
    }

    /**
    * @DateOfCreation        09 May 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function loginValidations($requestData, $type){
        $errors         = [];
        $error          = false;
        $validationData = [];

        // Check the login type is Email or Mobile
        if(is_numeric($requestData['user_username'])){
            $validationData = [
                'user_username' => 'required|max:10',
            ];
        }else{
            $validationData = [
                'user_username' => 'required|email|max:150',
            ];
        }

        //  For Login method only
        if($type == 'login'){
            $validationData['user_password'] = 'required';
        }

        // For Reset Password method only
        if($type == "resetpassword"){
            $validationData['token']    = 'required';
            $validationData['password'] = 'required';
        }

        //  For update password method only
        if($type == 'updatePassword'){
            $validationData['user_password'] = 'required|min:6|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!@$#%]).*$/';
        }
        $validator  = Validator::make(
            $requestData,
            $validationData
        );

        // finally check the Validation corect or not
        if($validator->fails()){
            $error  = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors"=>$errors];
    }

    /**
	* @OA\Post(
	*		path="/api/login",
	*		tags={"Auth"},
	*		summary="LMS login process",
	* 		@OA\RequestBody(
	*			required=true,
	*			@OA\JsonContent(ref="#/components/schemas/LoginRequest")
	*		),	
	*		@OA\Response(
	* 			response="200", description="User login successfully"
	* 		),
	* 		@OA\Response(
	* 			response="400", description="Validation error"
	* 		),
	* 		@OA\Response(
	* 			response="500", description="Internal server error"
	* 		)
	* 	)
	*/
    public function postLogin(Request $request)
    {
        $userInfo = [];
        $requestData = $this->getRequestData($request);
        $validate    = $this->loginValidations($requestData, 'login');
        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                $validate['errors'],
                trans('Auth::messages.user_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        //check tenant_id available for this clientID-secret.
        //if available then return row ID otherwise null
        $checkTenantExist = $this->secondDBModelObj->checkTenant($requestData);

        if(!empty($checkTenantExist))
        {
            // Check the login type is Email or Mobile
            $requestType = $this->checkRequestType($requestData['user_username']);

            $inputData = array(
                $requestType => $requestData['user_username'],
                'password' => $requestData['user_password'],
                'tenant_id' => $checkTenantExist->user_secret_id,
            );
            
            if (Auth::attempt($inputData)) {
                $user = Auth::user();
                
                // Check if user is not active or deleted
                if( $user->is_deleted == Config::get('constants.IS_DELETED_YES') OR
                    $user->user_status != Config::get('constants.USER_STATUS_ACTIVE')
                ){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        ["user" => [trans('Auth::messages.user_not_active_or_deleted')]],
                        trans('Auth::messages.user_not_active_or_deleted'),
                        $this->http_codes['HTTP_NOT_FOUND']
                    );
                }

                if($request->route()->getPrefix() == Config::get('constants.API_PREFIX')){
                    $userInfo = $this->authModelObj->getUserInfo(Auth::user());
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        [
                            "accessToken" => $user->createToken('Auth::messages.app_name')->accessToken
                            ,"user" => $userInfo],
                        [],
                        trans('Auth::messages.user_verified'),
                        $this->http_codes['HTTP_OK']
                    );
                }else{
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        Auth::user(),
                        [],
                        trans('Auth::messages.user_verified'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    ["user_password" => [trans('Auth::messages.incorrect_password')]],
                    trans('Auth::messages.incorrect_password'),
                    $this->http_codes['HTTP_NOT_ACCEPTABLE']
                );
            }
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                ["error" => [trans('Auth::messages.user_tenant_error')]],
                trans('Auth::messages.user_tenant_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        26 March 2021
    * @ShortDescription      This function is responsible for check the login data
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function whiteLablePostLogin(Request $request)
    {
        $userInfo = [];
        $requestData = $this->getRequestData($request);
        $validate    = $this->whiteLableloginValidations($requestData, 'login');
        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                $validate['errors'],
                trans('Auth::messages.user_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }

        //check tenant_id available for this clientID-secret.
        //if available then return row ID otherwise null
        $checkTenantExist = $this->secondDBModelObj->checkTenant($requestData);

        if($checkTenantExist){
            // Check the login type is Email or Mobile
            $requestType = $this->checkRequestType($requestData['user_username']);

            // Check if patient
            $inputData = array(
                $requestType => $requestData['user_username'],
                'tenant_id' => $checkTenantExist->user_secret_id,
                'password' => $requestData['user_password']
            );

            if (Auth::attempt($inputData)) {
                $user = Auth::user();

                // Check if user is not active or deleted
                if( $user->is_deleted == Config::get('constants.IS_DELETED_YES') OR
                    $user->user_status != Config::get('constants.USER_STATUS_ACTIVE')
                ){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        ["user" => [trans('Auth::messages.user_not_active_or_deleted')]],
                        trans('Auth::messages.user_not_active_or_deleted'),
                        $this->http_codes['HTTP_NOT_FOUND']
                    );
                }

                $user_type = $user->user_type;
                if($user_type != Config::get('constants.USER_TYPE_PATIENT')){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        ["user" => [trans('Auth::messages.doctor_not_permitted_to_login_app')]],
                        trans('Auth::messages.doctor_not_permitted_to_login_app'),
                        $this->http_codes['HTTP_NOT_FOUND']
                    );
                }

                // Check patient and doctor relation
                $dr_id = $this->securityLibObj->decrypt($requestData['dr_id']);
                $relation = DoctorPatientRelation::where([
                                'user_id' => $dr_id,
                                'pat_id' => $user->user_id,
                                'is_deleted' => Config::get('constants.IS_DELETED_NO')
                            ])
                            ->first();
                if(empty($relation)){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        ["user" => [trans('Auth::messages.patient_not_associated_with_doctor')]],
                        trans('Auth::messages.patient_not_associated_with_doctor'),
                        $this->http_codes['HTTP_NOT_FOUND']
                    );
                }
                if($request->route()->getPrefix() == Config::get('constants.API_PREFIX')){
                    $userInfo = $this->authModelObj->getUserInfo(Auth::user());
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        [
                            "accessToken" => $user->createToken('Auth::messages.app_name')->accessToken
                            ,"user" => $userInfo],
                        [],
                        trans('Auth::messages.user_verified'),
                        $this->http_codes['HTTP_OK']
                    );
                }else{
                    return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        Auth::user(),
                        [],
                        trans('Auth::messages.user_verified'),
                        $this->http_codes['HTTP_OK']
                    );
                }
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    ["user_password" => [trans('Auth::messages.incorrect_password')]],
                    trans('Auth::messages.incorrect_password'),
                    $this->http_codes['HTTP_NOT_ACCEPTABLE']
                );
            }
            
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                ["error" => [trans('Auth::messages.user_tenant_error')]],
                trans('Auth::messages.user_tenant_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        26 March 2021
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function whiteLableloginValidations($requestData, $type){
        $errors         = [];
        $error          = false;
        $validationData = [];

        // Check the login type is Email or Mobile
        if(is_numeric($requestData['user_username'])){
            $validationData = [
                'user_username' => 'required|max:10',
            ];
        }else{
            $validationData = [
                'user_username' => 'required|email|max:150',
            ];
        }
        
        $validationData['user_password'] = 'required';
        $validationData['dr_id'] = 'required';
        
        $validator  = Validator::make(
            $requestData,
            $validationData
        );

        // finally check the Validation corect or not
        if($validator->fails()){
            $error  = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors"=>$errors];
    }

     /**
    * @DateOfCreation        24 May 2018
    * @ShortDescription      This function is responsible check the request type and return
                             correct one
    * @param                 String/Number $user_username
    * @return                String/number $requestType
    */
    protected function checkRequestType($user_username){
        if(is_numeric($user_username)){
            $requestType = "user_mobile";
        }else{
            $requestType = "user_email";
        }
        return $requestType;
    }

    /**
    * @DateOfCreation        31 July 2018
    * @ShortDescription      This function is responsible for generate the Reset tocken
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function getResetToken(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $validate    = $this->loginValidations($requestData, 'forgot');
        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Auth::messages.user_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }
        $requestType = $this->checkRequestType($requestData['user_username']);
        $user = SecondDBUsers::where($requestType, $requestData['user_username'])->where('is_deleted',Config::get('constants.IS_DELETED_NO'))->first();

        if (!$user) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                ["email" => [trans('Auth::messages.user_not_found')]],
                trans('Auth::messages.user_not_found'),
                $this->http_codes['HTTP_NOT_FOUND']
            );
        }else if (empty($user->user_email)){
            return $this->resultResponse(
                Config::get('restresponsecode.EXCEPTION'),
                [],
                trans('Auth::messages.email_not_linked'),
                trans('Auth::messages.email_not_linked'),
                $this->http_codes['HTTP_NOT_FOUND']
            );
        }
        $isMailSent = $this->sendVerificationLink($user, $user['user_id'], $resetType = 'resetPassword');

        if($isMailSent){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('Auth::messages.forgot_link_sent'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            DB::rollback();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Auth::messages.forgot_link_sent_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        31 July 2018
    * @ShortDescription      This function is responsible for reset password
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function reset(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $validate    =  $this->resetPasswordValidations($requestData);
        if($validate["error"]){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Auth::messages.user_validation_error'),
                $this->http_codes['HTTP_OK']
            );
        }
        $userEmail          = $this->securityLibObj->decrypt($requestData['user_token']);
        $hashTokenDecrypt   = $this->securityLibObj->decrypt($requestData['token']);
        $currentTime        = $this->dateTimeLibObj->getPostgresTimestampAfterXmin();
        $verifyResult = $this->userVerificationObj->getVerificationDetailByhashAndUserEmail($hashTokenDecrypt, $userEmail, $currentTime);
        if(!empty($verifyResult))
        {
            $updateUserPassword = $this->authModelObj->userDataUpdate(['user_password' => bcrypt($requestData['password'])], ['user_email' => $userEmail, 'user_id' => $verifyResult->user_id, 'user_status'=>Config::get('constants.USER_STATUS_ACTIVE')]);
            if($updateUserPassword)
            {
                $this->userVerificationObj->deleteTokenLink($verifyResult->user_ver_id);
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Auth::messages.password_reset_success'),
                    $this->http_codes['HTTP_OK']
                );
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Auth::messages.password_invalid_token_message'),
                    $this->http_codes['HTTP_OK']
                );
            }

        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                ["email" => [trans('Auth::messages.password_invalid_token_message')]],
                trans('Auth::messages.password_invalid_token_message'),
                $this->http_codes['HTTP_NOT_FOUND']
            );
        }
    }

    /**
    * @DateOfCreation        31 July 2018
    * @ShortDescription      Get a validator for an incoming reset password request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    protected function resetPasswordValidations($requestData){
        $errors         = [];
        $error          = false;
        $validationData = [];
        // Check the login type is Email or Mobile
        $validationData = [
            'password' => 'required|min:6|regex:'.Config::get('constants.REGEX_PASSWORD'),
        ];
        $validationMessageData = [
            'password.required' => trans('passwords.password_required'),
            'password.min'      => trans('passwords.password_validate_message'),
            'password.regex'    => trans('passwords.password_validate_message'),
        ];

        $validator  = Validator::make(
            $requestData,
            $validationData,
            $validationMessageData
        );
        if($validator->fails()){
            $error  = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors"=>$errors];
    }

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to delete access token
                             for current user
    * @param                 String $id
    * @return                Array of status and message
    */
    public function logout($id, Request $request)
    {
        $user_id = $this->securityLibObj->decrypt($id);

        $value  = $request->bearerToken();
        $parser = (new Parser( ))->parse($value);
        $id     = $parser->getClaim('jti');
        $user   = Users::find($user_id);
        $token  = $user->authAccessToken()->find($id);

        if($token){
            UserDeviceToken::where([
                'user_id' => $user_id,
                'is_deleted' =>  Config::get('constants.IS_DELETED_NO')
            ])->update(['is_deleted' =>  Config::get('constants.IS_DELETED_YES')]);
            
            if($request->route()->getPrefix() == Config::get('constants.API_PREFIX')){
                $token->delete();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Auth::messages.user_logged_out'),
                    $this->http_codes['HTTP_OK']
                );
            }else{
                Auth::logout();
                Session::flush();
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    ['user' => [trans('Auth::messages.user_logged_out')]],
                    trans('Auth::messages.user_logged_out'),
                    $this->http_codes['HTTP_OK']
                );
            }
        }
        return $this->resultResponse(
            Config::get('restresponsecode.ERROR'),
            [],
            ["email" => [trans('Auth::messages.user_logged_out')]],
            trans('Auth::messages.user_logged_out'),
            $this->http_codes['HTTP_NOT_FOUND']
        );
    }

   /**
	* @OA\Post(
	*		path="/api/register",
	*		tags={"Auth"},
	*		summary="LMS registration process",
	* 		@OA\RequestBody(
	*			required=true,
	*			@OA\JsonContent(ref="#/components/schemas/RegisterRequest")
	*		),	
	*		@OA\Response(
	* 			response="200", description="User registration completed successfully"
	* 		),
	* 		@OA\Response(
	* 			response="400", description="Validation error"
	* 		),
	* 		@OA\Response(
	* 			response="500", description="Internal server error"
	* 		)
	* 	)
	*/
    public function postDoctorRegistration(Request $request)
    {
        $dateTimeObj = new DateTimeLib();
        $requestData = $this->getRequestData($request);
        $extra = [];

        // Validate request
        if($requestData['send_otp'] == 'n'){
            $extra['user_otp'] = 'required';
        }

        if(!empty($requestData['user_email'])){

            $extra['user_email'] =  'filled|email|max:150';
        }

        $validate = $this->doctorRegistrationValidator($requestData, $extra);
        if($validate["error"]){
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    $validate['errors'],
                    trans('Auth::messages.doctor_registration_validation_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }
        
        // Send OTP -- when we have msg gateway, we will open comment for send otp and verification
        /*if($requestData['send_otp'] == 'y'){
            if($this->sendOtpToVerifyMobile($requestData)){
                return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $this->emptyArrayObject,
                        [],
                        trans('Auth::messages.doctor_otp_sent_successfully'),
                        $this->http_codes['HTTP_OK']
                  );
            }else{
                return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        [
                           'user_otp' => [trans('Auth::messages.doctor_error_in_otp_genration')]
                        ],
                        trans('Auth::messages.doctor_error_in_otp_genration'),
                        $this->http_codes['HTTP_OK']
                  );
            }
        }else if(($otpErrorMsg = $this->isDoctorOTPValid($requestData)) != ''){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                [
                   'user_otp' => [$otpErrorMsg]
                ],
                $otpErrorMsg,
                $this->http_codes['HTTP_OK']
              );
        }*/
        
        // Make user type as doctor
        $requestData['user_status']   = Config::get('constants.USER_STATUS_ACTIVE');
        $requestData['user_is_mob_verified']   = Config::get('constants.USER_MOB_VERIFIED_YES');

        // Create user in database
        try {
            //before insert data into users table we have to check tenant_id available for this clientID-secret.
            //if available then return row ID otherwise null
            $checkTenantExist = $this->secondDBModelObj->checkTenant($requestData);

            //in this case create new user and insert into DB
            if (!empty($checkTenantExist)){
                $tenant_id = $checkTenantExist->user_secret_id;
                $mobileExists = Users::where([
                                        'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                        'user_mobile' => $requestData['user_mobile'],
                                        'tenant_id' => $tenant_id
                                    ])
                                    ->first();
                if(!empty($mobileExists)){
                    return $this->resultResponse(
                        Config::get('restresponsecode.ERROR'),
                        $this->emptyArrayObject,
                        ['error' => [trans('Auth::messages.mobile_exits')] ],
                        trans('Auth::messages.mobile_exits'),
                        $this->http_codes['HTTP_OK']
                    );
                }

                if(!empty($requestData['user_email'])){
                    $emailExists = Users::where([
                                        'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                        'user_email' => $requestData['user_email'],
                                        'tenant_id' => $tenant_id
                                    ])
                                    ->first();
                    if(!empty($emailExists)){
                        return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            $this->emptyArrayObject,
                            ['error' => [trans('Auth::messages.email_exits')] ],
                            trans('Auth::messages.email_exits'),
                            $this->http_codes['HTTP_OK']
                        );
                    }
                }
                DB::beginTransaction();
                $requestData['tenant_id'] = $tenant_id;
                $createdUserId = $this->secondDBModelObj->createUser($requestData);
                // validate, is query executed successfully
                if($createdUserId){
                    // We are not paasing email error to user, we are logging error
                    if($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR')){
                        $this->doctorModelObj->createDoctor($requestData, $createdUserId);
                        $clinicId = $this->clinicsModelObj->createClinic($requestData, $createdUserId);
                        if(!empty($clinicId)){
                            $timing = [
                                "appointment_type" => Config::get("constants.APPOINTMENT_TYPE_NORMAL"),
                                "clinic_id" => $clinicId,
                                "end_time" => "1800",
                                "patients_per_slot" => "1",
                                "slot_duration" => "15",
                                "start_time" => "1000",
                                "week_day" => "0",
                                "user_id" => $createdUserId
                            ];
                            $this->timingObj->createInitialTimingOnRegister($timing);
                        }
                        $this->createdefaultComponent($requestData, $createdUserId);
                    }if($requestData['user_type'] == Config::get('constants.USER_TYPE_LAB_MANAGER')){
                        $this->labModelObj->createLaboratory($requestData, $createdUserId);
                    }else{
                        $requestData['pat_code'] = $this->utilityLibObj->patientsCodeGenrator(6);
                        $this->patientModelObj->createPatient($requestData, $createdUserId);

                        $visitData = [
                            'user_id'       => Config::get('constants.DEFAULT_USER_VISIT_ID'),
                            'pat_id'        => $createdUserId,
                            'visit_type'    => Config::get('constants.PROFILE_VISIT_TYPE'),
                            'visit_number'  => Config::get('constants.INITIAL_VISIT_NUMBER'),
                            'resource_type' => $requestData['resource_type'],
                            'ip_address'    => $requestData['ip_address'],
                            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
                            'status'        => Config::get('constants.VISIT_COMPLETED'),
                        ];

                        // Create default visit
                        $visitId = '';
                        $visitId = $this->visitsModelObj->createPatientDoctorVisit('patients_visits', $visitData);
                    }

                    if(array_key_exists('user_email', $requestData)){
                        $isMailSent = 1;
                        //$isMailSent = $this->sendVerificationLink($requestData, $createdUserId);
                        if($isMailSent){
                            DB::commit();
                            // return success response
                            return $this->resultResponse(
                                    Config::get('restresponsecode.SUCCESS'),
                                    $this->emptyArrayObject,
                                    [],
                                    trans('Auth::messages.doctor_registration_successfull'),
                                    $this->http_codes['HTTP_OK']
                                  );
                        }else{
                            DB::rollback();
                            return $this->resultResponse(
                                    Config::get('restresponsecode.ERROR'),
                                    $this->emptyArrayObject,
                                    [],
                                    trans('Auth::messages.doctor_registration_fail'),
                                    $this->http_codes['HTTP_OK']
                                  );
                        }
                    }else{
                        DB::commit();
                        // return success response
                        return $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            $this->emptyArrayObject,
                            [],
                            trans('Auth::messages.doctor_registration_successfull'),
                            $this->http_codes['HTTP_OK']
                          );
                    }
                }else{
                    DB::rollback();
                    return $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            $this->emptyArrayObject,
                            [],
                            trans('Auth::messages.doctor_registration_fail'),
                            $this->http_codes['HTTP_OK']
                          );
                }
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    [trans('Auth::messages.user_tenant_error')],
                    trans('Auth::messages.user_tenant_error'),
                    $this->http_codes['HTTP_OK']
                );
            }

        } catch (\Throwable $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'AuthController', 'postDoctorRegistration');

            return $this->resultResponse(
                    Config::get('restresponsecode.EXCEPTION'),
                    [],
                    [],
                    $eMessage,
                    $this->http_codes['HTTP_OK']
                  );
        }
    }

    public function createdefaultComponent($requestData, $userId){
        if(array_key_exists('doc_spl_id', $requestData) && !empty($requestData['doc_spl_id'])){
            $doc_spl_id = $this->securityLibObj->decrypt($requestData['doc_spl_id']);
            $componentList = DefaultSpcializationComponentList::where([
                                                                    'spicialization_id' => $doc_spl_id
                                                                ])
                                                                ->first();
            $spic = [
                "user_id" => $userId,
                "user_type" => Config::get("constants.USER_TYPE_DOCTOR"),
                "spl_id"  => $doc_spl_id,
                "is_primary" => Config::get("constants.IS_PRIMARY_YES"),
                "ip_address" => $requestData['ip_address'],
                "resource_type" => $requestData['resource_type'],
                "created_by" => $userId,
                "updated_by" => $userId
            ];
            $spcObj = new DoctorSpecialisations();
            $spcObj->insertOnlySpecialisations($spic);
            if(empty($componentList)){
                $componentList = DefaultSpcializationComponentList::where([
                                                                    'spicialization_id' => "default"
                                                                ])
                                                                ->first();
            }
        }else{
            $componentList = DefaultSpcializationComponentList::where([
                                                                    'spicialization_id' => "default"
                                                                ])
                                                                ->first();
        }

        $component = $componentList->component;
        if(!empty($component)){
            $component = json_decode($component);
            foreach ($component as $cmp) {
                $rqData = [
                    "is_visible" => $cmp->is_visible,
                    "is_visible_in_profile" => $cmp->is_visible_in_profile,
                    "show_in" => $cmp->show_in,
                    "visit_cmp_id" => $cmp->id,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $visitComponents = $this->visitsModelObj->insertDefaultVisitSettingComponent($rqData);
            }
        }

        // Default Categories
        $appointmentCategory = $componentList->appointment_category;
        if(!empty($appointmentCategory)){
            $appointmentCategory = json_decode($appointmentCategory);
            foreach ($appointmentCategory as $cat) {
                $rqData = [
                    "appointment_cat_name" => $cat,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $appointmentCat = $this->appointmentCategoryModelObj->createAppointmentCategory($rqData);
            }
        }

        // Default Patient Groups
        $patientGroups = $componentList->patient_groups;
        if(!empty($patientGroups)){
            $patientGroups = json_decode($patientGroups);
            foreach ($patientGroups as $grp) {
                $rqData = [
                    "pat_group_name" => $grp,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->patientsGroupsModelObj->createPatientGroup($rqData);
            }
        }

        // Default Checkup Types
        $checkupType = $componentList->checkup_type;
        if(!empty($checkupType)){
            $checkupType = json_decode($checkupType);
            foreach ($checkupType as $ck) {
                $rqData = [
                    "checkup_type" => $ck,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->checkupTypeObj->saveCheckupType($rqData);
            }
        }

        // Default Payment Modes
        $paymentMode = $componentList->payment_mode;
        if(!empty($paymentMode)){
            $paymentMode = json_decode($paymentMode);
            foreach ($paymentMode as $pmt) {
                $rqData = [
                    "payment_mode" => $pmt,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->paymentModeObj->savePaymentMode($rqData);
            }
        }

        // Default Patient at a glance
        $patientGlance = $componentList->patient_at_a_glance;
        if(!empty($patientGlance)){
            $patientGlance = json_decode($patientGlance);
            foreach ($patientGlance as $pglance) {
                $rqData = [
                    "patg_cmp_id" => $pglance->id,
                    "is_visible" => $pglance->is_visible,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->visitsModelObj->initialInsertPatgSettingComponent($rqData);
            }
        }

        // Default Consent Form
        $consentForm = $componentList->consent_form;
        if(!empty($consentForm)){
            $consentForm = json_decode($consentForm);
            foreach ($consentForm as $form) {
                $rqData = [
                    "consent_form_id" => "",
                    "consent_form_title" => $form->consent_form_title,
                    "consent_form_content" => $form->consent_form_content,
                    "user_id" => $userId,
                    "ip_address" => $requestData['ip_address'],
                    "resource_type" => $requestData['resource_type'],
                    "created_by" => $userId,
                    "updated_by" => $userId
                ];
                $patientGroup = $this->consentFormsModelObj->saveConsentForm($rqData);
            }
        }
    }

    /**
    * @DateOfCreation        28 May 2018
    * @ShortDescription      This function is responsible for sending verification link
    * @param                 Array $requestData This contains full request data
    * @return                true/false
    */
    public function sendVerificationLink($requestData, $userId, $resetType = 'registration')
    {
        // get otp expiry time
        $expiryDatetime = $this->dateTimeLibObj->getPostgresTimestampAfterXmin(Config::get('app.link_expiry_time_in_minuit'));
        if(!$expiryDatetime){
            return false;
        }

        // get six digit random otp
        $linkhash = $this->utilityLibObj->randomNumericInteger();

        // Make insert data to store otp in database
        $inserData = array(
            'user_id'               => $userId,
            'user_ver_object'       => $requestData['user_email'],
            'user_ver_obj_type'     => Config::get('constants.USER_VERI_OBJECT_TYPE_EMAIL'),
            'user_ver_hash_otp'     => $linkhash,
            'user_ver_expiredat'    => $expiryDatetime,
            'resource_type'         => $requestData['resource_type'],
            'ip_address'            => $requestData['ip_address'],
            'created_by'            => 0, // This record genrated by system it self
            'updated_by'            => 0
        );

        // store hash in database
        $isHashStored = $this->userVerificationObj->saveLinkHashInDatabase($inserData);
        if($isHashStored){
            if($resetType == 'resetPassword'){
                $encryptEmailID = $this->securityLibObj->encrypt($requestData['user_email']);
                $encryptedLinkHash = $this->securityLibObj->encrypt($linkhash);
                // Send Email to user
                $emailConfig = [
                    'viewData'      => [
                                        'user' => $requestData,
                                        'reset_url' => url('/forgot-password-verification/'.$encryptEmailID.'/'.$encryptedLinkHash),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.forgotpassword',
                    'subject'       => trans('emailmessage.subject_reset_password'),
                    'to' => $requestData['user_email']
                ];
            } else if($resetType == 'patientPassword'){
                $encryptEmailID = $this->securityLibObj->encrypt($requestData['user_email']);
                $encryptedLinkHash = $this->securityLibObj->encrypt($linkhash);
                // Send Email to user
                $emailConfig = [
                    'viewData'      => [
                                        'user' => $requestData,
                                        'generate_password_url' => url('/generate-password/'.$encryptEmailID.'/'.$encryptedLinkHash),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.patientpassword',
                    'subject'       => trans('frontend.site_title').' | '.trans('emailmessage.subject_new_password'),
                    'to' => $requestData['user_email']
                ];
            } else {
                // Create verification link
                $encryptedUserId = $this->securityLibObj->encrypt($userId);
                $encryptedLinkHash = $this->securityLibObj->encrypt($linkhash);
                $verification_link = url('/')."/verify/".$encryptedUserId."/".$encryptedLinkHash;

                // Prepare email config
                $userPrefix = isset($requestData['user_type']) && $requestData['user_type'] == Config::get('constants.USER_TYPE_PATIENT') ? '' : Config::get('constants.DOCTOR_TITLE').' ';
                $emailConfig = [
                    'viewData' => [
                            'name'              => $userPrefix.$requestData['user_firstname'].' '.$requestData['user_lastname'],
                            'verification_link' => $verification_link,
                            'app_name'          => Config::get('constants.APP_NAME'),
                            'app_url'           => Config::get('constants.SUPPORT_PAGE_URL'),
                            'support_email'     => Config::get('constants.SUPPORT_EMAIL'),
                            'unsubscribe_email' => Config::get('constants.UNSUBSCRIBE_EMAIL'),
                        ],
                    'emailTemplate' => 'emails.doctorregistration',
                    'subject' => trans('Auth::messages.registration_email_subject'),
                    'to' => $requestData['user_email']
                ];
            }

            // Send verification mail
            ProcessEmail::dispatch($emailConfig);
            
            return true;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        14 May 2018
    * @ShortDescription      This function is responsible for validating Doctor OTP
    * @param                 Array $requestData This contains full request data
    * @return                Error Array
    */
    protected function isDoctorOTPValid($requestData){
        $errorMsg = "";
        $otpDetail = $this->userVerificationObj->getVerificationDetailByMob($requestData['user_mobile']);

        // Check otp
        if($requestData['user_otp'] != $otpDetail->user_ver_hash_otp){
            $errorMsg = trans('Auth::messages.doctor_wrong_otp');
        }else if($this->dateTimeLibObj->isTimePassed($otpDetail->user_ver_expiredat, Config::get('app.database_timezone'))){
            $errorMsg = trans('Auth::messages.doctor_otp_expired');
        }

        return $errorMsg;
    }

    /**
    * @DateOfCreation        11 May 2018
    * @ShortDescription      This function is responsible for validating Doctor data
    * @param                 Array $data This contains full request data
    * @param                 Array $extra extra validation rules
    * @return                Error Array
    */
    protected function doctorRegistrationValidator(array $data, $extra = [])
    {
        $error = false;
        $errors = [];
        $rules =  [
            'user_firstname' => 'required|string|max:150|min:3',
            'user_lastname' => 'required|string|max:150|min:3',
            'user_country_code' => 'required',
            'user_gender'   => 'required',
            'user_mobile' => 'required|numeric|regex:/[0-9]{10}/',
            'user_adhaar_number'=> 'filled|numeric|regex:/[0-9]{12}/',
            //'user_email' => 'filled|email',
            'user_password' => 'required|min:6|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!@$#%]).*$/',
            'resource_type' => 'required|integer'
        ];
        $rules = array_merge($rules,$extra);

        $validator = Validator::make($data, $rules);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * @DateOfCreation        23 May 2018
    * @ShortDescription      This function is responsible for sending otp
    * @param                 Array $data This contains full request data
    * @return                Error Array
    */
    protected function sendOtpToVerifyMobile($requestData){
        // get otp expiry time
        $expiryDatetime = $this->dateTimeLibObj->getPostgresTimestampAfterXmin(Config::get('app.otp_expiry_time_in_minuit'));
        if(!$expiryDatetime){
            return false;
        }
        // get six digit random otp
        //$otp = $this->utilityLibObj->randomNumericInteger();
        // By pass otp as currently we have not message gateway
        $otp = 123456;

        // Make insert data to store otp in database
        $inserData = array(
            'user_id'               => 0, // we are not registring user without otp verification
            'user_ver_object'       => $requestData['user_mobile'],
            'user_ver_obj_type'     => Config::get('constants.USER_VERI_OBJECT_TYPE_MOBILE'),
            'user_ver_hash_otp'     => $otp,
            'user_ver_expiredat'    => $expiryDatetime,
            'resource_type'         => $requestData['resource_type'],
            'ip_address'            => $requestData['ip_address'],
            'created_by'            => 0, // This record genrated by system it self
            'updated_by'            => 0
        );

        // store otp in database
        $isOTPStored = $this->userVerificationObj->saveOTPInDatabase($inserData);

        return $isOTPStored;
    }

    /**
     * @DateOfCreation        22 May 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getLogo(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $imagePath = env('SAFE_HEALTH_APP_URL').'app/public/images/Rxlogo.png';
        $file = File::get($imagePath);
        $type = File::mimeType($imagePath);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }


    public function refreshToken(Request $request) {

        //first we have check that userId is exist in users table
        //if user available then check token is exist in DB or not
        //if token is available then update old entry and assign new token
        //to user and also update entry in 2nd DB.

        $requestData = $request->all();
        $validator = Validator::make($requestData, [
            'access_token' => 'required',
            'user_id'      => 'required',
            'device_type'  => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return $this->resultResponse(
                Config::get('restresponsecode.BAD_REQUEST'),
                $this->emptyArrayObject,
                $errors,
                ''
            );
        }

        //check user exists
        $checkUserExist = SecondDBUsers::where(['user_id' => $requestData['user_id']])->exists();

         if (!$checkUserExist) {
            return $this->resultResponse(
                Config::get('restresponsecode.BAD_REQUEST'),
                $this->emptyArrayObject,
                trans('messages.user_not_found'),
                ''
            );
        }

        //check old token is exist in DB or not
        $checkTokenExist = $this->secondDBModelObj->getToken($requestData);
        if (!$checkTokenExist) {
            return $this->resultResponse(
                Config::get('restresponsecode.BAD_REQUEST'),
                $this->emptyArrayObject,
                trans('messages.invalid_request'),
                ''
            );
        }

        //update token using userId, old token and device type in table

        //prepare update data
        $access_token_id = Str::random(150);
        $expires_at = date('Y-m-d H:i:s', strtotime(date(Config::get('constants.STORE_DATE_TIME')) . ' +1 day'));

        $updateData['access_token'] = $access_token_id;
        $updateData['expires_at']   = $expires_at;

        $token_id = $this->secondDBModelObj->updateToken($requestData, $updateData);

        $second_token_id = $this->authModelObj->updateToken($requestData, $updateData);

        $data['token'] = $access_token_id;
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $data,
                '',
                trans('messages.refresh_token')
            );
    }

    public function userSecret(Request $request) {

        //first we have check that tenant_name is exist in user_secret table
        //if tenant_name available then do nothing otherwise insert new entry
        //in table with tenant name

        $requestData = $request->all();
        $validator = Validator::make($requestData, [
            'tenant_name' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return $this->resultResponse(
                Config::get('restresponsecode.BAD_REQUEST'),
                $this->emptyArrayObject,
                $errors,
                ''
            );
        }

        //check tenant_name is exist in DB or not
        $checkSecretExist = $this->authModelObj->getClientSecret($requestData);

        //in this case create new secret and insert into DB
        if ($checkSecretExist->isEmpty()) {

            //prepare insert data
            $clientId     = Str::uuid();
            $clientSecret = Str::random(60);

            $insertData['client_secret'] = $clientSecret;
            $insertData['client_id']     = $clientId;
            $insertData['tenant_name']   = $requestData['tenant_name'];

            $secretId = $this->authModelObj->insertClientSecret($insertData);

            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $insertData,
                '',
                trans('register_investigator.client_id_secret_generated')
            );
        }

        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $checkSecretExist,
            '',
             trans('register_investigator.client_id_secret_generated')
        );

    }

    public function updatePasswordForPatient(){
        $list = Users::where([
                        'user_type' => Config::get('constants.USER_TYPE_PATIENT'),
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    ])
                    ->whereNull('user_password')
                    ->get();
        foreach ($list as $us) {
            $user = Users::find($us->user_id);
            if($user){
                $user->user_password = Hash::make($us->user_mobile);
                $user->save();
            }
        }
        print_r($list->toArray());die;
    }

    public function createHashByString(Request $request){
        $requestData = $this->getRequestData($request);
        $rules =  [
            'string' => 'required',
            'type' => 'required'  // hash or security 
        ];

        $validator = Validator::make($requestData, $rules);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    $errors,
                    trans('Auth::messages.device_register_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }

        if($requestData['type'] == 'hash'){
            $string = Hash::make($requestData['string']);
        }else{
            if($requestData['convert'] == 'encrypt')
                $string = $this->securityLibObj->encrypt($requestData['string']);
            else
                $string = $this->securityLibObj->decrypt($requestData['string']);
        }
        echo $string;
        die;
    }

    public function registerDevice(Request $request){
        $requestData = $this->getRequestData($request);
        $rules =  [
            'token' => 'required',
            'plateform' => 'required'
        ];

        $validator = Validator::make($requestData, $rules);
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    $this->emptyArrayObject,
                    $errors,
                    trans('Auth::messages.device_register_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }

        $user = Auth::user();
        $user_id = $user->user_id;
        $data = [
            "token" => $requestData['token'],
            "plateform" => strtolower($requestData['plateform']),
            "user_id" => $user_id
        ];
        $details = UserDeviceToken::create($data);

        if($details){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $details,
                [],
                trans('Auth::messages.device_register_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                $this->emptyArrayObject,
                [],
                trans('Auth::messages.device_register_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
    }
}