<?php

namespace App\Modules\Bookings\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use App\Libraries\DateTimeLib;
use Config;
use Carbon\Carbon;
use App\Libraries\UtilityLib;
use App\Modules\Search\Models\Search;
use App\Modules\Patients\Models\Patients;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Modules\DoctorProfile\Models\DoctorProfile;
use App\Modules\AppointmentCategory\Models\AppointmentCategory;
use App\Modules\Doctors\Models\ManageCalendar;
use App\Modules\DoctorProfile\Models\Timing;

/**
 * Bookings
 *
 * @package                 Safehealth
 * @subpackage              Bookings
 * @category                Model
 * @DateOfCreation          12 July 2018
 * @ShortDescription        This Model to handle database operation with current table
                            bookings
 **/
class Bookings extends Model {

    use HasApiTokens,Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'bookings';

    // This protected member contains table name use for Calendar data get
    protected $_timingSlotTable = 'timing';
    protected $_clinicTable = 'clinics';
    protected $_bookingReasonTable = 'appointment_category';
    protected $_bookingTable = 'bookings';
    protected $_userTable = 'users';
    protected $_patientTable = 'patients';
    protected $_doctorsTable = 'doctors';
    protected $booking_visit_relation = "booking_visit_relation";

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'booking_id';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init DateTimeLib library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init Search model object
        $this->searchObj = new Search();

        // Init UtilityLib library object
        $this->utilityLibObj = new UtilityLib();

        // Init Patients model object
        $this->patientsObj = new Patients();

        // Init StaticDataConfig model object
        $this->staticDataObj = new StaticDataConfig();

        // Init DoctorProfile model object
        $this->doctorprofileObj = new DoctorProfile();

        // Init DoctorProfile model object
        $this->appointmentCategoryObj = new AppointmentCategory();

        // Init ManageCalendar model object
        $this->manageCalendarObj = new ManageCalendar();

