<?php

namespace App\Modules\DoctorProfile\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use App\Libraries\SecurityLib;
use App\Libraries\FileLib;
use App\Libraries\ImageLib;
use App\Libraries\S3Lib;
use Config;
use File;
use App\Modules\Region\Models\Country;
use Uuid;

/**
 * DoctorProfile
 *
 * @package                ILD India Registry
 * @subpackage             DoctorProfile
 * @category               Model
 * @DateOfCreation         18 May 2018
 * @ShortDescription       This class is responsiable for Doctors profile
 */
class DoctorProfile extends Model {

    use HasApiTokens,Encryptable;

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'doc_short_info', 'user_id', 'doc_profile_img', 'doc_facebook_url', 'doc_linkedin_url', 'doc_twitter_url', 'doc_googlep_url', 'user_city' ,'user_state_id', 'doc_locality', 'doc_email', 'password', 'doc_city', 'doc_state_id', 'doc_country_id', 'doc_pincode', 'doc_status','doc_latitude','doc_longitude'
    ];

    /**
    * @DateOfCreation        18 May 2018
    * @ShortDescription      Fetch the Doctors experience
    * @return                Array
    */
    public function doctorsExperince()
    {
        return $this->hasMany('App\Modules\DoctorProfile\Models\DoctorExperience','doc_id', 'doc_id');
    }

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
    */
    protected $primaryKey = 'user_id';

    /**
     *@ShortDescription Override the Table.
     *
     * @var string
    */
    protected $table = 'doctors';
    protected $tableDoctorSettings = 'doctor_settings';
    protected $ClinicTable = 'clinics';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();
        $this->FileLib = new FileLib();
        $this->s3LibObj = new S3Lib();

        // Init Country model object
        $this->countrytModelObj = new Country();
    }

    /**
     * Update doctor profile details
     *
     * @param array $data detailed data and doctor id
     * @return boolean true if updated
     */
    public function updateProfile($requestData)
    {
        $userDetail = [
            'user_firstname'  => $requestData['user_firstname'],
            'user_lastname'   => $requestData['user_lastname'],
            'user_mobile'     => $requestData['user_mobile'],
            'user_gender'     => $requestData['user_gender']
        ];

        $clinicData = [
            'clinic_address_line1'  => $requestData['doc_address_line1'],
            'clinic_latitude'       => $requestData['doc_latitude'],
            'clinic_longitude'      => $requestData['doc_longitude'],
        ];

        $doctorDetail = [
            'doc_short_info'    => $requestData['doc_short_info'],
            'doc_consult_fee'   => $requestData['doc_consult_fee'],
            'doc_address_line1' => $requestData['doc_address_line1'],
            'doc_address_line2' => $requestData['doc_address_line2'],
            'city_id'           => $this->securityLibObj->decrypt($requestData['city_id']),
            'state_id'          => $this->securityLibObj->decrypt($requestData['state_id']),
            'doc_pincode'       => $requestData['doc_pincode'],
            'doc_facebook_url'  => $requestData['doc_facebook_url'],
            'doc_twitter_url'   => $requestData['doc_twitter_url'],
            'doc_linkedin_url'  => $requestData['doc_linkedin_url'],
            'doc_google_url'    => $requestData['doc_google_url'],
            'doc_latitude'      => $requestData['doc_latitude'],
            'doc_longitude'     => $requestData['doc_longitude'],
            'doc_reg_num'       => $requestData['doc_reg_num'],
        ];

        $doctorDetail['doc_other_city'] = ($doctorDetail['city_id'] == Config::get('constants.OTHER_CITY_ID')) ? $requestData['doc_other_city'] : '';
        $countryDetailes = $this->countrytModelObj->getCountryDetailsByStateId($doctorDetail['state_id']);
        $userDetail['user_country_code'] = $countryDetailes->country_code;

        $whereData   =  ['user_id'=> $this->securityLibObj->decrypt($requestData['user_id'])];

        $isUpdated =  $this->dbUpdate('users', $userDetail, $whereData, 'masterdb');
         if(!empty($isUpdated)){
            $this->dbUpdate($this->table, $doctorDetail, $whereData);
            $this->clinicUpdate($clinicData, $whereData);
            return $this->getProfileDetail($this->securityLibObj->decrypt($requestData['user_id']));
        }
          return false;
    }

    public function clinicUpdate($clinicData, $whereData)
    {
        $latLongExist = DB::table($this->ClinicTable)->select('clinic_id','clinic_latitude', 'clinic_longitude')->where($whereData)->orderBy('clinic_id','asc')->first();
        if(empty($latLongExist->clinic_latitude) || empty($latLongExist->clinic_longitude)){
            $this->dbUpdate($this->ClinicTable, $clinicData, ['clinic_id'=>$latLongExist->clinic_id]);
        }
    }

    /**
     * Get doctor profile details
     *
     * @param array $data doctor id
     * @return array user detailed information
     */
    public function getProfileDetail($user_id)
    {
        $joinTableName = "doctors";
        $joinTableName1 = "states";
        $joinTableName2 = "cities";
        $joinTableName3 = "country";

        $user = DB::connection('masterdb')->table('users')
                ->select(
                        'users.user_id',
                        'users.user_firstname',
                        'users.user_lastname',
                        'users.user_email',
                        'users.user_mobile',
                        'users.user_gender',
                        'users.user_type',
                        'users.user_country_code'
                    )
                    ->where([
                        'users.user_id'=> $user_id
                    ])->first();


        $doctors = DB::connection('pgsql')
                        ->table($joinTableName)
                        ->select(
                            'doctors.doc_consult_fee',
                            'doctors.doc_short_info',
                            'doctors.doc_address_line1',
                            'doctors.doc_address_line2',
                            'doctors.doc_profile_img',
                            'doctors.city_id',
                            'doctors.doc_other_city',
                            'doctors.state_id',
                            'doctors.doc_pincode',
                            'doctors.doc_latitude',
                            'doctors.doc_longitude',
                            'doctors.doc_reg_num',
                            'doctors.doc_facebook_url',
                            'doctors.doc_twitter_url',
                            'doctors.doc_linkedin_url',
                            'doctors.doc_google_url'
                        )
                        ->where([
                            'doctors.user_id' => $user_id
                        ])
                        ->first();
        $user->doctors = $doctors;

        $states = DB::connection('pgsql')
                    ->table($joinTableName1)
                    ->select(
                        'states.country_id',
                        'states.name AS state_name'
                    )
                    ->where([
                        'states.id' => $doctors->state_id
                    ])
                    ->first();
        $user->states = $states;

        $cities = DB::connection('pgsql')
                    ->table($joinTableName2)
                    ->select(
                        'cities.name AS city_name'
                    )
                    ->where([
                        'cities.id' => $doctors->city_id
                    ])
                    ->first();
        $user->cities = $cities;

        if($states)
        {
            $country = DB::connection('pgsql')
                        ->table($joinTableName3)
                        ->select(
                            'country.country_name'
                        )
                        ->where([
                            'country.country_id' => $states->country_id
                        ])
                        ->first();
            $user->country = $country;
        }

        $doctorDegree = DB::table('doctors_degrees')
                        ->select('doc_deg_name')
                        ->where('user_id',$user_id)
                        ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
                        ->groupBy('doc_deg_name')
                        ->get();
        if(!empty($doctorDegree)){
            $degArr  = array();
            foreach ($doctorDegree as $deg) {
                $degArr[] = $deg->doc_deg_name;
            }
            $doc_deg = implode(', ', $degArr);
            $user->doc_deg_name = $doc_deg;
        }

        if($user)
        {
            $user->user_id = $this->securityLibObj->encrypt($user->user_id);
            if($states){
                $user->country_id = !is_null($states->country_id) ? $states->country_id: Config::get('constants.DEFAULT_DOCTOR_COUNTRY_ID');
                $user->country_id = $this->securityLibObj->encrypt($states->country_id);
                $user->state_id = !is_null( $doctors->state_id ) ? $this->securityLibObj->encrypt( $doctors->state_id ) : null;
                $user->doctors->state_id = !is_null( $doctors->state_id ) ? $this->securityLibObj->encrypt( $doctors->state_id ) : null;
            }
            if($cities)
            {
                $user->city_id = !is_null( $doctors->city_id ) ? $this->securityLibObj->encrypt( $doctors->city_id ) : null;
                $user->doctors->city_id = !is_null( $doctors->city_id ) ? $this->securityLibObj->encrypt( $doctors->city_id ) : null;
            }
            $user->doc_profile_img_name = !empty($user->doctors->doc_profile_img) ? $user->doctors->doc_profile_img : '';
            $user->doc_profile_img = !empty($user->doctors->doc_profile_img) ? url('api/doctor-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($user->doctors->doc_profile_img)) : '';
            return $user;
        }else{
            return false;
        }
    }

    /**
     * Update doctor image with regarding details
     *
     * @param array $data image data and doctor id
     * @return array profile image
     */
    public function updateProfileImage($requestData, $user_id)
    {
        $isExist = DB::table('doctors')->select('doc_profile_img')->where('user_id', $user_id)->first();
        $destination = storage_path('app/public/'.Config::get('constants.DOCTOR_PROFILE_PATH'));
        $data = $requestData['doc_profile_img'];

        // To convert base64 to image file
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);
        $randomString = Uuid::generate();
        $filename = $randomString.'.png';

        $environment = Config::get('constants.ENVIRONMENT_CURRENT');
        if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION')){
            $filePath = Config::get('constants.DOCTOR_PROFILE_S3_PATH').$filename;

            $upload  = $this->s3LibObj->putObject($data, $filePath, 'public');

            $imageData = array();
            if($upload['code'] = Config::get('restresponsecode.SUCCESS')) {
                $imageData = array(
                    "doc_profile_img" => $filename,
                    "created_by" => $user_id,
                    "updated_by" => $user_id,
                    );
                try {
                    DB::beginTransaction();
                    $isUpdated = DB::table('doctors')->where('user_id', $user_id)->update($imageData);

                    if($isUpdated){
                        DB::commit();
                        if(!empty($isExist->doc_profile_img)){
                            $oldFilePath = Config::get('constants.DOCTOR_PROFILE_S3_PATH').$isExist->doc_profile_img;
                            if($this->s3LibObj->isFileExist($oldFilePath)){
                                $this->s3LibObj->deleteFile($oldFilePath);
                            }
                        }
                        return url('api/doctor-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($filename));
                    }else{
                        if($this->s3LibObj->isFileExist($filePath)){
                            $this->s3LibObj->deleteFile($filePath);
                        }
                    }
                } catch (\Exception $e) {
                    if($this->s3LibObj->isFileExist($filePath)){
                        $this->s3LibObj->deleteFile($filePath);
                    }
                  DB::rollback();
                }
            }
        }else{
            $this->FileLib->createDirectory($destination);
            $data = $this->FileLib->base64ToPng($requestData['doc_profile_img'], $destination, 'png');
            $imageData = array();
            $uploadImage = $data['uploaded_file'];
            if(!empty($data['uploaded_file'])) {
                $imageData = array(
                    "doc_profile_img" => $data['uploaded_file'],
                    "created_by" => $user_id,
                    "updated_by" => $user_id,
                    );
                try {
                    DB::beginTransaction();
                    $thumbStatus = $this->generateThumbWithImage($data['uploaded_file']);

                    if($thumbStatus[0]['code'] == Config::get('restresponsecode.SUCCESS')){
                        $isUpdated = DB::table('doctors')->where('user_id', $user_id)->update($imageData);
                        if(!empty($isUpdated)){
                            if(!empty($isExist->doc_profile_img) && File::exists($destination.$isExist->doc_profile_img)) {
                                File::delete($destination.$isExist->doc_profile_img);
                            }
                            DB::commit();
                            return url('api/doctor-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($data['uploaded_file']));
                        }
                    }else{
                        if(File::exists($destination.$uploadImage)){
                            File::delete($destination.$uploadImage);
                        }
                        return false;
                    }
                } catch (\Exception $e) {
                    if(File::exists($destination.$uploadImage)){
                        File::delete($destination.$uploadImage);
                    }
                  DB::rollback();
                }
            }
        }
    }

    /**
     * Check doctor existing password with regarding details
     *
     * @param array $data password data
     * @return boolean true if exist otherwise false
     */
    public function isPasswordExist($requestData, $user_password)
    {
       if(!(Hash::check($requestData['user_old_password'], $user_password))){
        return true;
       }
       return false;
    }

    /**
     * Update doctor password with regarding details
     *
     * @param array $data password data
     * @return boolean true if updated otherwise false
     */
    public function passwordUpdate($requestData, $user_id)
    {
       $userPassword = Hash::make($requestData['user_password']);
       $isUpdate = $this->dbUpdate('users', ['user_password' => $userPassword], ['user_id'=>$user_id], 'masterdb');
       if($isUpdate){
            return true;
       }
       return false;
    }

    protected function generateThumbWithImage($mainImage)
    {
        $imageLibObj = new ImageLib();
        $thumb = [];
        $thumb = array(
            ['thumb_name' => $mainImage,'thumb_path' => Config::get('constants.DOCTOR_PROFILE_MTHUMB_IMG_PATH'),'width' => Config::get('constants.MEDIUM_THUMB_SIZE') , 'height' => Config::get('constants.MEDIUM_THUMB_SIZE')],
            ['thumb_name' => $mainImage,'thumb_path' => Config::get('constants.DOCTOR_PROFILE_STHUMB_IMG_PATH'),'width' => Config::get('constants.SMALL_THUMB_SIZE') , 'height' => Config::get('constants.SMALL_THUMB_SIZE')],
        );
        $thumbGenerate = $imageLibObj->genrateThumbnail(Config::get('constants.DOCTOR_PROFILE_PATH').$mainImage,$thumb);
        return $thumbGenerate;
    }

    /**
     * Update doctor settings
     *
     * @param array $data detailed data and doctor id
     * @return boolean true if updated
     */
    public function saveDoctorSettings($requestData)
    {
        if(isset($requestData['pat_code_prefix']) && !empty($requestData['pat_code_prefix'])){

            $doctorDetail = [
                'pat_code_prefix'  => $requestData['pat_code_prefix'],
            ];

            $whereData   =  ['user_id'=> $this->securityLibObj->decrypt($requestData['user_id'])];
            $isUpdated = $this->dbUpdate($this->table, $doctorDetail, $whereData);
        }
        if($isUpdated){
            return true;
        }
        return false;
    }

    /**
     * Update doctor settings
     *
     * @param array $data detailed data and doctor id
     * @return boolean true if updated
     */
    public function saveSmsSettings($requestData)
    {
        if(!empty($requestData['user_id'])){
            $userId = $this->securityLibObj->decrypt($requestData['user_id']);
            $settingsDetail = [
                'send_birthday_sms'  => $requestData['send_birthday_sms'],
                'birthday_sms_content'  => $requestData['birthday_sms_content'],
                'send_welcome_sms'  => $requestData['send_welcome_sms'],
                'welcome_sms_content'  => $requestData['welcome_sms_content'],
                'send_anniversary_sms'  => $requestData['send_anniversary_sms'],
                'anniversary_sms_content'  => $requestData['anniversary_sms_content'],
                'send_medicine_reminder_sms'  => $requestData['send_medicine_reminder_sms'],
                'medicine_reminder_sms_content'  => $requestData['medicine_reminder_sms_content'],
                'user_id'  => $userId,
                'ip_address'  => $requestData['ip_address'],
                'resource_type'  => $requestData['resource_type']
            ];
            $doc_setting_id = $this->getDoctorSettingsId($userId);
            if($doc_setting_id){
                    $whereData =  ['doc_setting_id'=> $doc_setting_id];
                    $isSaved = $this->dbUpdate($this->tableDoctorSettings, $settingsDetail, $whereData);
            }else{
                $isSaved= $this->dbInsert($this->tableDoctorSettings, $settingsDetail);
            }
            if($isSaved){
                return true;
            }
        }
        return false;
    }

    /**
     * Update doctor settings
     *
     * @param array $data detailed data and doctor id
     * @return boolean true if updated
     */
    public function getDoctorSettingsId($user_id)
    {
        $result = false;
        $detail = DB::table('doctor_settings')
                    ->select('doc_setting_id')
                        ->where('user_id',$user_id)
                        ->first();

        if($detail){
            $result =  $detail->doc_setting_id;
        }
        return $result;
    }

    /**
     * Get doctor profile details
     *
     * @param array $data doctor id
     * @return array user detailed information
     */
    public function getPatCodePrefix($user_id)
    {
        $detail = DB::table('doctors')
                    ->select('pat_code_prefix')
                        ->where('user_id',$user_id)
                        ->first();

        if($detail){
            return $detail;
        }else{
            return false;
        }
    }

    /**
     * Get doctor profile details
     *
     * @param array $data doctor id
     * @return array user detailed information
     */
    public function getSmsSettingsData($user_id)
    {
        $result = [
            'send_birthday_sms' => ["2"],
            'birthday_sms_content' => "",
            'send_welcome_sms' => ["2"],
            'welcome_sms_content' => "",
            'send_anniversary_sms' => ["2"],
            'anniversary_sms_content' => "",
            'send_medicine_reminder_sms' => ["2"],
            'medicine_reminder_sms_content' => "",
        ];
        $result = (object) $result;
        $detail = DB::table('doctor_settings')
                    ->select('send_birthday_sms', 'birthday_sms_content','send_welcome_sms','welcome_sms_content','send_anniversary_sms','anniversary_sms_content','send_medicine_reminder_sms','medicine_reminder_sms_content')
                        ->where('user_id',$user_id)
                        ->first();

        if($detail){
            $detail->send_birthday_sms          = !empty($detail->send_birthday_sms) ? [(string) $detail->send_birthday_sms] : [];
            $detail->send_welcome_sms           = !empty($detail->send_welcome_sms) ? [(string) $detail->send_welcome_sms] : [];
            $detail->send_anniversary_sms       = !empty($detail->send_anniversary_sms) ? [(string) $detail->send_anniversary_sms] : [];
            $detail->send_medicine_reminder_sms = !empty($detail->send_medicine_reminder_sms) ? [(string) $detail->send_medicine_reminder_sms] : [];
            $result =  $detail;
        }
        return $result;
    }
}