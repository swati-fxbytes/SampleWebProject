<?php

namespace App\Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Libraries\SecurityLib;
use App\Traits\Encryptable;
use App\Libraries\UtilityLib;
use Config;
use Carbon\Carbon;
use App\Modules\Search\Models\Search;

/**
 * Doctors Class
 *
 * @package                Safe Health
 * @subpackage             Doctors
 * @category               Model
 * @DateOfCreation         10 May 2018
 * @ShortDescription       This is model which need to perform the options related to
                           Doctors info
 */
class Doctors extends Model {
    use Encryptable;
    // @var string $table
    // This protected member contains table name
    protected $table = 'doctors';
    protected $encryptable = [];

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'doc_id';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();
        // Init utility library object
        $this->utilityLibObj = new UtilityLib();
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for creating new doctor in DB
    * @param                 Array $data This contains full user input data
    * @return                True/False
    */
   public function createDoctor($data, $userId)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;
        $slug = str_slug($data['user_firstname'].' '.$data['user_lastname']).$this->utilityLibObj->alphabeticString(6);

        // @var Array $inserData
        // This Array contains insert data for users
        $inserData = array(
            'user_id'      => $userId,
            'doc_slug'     => $slug,
            'resource_type'=> $data['resource_type'],
            'ip_address'   => $data['ip_address'],
            'created_by'   => $userId,
            'updated_by'   => $userId
        );

