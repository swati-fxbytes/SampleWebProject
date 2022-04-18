<?php

namespace App\Modules\DoctorProfile\Controllers;
use App\Modules\DoctorProfile\Models\DoctorProfile as Doctors;
use App\Modules\DoctorProfile\Models\DoctorExperience;
use App\Modules\DoctorProfile\Models\DoctorAward;
use App\Modules\DoctorProfile\Models\DoctorDegree;
use App\Modules\DoctorProfile\Models\DoctorMedia;
use App\Modules\DoctorProfile\Models\DoctorMembership;
use App\Modules\DoctorProfile\Models\DoctorSpecialisations;
use App\Modules\DoctorProfile\Models\States as States;
use App\Modules\DoctorProfile\Models\Cities as Cities;
use App\Modules\DoctorProfile\Models\DoctorColorSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Traits\RestApi;
use App\Libraries\SecurityLib;
use App\Libraries\S3Lib;
use App\Libraries\ExceptionLib;
use File;
use Response;
use Config;
use Auth;

/**
 * DoctorProfileController
 *
 * @package                Safe health
 * @subpackage             DoctorProfileController
 * @category               Controller
 * @DateOfCreation         21 may 2018
 * @ShortDescription       This controller to get all the info related to the doctor and
                           also need to check the authentication
 **/
class DoctorProfileController extends Controller
{