        // Init ManageCalendar model object
        $this->timingObj = new Timing();
    }

    /**
    * @DateOfCreation        12 July 2018
    * @ShortDescription      This function is responsible to get the Booking by id
    * @param                 String $booking_id
    * @return                Array of time
    */
    public function getBookingById($booking_id)
    {
        $queryResult = DB::table($this->table)
                        ->select('bookings.booking_id',
                            'bookings.user_id',
                            'bookings.pat_id',
                            'bookings.clinic_id',
                            'bookings.booking_date',
                            'bookings.booking_time',
                            'bookings.booking_reason',
                            'bookings.patient_extra_notes'
                        )
                        ->where([
                            'bookings.booking_id' => $booking_id,
                        ])
                        ->get()
                        ->first();
        if(!empty($queryResult)){
            $user = DB::connection('masterdb')
                        ->table('users')
                        ->select(DB::raw("CONCAT(users.user_firstname,' ',users.user_lastname) AS pat_name"))
                        ->where([ 
                            'user_id' => $queryResult->pat_id
                        ])
                        ->first();
            $queryResult->pat_name = $user->pat_name;
        }
        return $queryResult;
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for creating new booking in DB
    * @param                 Array $data This contains full user input data
    * @return                True/False
    */
    public function createBooking($requestData=array())
    {
        $requestData['created_by'] = $requestData['user_id'];
        $requestData['created_at'] = Carbon::now();
        $requestData = $this->encryptData($requestData);
        $isInserted = DB::table($this->table)->insert($requestData);
        if(!empty($isInserted)) {
            $insertedId = DB::getPdo()->lastInsertId();
            $bookingData = $this->getBookingById($insertedId);
            
            // Encrypt the ID
            $bookingData->booking_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            $bookingData->user_id    = $this->securityLibObj->encrypt($bookingData->user_id);
            $bookingData->pat_id     = $this->securityLibObj->encrypt($bookingData->pat_id);
            $bookingData->clinic_id  = $this->securityLibObj->encrypt($bookingData->clinic_id);
            return $bookingData;
        }
        return false;
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for patient detail
    * @param                 user id and patient id
    * @return                True/False
    */
    public function getPatientDetail($patId)
    {
        // $patientDetail = DB::table('users')
        //                 ->select('users.user_firstname','users.user_lastname','patients.pat_code')
        //                 ->leftjoin('patients','patients.user_id','=','users.user_id')
        //                 ->where([
        //                     'users.user_id'=> $patId,
        //                     'users.is_deleted' => Config::get('constants.IS_DELETED_NO')
        //                 ])->first();
        // if($patientDetail){
        //     return $patientDetail;
        // }else{
        //     return false;
        // }

        $joinTableName = "patients";
        $user = DB::connection('masterdb')->table('users')
                        ->select('users.user_firstname','users.user_lastname')
                        ->where([
                            'users.user_id'=> $patId,
                            'users.is_deleted' => Config::get('constants.IS_DELETED_NO')
                        ])->first();
        if($user){
            
            $patientDetail = DB::connection('pgsql')
                            ->table($joinTableName)
                            ->where([
                                'patients.user_id' => $patId
                            ])
                            ->first();
            
            $user->patientDetail = $patientDetail;
            return $user;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for appointment exist
    * @param                 user id and patient id
    * @return                True/False
    */
    public function isAppointmentExist($userId, $patId)
    {
        $total_booked = DB::table('bookings')
                        ->where([
                            'user_id'=> $userId,
                            'pat_id'=> $patId,
                            'is_deleted' => Config::get('constants.IS_DELETED_NO')
                        ])
                        ->where('booking_date', '>=', date('Y-m-d'))
                        ->count();
        if($total_booked > 0){
            return true;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for appointment exist
    * @param                 user id and patient id
    * @return                True/False
    */
    public function getRecentAppointmentData($userId, $patId)
    {
        return DB::table('bookings')
                    ->leftJoin('patient_appointment_reasons AS ptr', 'ptr.booking_id', 'bookings.booking_id' )
                    ->where([
                        'user_id'=> $userId,
                        'pat_id'=> $patId,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    ])
                    ->where('booking_date', '>', date('Y-m-d'))
                    ->first();
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for doctor clinic
    * @param                 user id and patient id
    * @return                True/False
    */
    public function getDoctorClinic($userId)
    {
        $clinics = DB::table('clinics')->select('clinic_id')->where(['user_id'=> $userId, 'is_deleted' => Config::get('constants.IS_DELETED_NO')])->first();
        if($clinics){
            return $clinics->clinic_id;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for appointment category
    * @param                 user id and patient id
    * @return                True/False
    */
    public function getAppointmentCategory($userId)
    {
        $appointmentCategories = DB::table('appointment_category')->select('appointment_cat_id')->where(['user_id'=> $userId, 'is_deleted' => Config::get('constants.IS_DELETED_NO')])->first();
        if($appointmentCategories){
            return $appointmentCategories->appointment_cat_id;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        29 Apr 2018
    * @ShortDescription      This function is responsible for get timing id
    * @param                 user id and patient id
    * @return                True/False
    */
    public function getTimingId($userId,$weekDay,$finishTime,$appointmentType)
    {
        $timingData = DB::table('timing')
                                ->select('timing_id')
                                ->where([
                                    'user_id'=> $userId,
                                    'week_day'=> $weekDay,
                                    'appointment_type'=> $appointmentType,
                                    'is_deleted' => Config::get('constants.IS_DELETED_NO')])
                                ->where('start_time','<',$finishTime)
                                ->where('end_time','>',$finishTime)
                                ->first();
        if($timingData){
            return $timingData->timing_id;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the Timing by id
    * @param                 String $timing_id
    * @return                Array of time
    */
    public function isSlotAvailable($timing_id, $booking_date, $booking_time, $appointmentType)
    {
        $whereDataBooking = array(
                        'timing_id'    => $timing_id,
                        'booking_date' => $booking_date,
                        'booking_time' => $booking_time,
                    );
        $result = array();

        $isSlotValid = Config::get('constants.NO_BOOKINGS_AVAILABLE');
        $total_booked = '0';

        $total_slot = DB::table('timing')
                        ->select('patients_per_slot')
                        ->where('timing_id', '=', $timing_id)
                        ->where('appointment_type', '=', $appointmentType)
                        ->first();

        if(!empty($total_slot)){
            $total_booked = DB::table('bookings')
                            ->where($whereDataBooking)
                            ->count();
            if($total_booked < $total_slot->patients_per_slot){
                $isSlotValid = true;
            }
        }

        return $isSlotValid;
    }

    /**
    * @DateOfCreation        09 Aug 2018
    * @ShortDescription      This function is responsible to get the Timing by id
    * @param                 String $timing_id
    * @return                Array of time
    */
    public function userAlreadyBooked($timing_id, $booking_date, $booking_time, $user_id)
    {
        $whereDataBooking = array(
                        'timing_id'    => $timing_id,
                        'booking_date' => $booking_date,
                        'pat_id'       => $user_id,
                        'is_deleted'   => Config::get('constants.IS_DELETED_NO'),
                    );
        $result = array();

        $userAlreadyBooked = false;
        $total_booked = '0';

        $bookings = DB::table('bookings')
                ->select('booking_time')
                ->where($whereDataBooking)
                ->get();
        $total_booked = sizeof($bookings);
        if($total_booked > 0){
            $userAlreadyBooked = Config::get('constants.PATIENT_ALREADY_BOOKED_DAY');
            foreach($bookings as $slot){
                if($slot->booking_time == $booking_time){
                    $userAlreadyBooked = Config::get('constants.PATIENT_ALREADY_BOOKED_SLOT');
                    break;
                }
            }
        }
        return $userAlreadyBooked;
    }

    /**
    * @DateOfCreation        30 july 2018
    * @ShortDescription      This function is responsible to get the appointment by user id and user type
    * @return                Array of appointment
    */
    public function getAppointmentList($requestData)
    {
        $clinicId = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $requestData['clinic_id'] :'';
        $data_limit = $requestData['pageSize'];
        $query = "SELECT
                    bookings.booking_id,
                    bookings.booking_date,
                    bookings.booking_time,
                    bookings.user_id,
                    bookings.pat_id,
                    bookings.booking_status,
                    bookings.patient_extra_notes,
                    clinics.clinic_address_line1,
                    clinics.clinic_address_line2,
                    clinics.clinic_landmark,
                    clinics.clinic_pincode,
                    booking_visit_relation.visit_id,
                    patients_visits.status as visit_status,
                    appointment_category.appointment_cat_name as booking_reason,
                    CONCAT('Dr. ',doctor.doctor_firstname,' ',doctor.doctor_lastname) AS doc_name,
                    CONCAT(users.user_firstname,' ',users.user_lastname) AS pat_name,
                    users.user_mobile,
                    patients.pat_code,
                    patients_visits.created_at,
                    patients_visits.visit_number,
                    ".$this->_doctorsTable.".doc_profile_img,
                    patients.pat_profile_img,
                    bookings.patient_appointment_status,
                    timing.appointment_type,
                    vc.video_channel,
                    appointment_reason,
                    doctor.*
                FROM bookings
                JOIN clinics on clinics.clinic_id = bookings.clinic_id 
                JOIN timing on timing.timing_id = bookings.timing_id 
                JOIN appointment_category on appointment_category.appointment_cat_id = bookings.booking_reason
                JOIN ".$this->_doctorsTable." on ".$this->_doctorsTable.".user_id = bookings.user_id 
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS pat1_id,user_firstname,user_lastname,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(pat1_id int,
                    user_firstname text,
                    user_lastname text,
                    user_mobile text
                    )) AS users ON users.pat1_id= bookings.pat_id
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname, user_mobile AS doctor_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                    doctor_firstname text,
                    doctor_lastname text,
                    doctor_mobile text
                    )) AS doctor ON doctor.doc_id= bookings.user_id ";
        if($requestData['user_type'] != Config::get('constants.USER_TYPE_PATIENT')){
            $query .= "JOIN doctor_patient_relation on doctor_patient_relation.pat_id = users.pat1_id";
        }

        $query .= " JOIN patients on bookings.pat_id=patients.user_id 
                    LEFT JOIN booking_visit_relation on booking_visit_relation.booking_id=bookings.booking_id 
                    LEFT JOIN patients_visits on patients_visits.visit_id=booking_visit_relation.visit_id 
                    LEFT JOIN video_consulting as vc on vc.booking_id=bookings.booking_id 
                    LEFT JOIN patient_appointment_reasons as par on par.booking_id=bookings.booking_id WHERE ";

        if($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR') || in_array($requestData['user_type'], Config::get('constants.USER_TYPE_STAFF'))){
            $query .= " bookings.user_id=".$requestData['user_id']." 
                        AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                        AND doctor_patient_relation.user_id=".$requestData['user_id']." 
                        AND doctor_patient_relation.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                        AND booking_date='".$requestData['appointmentDate']."' ";
        }

        if($requestData['user_type'] == Config::get('constants.USER_TYPE_PATIENT')){
            $query .= " bookings.pat_id=".$requestData['user_id']." 
                        AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." 
                        AND booking_date='".$requestData['appointmentDate']."' ";
        }

        if(!empty($clinicId)){
            $query .= " AND bookings.clinic_id=".$clinicId." ";
        }

        if( array_key_exists('start_date', $requestData) && !empty($requestData['start_date'])){  
            $query .= " AND booking_date::date >='".$requestData['start_date']."' ";
        }

        if(array_key_exists('dr_id', $requestData)){
            $query .= " AND doctor.doc_id=".$requestData['dr_id']." ";
        }

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            $query .= "AND ( ";
            foreach ($requestData['filtered'] as $key => $value) {
                $query .= " appointment_category.appointment_cat_name ilike '%".$value['value']."%'
                            OR users.user_firstname ilike '%".$value['value']."%' 
                            OR users.user_lastname ilike '%".$value['value']."%' ";
                if(!empty($value['value']) && strpos($value['value'], ':') !== false) {
                    $format_time = $value['value'];
                    if(strpos($format_time,'pm') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }else if(strpos($format_time,'am') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }
                    $query .= " OR bookings.booking_time ilike '%".$format_time."%' ";
                }else{
                    $query .= " OR bookings.booking_time ilike '%".$value['value']."%' 
                                OR bookings.booking_time ilike '%".((int)$value['value']+12)."%' ";
                }
            }
            $query .= ")";
        }

        $withoutPagination = DB::select(DB::raw($query));

        if($requestData['page'] > 0){
            $offset = $requestData['page']*$data_limit;
        }else{
            $offset = 0;
        }
        $bookingsResult['pages'] = ceil(count($withoutPagination)/$data_limit);
        $bookingsResult['date'] = $requestData['appointmentDate'];
        if($data_limit > 0){
            $query .= " ORDER BY bookings.booking_time ASC limit ".$data_limit." offset ".$offset.";";
        }else{
            $query .= " ORDER BY bookings.booking_time ASC;";
        }
        $result = DB::select(DB::raw($query));
        $bookingsResult['result'] = [];
        foreach($result as $bookings){
            $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
            $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
            if($bookings->visit_id){
                $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
            }
            $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
            $bookings->doc_profile_img = !empty($bookings->doc_profile_img) ? $this->securityLibObj->encrypt($bookings->doc_profile_img) : '';
            $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
            if(!empty($bookings->appointment_reason)){
                $bookings->appointment_reason= json_decode($bookings->appointment_reason);
                foreach($bookings->appointment_reason as $key => $res){
                    $bookings->appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $bookings->appointment_reason = [];
            }
            $bookingsResult['result'][] = $bookings;
        }

        if(!empty($bookingsResult)){
            return $bookingsResult;
        }
        return false;
    }

    /**
    * @DateOfCreation        01 June 2021
    * @ShortDescription      This function is responsible to get the appointment by user id and user type
    * @return                Array of appointment
    */
    public function getAppointmentListForApp($requestData)
    {
        $clinicId = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $requestData['clinic_id'] :'';
        $data_limit = $requestData['pageSize'];

        $query = "SELECT 
                    bookings.booking_id,
                    bookings.booking_date,
                    bookings.booking_time,
                    bookings.user_id,
                    bookings.pat_id,
                    bookings.booking_status,
                    bookings.patient_extra_notes,
                    clinics.clinic_id,
                    clinics.clinic_address_line1,
                    clinics.clinic_address_line2,
                    clinics.clinic_landmark,
                    clinics.clinic_pincode,
                    booking_visit_relation.visit_id,
                    patients_visits.status as visit_status,
                    appointment_category.appointment_cat_name as booking_reason,
                    patients.pat_code,
                    patients_visits.created_at,
                    patients_visits.visit_number,
                    ".$this->_doctorsTable.".doc_profile_img,
                    patients.pat_profile_img,
                    bookings.patient_appointment_status,
                    timing.appointment_type,
                    vc.video_channel,
                    appointment_reason,
                    users.user_mobile,
                    doctor.*,
                    CONCAT('Dr. ',doctor.doctor_firstname,' ',doctor.doctor_lastname) AS doc_name,
                    CONCAT(users.user_firstname,' ',users.user_lastname) AS pat_name
                    FROM bookings 
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS pat1_id,user_firstname,user_lastname,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(pat1_id int,
                    user_firstname text,
                    user_lastname text,
                    user_mobile text
                    )) AS users ON users.pat1_id= bookings.pat_id
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname, user_mobile AS doctor_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                    doctor_firstname text,
                    doctor_lastname text,
                    doctor_mobile text
                    )) AS doctor ON doctor.doc_id= bookings.user_id
                    JOIN clinics on clinics.clinic_id = bookings.clinic_id 
                    JOIN timing on timing.timing_id = bookings.timing_id 
                    JOIN appointment_category on appointment_category.appointment_cat_id = bookings.booking_reason 
                    JOIN ".$this->_doctorsTable." on ".$this->_doctorsTable.".user_id = bookings.user_id ";
        if($requestData['user_type'] != Config::get('constants.USER_TYPE_PATIENT')){
            $query .= "JOIN doctor_patient_relation on doctor_patient_relation.pat_id = users.pat1_id ";
        }

        $query .= " JOIN patients on bookings.pat_id=patients.user_id 
                    LEFT JOIN booking_visit_relation on booking_visit_relation.booking_id=bookings.booking_id 
                    LEFT JOIN patients_visits on patients_visits.visit_id=booking_visit_relation.visit_id 
                    LEFT JOIN video_consulting as vc on vc.booking_id=bookings.booking_id 
                    LEFT JOIN patient_appointment_reasons as par on par.booking_id=bookings.booking_id WHERE";
        
        if($requestData['user_type'] == Config::get('constants.USER_TYPE_DOCTOR') || in_array($requestData['user_type'], Config::get('constants.USER_TYPE_STAFF'))){
            $query .= " bookings.user_id=".$requestData['user_id']." AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." AND doctor_patient_relation.user_id=".$requestData['user_id']." AND doctor_patient_relation.is_deleted=".Config::get('constants.IS_DELETED_NO')." ";
        }

        if($requestData['user_type'] == Config::get('constants.USER_TYPE_PATIENT')){
            $query .= " bookings.pat_id=".$requestData['user_id']." ";
        }

        if(!empty($clinicId)){
            $query .= " AND bookings.clinic_id=".$clinicId." ";
        }

        if( array_key_exists('start_date', $requestData) && !empty($requestData['start_date'])){  
            $query .= " AND booking_date::date >='".$requestData['start_date']."' ";
        }

        if( array_key_exists('end_date', $requestData) && !empty($requestData['end_date'])){  
            $query .= " AND booking_date::date <= '".$requestData['end_date']."' ";
        }

        if(array_key_exists('dr_id', $requestData)){
            $query .= " AND doctor.doc_id = ".$requestData['dr_id']." ";
        }

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            $query .= "AND ( ";
            foreach ($requestData['filtered'] as $key => $value) {
                $query .= " appointment_category.appointment_cat_name ilike '%".$value['value']."%'
                            OR users.user_firstname ilike '%".$value['value']."%' 
                            OR users.user_lastname ilike '%".$value['value']."%' ";
                if(!empty($value['value']) && strpos($value['value'], ':') !== false) {
                    $format_time = $value['value'];
                    if(strpos($format_time,'pm') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }else if(strpos($format_time,'am') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }
                    $query .= " OR bookings.booking_time ilike '%".$format_time."%' ";
                }else{
                    $query .= " OR bookings.booking_time ilike '%".$value['value']."%' 
                                OR bookings.booking_time ilike '%".((int)$value['value']+12)."%' ";
                }
            }
            $query .= ")";
        }


        $withoutPagination = DB::select(DB::raw($query));

        if($requestData['page'] > 0){
            $offset = $requestData['page']*$data_limit;
        }else{
            $offset = 0;
        }
        $totalRecords = count($withoutPagination);
        $bookingsResult['pages'] = ceil($totalRecords/$data_limit);
        $bookingsResult['total_records'] = $totalRecords;
        $bookingsResult['start_date'] = empty($requestData['start_date']) ? '' : $requestData['start_date'];
        $bookingsResult['end_date'] = empty($requestData['end_date']) ? '' : $requestData['end_date'];
        $query .= " ORDER BY bookings.booking_date DESC, booking_time ASC limit ".$data_limit." offset ".$offset.";";
        $result = DB::select(DB::raw($query));
        $bookingsResult['result'] = [];
        foreach($result as $bookings){
            $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
            $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
            $bookings->doc_id = $this->securityLibObj->encrypt($bookings->doc_id);
            if($bookings->visit_id){
                $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
            }
            $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);

            if($bookings->clinic_id)
            {
                $bookings->clinic_id = $this->securityLibObj->encrypt($bookings->clinic_id);
            }
            $bookings->doc_profile_img = !empty($bookings->doc_profile_img) ? $this->securityLibObj->encrypt($bookings->doc_profile_img) : '';
            $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
            if(!empty($bookings->appointment_reason)){
                $bookings->appointment_reason= json_decode($bookings->appointment_reason);
                foreach($bookings->appointment_reason as $key => $res){
                    $bookings->appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $bookings->appointment_reason = [];
            }
            $bookingsResult['result'][] = $bookings;
        }

        if(!empty($bookingsResult)){
            return $bookingsResult;
        }

        return false;
    }

    /**
    * @DateOfCreation        17 Feb 2021
    * @ShortDescription      This function is responsible to get all appointments for a patient
    * @return                Array of appointment
    */
    public function getAllAppointmentsForPatient($requestData)
    {
        $clinicId = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $requestData['clinic_id'] :'';
        $data_limit = $requestData['pageSize'];
        $query = "SELECT 
                bookings.booking_id,
                bookings.booking_date,
                bookings.booking_time,
                bookings.user_id,
                bookings.pat_id,
                bookings.booking_status,
                bookings.created_at,
                bookings.patient_extra_notes,
                clinics.clinic_address_line1,
                clinics.clinic_address_line2,
                clinics.clinic_landmark,
                clinics.clinic_pincode,
                doctor.user_mobile,
                appointment_category.appointment_cat_name as booking_reason,
                CONCAT('Dr. ',doctor.user_firstname,' ',doctor.user_lastname) AS doc_name,
                patients.pat_code,
                booking_visit_relation.visit_id,
                patients_visits.status as visit_status,
                patients_visits.visit_number,
                ".$this->_doctorsTable.".doc_profile_img,
                patients.pat_profile_img,
                par.appointment_reason
                FROM bookings
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname,user_lastname,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                user_firstname text,
                user_lastname text,
                user_mobile text
                )) AS doctor ON doctor.doc_id= bookings.user_id
                JOIN clinics on clinics.clinic_id = bookings.clinic_id 
                JOIN timing on timing.timing_id = bookings.timing_id 
                JOIN appointment_category on appointment_category.appointment_cat_id = bookings.booking_reason 
                JOIN ".$this->_doctorsTable." on ".$this->_doctorsTable.".user_id = bookings.user_id
                JOIN patients on bookings.pat_id=patients.user_id 
                LEFT JOIN booking_visit_relation on booking_visit_relation.booking_id=bookings.booking_id 
                LEFT JOIN patients_visits on patients_visits.visit_id=booking_visit_relation.visit_id 
                LEFT JOIN video_consulting as vc on vc.booking_id=bookings.booking_id 
                LEFT JOIN patient_appointment_reasons as par on par.booking_id=bookings.booking_id WHERE
                bookings.pat_id = ".$requestData['user_id']."
                AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." ";
        
        if(!empty($requestData['appointmentDate']))
            $query .= " AND booking_date = ".$requestData['appointmentDate']." ";
        if(!empty($clinicId))
            $query .= " AND bookings.clinic_id = ".$clinicId." ";

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            $query .= "AND ( ";
            foreach ($requestData['filtered'] as $key => $value) {
                $query .= " appointment_category.appointment_cat_name ilike '%".$value['value']."%'
                            OR doctor.user_firstname ilike '%".$value['value']."%' 
                            OR doctor.user_lastname ilike '%".$value['value']."%' ";
                if(!empty($value['value']) && strpos($value['value'], ':') !== false) {
                    $format_time = $value['value'];
                    if(strpos($format_time,'pm') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }else if(strpos($format_time,'am') !== false){
                        $format_time = date('Hi',strtotime($format_time));
                    }
                    $query .= " OR bookings.booking_time ilike '%".$format_time."%' ";
                }else{
                    $query .= " OR bookings.booking_time ilike '%".$value['value']."%' 
                                OR bookings.booking_time ilike '%".((int)$value['value']+12)."%' ";
                }
            }
            $query .= ")";
        }


        $withoutPagination = DB::select(DB::raw($query));
        if($requestData['page'] > 0){
            $offset = $requestData['page']*$data_limit;
        }else{
            $offset = 0;
        }

        $bookingsResult['pages'] = ceil(count($withoutPagination)/$data_limit);
        $bookingsResult['date'] = $requestData['appointmentDate'];
        $query .= " ORDER BY bookings.booking_date ASC limit ".$data_limit." offset ".$offset.";";
        $result = DB::select(DB::raw($query));
        $bookingsResult['result'] = [];
        foreach($result as $bookings){
            $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
            $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
            if($bookings->visit_id){
                $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
            }
            $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
            $bookings->doc_profile_img = !empty($bookings->doc_profile_img) ? $this->securityLibObj->encrypt($bookings->doc_profile_img) : '';
            $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
            if(!empty($bookings->appointment_reason)){
                $bookings->appointment_reason= json_decode($bookings->appointment_reason);
                foreach($bookings->appointment_reason as $key => $res){
                    $bookings->appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $bookings->appointment_reason = [];
            }
            $bookingsResult['result'][] = $bookings;
        }

        if(!empty($bookingsResult)){
            return $bookingsResult;
        }
        return false;
    }

    /**
    * @DateOfCreation        18 June 2021
    * @ShortDescription      This function is responsible to get booking information for a patient
    * @return                Array of appointment
    */
    public function getBookingDetailsById($bookingId)
    {
        $bookingId = $this->securityLibObj->decrypt($bookingId);
        $bookings = DB::table('bookings')
                    ->select(
                        'bookings.booking_id',
                        'bookings.booking_date',
                        'bookings.booking_time',
                        'bookings.user_id',
                        'bookings.pat_id',
                        'bookings.booking_status',
                        'bookings.created_at',
                        'bookings.patient_extra_notes',
                        'clinics.clinic_address_line1',
                        'clinics.clinic_address_line2',
                        'clinics.clinic_landmark',
                        'clinics.clinic_pincode',
                        'appointment_category.appointment_cat_name as booking_reason',
                        'patients.pat_code',
                        'booking_visit_relation.visit_id',
                        'patients_visits.status as visit_status',
                        'patients_visits.visit_number',
                        $this->_doctorsTable.'.doc_profile_img',
                        'patients.pat_profile_img',
                        'par.appointment_reason'
                    )
                    ->join('clinics', 'bookings.clinic_id', '=', 'clinics.clinic_id')
                    ->join('appointment_category', 'appointment_category.appointment_cat_id', '=', 'bookings.booking_reason')
                    ->leftJoin('patient_appointment_reasons AS par', 'par.booking_id', '=', 'bookings.booking_id')
                    ->join($this->_doctorsTable, $this->_doctorsTable.'.user_id', '=', 'bookings.user_id')
                    ->join('patients', 'bookings.pat_id', '=', 'patients.user_id')
                    ->leftjoin('booking_visit_relation', 'bookings.booking_id', '=', 'booking_visit_relation.booking_id')
                    ->leftjoin('patients_visits', 'booking_visit_relation.visit_id', '=', 'patients_visits.visit_id')
                    ->where('bookings.booking_id', $bookingId)
                    ->first();
        if(!empty($bookings)){
            $doctor = DB::connection('masterdb')
                        ->table('users')
                        ->select(DB::raw("CONCAT('Dr. ',user_firstname,' ',user_lastname) AS doc_name"),'user_mobile')
                        ->where([
                            'user_id' => $bookings->user_id
                        ])->first();
            $bookings->user_mobile = $doctor->user_mobile;
            $bookings->doc_name = $doctor->doc_name;
            $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
            $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
            if($bookings->visit_id){
                $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
            }
            $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
            $bookings->doc_profile_img = !empty($bookings->doc_profile_img) ? $this->securityLibObj->encrypt($bookings->doc_profile_img) : '';
            $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
            if(!empty($bookings->appointment_reason)){
                $bookings->appointment_reason= json_decode($bookings->appointment_reason);
                foreach($bookings->appointment_reason as $key => $res){
                    $bookings->appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $bookings->appointment_reason = [];
            }
        }
        return $bookings;
    }

    /**
    * @DateOfCreation        30 july 2018
    * @ShortDescription      This function is responsible to get the appointment by user id and user type
    * @return                Array of appointment
    */
    public function getTodayAppointmentList($user_id, $user_type)
    {
        $query = "SELECT bookings.booking_id,
                bookings.booking_date,
                bookings.booking_time,
                bookings.user_id,
                bookings.pat_id,
                bookings.patient_extra_notes,
                clinics.clinic_id,
                clinics.clinic_address_line1,
                clinics.clinic_address_line2,
                clinics.clinic_landmark,
                clinics.clinic_pincode,
                users.user_firstname,
                users.user_lastname,
                users.user_mobile,
                CONCAT('Dr. ',doctor.doctor_firstname,' ',doctor.doctor_lastname) AS doc_name,
                CONCAT(users.user_firstname,' ',users.user_lastname) AS pat_name,
                booking_visit_relation.visit_id,
                patients_visits.status as visit_status,
                appointment_category.appointment_cat_name as booking_reason,
                patients.pat_profile_img,
                patients.pat_code,
                patients_visits.created_at,
                patients_visits.visit_number,
                appointment_category.appointment_cat_name as appointment_reason,
                patients.pat_profile_img,
                bookings.booking_status,
                timing.appointment_type,
                vc.video_channel,
                par.appointment_reason AS pat_appointment_reason,
                users.*,
                doctor.*
                FROM bookings 
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS pat1_id,user_firstname,user_lastname,user_gender,user_email,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(pat1_id int,
                    user_firstname text,
                    user_lastname text,
                    user_gender int,
                    user_email text,
                    user_mobile text
                    )) AS users ON users.pat1_id= bookings.pat_id
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                    doctor_firstname text,
                    doctor_lastname text
                    )) AS doctor ON doctor.doc_id= bookings.user_id
                JOIN clinics on bookings.clinic_id = clinics.clinic_id
                JOIN ".$this->_patientTable." on ".$this->_patientTable.".user_id = bookings.pat_id 
                JOIN timing on timing.timing_id = bookings.timing_id 
                LEFT JOIN patient_appointment_reasons AS par on par.booking_id = bookings.booking_id 
                LEFT JOIN ".$this->booking_visit_relation." on ".$this->booking_visit_relation.".booking_id = bookings.booking_id 
                LEFT JOIN patients_visits on booking_visit_relation.visit_id = patients_visits.visit_id 
                LEFT JOIN video_consulting as vc on vc.booking_id = bookings.booking_id 
                LEFT JOIN ".$this->_bookingReasonTable." on ".$this->_bookingReasonTable.".appointment_cat_id = bookings.booking_reason";
        if($user_type == Config::get('constants.USER_TYPE_DOCTOR') || in_array($user_type, Config::get('constants.USER_TYPE_STAFF'))){
            $query .= " WHERE bookings.user_id=".$user_id." AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." AND booking_date='".date(Config::get('constants.DB_SAVE_DATE_FORMAT'))."'";
        }

        $query .= " order by bookings.booking_time ASC";

        $list = DB::select(DB::raw($query));
        $bookingList = [];
        foreach($list as $bookings){
            $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
            $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
            if($bookings->visit_id){
                $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
            }
            $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
            $bookings->clinic_id = $this->securityLibObj->encrypt($bookings->clinic_id);
            $bookings->address = $bookings->clinic_address_line1.', '.$bookings->clinic_address_line2.' '.$bookings->clinic_landmark.', '.$bookings->clinic_pincode;
            $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
            if(!empty($bookings->pat_appointment_reason)){
                $bookings->pat_appointment_reason= json_decode($bookings->pat_appointment_reason);
                foreach($bookings->pat_appointment_reason as $key => $res){
                    $bookings->pat_appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $bookings->pat_appointment_reason = [];
            }
            $bookingList[] = $bookings;
        }

        return $bookingList;
    }

    /**
    * @DateOfCreation        30 july 2018
    * @ShortDescription      This function is responsible to get the appointment by user id and user type
    * @return                Array of appointment
    */
    public function getTodayAppointmentListForPatient($requestData)
    {
        $whereData = [
            'bookings.user_id'=>$requestData['user_id'],
            'bookings.pat_id'=>$requestData['pat_id'],
            'bookings.is_deleted'=>Config::get('constants.IS_DELETED_NO'),
            'booking_date'=>date(Config::get('constants.DB_SAVE_DATE_FORMAT'))
        ];

        $bookings = DB::table('bookings')
            ->select('bookings.booking_id','bookings.booking_date', 'bookings.booking_time','users.user_firstname','users.user_lastname','bookings.user_id','bookings.pat_id','clinics.clinic_address_line1', 'clinics.clinic_address_line2', 'clinics.clinic_landmark', 'clinics.clinic_pincode','booking_visit_relation.visit_id','patients.pat_profile_img','appointment_category.appointment_cat_name as appointment_reason','bookings.booking_status', 'timing.appointment_type', 'par.appointment_reason AS pat_appointment_reason')
            ->where($whereData)
            ->leftjoin("patient_appointment_reasons AS par", 'par.booking_id', '=', $this->table.'.booking_id')
            ->leftjoin('booking_visit_relation', 'bookings.booking_id', '=', 'booking_visit_relation.booking_id')
            ->leftJoin('timing', 'timing.timing_id', '=', 'bookings.timing_id')
            ->leftjoin($this->_bookingReasonTable, $this->_bookingReasonTable.'.appointment_cat_id', '=', $this->table.'.booking_reason')
            ->join('users', 'bookings.pat_id', '=', 'users.user_id')
            ->join('clinics', 'bookings.clinic_id', '=', 'clinics.clinic_id')
            ->join('patients', 'bookings.pat_id', '=', 'patients.user_id')
            ->orderBy('bookings.booking_time', 'asc')
            ->get()
            ->map(function($bookings){
                $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
                $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
                if($bookings->visit_id){
                    $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
                }
                $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
                $bookings->address = $bookings->clinic_address_line1.', '.$bookings->clinic_address_line2.' '.$bookings->clinic_landmark.', '.$bookings->clinic_pincode;
                $bookings->pat_profile_img = !empty($bookings->pat_profile_img) ? url('api/patient-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($bookings->pat_profile_img)) : '';
                if(!empty($bookings->pat_appointment_reason)){
                    $bookings->pat_appointment_reason= json_decode($bookings->pat_appointment_reason);
                    foreach($bookings->pat_appointment_reason as $key => $res){
                        $bookings->pat_appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                    }
                }else{
                    $bookings->pat_appointment_reason = [];
                }
                return $bookings;
            });
        if(!empty($bookings)){
            return $bookings;
        }
        return false;
    }

    /**
     * @DateOfCreation        2 Aug 2018
     * @ShortDescription      This function is responsible to create relation between doctor ans patient
     * @param                 $tablename - insertion table name
     * @param                 Array $insertData
     * @return                String {booking visit relatrion id}
     */
    public function createBookingVisitRelation($tablename, $insertData){
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        $response = $this->dbInsert($tablename, $insertData);
        if($response){
            $relId = DB::getPdo()->lastInsertId();
            return $relId;
        }else{
            return $response;
        }
    }

    /**
     * @DateOfCreation        6 Aug 2018
     * @ShortDescription      This function is responsible to change booking status on
                              patient visit
     * @param                 $tablename - insertion table name
     * @param                 Array $insertData
     * @return                String {booking visit relatrion id}
     */
    public function updateBookingState($bookingId, $bookingStatus = 0){
        // @var Boolean $response
        // This variable contains update query response
        $tablename = 'bookings';
        $requestData = $whereData = [];
        $response = false;
        if(!empty($bookingId) && $bookingStatus != 0){
            $requestData['booking_status'] = $bookingStatus;
            $whereData['booking_id'] = $bookingId;
            $response = $this->dbUpdate($tablename, $requestData, $whereData);
        }
        return $response;
    }

    /**
     * @DateOfCreation        6 Aug 2018
     * @ShortDescription      This function is responsible to get Appointment List of user
                              patient visit
     * @param                 $startDate - Calendar show start date
     * @param                 $endDate - Calendar show end date
     * @param                 $userId - Calendar show for doctor id
     * @param                 Array $extra  if specail functionlity performing otherwise empty array set
     * @return                array resousece and events
     */
    public function getTimeSlot($startDate,$endDate,$userId,$extra=[]){
        $slotSettings = $this->manageCalendarObj->getManageCalendarRecordByUserId($userId);
        $timeing = [
            'start_time' => !empty($slotSettings) &&  isset($slotSettings->mcs_start_time) ? $slotSettings->mcs_start_time : Config::get('constants.CLINIC_DEFAULT_START_TIME'),
            'end_time' => !empty($slotSettings) && isset($slotSettings->mcs_end_time) ? $slotSettings->mcs_end_time :Config::get('constants.CLINIC_DEFAULT_END_TIME'),
            'slot_duration' =>!empty($slotSettings) && isset($slotSettings->mcs_slot_duration)?  $slotSettings->mcs_slot_duration : Config::get('constants.CALENDAR_SLOT_DURATION')
        ];
        $extraTimeSlotCreat = [
        'time_slot_format' => 'h:i A',
        'booking_calculation_disable' => '1',
        ];
        $timeSlots = $this->searchObj->createTimeSlot((object) $timeing, date(Config::get('constants.DB_SAVE_DATE_FORMAT')),$extraTimeSlotCreat);
        $arrangeSlot = !empty($timeSlots) ? array_pluck($timeSlots,'slot_time_format','slot_time') :[];
        return ['slots' => $arrangeSlot,'slots_duration'=>$timeing['slot_duration']];
    }

    /**
     * @DateOfCreation        6 Aug 2018
     * @ShortDescription      This function is responsible to get Appointment List of user
                              patient visit
     * @param                 $startDate - Calendar show start date
     * @param                 $endDate - Calendar show end date
     * @param                 $userId - Calendar show for doctor id
     * @param                 Array $extra  if specail functionlity performing otherwise empty array set
     * @return                array resousece and events
     */
    public function getAppointmentListCalendar($startDate,$endDate,$userId,$appointmentType,$extra=[]){
        $clinicId = isset($extra['clinic_id']) && $extra['clinic_id']!='' ? $extra['clinic_id'] :'';
        $viewType = isset($extra['view_type']) && $extra['view_type']!='' ? $extra['view_type'] :'';
        $timeSlotsExtara = [];

        $timeSlots = $this->getTimeSlot($startDate,$endDate,$userId,$timeSlotsExtara);
        $extraEvents = [
            'clinic_id' => $clinicId,
            'slot_data' => $timeSlots['slots'],
            'slot_duration' => $timeSlots['slots_duration'],

        ];
        $eventsData = $this->getAppointmentListEvents($startDate,$endDate,$userId,$extraEvents);
        $eventsCalendarData = [];
        $resourcesCalendarData = [];
        $checkEventData = count($eventsData)>0 ? count(array_filter((array)$eventsData[0])) > 2?[1]:[] :[];

        if (count($eventsData)>0 && !empty($checkEventData)) {
            $colorDataProcess = ['1'=>Config::get('constants.CALENDAR_NOT_STARTED_COLOR'),'2'=>Config::get('constants.CALENDAR_INPROGRESS_COLOR'),'3'=>Config::get('constants.CALENDAR_COMPLETED_COLOR')];
            $eventsData = $this->utilityLibObj->changeObjectToArray($eventsData);
            $events     = $eventsData[0];
            $doctorDetails  = $this->doctorprofileObj->getProfileDetail($userId);
            $doctorDetails  = $this->utilityLibObj->changeObjectToArray($doctorDetails);
            $doctorName     = trans('Doctors::messages.doctors_title_name').' '.$doctorDetails['user_firstname'].' '.$doctorDetails['user_lastname'];

            $patientDetails = $this->patientsObj->patientListQuery($userId);
            $patientDetails = $patientDetails->get()->toArray();
            $patientDetails = !empty($patientDetails) ? $this->utilityLibObj->changeArrayKey($patientDetails,'user_id'):[]; //patientDetails array index arrange by user id
            $patAppointmentReasonsData = $this->appointmentCategoryObj->getAppointmentReasons(['user_id'=>$userId],false);
            $patAppointmentReasonsData = $this->utilityLibObj->changeObjectToArray($patAppointmentReasonsData);
            $patAppointmentReasonsData = array_pluck($patAppointmentReasonsData,'appointment_cat_name','appointment_cat_id');

            $patTimingSlotData = $this->timingObj->getAllTimingListByUserId($userId,$appointmentType,false);
            $patTimingSlotData = $this->utilityLibObj->changeObjectToArray($patTimingSlotData);
            $patTimingSlotData = array_pluck($patTimingSlotData,'slot_duration','timing_id');
            $patVisitData = $this->getBookingVisitsIdByUserId($userId,false);
            $patTimingSlotData = $this->utilityLibObj->changeArrayKey($patVisitData,'booking_id');


            foreach ($timeSlots['slots'] as $key => $value) {
                $resourcesCalendarData[] = ['id' => $key,'name' => $value];
                if((isset($events['slot'.$key]) && empty($events['slot'.$key])) || (!isset($events['slot'.$key]))){
                    continue;
                }
               $data            = explode('#',$events['slot'.$key]);
               $bookingIds      = isset($data[0]) ? explode(',', $data[0]) : [];
               $clinicsIds      = isset($data[1]) ? explode(',', $data[1]) : ['0'];
               $bookingTime     = isset($data[2]) ? explode(',', $data[2]) : [];
               $bookingDate     = isset($data[3]) ? explode(',', $data[3]) : [];
               $bookingStatus   = isset($data[4]) ? explode(',', $data[4]) : [];
               $bookingreason   = isset($data[5]) ? explode(',', $data[5]) : [];
               $patId           = isset($data[6]) ? explode(',', $data[6]) : [];
               $timingId           = isset($data[7]) ? explode(',', $data[7]) : [];
               if (!empty($bookingIds)) {
                foreach ($bookingIds as $keyBooking => $rowBooking) {

                    // IF BOOKING DATE IS PASSED AND VISIT NOT STARTED, THEN RECORD NOT SHOWING ON CALENDER
                    if( (isset($bookingDate[$keyBooking]) && strtotime($bookingDate[$keyBooking]) < strtotime(date('Y-m-d'))) && $bookingStatus[$keyBooking] == Config::get('constants.BOOKING_NOT_STARTED') ){
                        continue;
                    }

                    $temp = [];
                    $temp['id'] = $rowBooking;
                    $slotTiming = '15';
                    $start_time = isset($bookingDate[$keyBooking]) && isset($bookingTime[$keyBooking]) ? $bookingDate[$keyBooking].' '. $this->utilityLibObj->changeTimingFormat($bookingTime[$keyBooking],'H:i:s') : '';
                    $end_time = date('Y-m-d H:i:s',strtotime('+'.$slotTiming.' minutes', strtotime($start_time)));
                    $patName = isset($patientDetails[$patId[$keyBooking]]) ? $this->staticDataObj->getTitleNameById($patientDetails[$patId[$keyBooking]]['pat_title']).' '.$patientDetails[$patId[$keyBooking]]['user_firstname'].' '.$patientDetails[$patId[$keyBooking]]['user_lastname'] : '';
                    $patCode = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['pat_code'] :'';
                    $patGender = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['user_gender'] :'';
                    $patGenderName = isset($patientDetails[$patId[$keyBooking]]) ? $this->staticDataObj->getGenderNameById($patGender) :'';
                    $patMobile = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['user_mobile'] : '';
                    $patMobileCode = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['user_country_code'] :'';
                    $patEmail = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['user_email'] : '';
                    $patDob = isset($patientDetails[$patId[$keyBooking]]) ? $patientDetails[$patId[$keyBooking]]['pat_dob']:'';
                    $param= isset($patientDetails[$patId[$keyBooking]]) && !empty($patientDetails[$patId[$keyBooking]]['pat_profile_img']) ? $patientDetails[$patId[$keyBooking]]['pat_profile_img']:Config::get('constants.DEFAULT_IMAGE_NAME');
                    $imgUrl = url('api/patient-profile-thumb-image/medium/'.$this->securityLibObj->encrypt($param));
                    $patAppointment = date(Config::get('constants.CALENDAR_PATIENT_POPUP_DATE'),strtotime($start_time));
                    $patAge = !empty($patDob) ? $this->dateTimeLibObj->ageCalculation($patDob,Config::get('constants.DB_SAVE_DATE_FORMAT'),'y') :'';
                    $patAge= !empty($patAge) && isset($patAge['code']) && $patAge['code']==Config::get('restresponsecode.SUCCESS') ? $patAge['result'] : '';
                    $patAppointmentReasons = isset($patAppointmentReasonsData[$bookingreason[$keyBooking]]) ? $patAppointmentReasonsData[$bookingreason[$keyBooking]]:'';

                    $temp['start'] = $start_time;
                    $temp['end'] = $start_time;
                    $temp['resourceId'] = $key;
                    if(strtolower($viewType) == strtolower('Day')){
                        $temp['groupId'] = 'r1';
                        $temp['groupName'] = 'Appointment';
                        $temp['end'] = $end_time;
                    }
                    $temp['title'] = $patName;
                    $temp['bgColor'] =  isset($colorDataProcess[$bookingStatus[$keyBooking]]) ? '#'.$colorDataProcess[$bookingStatus[$keyBooking]] :'#D9D9D9';
                    $temp['movable'] =  false;
                    $temp['details'] = [
                    'name' => $patName,
                    'code' => $patCode,
                    'gender' => $patGenderName,
                    'mobile' => $patMobileCode.$patMobile,
                    'email' => $patEmail,
                    'age' => $patAge.' Year\'s',
                    'appointment_data' => $patAppointment,
                    'image' => $imgUrl,
                    'doctor_name' => $doctorName,
                    'appointment_reason' => $patAppointmentReasons,
                    'booking_id' => $this->securityLibObj->encrypt($rowBooking),
                    'pat_id' => $this->securityLibObj->encrypt($patId[$keyBooking]),
                    'visit_id' => isset($patTimingSlotData[$rowBooking]) && isset($patTimingSlotData[$rowBooking]['visit_id']) ? $this->securityLibObj->encrypt($patTimingSlotData[$rowBooking]['visit_id']):null
                    ] ;
                    $eventsCalendarData[] = $temp;
                }

               }
            }
        }else{
            foreach ($timeSlots['slots'] as $key => $value) {
                $resourcesCalendarData[] = ['id' => $key,'name' => $value];
            }
            if(strtolower($viewType) == strtolower('Day')){
                $eventsCalendarData[] = [
                    'id'=>'0',
                    'groupId'=>'r1',
                    'groupName'=>'Appointment',
                    'resourceId' => $key,
                    'start' =>'2018-09-04 00:00:00',
                    'end'=>'2018-09-04 00:00:00',
                    'title' => ''
                ];
            }

        }
        return ['calendarEvents' => $eventsCalendarData , 'calendarResources' => $resourcesCalendarData,'calendarSlotDuration' => $timeSlots['slots_duration']];
    }

    public function getAppointmentEvents($userId,$extra=[]){
        $query = "SELECT ".$this->table.".booking_id as id,
                    ".$this->table.".booking_time,
                    ".$this->_bookingReasonTable.".appointment_cat_name as appointment_reason,
                    CONCAT('Dr. ',doctor.doctor_firstname,' ',doctor.doctor_lastname) AS doc_name,
                    CONCAT(users.user_firstname,' ',users.user_lastname) AS title,
                    ".$this->table.".booking_date,
                    ".$this->table.".patient_extra_notes,
                    ".$this->_patientTable.".pat_profile_img,
                    ".$this->_patientTable.".pat_code,
                    ".$this->_patientTable.".pat_dob,
                    ".$this->table.".pat_id,
                    ".$this->table.".user_id,
                    clinics.clinic_id,
                    clinics.clinic_address_line1,
                    clinics.clinic_address_line2,
                    clinics.clinic_landmark,
                    clinics.clinic_pincode,
                    ".$this->booking_visit_relation.".visit_id,
                    patients_visits.status as visit_status,
                    patients_visits.created_at,
                    patients_visits.visit_number,
                    ".$this->_doctorsTable.".doc_profile_img,
                    ".$this->table.".booking_status, 
                    ".$this->table.".patient_appointment_status, 
                    timing.appointment_type, 
                    vc.video_channel,
                    par.appointment_reason as pat_appointment_reason,
                    users.*,
                    doctor.*
                    from ".$this->table."
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS pat1_id,user_firstname,user_lastname,user_gender,user_email,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(pat1_id int,
                    user_firstname text,
                    user_lastname text,
                    user_gender int,
                    user_email text,
                    user_mobile text
                    )) AS users ON users.pat1_id= bookings.pat_id
                JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                    doctor_firstname text,
                    doctor_lastname text
                    )) AS doctor ON doctor.doc_id= bookings.user_id
                JOIN clinics on bookings.clinic_id = clinics.clinic_id
                JOIN ".$this->_patientTable." on ".$this->_patientTable.".user_id = bookings.pat_id
                JOIN ".$this->_doctorsTable." on ".$this->_doctorsTable.".user_id = bookings.user_id
                JOIN timing on timing.timing_id = bookings.timing_id
                LEFT JOIN patient_appointment_reasons AS par on par.booking_id = bookings.booking_id
                LEFT JOIN ".$this->_bookingReasonTable." on ".$this->_bookingReasonTable.".appointment_cat_id = bookings.booking_reason
                LEFT JOIN ".$this->booking_visit_relation." on ".$this->booking_visit_relation.".booking_id = bookings.booking_id
                LEFT JOIN patients_visits on booking_visit_relation.visit_id = patients_visits.visit_id
                LEFT JOIN video_consulting as vc on vc.booking_id = bookings.booking_id
                    where bookings.user_id=".$userId." AND bookings.is_deleted=".Config::get('constants.IS_DELETED_NO')." AND 
                    ((".$this->table.".booking_status != ".Config::get('constants.BOOKING_NOT_STARTED')." AND ".$this->table.".booking_date < '".date('Y-m-d')."') OR (".$this->table.".booking_date >= '".date('Y-m-d')."'))";
        $query = DB::select(DB::raw($query));
        $getBookingList = [];
        foreach($query as $booking){
            $booking->id = $this->securityLibObj->encrypt($booking->id);
            $booking->pat_id = $this->securityLibObj->encrypt($booking->pat_id);
            $booking->user_id = $this->securityLibObj->encrypt($booking->user_id);
            $booking->visit_id = $this->securityLibObj->encrypt($booking->visit_id);
            $booking->clinic_id = $this->securityLibObj->encrypt($booking->clinic_id);
            $booking->doc_profile_img = !empty($booking->doc_profile_img) ? $this->securityLibObj->encrypt($booking->doc_profile_img) : null;
            $booking->pat_profile_img = !empty($booking->pat_profile_img) ? $this->securityLibObj->encrypt($booking->pat_profile_img) : null;
            $booking->start = $booking->booking_date.'T'.date("H:i", strtotime($booking->booking_time)).':00';
            $booking->user_gender = isset($booking->user_gender) ? $this->staticDataObj->getGenderNameById($booking->user_gender) :'';
            $booking->backgroundColor   = $this->getBookingBgColor($booking->booking_status);
            $booking->borderColor       = $booking->backgroundColor;
            $booking->textColor         = "#FFF";
            if(!empty($booking->pat_appointment_reason)){
                $booking->pat_appointment_reason= json_decode($booking->pat_appointment_reason);
                foreach($booking->pat_appointment_reason as $key => $res){
                    $booking->pat_appointment_reason[$key]->id = $this->securityLibObj->encrypt($res->id);
                }
            }else{
                $booking->pat_appointment_reason = [];
            }
            $getBookingList[] = $booking;
        }

        return $getBookingList;
    }

    /**
     * @DateOfCreation        12 Dec 2018
     * @ShortDescription      This function is responsible color from status
     * @param                 integer $booking_status
     * @return                $color
     */
    public function getBookingBgColor($booking_status)
    {
        $bgColor = "#fff";
        switch ($booking_status) {
            case Config::get('constants.BOOKING_NOT_STARTED'):
                $bgColor = "#039BE5";
                break;
            case Config::get('constants.BOOKING_IN_PROGRESS'):
               $bgColor = "#7986CB";
                break;
            case Config::get('constants.BOOKING_COMPLETED'):
                $bgColor = "#32CD32";
                break;
            case Config::get('constants.BOOKING_PASSED'):
                $bgColor = "#E63737";
                break;
            case Config::get('constants.BOOKING_CANCELLED'):
                $bgColor = "#E63737";
                break;
        }
        return $bgColor;
    }

    /**
     * @DateOfCreation        26 June 2018
     * @ShortDescription      This function is responsible to get all information  for Analytics show
     * @param                 integer $visitId
     * @return                object Array of medical history records
     */
    public function getAppointmentListEvents($startDate,$endDate,$userId,$extraEvents)
    {
        $slotData = isset($extraEvents['slot_data']) ?  $extraEvents['slot_data']:[];
        $clinicId = isset($extraEvents['clinic_id']) ?  $extraEvents['clinic_id']:0;
        $slotDuration = isset($extraEvents['slot_duration']) ?  $extraEvents['slot_duration']:Config::get('constants.CALENDAR_SLOT_DURATION');
        $slotDuration = $slotDuration -1;
        $whereData = [
           'isDeleted' => Config::get('constants.IS_DELETED_NO'),
           'startDate' => $startDate,
           'endDate' => $endDate,
           'userId' => $userId,
        ];
        $startslots = array_keys($slotData);
        $endslots = array_map(function($row) use($slotDuration){
        $duration = $row+$slotDuration;
            if(strlen($duration)==0){
                $duration = '0000';
            }elseif(strlen($duration)==1){
                $duration = '000'.$duration;
            }elseif(strlen($duration)==2){
                $duration = '00'.$duration;
            }elseif(strlen($duration)==3){
                $duration = '0'.$duration;
            }
            return $duration;
        }, $startslots);

        $prefixedArrayStart = preg_filter('/^/', 'startslot', $startslots);
        $prefixedArrayEnd = preg_filter('/^/', 'endslot', $startslots);
        $slotDatasEnd = array_combine($prefixedArrayEnd, $endslots);
        $slotDatasStart = array_combine($prefixedArrayStart, $startslots);
        $whereData = array_merge($whereData,$slotDatasStart,$slotDatasEnd);

        $dataQuery = "Select concat_ws('#','booking_id','clinic_id','booking_time','booking_date','booking_status','booking_reason','pat_id') as format,k.* From ( select user_id, ";

        $clinicIdCondition = !empty($clinicId) && is_numeric($clinicId) ? " and d.clinic_id=:clinicId ": " ";
        if(!empty($clinicId)){
            $whereData['clinicId'] = $clinicId;
        }

        foreach ($startslots as $key => $row) {

            $dataQuery .= "( select concat_ws('#',STRING_AGG(booking_id::character varying,','), STRING_AGG(clinic_id::character varying,','), STRING_AGG(booking_time::character varying,','), STRING_AGG(booking_date::character varying,','), STRING_AGG(booking_status::character varying,','), STRING_AGG(booking_reason::character varying,','), STRING_AGG(pat_id::character varying,','),STRING_AGG(timing_id::character varying,',') ) as slot from bookings as d where d.booking_time between :startslot".$row." and :endslot".$row." and d.is_deleted=:isDeleted and b.user_id=d.user_id ".$clinicIdCondition." and d.booking_date between :startDate and :endDate ) as slot".$row." ,";
        }
        $dataQuery = rtrim($dataQuery,',');
        $dataQuery .= " from bookings b where b.user_id=:userId and b.booking_date between :startDate and :endDate group by user_id) as k";

        $res =  DB::select(DB::raw($dataQuery), $whereData);
        return $res;
    }

    public function getTimeSlotForBooking($startTime,$endTime,$userId,$extra,$appointmentType){
        $clinicId = isset($extra['clinic_id']) ? $extra['clinic_id'] : '';
        $weekDay = isset($extra['week_day']) ? $extra['week_day'] : [];
        $original_starttime = isset($extra['original_starttime']) ? $extra['original_starttime'] : "0000";
        $weekDay = !empty($weekDay) && !is_array($weekDay) && is_numeric($weekDay) ? [$weekDay] :[];
        $whereData = [
            'is_deleted' => Config::get('constants.IS_DELETED_NO'),
            'user_id' => $userId,
            'timing.appointment_type' => $appointmentType
        ];
        if(!empty($clinicId)){
            $whereData['clinic_id']  = $clinicId;
        }
        $timingRes = DB::table($this->_timingSlotTable)
            ->select('timing_id','user_id', 'start_time','end_time','slot_duration','patients_per_slot','clinic_id')
            ->where($whereData);
        /* For new calendar to display all time slots on month view */
        if($original_starttime != "0000"){
            $timingRes->where('start_time','<',$endTime)
            ->where('end_time','>',$startTime);
        }
        if(!empty($weekDay)){
            $timingRes = $timingRes->whereIn('week_day',$weekDay);
        }
        $timingRes = $timingRes->orderBy('start_time','asc')->get()
        ->map(function($timings){
            $timings->timing_id = $this->securityLibObj->encrypt($timings->timing_id);
            $timings->user_id = $this->securityLibObj->encrypt($timings->user_id);
            $timings->clinic_id = $this->securityLibObj->encrypt($timings->clinic_id);
            return $timings;
        });
        return $timingRes;
    }

    /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to save record for the Patient Medication History
     * @param                 array $requestData
     * @return                integer Patient Medication History id
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to save record for the Patient Medication History
     * @param                 array $requestData
     * @return                integer Patient Medication History id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible to check the Visit  wefId exist in the system or not
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function isPrimaryIdExist($primaryId){
        $primaryIdExist = DB::table($this->table)
                        ->where($this->primaryKey, $primaryId)
                        ->exists();
        return $primaryIdExist;
    }

    /**
     * @DateOfCreation        11 June 2018
     * @ShortDescription      This function is responsible to Delete Work Environment data
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function doDeleteRequest($primaryId,$updateData=[])
    {
        $updateDataInit = [ 'is_deleted' => Config::get('constants.IS_DELETED_YES') ];
        $updateDataInit = array_merge($updateDataInit,$updateData);
        $queryResult = $this->dbUpdate( $this->table, $updateDataInit
                                        ,
                                        [$this->primaryKey => $primaryId]
                                    );

        if($queryResult){
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        12 April 2021
     * @ShortDescription      This function is responsible to change booking details on
                              patient appointment update
     * @param                 $tablename - bookings
     * @param                 Array $insertData
     * @return                String {booking visit relatrion id}
     */
    public function updateBooking($whereData, $requestData){
        $tablename = 'bookings';
        $response = false;

        //if patient cancle appointment then change status and also delete booking
        $requestData['is_deleted'] = Config::get('constants.IS_DELETED_YES');

        $response = $this->dbUpdate($tablename, $requestData, $whereData);
        return $response;
    }

    /**
    * @DateOfCreation        12 July 2018
    * @ShortDescription      This function is responsible to get the Booking by id
    * @param                 String $booking_id
    * @return                Array of time
    */
    public function getBookingVisitsIdByUserId($userId,$encrypt=true)
    {
        $queryResult = DB::table($this->table)
                        ->select('bookings.booking_id', 'bookings.user_id', 'bookings.pat_id', 'bookings.clinic_id', 'bookings.booking_date', 'bookings.booking_time', 'bookings.booking_reason','booking_visit_relation.visit_id', 'bookings.booking_status')
                        ->join('booking_visit_relation', 'bookings.booking_id', '=', 'booking_visit_relation.booking_id')
                        ->where([
                            'bookings.is_deleted' => Config::get('constants.IS_DELETED_NO'),
                            'booking_visit_relation.is_deleted' =>Config::get('constants.IS_DELETED_NO'),
                            'bookings.user_id'=>$userId
                        ])
                        ->get();
        if($encrypt){
            $queryResult = $queryResult->map(function($bookings){
                $bookings->user_id = $this->securityLibObj->encrypt($bookings->user_id);
                $bookings->booking_id = $this->securityLibObj->encrypt($bookings->booking_id);
                if($bookings->visit_id){
                    $bookings->visit_id = $this->securityLibObj->encrypt($bookings->visit_id);
                }
                $bookings->pat_id = $this->securityLibObj->encrypt($bookings->pat_id);
                return $bookings;
            });
        }
        return $queryResult;
    }

    /**
    * @DateOfCreation        12 July 2018
    * @ShortDescription      This function is responsible to get the Booking by id
    * @param                 String $booking_id
    * @return                Array of time
    */
    public function getPatientNextVisitSchedule($patId){
        $queryResult = DB::table($this->table)
                    ->select('booking_date','booking_time')
                    ->where('booking_date','>',date('Y-m-d'))
                    ->where([
                        'pat_id' => $patId,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                        'booking_status' => Config::get('constants.BOOKING_NOT_STARTED')
                    ])
                    ->first();
        if(!empty($queryResult)){
            return $queryResult;
        }else{
            return false;
        }
    }

    /**
    * @DateOfCreation        12 August 2021
    * @ShortDescription      This function is responsible to get the data for start video call
    * @return                Array of user
    */
    public function getDataForStartVideo($requestData)
    {
        $clinicId = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $requestData['clinic_id'] :'';

        $query = "SELECT 
                    bookings.booking_id,
                    bookings.booking_date,
                    bookings.booking_time,
                    bookings.user_id,
                    bookings.pat_id,
                    bookings.booking_status,
                    bookings.patient_extra_notes,
                    bookings.patient_appointment_status,
                    clinics.clinic_id,
                    clinics.clinic_address_line1,
                    clinics.clinic_address_line2,
                    clinics.clinic_landmark,
                    clinics.clinic_pincode,
                    booking_visit_relation.visit_id,
                    patients_visits.status as visit_status,
                    appointment_category.appointment_cat_name as booking_reason,
                    patients.pat_code,
                    patients_visits.created_at,
                    patients_visits.visit_number,
                    ".$this->_doctorsTable.".doc_profile_img,
                    patients.pat_profile_img,
                    bookings.patient_appointment_status,
                    timing.appointment_type,
                    vc.video_channel,
                    appointment_reason,
                    users.user_mobile,
                    doctor.*,
                    CONCAT('Dr. ',doctor.doctor_firstname,' ',doctor.doctor_lastname) AS doc_name,
                    CONCAT(users.user_firstname,' ',users.user_lastname) AS pat_name
                    FROM bookings 
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS pat1_id,user_firstname,user_lastname,user_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(pat1_id int,
                    user_firstname text,
                    user_lastname text,
                    user_mobile text
                    )) AS users ON users.pat1_id= bookings.pat_id
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname, user_mobile AS doctor_mobile from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                    doctor_firstname text,
                    doctor_lastname text,
                    doctor_mobile text
                    )) AS doctor ON doctor.doc_id= bookings.user_id
                    JOIN clinics on clinics.clinic_id = bookings.clinic_id 
                    JOIN timing on timing.timing_id = bookings.timing_id 
                    JOIN appointment_category on appointment_category.appointment_cat_id = bookings.booking_reason 
                    JOIN ".$this->_doctorsTable." on ".$this->_doctorsTable.".user_id = bookings.user_id ";
        
        $query .= " JOIN patients on bookings.pat_id=patients.user_id 
                    LEFT JOIN booking_visit_relation on booking_visit_relation.booking_id=bookings.booking_id 
                    LEFT JOIN patients_visits on patients_visits.visit_id=booking_visit_relation.visit_id 
                    LEFT JOIN video_consulting as vc on vc.booking_id=bookings.booking_id 
                    LEFT JOIN patient_appointment_reasons as par on par.booking_id=bookings.booking_id WHERE";

        $query .= " bookings.booking_id=".$requestData['booking_id']." ";

        $bookings = DB::select(DB::raw($query));

        if(!empty($bookings))
        {
            $bookings[0]->user_id    = $this->securityLibObj->encrypt($bookings[0]->user_id);
            $bookings[0]->pat_id     = $this->securityLibObj->encrypt($bookings[0]->pat_id);
            $bookings[0]->booking_id = $this->securityLibObj->encrypt($bookings[0]->booking_id);
            $bookings[0]->clinic_id = $this->securityLibObj->encrypt($bookings[0]->clinic_id);
            $bookings[0]->doc_profile_img = !empty($bookings[0]->doc_profile_img) ? $this->securityLibObj->encrypt($bookings[0]->doc_profile_img) : '';
            $bookings[0]->pat_profile_img = !empty($bookings[0]->pat_profile_img) ? url('api/patient-profile-thumb-image/meduim/'.$this->securityLibObj->encrypt($bookings[0]->pat_profile_img)) : '';

            if($bookings[0]->visit_id)
            {
                $bookings[0]->visit_id = $this->securityLibObj->encrypt($bookings[0]->visit_id);
            }
            return $bookings;
        }

        return false;
    }
}