        // Prepair insert query
        $response = DB::table($this->table)->insert(
                        $inserData
                    );
        return $response;
    }

    /**
     * Get doctor profile details
     *
     * @param array $data doctor id
     * @return array user detailed information
     */
    public function getDoctorPublicProfile($slug, $requestData=[])
    {
        // Init search model object
        $this->searchModelObj = new Search();
        $user = DB::table('doctors')
                    ->select('user_id')
                    ->where(['doc_slug'=>$slug,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])
                    ->first();
        if(empty($user)){
            return false;
        }

        $doctorDetail = [];
        $userId = $user->user_id;
        
        $joinTableName = "doctors";
        $user = DB::connection('masterdb')->table('users')
                        ->select(
                            'users.user_id',
                            'users.user_firstname',
                            'users.user_lastname',
                            'users.user_gender',
                            'users.user_mobile'
                        )
                        ->where([
                            'users.user_id'=> $userId
                        ])->first();

        $doctorDetail = DB::connection('pgsql')->table($joinTableName)
                            ->select(
                                'doctors.doc_short_info',
                                'doctors.doc_address_line1',
                                'doctors.doc_address_line2',
                                'doctors.doc_pincode',
                                'doctors.doc_profile_img',
                                'doctors.city_id',
                                'doctors.state_id',
                                'doctors.doc_consult_fee'
                            )
                            ->where([
                                'doctors.user_id' => $userId
                            ])
                            ->first();

        $user->doctorDetail = $doctorDetail;

        /*doctor membership*/
        $doctorMembership = DB::table('doctor_membership')->select('doc_mem_name')->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->get();
        if(!empty($doctorMembership)){
            $memArr  = array();
            foreach ($doctorMembership as $mem) {
                $memArr[] = $mem->doc_mem_name;
            }
            $user->doc_mem_name = $memArr;
        }

        /*doctor experience*/
        $doctorExperience = DB::table('doctors_experience')->select(DB::raw('MIN(doc_exp_start_year) as start_year, MAX(doc_exp_end_year) as end_year'))->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->first();
        if(!empty($doctorExperience)){
            $user->doc_experience = $doctorExperience->end_year - $doctorExperience->start_year ;
        }

        /*doctor degree*/
        $doctorDegree = DB::table('doctors_degrees')->select('doc_deg_name')->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->get();
        if(!empty($doctorDegree)){
            $degArr  = array();
            foreach ($doctorDegree as $deg) {
                $degArr[] = $deg->doc_deg_name;
            }
            $doc_deg = implode(', ', $degArr);
            $user->doc_deg_name = $doc_deg;
            $user->doc_deg = $degArr;
        }

        /*doctor media*/
        $doctorMedia = DB::table('doctor_media')->select('doc_media_file')->where(['user_id'=>$userId,'doc_media_status'=>Config::get('constants.DOCTOR_MEDIA_ACTIVE')])->get();
        if(!empty($doctorMedia)){
            $mediaArr  = array();
            foreach ($doctorMedia as $media) {
                $mediaArr[] = $this->securityLibObj->encrypt($media->doc_media_file);
            }
            $user->doc_media = $mediaArr;
        }

        /*doctor specialisations*/
        $doctorSpecialisation = $this->getDoctorSpecialisation($userId);
        $user->doc_specialisations = $doctorSpecialisation['doc_specialisations'];
        $user->doc_special = $doctorSpecialisation['doc_special'];

        /*doctor award*/
        $doctorAwards = DB::table('doctors_awards')->select('doc_award_name')->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->get();
        if(!empty($doctorAwards)){
            $awardArr  = array();
            foreach ($doctorAwards as $award) {
                $awardArr[] = $award->doc_award_name;
            }
            $user->doc_award = $awardArr;
        }

        /*doctor Services*/
        $doctorServices = DB::table('services')->select('srv_name')->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->get();
        if(!empty($doctorServices)){
            $serviceArr  = array();
            foreach ($doctorServices as $services) {
                $serviceArr[] = $services->srv_name;
            }
            $user->doc_services = $serviceArr;
        }

        /*doctor average rating*/
         $doctorAvgRating = DB::table('review_rating')
                        ->select(DB::raw('ROUND(AVG(overall),0) as overall_average'),DB::raw('ROUND(AVG(wait_time),0) as waiting_time_average'),DB::raw('ROUND(AVG(manner),0) as badside_manner_average'))
                        ->where(['user_id'=>$userId,'is_deleted'=>Config::get('constants.IS_DELETED_NO')])->first();
        if(!empty($doctorAvgRating)){
            $ratings = array();
            $ratings['overall'] = $doctorAvgRating->overall_average;
            $ratings['wait_time']=$doctorAvgRating->waiting_time_average;
            $ratings['manner']=$doctorAvgRating->badside_manner_average;

            $user->doc_rating = $ratings;
        }

        /*Patient comment*/
        $doctorReview = DB::select(DB::raw("SELECT 
                                    review_rating.overall,
                                    review_rating.wait_time,
                                    review_rating.manner,
                                    review_rating.comment,
                                    users.user_firstname,
                                    users.user_lastname,
                                    review_rating.created_at
                                    FROM review_rating 
                                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id,user_firstname,user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(user_id int,
                                    user_firstname text,
                                    user_lastname text
                                    )) AS users ON users.user_id=review_rating.review_user_id
                                    WHERE review_rating.user_id=".$userId." AND review_rating.is_deleted=".Config::get('constants.IS_DELETED_NO')."
                                    ORDER BY review_rating.rev_rat_id DESC"));
        if(!empty($doctorReview)){
            $user->doc_review = $doctorReview;
            $user->doc_review_count = count($doctorReview);
        }else{
            $user->doc_review = [];
            $user->doc_review_count = 0;
        }

        /*clinc location*/
        if(array_key_exists('apt_type', $requestData) && !empty($requestData['apt_type'])){
            $doctorTiming = $this->doctorBookingDetail($userId, Null, Null, $requestData['apt_type']);
        }else{
            $doctorTiming = $this->doctorBookingDetail($userId);
        }
        $doctorClinic = DB::table('clinics')
                            ->select(
                                'clinics.clinic_id',
                                'clinics.clinic_name',
                                'clinics.clinic_address_line1',
                                'clinics.clinic_pincode'
                            )
                            ->where('clinics.user_id',$userId)
                            ->where('clinics.is_deleted',Config::get("constants.IS_DELETED_NO"))
                            ->get()
                            ->toArray();

        $clinicArr  = array();
        if(!empty($doctorClinic)){
            foreach($doctorClinic as $clinicData){
                if(!empty($doctorTiming)){
                    $timeDataArr = array();
                    foreach ($doctorTiming as $timingData) {
                        if($timingData->clinic_id == $this->securityLibObj->encrypt($clinicData->clinic_id)){
                            $timingData->user_id= $this->securityLibObj->encrypt($user->user_id);
                            $timingData->slot = $this->createTimeSlot($timingData,date('Y-m-d'));
                            $timeDataArr[] = $timingData;
                        }
                    }
                }
                $timingArray = array();
                if(!empty($timeDataArr)){
                    if(count($timeDataArr) > 1){
                        $finalSlots = $timeDataArr[0];
                        for ($i=1; $i < count($timeDataArr); $i++) {
                            foreach ($timeDataArr[$i]->slot as $value) {
                                array_push($finalSlots->slot, ['booking_count'=>$value['booking_count'],'slot_time'=>$value['slot_time']]);
                            }
                        }
                        $timingArray[] = $finalSlots;
                    }else{
                        $timingArray[] = $timeDataArr[0];
                    }
                }else{
                    $inputDate = date('Y/m/d', strtotime(date('Y-m-d')));
                    $availableDate = $this->searchModelObj->nextAvailableSlot($inputDate, $clinicData->clinic_id);
                    if($availableDate){
                        $nextDate = date('d M Y', strtotime($availableDate));
                        $nextDay   = date('D', strtotime($nextDate));
                    }else{
                       $nextDate = 'N/A';
                       $nextDay = 'N/A';
                    }
                    $timingArray[] = ["date"=>date('Y-m-d'),"user_id"=>$this->securityLibObj->encrypt($userId),'clinic_id'=>$this->securityLibObj->encrypt($clinicData->clinic_id),"nextDate"=>$nextDate,'nextDay' => $nextDay];
                }
                $clinicArr[] = [
                    "clinic_id"=>$this->securityLibObj->encrypt($clinicData->clinic_id),
                    "name"=>$clinicData->clinic_name,
                    "address"=>$clinicData->clinic_address_line1,
                    "pincode"=>$clinicData->clinic_pincode,
                    "timing"=>$timingArray
                ];
            }
        }
        $user->doc_clinic =  $clinicArr;

        if($user){
            $user->user_id = $this->securityLibObj->encrypt($user->user_id);
        }

        $doctorDetail = [
            'doctor_detail'=>[
                            'user_id'           => $user->user_id,
                            'user_firstname'    => $user->user_firstname,
                            'user_lastname'     => $user->user_lastname,
                            'user_gender'       => $user->user_gender,
                            'doctor_mobile'     => $user->user_mobile,
                            'doc_short_info'    => $user->doctorDetail->doc_short_info,
                            'doc_address_line1' => $user->doctorDetail->doc_address_line1,
                            'doc_address_line2' => $user->doctorDetail->doc_address_line2,
                            'doc_pincode'       => $user->doctorDetail->doc_pincode,
                            'doc_profile_img'   => !empty($doctorDetail->doc_profile_img) ? $this->securityLibObj->encrypt($doctorDetail->doc_profile_img) : '',
                            'city_id'           => $user->doctorDetail->city_id,
                            'state_id'          => $user->doctorDetail->state_id,
                            'doc_deg_name'      => $user->doc_deg_name,
                            'doc_deg'           =>$user->doc_deg,
                            'doc_media'         => $user->doc_media,
                            'doc_specialisations'=> $user->doc_specialisations,
                            'doc_spac_string'   =>$user->doc_special,
                            'doc_award'         => $user->doc_award,
                            'doc_membership'    => $user->doc_mem_name,
                            'doc_experience'    => $user->doc_experience,
                            'doc_service'       => $user->doc_services,
                            'doc_rating'       => $user->doc_rating,
                            'doc_review'       => $user->doc_review,
                            'doc_review_count' => $user->doc_review_count,
                            'doc_consult_fee' => $user->doctorDetail->doc_consult_fee
                            ],
            'doctor_clinic'=>$user->doc_clinic
        ];

        if($doctorDetail){
            return $doctorDetail;
        }else{
            return false;
        }
    }

     /**
     * Get doctor profile details
     *
     * @param string $userId doctor id
     * @param string $clinicId clinic id
     * @param object $date booking date
     * @return array user detailed information
     */
    public function doctorBookingDetail($userId, $clinicId=0,$slotDate='', $apt_type=Null)
    {
        $date = ($slotDate == '') ? date('Y-m-d'):$slotDate;
        $doctorClinic = DB::table('clinics')
                         ->select('clinic_id','clinic_name','clinic_address_line1','clinic_pincode')
                         ->where([
                            'clinics.is_deleted' => Config::get("constants.IS_DELETED_NO"),
                            'user_id' => $userId
                        ]);
        if(!empty($clinicId)){
            $doctorClinic = $doctorClinic->where('clinic_id',$clinicId)
                        ->first();
            if(!empty($doctorClinic)){
                $clinicIdIn = $doctorClinic->clinic_id;
                $doctorClinic->date = $slotDate;
            }else{
                $clinicIdIn = '';
            }
        }else{
            $doctorClinic = $doctorClinic->get()->toArray();
            $clinicIdIn    = array_pluck($doctorClinic,'clinic_id');
        }
        $week_day = date('w', strtotime($date));
        $week_day = ($week_day != 0) ? $week_day : 7;

        if(!empty($doctorClinic)){
            $query = DB::table('timing')
                    ->join('clinics', 'timing.clinic_id', '=', 'clinics.clinic_id')
                    ->select('clinics.clinic_id','timing.timing_id', 'timing.week_day', 'timing.start_time', 'timing.end_time', 'timing.slot_duration', 'timing.patients_per_slot', 'timing.appointment_type')
                    ->where('timing.start_time','!=',Config::get('constants.TIMING_SLOT_OFF'))
                    ->where([
                        'timing.week_day'=>$week_day,
                        'timing.user_id'=> $userId,
                        'timing.is_deleted'=> Config::get('constants.IS_DELETED_NO')
                    ]);
            if(!empty($apt_type)){
                $query = $query->where("appointment_type", $apt_type);
            }

            $query = $query->orderBy('timing.start_time','ASC');
            if(is_array($clinicIdIn)){
                /*for multiple clinic*/
                $timingData = $query->whereIn('timing.clinic_id', $clinicIdIn)
                      ->get()
                      ->map(function($timing) use($date){
                        $timing->timing_id = $this->securityLibObj->encrypt($timing->timing_id);
                        $timing->clinic_id = $this->securityLibObj->encrypt($timing->clinic_id);
                        $timing->date = $date;
                        return $timing;
                    })->toArray();
                return $timingData;
            } else {
                /*for single clinic*/
                $timingData = $query->where('timing.clinic_id', $clinicIdIn)->get()
                ->map(function($timing) use($date,$userId){
                    $timing->user_id = $this->securityLibObj->encrypt($userId);
                    $timing->slot = $this->createTimeSlot($timing,$date);
                    $timing->timing_id = $this->securityLibObj->encrypt($timing->timing_id);
                    $timing->clinic_id = $this->securityLibObj->encrypt($timing->clinic_id);
                    $timing->date = $date;
                    return $timing;
                })->toArray();
                $timingArray = array();
                if(!empty($timingData)){
                    if(count($timingData) > 1){
                        $finalSlots = $timingData[0];
                        for ($i=1; $i < count($timingData); $i++) {
                            foreach ($timingData[$i]->slot as $value) {
                                array_push($finalSlots->slot, ['booking_count'=>$value['booking_count'],'slot_time'=>$value['slot_time']]);
                            }
                            $timingArray[] = $finalSlots;
                        }
                    }else{
                        $timingArray = $timingData;
                    }
                    return $timingArray;
                }
            }
        }
        return false;
    }

   /**
    * @DateOfCreation        10 August 2018
    * @ShortDescription      This function is responsible to create timeslot
    * @param                 array $timing
                             Date $date optional
    * @return                specility
    */
    public function createTimeSlot($timing,$date){
        $timeSlotArray  = array ();
        $startTime      = strtotime ($timing->start_time); //change to strtotime
        $endTime        = strtotime ($timing->end_time); //change to strtotime
        $duration       = $timing->slot_duration;
        $add_mins       = $duration * 60;
        while ($startTime < $endTime) // loop between time
        {
            $bookingCount = $this->getBookingCount(date ("Hi", $startTime),$timing->user_id, $date);
            $timeSlotArray[] =[
            'booking_count'=> $bookingCount->booking_count,
            'slot_time'=> date ("Hi", $startTime)
            ];
            $startTime += $add_mins; // to check endtie=me
        }

        return $timeSlotArray;
    }

    function getBookingCount($bookingTimeSlot,$doctorId,$bookingDate=''){
        return DB::table('bookings')
                ->select(DB::raw("COUNT(booking_id) AS booking_count"))
                ->where([
                    'booking_time'=>$bookingTimeSlot,
                    'booking_date'=>!empty($bookingDate) ? $bookingDate : date('Y-m-d'),
                    'user_id'=> $this->securityLibObj->decrypt($doctorId)
                ])->first();
    }

     /**
     * Get doctor profile details
     *
     * @param string $userId doctor id
     * @param string $clinicId clinic id
     * @param object $date booking date
     * @return array user detailed information
     */
    public function getMaxDoctorCenterCode()
    {
        $this->utilitObj = new UtilityLib();
        $maxCenerCode = DB::table('doctors')->max('center_code');
        $newCenerCode = $this->utilitObj->doctorCenterCodeGenrator($maxCenerCode);
        return $newCenerCode;
    }

    public function getDoctorSpecialisation($userId){
        $result = array();
        $whereData = [
            'doctors_specialisations.is_deleted' => Config::get('constants.IS_DELETED_NO'),
            'user_id' => $userId
        ];
        $doctorSpecialisation = DB::table('doctors_specialisations')
                        ->join('specialisations','doctors_specialisations.spl_id', '=', 'specialisations.spl_id')
                        ->select('specialisations.spl_name', 'doctors_specialisations.is_primary')
                        ->where($whereData)
                        ->orderBy('specialisations.spl_name', 'ASC')
                        ->get();
        if(!empty($doctorSpecialisation)){
            $doctorSpecArr  = array();
            $doctorSpecString = array();
            $i=0;
            $primary = "";
            foreach ($doctorSpecialisation as $special) {
                $doctorSpecArr[] = $special->spl_name;
                $doctorSpecString[] = $special->spl_name;
                // For Doctors Logo as per their specialisations
                if($special->is_primary == Config::get('constants.IS_PRIMARY_YES')){
                    $primary = $special->spl_name;
                }
                $i++;
            }
            $result['doc_specialisations'] = $doctorSpecArr;
            $result['primary'] = $primary;
            $result['doc_special'] = implode(', ', $doctorSpecString);
        }
        return $result;
    }
}