    use RestApi;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();
        $this->profileModelObj = new Doctors();
        $this->statesModelObj = new States();
        $this->cityModelObj = new Cities();
        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init S3 bucket library object
        $this->s3LibObj = new S3Lib();
    }

    /**
    * This function is responsible for validating membership data
    *
    * @param  Array $data This contains full member input data
    * @return Array $error status of error
    */
    private function DoctorProfileValidator(array $requestData)
    {
        $error  = false;
        $errors = [];
        $validationData  = [
            'user_firstname' => 'required',
            'user_lastname' => 'required',
            'user_mobile' => 'required'
        ];
        $validator  = Validator::make(
            $requestData,
            $validationData
        );
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        $requestData = $this->getRequestData($request);
        // Validate request
        $validate = $this->DoctorProfileValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('DoctorProfile::messages.profile_update_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
        try {
            DB::beginTransaction();
            $doctorProfile = $this->profileModelObj->updateProfile($requestData);
            // validate, is query executed successfully
            if($doctorProfile)
            {
                DB::commit();
                return  $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            $doctorProfile,
                            [],
                             trans('DoctorProfile::messages.profile_update_success'),
                            $this->http_codes['HTTP_OK']
                        );

            }else{
                DB::rollback();
                return  $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('DoctorProfile::messages.profile_fatch_fail'),
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'DoctorProfileController', 'updateProfile');
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateImage(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $user_id = $request->user()->user_id;

        $uploadedImage = $this->profileModelObj->updateProfileImage($requestData,$user_id);

        // validate, is query executed successfully
        if($uploadedImage){
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $uploadedImage,
                [],
                trans('DoctorProfile::messages.profile_image_success'),
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
        $imagePath =  'app/public/'.Config::get('constants.DOCTOR_MEDIA_PATH');
        $path = storage_path($imagePath) . $imageName;
        if(!File::exists($path)){
            $path = public_path(Config::get('constants.DEFAULT_IMAGE_PATH'));
        }
        $file = File::get($path);
        $type = File::mimeType($path);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }

     /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request memberlist of doctor
     * @return \Illuminate\Http\Response
     */
    public function getProfileDetail(Request $request)
    {
        $profileDetail = [];

        $user_id = $request->user()->user_id;

        $profileDetail     =  $this->profileModelObj->getProfileDetail($user_id);
        // validate, is query executed successfully
        if($profileDetail)
        {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $profileDetail,
                '',
                trans('DoctorProfile::messages.profile_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.profile_fatch_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request memberlist of doctor
     * @return \Illuminate\Http\Response
     */
    public function states()
    {
        $states =  $this->statesModelObj->getAllStates();

        // validate, is query executed successfully
        if($states)
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $states,
                [],
                '',
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                trans('DoctorProfile::messages.state_not_found'),
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request memberlist of doctor
     * @return \Illuminate\Http\Response
     */
    public function cities(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $cities =  $this->cityModelObj->getCityByState($requestData);
        // validate, is query executed successfully
        if($cities)
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $cities,
                [],
                '',
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                trans('DoctorProfile::messages.city_not_found'),
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }
    /**
    * @DateOfCreation        11 May 2018
    * @ShortDescription      This function is responsible for update password
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function passwordUpdate(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $validate = $this->_passwordValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('DoctorProfile::messages.password_updation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $existingPassword = $request->user()->user_password;
        $isPasswordNotExist = $this->profileModelObj->isPasswordExist($requestData,$existingPassword);

        if($isPasswordNotExist){
             return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                ["user_password" => [trans('DoctorProfile::messages.invalid_old_password')]],
                trans('DoctorProfile::messages.invalid_old_password'),
                $this->http_codes['HTTP_NOT_FOUND']
            );
        }

        $userId = $request->user()->user_id;

        $isUpdated = $this->profileModelObj->passwordUpdate($requestData,$userId);
         if(!empty($isUpdated)){
             return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [],
                [],
                trans('DoctorProfile::messages.password_updation_successfull'),
                $this->http_codes['HTTP_OK']
              );
        }else{
             return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.password_updation_failed'),
                $this->http_codes['HTTP_OK']
              );
        }
    }

    /**
    * This function is responsible for validating password data
    *
    * @param  Array $data This contains password input data
    * @return Array $error status of error
    */
    private function _passwordValidator(array $requestData)
    {
        $error = false;
        $errors = [];
        $validationData = [];
        $validationData = [
            'user_password' => 'required|min:6'
         ];

        $validator  = Validator::make(
            $requestData,
            $validationData
        );
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * @DateOfCreation        31 Dec 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getThumbProfileImage($type='small', $imageName, Request $request)
    {
        $s3LibObj = new S3Lib();
        $requestData = $this->getRequestData($request);
        $defaultPath = ($type == 'small' ? public_path(Config::get('constants.DEFAULT_SMALL_PATH')) : public_path(Config::get('constants.DEFAULT_MEDIUM_PATH')));
        $imageName = $this->securityLibObj->decrypt($imageName);
        $imageName = empty($imageName) ? Config::get('constants.DEFAULT_IMAGE_NAME'):$imageName;
        $path = Config::get('constants.DOCTOR_PROFILE_S3_PATH').$imageName;

        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            if($s3LibObj->isFileExist($path)){
                 return $response = $s3LibObj->getObject($path)['fileObject'];
            }
        }else{
            $path_name = ($type == 'small' ? Config::get('constants.DOCTOR_PROFILE_STHUMB_IMG_PATH') : Config::get('constants.DOCTOR_PROFILE_MTHUMB_IMG_PATH'));
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
     * Update Doctor Settings.
     *
     * @param  int  $request
     * @return \Illuminate\Http\Response
     */
    public function saveDoctorSettings(Request $request)
    {
        $requestData = $this->getRequestData($request);
        // Validate request
        $validate = $this->DoctorSettingsValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('DoctorProfile::messages.profile_update_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
        try {
            DB::beginTransaction();
            $isUpdated = $this->profileModelObj->saveDoctorSettings($requestData);
            // validate, is query executed successfully
            if($isUpdated)
            {
                DB::commit();
                return  $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('DoctorProfile::messages.doctor_settings_success'),
                            $this->http_codes['HTTP_OK']
                        );

            }else{
                DB::rollback();
                return  $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('DoctorProfile::messages.doctor_settings_fail'),
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'DoctorProfileController', 'saveDoctorSettings');
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
     * Update Doctor Settings.
     *
     * @param  int  $request
     * @return \Illuminate\Http\Response
     */
    public function saveSmsSettings(Request $request)
    {
        $requestData = $this->getRequestData($request);
        // Validate request
        $requestData['send_birthday_sms'] = count($requestData['send_birthday_sms']) > 0 ? $requestData['send_birthday_sms'][0] : null;
        $requestData['send_welcome_sms'] = count($requestData['send_welcome_sms']) > 0 ? $requestData['send_welcome_sms'][0] : null;
        $requestData['send_anniversary_sms'] = count($requestData['send_anniversary_sms']) > 0 ? $requestData['send_anniversary_sms'][0] : null;
        $requestData['send_medicine_reminder_sms'] = count($requestData['send_medicine_reminder_sms']) > 0 ? $requestData['send_medicine_reminder_sms'][0] : null;
        $validate = $this->SmsSettingsValidator($requestData);
        if($validate["error"])
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('DoctorProfile::messages.profile_update_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
        try {
            DB::beginTransaction();
            $requestData['welcome_sms_content'] = (isset($requestData['welcome_sms_content'])) ? $requestData['welcome_sms_content'] : "";
            $requestData['birthday_sms_content'] = (isset($requestData['birthday_sms_content'])) ? $requestData['birthday_sms_content'] : "";
            $requestData['anniversary_sms_content'] = (isset($requestData['anniversary_sms_content'])) ? $requestData['anniversary_sms_content'] : "";
            $requestData['medicine_reminder_sms_content'] = (isset($requestData['medicine_reminder_sms_content'])) ? $requestData['medicine_reminder_sms_content'] : "";
            $isSaved = $this->profileModelObj->saveSmsSettings($requestData);
            // validate, is query executed successfully
            if($isSaved)
            {
                DB::commit();
                return  $this->resultResponse(
                            Config::get('restresponsecode.SUCCESS'),
                            [],
                            [],
                            trans('DoctorProfile::messages.doctor_settings_success'),
                            $this->http_codes['HTTP_OK']
                        );

            }else{
                DB::rollback();
                return  $this->resultResponse(
                            Config::get('restresponsecode.ERROR'),
                            [],
                            [],
                            trans('DoctorProfile::messages.doctor_settings_fail'),
                            $this->http_codes['HTTP_OK']
                        );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex,'DoctorProfileController', 'saveSmsSettings');
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
    * This function is responsible for validating doctor settings data
    *
    * @param  Array $data This contains full member input data
    * @return Array $error status of error
    */
    private function DoctorSettingsValidator(array $requestData)
    {
        $error  = false;
        $errors = [];
        $validationData  = [
            'pat_code_prefix' => 'required|alpha',
        ];
        $messages = [
            'pat_code_prefix.required'    => 'The Patient Registration Number Prefix is required',
            'pat_code_prefix.alpha'    => 'The Patient Registration Number Prefix must contain only letters.',
        ];
        $validator  = Validator::make(
            $requestData,
            $validationData,
            $messages
        );
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * This function is responsible for validating doctor settings data
    *
    * @param  Array $data This contains full member input data
    * @return Array $error status of error
    */
    private function SmsSettingsValidator(array $requestData)
    {
        $error  = false;
        $errors = [];
        $validationData  = [
            'send_birthday_sms' => 'required',
            'send_welcome_sms' => 'required',
            'send_anniversary_sms' => 'required',
            'send_medicine_reminder_sms' => 'required',
        ];
        $messages = [
            'send_birthday_sms.required'    => 'This field is required',
            'send_welcome_sms.required'    => 'This field is required',
            'send_anniversary_sms.required'    => 'This field is required',
            'send_medicine_reminder_sms.required'    => 'This field is required',
        ];
        $validator  = Validator::make(
            $requestData,
            $validationData,
            $messages
        );
        if($validator->fails()){
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

     /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request memberlist of doctor
     * @return \Illuminate\Http\Response
     */
    public function getPatCodePrefix(Request $request)
    {
        $detail = [];
        $user_id = $request->user()->user_id;

        $detail     =  $this->profileModelObj->getPatCodePrefix($user_id);
        // validate, is query executed successfully
        if($detail)
        {
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $detail,
                '',
                trans('DoctorProfile::messages.doctor_settings_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.doctor_settings_fetch_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

     /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request memberlist of doctor
     * @return \Illuminate\Http\Response
     */
    public function getSmsSettingsData(Request $request)
    {   
        $user_id = Auth::user()->user_id;
        $detail  =  $this->profileModelObj->getSmsSettingsData($user_id);

        // validate, is query executed successfully
        if($detail)
        {
            $detail = ($detail === true) ? [] : $detail;
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $detail,
                '',
                trans('DoctorProfile::messages.doctor_settings_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.doctor_settings_fetch_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /* @DateOfCreation        21 Jan 2018
     * @ShortDescription      This function is responsible to trasnfer all data from folder to S3
     * @return                upload status
     */
    public function transferAllDoctorProfileImages(){
        $directory = storage_path('app/public/'.Config::get('constants.DOCTOR_PROFILE_PATH'));
        $S3filePath = 'doctors/doctorsprofile/';
        return $this->s3LibObj->folderToS3Bucket($directory, $S3filePath);
    }

    public function getColorCode(Request $request){
        $requestData = $this->getRequestData($request);
        $user_id = $request->user()->user_id;

        $colorCode = DoctorColorSetting::select("dr_id", "primary_color_code", "secondary_color_code")
                                        ->where([
                                            "dr_id" => $user_id,
                                            "is_deleted" => Config::get('constants.IS_DELETED_NO')
                                        ])
                                        ->first();
        // validate, is query executed successfully
        if(!empty($colorCode))
        {
            $colorCode->dr_id = $this->securityLibObj->encrypt($colorCode->dr_id);
            return  $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $colorCode,
                '',
                trans('DoctorProfile::messages.doctor_settings_fetch_success'),
                $this->http_codes['HTTP_OK']
            );
        }else{
            return  $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('DoctorProfile::messages.doctor_settings_fetch_fail'),
                $this->http_codes['HTTP_OK']
            );
        }
    }
}