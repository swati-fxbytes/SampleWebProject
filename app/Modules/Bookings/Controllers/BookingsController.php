<?php

namespace App\Modules\Bookings\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use App\Traits\SmsTrait;
use App\Traits\Notification as NotificationTrait;
use App\Jobs\ProcessEmail;
use App\Jobs\ProcessPushNotification;
use Config;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Libraries\DateTimeLib;
use App\Libraries\EmailLib;
use Illuminate\Support\Facades\Mail;
use App\Modules\Auth\Models\Auth as Users;
use App\Modules\Bookings\Models\Bookings;
use App\Modules\Bookings\Models\PatientAppointmentReason;
use App\Modules\Patients\Models\Patients;
use App\Modules\Auth\Models\UserDeviceToken;
use App\Modules\DoctorProfile\Models\Timing;
use App\Modules\AppointmentCategory\Models\AppointmentCategory as AppointmentCategory;
use App\Modules\Accounts\Models\Accounts as Accounts;

/**
 * BookingsController
 *
 * @package                Safehealth
 * @subpackage             BookingsController
 * @category               Controller
 * @DateOfCreation         11 July 2018
 * @ShortDescription       This controller to handle all the operation related to
                           bookings
 **/
class BookingsController extends Controller
{
    use SessionTrait, RestApi, SmsTrait, NotificationTrait;

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

        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init Bookings model object
        $this->bookingsModelObj = new Bookings();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init Patients model object
        $this->patientsModelObj = new Patients();

        $this->accountsModelObj = new Accounts();
    }

    /**
    * @DateOfCreation        11 July 2018
    * @ShortDescription      This function is responsible for creating new Appointment
    * @param $request - Request object for request data
    * @return \Illuminate\Http\Response
    */
    public function createBooking(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $userType = $request->user()->user_type;

        $extra['user_type'] = $userType;
        $appointmentType = (isset($requestData['appointment_type']) && !empty($requestData['appointment_type'])) ? $requestData['appointment_type'] : 1;
        $extra['appointment_type'] = $appointmentType;
        $isSubmittedFromVisit = $requestData['is_submit_from_visit'] ?? false;
        unset($requestData['is_submit_from_visit']);
        unset($requestData['appointment_type']);

        // Create timing in database
        $logginUserId = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        if ($request->user()->user_type == Config::get('constants.USER_TYPE_PATIENT')) {
            $patId = $request->user()->user_id;
        } else {
            $patId = $this->securityLibObj->decrypt($requestData['pat_id']);
        }

        $appointmentExist = $this->bookingsModelObj->isAppointmentExist($logginUserId, $patId);
        $requestData['clinic_id'] = (isset($requestData['clinic_id']) && !empty($requestData['clinic_id'])) ? $requestData['clinic_id'] : $this->bookingsModelObj->getDoctorClinic($logginUserId);
        $booking_reason = [];
        $appointmentCategoryModelObj = new AppointmentCategory();
        if(array_key_exists('booking_reason', $requestData)){
            foreach($requestData['booking_reason'] as $reason){
                $dcId = $this->securityLibObj->decrypt($reason);
                if(!empty($dcId)){
                    $checkCat = AppointmentCategory::where([
                                                        "appointment_cat_id" => $dcId
                                                    ])
                                                    ->first();
                    if(empty($checkCat)){
                        $checkCat = AppointmentCategory::where(DB::raw("lower(appointment_cat_name)"), "=", strtolower($reason))
                                                    ->where('user_id', '=', $logginUserId)
                                                    ->first();
                        if(!empty($checkCat)){
                            $booking_reason[] = $this->securityLibObj->encrypt($checkCat->appointment_cat_id);
                        }else{
                            $catData = [
                                "appointment_cat_name" => $reason,
                                "user_id" => $logginUserId,
                                "ip_address" => $requestData['ip_address'],
                                "resource_type" => !empty($requestData['resource_type']) ? $requestData['resource_type'] : '1'
                            ];
                            $insertId = $appointmentCategoryModelObj->createAppointmentCategory($catData);
                            $booking_reason[] = $this->securityLibObj->encrypt($insertId);
                        }
                    }else{
                        $booking_reason[] = $reason;
                    }
                }else{
                    $checkCat = AppointmentCategory::where(DB::raw("lower(appointment_cat_name)"), "=", strtolower($reason))
                                                    ->where('user_id', '=', $logginUserId)
                                                    ->first();
                    if(!empty($checkCat)){
                        $booking_reason[] = $this->securityLibObj->encrypt($checkCat->appointment_cat_id);
                    }else{                    
                        $catData = [
                            "appointment_cat_name" => $reason,
                            "user_id" => $logginUserId,
                            "ip_address" => $requestData['ip_address'],
                            "resource_type" => !empty($requestData['resource_type']) ? $requestData['resource_type'] : '1'
                        ];
                        $insertId = $appointmentCategoryModelObj->createAppointmentCategory($catData);
                        $booking_reason[] = $insertId->appointment_cat_id;
                    }
                }
            }
            $requestData['booking_reason'] = $this->securityLibObj->decrypt($booking_reason[0]);
        }

        $requestData['booking_time'] = (isset($requestData['booking_time']) && !empty($requestData['booking_time'])) ? $requestData['booking_time'] : date("Hi");
        if ($requestData['timing_id'] == Config::get('constants.NEXT_VISIT') && !$appointmentExist) {
            $recentAppointment = $this->bookingsModelObj->getRecentAppointmentData($logginUserId, $patId);
            if(!empty($recentAppointment)){
                $resns=$recentAppointment->appointment_reason;
                $requestData['booking_reason'] = $recentAppointment->booking_reason;
            }else{
                $resns= "[]";
                $requestData['booking_reason'] = '';
            }
            $nextVisitBookingDate               = strtotime('+'.Config::get('constants.NEXT_VISIT_DAYS').' days', strtotime(date('Y-m-d')));
            $requestData['booking_date']        = date('Y-m-d', $nextVisitBookingDate);
            $requestData['user_id']             = $logginUserId;
            $requestData['pat_id']              = $patId;
            $requestData['is_profile_visible']  = Config::get('constants.IS_VISIBLE_YES');
            $week_day = date('w', $nextVisitBookingDate);
            $requestData['timing_id'] = $this->bookingsModelObj->getTimingId($logginUserId, $week_day, $requestData['booking_time'], $appointmentType);
            if (!$requestData['timing_id'] && !$isSubmittedFromVisit) {
                $doctorDetail = Users::where('user_id', $requestData['user_id'])->first();
                $patientDetail = $this->bookingsModelObj->getPatientDetail($requestData['pat_id']);
                $emailDetail['doctorDetail'] = $doctorDetail;
                $emailDetail['patientDetail'] = $patientDetail;
                $emailConfigDoctor = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.nextBookingFailure',
                    'subject'       => trans('Bookings::messages.next_booking_unavailable'),
                    'to'            => $doctorDetail->user_email
                ];
                
                // Email add to queue
                ProcessEmail::dispatch($emailConfigDoctor);
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Bookings::messages.next_booking_unavailable'),
                    $this->http_codes['HTTP_OK']
                );
            }
            unset($requestData['visit_id']);
            unset($requestData['user_type']);
        } else {
            if ($userType == Config::get('constants.USER_TYPE_PATIENT')) {
                $requestData['user_id'] = $this->securityLibObj->decrypt($requestData['user_id']);
                $requestData['pat_id'] = $request->user()->user_id;
            } else {
                $requestData['user_id']             = $logginUserId;
                $requestData['pat_id']              = $patId;
                $requestData['is_profile_visible']  = Config::get('constants.IS_VISIBLE_YES');
                $requestData['booking_date']        = isset($requestData['booking_date']) && !empty($requestData['booking_date']) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['booking_date'], 'dd/mm/YYYY', 'Y-m-d')['result'] : date('Y-m-d');

                unset($requestData['visit_id']);
                unset($requestData['user_type']);
            }
            $requestData['timing_id'] = $this->securityLibObj->decrypt($requestData['timing_id']);
            $requestData['clinic_id'] = $this->securityLibObj->decrypt($requestData['clinic_id']);
            $requestData['booking_reason'] = $this->securityLibObj->decrypt($booking_reason[0]);
        }

        unset($requestData['booking_id']);
        unset($requestData['payment_mode']);
        unset($requestData['clinic_address']);

        $validate = $this->BookingsValidator($requestData, $extra);
        if ($validate["error"]) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Bookings::messages.booking_validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $isSlotTimeOver = $this->isSlotTimeOver($requestData['timing_id'], $requestData['booking_date'], $requestData['booking_time'], $appointmentType);
        if ($isSlotTimeOver) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                ['error' => [trans('Bookings::messages.booking_time_over')]],
                trans('Bookings::messages.booking_time_over'),
                $this->http_codes['HTTP_OK']
            );
        }
        try {
            /* According to new logic : we are saving patient's payment history also in table
            * because we implemented discount module for patient. Patient have 2 options for 
            * payment : cash / paytm.
            */
            //this comment will open when inputs come from mobile devices
            /*$paymentHistoryData                       = [];
            $paymentHistoryData['user_id']            = $requestData['user_id'];
            $paymentHistoryData['pat_id']             = $requestData['pat_id'];
            $paymentHistoryData['doctor_id']          = $requestData['user_id'];
            $paymentHistoryData['discount_id']        = $requestData['discount_id'];
            $paymentHistoryData['discount_amount']    = $requestData['discount_amount'];
            $paymentHistoryData['user_payment_notes'] = $requestData['user_payment_notes'];
            $paymentHistoryData['resource_type']      = $requestData['resource_type'];
            $paymentHistoryData['ip_address']         = $requestData['ip_address'];

            //if payment_type - cash then payment status will pending for user & doctor
            //if payment_type - "other" then payment status will suucess for user
            //user_payment_status - 1 for success, 2 for pending, 3 for failed
            if($requestData['payment_type'] == 'Cash')
            {
                $paymentHistoryData['user_payment_status'] = '2';
                $paymentHistoryData['dr_payment_status']   = '2';
            }else
            {
                $paymentHistoryData['user_payment_status'] = '1';
                $paymentHistoryData['dr_payment_status']   = '2';
            }

            unset($requestData['discount_id']);
            unset($requestData['discount_amount']);
            unset($requestData['user_payment_notes']);
            unset($requestData['payment_type']);

            $createPaymentsHistory = $this->accountsModelObj->createPaymentsHistoryFromBooking($paymentHistoryData);*/

            DB::beginTransaction();
            $requestData['patient_appointment_status'] = Config::get('constants.PATIENT_STATUS_GOING');
            $isBookingCreated = $this->bookingsModelObj->createBooking($requestData);

            $patDocRelData = [
                'user_id'       => $requestData['user_id'],
                'pat_id'        => $requestData['pat_id'],
                'assign_by_doc' => $requestData['pat_id'],
                'ip_address'    => $requestData['ip_address'],
                'is_deleted'    => Config::get('constants.IS_DELETED_NO')
            ];

            // Create Doctor-Patienr relation
            $patDocRelId = '';
            $patDocRelId = $this->patientsModelObj->createPatientDoctorRelation('doctor_patient_relation', $patDocRelData);

            // validate, is query executed successfully
            if (!empty($isBookingCreated) && !empty($patDocRelId)) {
                $categories = AppointmentCategory::where([
                                                    "user_id" => $logginUserId,
                                                    "is_deleted"=> Config::get('constants.IS_DELETED_NO')
                                                ])->get();
                // Upload booking Reasons
                $reasonArray =[];
                if ($requestData['timing_id'] == Config::get('constants.NEXT_VISIT') && $appointmentExist) {
                    PatientAppointmentReason::create([
                        "appointment_reason" => $resns,
                        "booking_id" => $this->securityLibObj->decrypt($isBookingCreated->booking_id),
                        "created_by" => $logginUserId,
                        "updated_by" => $logginUserId
                    ]);
                }else{
                    foreach($booking_reason as $key => $res){
                        $res = $this->securityLibObj->decrypt($res);
                        $reasonArray[$key]['id'] = $res;
                        $reasonArray[$key]['reason'] = AppointmentCategory::find($res)->appointment_cat_name;
                    }
                    PatientAppointmentReason::create([
                        "appointment_reason" => json_encode($reasonArray),
                        "booking_id" => $this->securityLibObj->decrypt($isBookingCreated->booking_id),
                        "created_by" => $logginUserId,
                        "updated_by" => $logginUserId
                    ]);
                }

                $emailDetail = array();
                $doctorDetail = Users::where('user_id', $requestData['user_id'])->first();
                $patientDetail = Users::where('user_id', $requestData['pat_id'])->first();
                $emailDetail['doctorDetail'] = $doctorDetail;
                $emailDetail['patientDetail'] = $patientDetail;
                $emailDetail['bookingDetail']  = $isBookingCreated;
                $emailConfigPatient = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.bookingsuccessfulpatient',
                    'subject'       => trans('Bookings::messages.booking_email_subject'),
                    'to'    => $patientDetail->user_email
                ];
                $emailConfigDoctor = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.bookingsuccessfuldoctor',
                    'subject'       => trans('Bookings::messages.booking_email_subject'),
                    'to'            => $doctorDetail->user_email
                ];

                try {
                    if (!$isSubmittedFromVisit) {
                        // Email add to queue
                        ProcessEmail::dispatch($emailConfigDoctor);
                        if (!empty($patientDetail->user_email)) {
                            ProcessEmail::dispatch($emailConfigPatient);
                        }
                    }
                    $userToken = UserDeviceToken::where('user_id', $requestData['pat_id'])
                                    ->where([ 'is_deleted' =>  Config::get('constants.IS_DELETED_NO') ])
                                    ->get();
                    if($userToken){
                        $tokens = [];
                        foreach($userToken as $tk){
                            $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
                        }
                        $message = "Your appointment to Dr. ".$doctorDetail->user_firstname." ".$doctorDetail->user_lastname." has been booked successful for ".$isBookingCreated->booking_date.".";
                        $notifData = [
                            "tokens" => $tokens,
                            "title" => 'Rxhealth',
                            "body" => $message,
                            "extra" => [ 
                                "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                                "title" => 'Rxhealth',
                                "body" => $message
                            ]
                        ];
                        ProcessPushNotification::dispatch($notifData);
                    }
                    DB::commit();
                    return  $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $isBookingCreated,
                        [],
                        trans('Bookings::messages.booking_added'),
                        $this->http_codes['HTTP_OK']
                    );
                } catch (\Exception $ex) {
                    DB::rollback();
                    $eMessage = $this->exceptionLibObj
                                     ->reFormAndLogException($ex, 'BookingsController', 'createBooking');
                    return $this->resultResponse(
                        Config::get('restresponsecode.EXCEPTION'),
                        [],
                        [],
                        $eMessage,
                        $this->http_codes['HTTP_OK']
                    );
                }
            } else {
                DB::rollback();
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Bookings::messages.booking_failed'),
                    $this->http_codes['HTTP_OK']
                );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex, 'BookingsController', 'createBooking');
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
    * @DateOfCreation        22 Feb 2021
    * @ShortDescription      This function is responsible for creating new Appointment
    * @param $request - Request object for request data
    * @return \Illuminate\Http\Response
    */
    public function createBookingByPatient(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $userType = $request->user()->user_type;
        $extra['user_type'] = $userType;
        
        $isSubmittedFromVisit = $requestData['is_submit_from_visit'] ?? false;
        $appointmentType = (isset($requestData['appointment_type']) && !empty($requestData['appointment_type'])) ? $requestData['appointment_type'] : 1;
        $extra['appointment_type'] = $appointmentType;
        unset($requestData['is_submit_from_visit']);
        unset($requestData['appointment_type']);

        // Create timing in database
        $doctId = $this->securityLibObj->decrypt($requestData['doct_id']);
        $patId = $request->user()->user_id;
        
        $appointmentExist = $this->bookingsModelObj->isAppointmentExist($doctId, $patId);
        $requestData['clinic_id'] = (isset($requestData['clinic_id']) && !empty($requestData['clinic_id'])) ? $requestData['clinic_id'] : $this->bookingsModelObj->getDoctorClinic($doctId);
        
        $booking_reason = [];
        $appointmentCategoryModelObj = new AppointmentCategory();
        foreach($requestData['booking_reason'] as $reason){
            $dcId = $this->securityLibObj->decrypt($reason);
            if(!empty($dcId)){
                $checkCat = AppointmentCategory::where([
                                                    "appointment_cat_id" => $dcId
                                                ])
                                                ->first();
                if(empty($checkCat)){
                    $checkCat = AppointmentCategory::where(DB::raw("lower(appointment_cat_name)"), "=", strtolower($reason))
                                                ->where('user_id', '=', $doctId)
                                                ->first();
                    if(!empty($checkCat)){
                        $booking_reason[] = $this->securityLibObj->encrypt($checkCat->appointment_cat_id);
                    }else{
                        $catData = [
                            "appointment_cat_name" => $reason,
                            "user_id" => $doctId,
                            "ip_address" => $requestData['ip_address'],
                            "resource_type" => $requestData['resource_type']
                        ];
                        $insertId = $appointmentCategoryModelObj->createAppointmentCategory($catData);
                        $booking_reason[] = $this->securityLibObj->encrypt($insertId);
                    }
                }else{
                    $booking_reason[] = $reason;
                }
            }else{
                $checkCat = AppointmentCategory::where(DB::raw("lower(appointment_cat_name)"), "=", strtolower($reason))
                                                ->where('user_id', '=', $doctId)
                                                ->first();
                if(!empty($checkCat)){
                    $booking_reason[] = $this->securityLibObj->encrypt($checkCat->appointment_cat_id);
                }else{
                    $catData = [
                        "appointment_cat_name" => $reason,
                        "user_id" => $doctId,
                        "ip_address" => $requestData['ip_address'],
                        "resource_type" => $requestData['resource_type']
                    ];
                    $insertId = $appointmentCategoryModelObj->createAppointmentCategory($catData);
                    $booking_reason[] = $insertId->appointment_cat_id;
                }
            }
        }
        $requestData['booking_reason'] = $this->securityLibObj->decrypt($booking_reason[0]);
        $requestData['booking_time'] = (isset($requestData['booking_time']) && !empty($requestData['booking_time'])) ? $requestData['booking_time'] : date("Hi");
        $requestData['booking_date'] = date('Y-m-d', strtotime($requestData['booking_date']));
        if ($requestData['timing_id'] == Config::get('constants.NEXT_VISIT') && !$appointmentExist) {
            $nextVisitBookingDate               = strtotime('+'.Config::get('constants.NEXT_VISIT_DAYS').' days', strtotime(date('Y-m-d')));
            $requestData['booking_date']        = date('Y-m-d', $nextVisitBookingDate);
            $requestData['user_id']             = $doctId;
            $requestData['pat_id']              = $patId;
            $requestData['is_profile_visible']  = Config::get('constants.IS_VISIBLE_YES');
            $week_day = date('w', $nextVisitBookingDate);
            $requestData['timing_id'] = $this->bookingsModelObj->getTimingId($doctId, $week_day, $requestData['booking_time'], $appointmentType);
            if (!$requestData['timing_id'] && !$isSubmittedFromVisit) {
                $doctorDetail = Users::where('user_id', $requestData['user_id'])->first();
                $patientDetail = $this->bookingsModelObj->getPatientDetail($requestData['pat_id']);
                $emailDetail['doctorDetail'] = $doctorDetail;
                $emailDetail['patientDetail'] = $patientDetail;
                $emailConfigDoctor = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.nextBookingFailure',
                    'subject'       => trans('Bookings::messages.next_booking_unavailable'),
                    'to' => $doctorDetail->user_email
                ];
                
                ProcessEmail::dispatch($emailConfigDoctor);
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Bookings::messages.next_booking_unavailable'),
                    $this->http_codes['HTTP_OK']
                );
            }
            unset($requestData['visit_id']);
            unset($requestData['user_type']);
        } else {
            if ($userType == Config::get('constants.USER_TYPE_PATIENT')) {
                $requestData['user_id'] = $doctId;
                $requestData['pat_id'] = $request->user()->user_id;
            } else {
                $requestData['user_id']             = $doctId;
                $requestData['pat_id']              = $patId;
                $requestData['is_profile_visible']  = Config::get('constants.IS_VISIBLE_YES');
                $requestData['booking_date']        = isset($requestData['booking_date']) && !empty($requestData['booking_date']) ? $this->dateTimeLibObj->covertUserDateToServerType($requestData['booking_date'], 'dd/mm/YYYY', 'Y-m-d')['result'] : date('Y-m-d');

                unset($requestData['visit_id']);
                unset($requestData['user_type']);
            }
            $requestData['timing_id'] = $this->securityLibObj->decrypt($requestData['timing_id']);
            $requestData['clinic_id'] = $this->securityLibObj->decrypt($requestData['clinic_id']);
        }

        unset($requestData['booking_id']);
        unset($requestData['payment_mode']);
        unset($requestData['clinic_address']);
        unset($requestData['apt_type']);
        unset($requestData['doct_id']);
        unset($requestData['user_type']);

        $validate = $this->BookingsValidator($requestData, $extra);
        if ($validate["error"]) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $validate['errors'],
                trans('Bookings::messages.booking_validation_failed'),
                $this->http_codes['HTTP_OK']
                  );
        }
        $isSlotTimeOver = $this->isSlotTimeOver($requestData['timing_id'], $requestData['booking_date'], $requestData['booking_time'],$appointmentType);
        if ($isSlotTimeOver) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                ['error' => [trans('Bookings::messages.booking_time_over')]],
                trans('Bookings::messages.booking_time_over'),
                $this->http_codes['HTTP_OK']
            );
        }
        try {
            DB::beginTransaction();
            $requestData['patient_appointment_status'] = Config::get("constants.PATIENT_STATUS_GOING");
            $isBookingCreated = $this->bookingsModelObj->createBooking($requestData);

            $patDocRelData = [
                'user_id'       => $requestData['user_id'],
                'pat_id'        => $requestData['pat_id'],
                'assign_by_doc' => $requestData['pat_id'],
                'ip_address'    => $requestData['ip_address'],
                'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
            ];

            // Create Doctor-Patienr relation
            $patDocRelId = '';
            $patDocRelId = $this->patientsModelObj->createPatientDoctorRelation('doctor_patient_relation', $patDocRelData);

            // validate, is query executed successfully
            if (!empty($isBookingCreated) && !empty($patDocRelId)) {
                // Upload booking Reasons
                $reasonArray =[];
                foreach($booking_reason as $key => $res){
                    $res = $this->securityLibObj->decrypt($res);
                    $reasonArray[$key]['id'] = $res;
                    $reasonArray[$key]['reason'] = AppointmentCategory::find($res)->appointment_cat_name;
                }
                PatientAppointmentReason::create([
                    "appointment_reason" => json_encode($reasonArray),
                    "booking_id" => $this->securityLibObj->decrypt($isBookingCreated->booking_id),
                    "created_by" => $patId,
                    "updated_by" => $patId
                ]);

                $emailDetail = array();
                $doctorDetail = Users::where('user_id', $requestData['user_id'])->first();
                $patientDetail = Users::where('user_id', $requestData['pat_id'])->first();
                $emailDetail['doctorDetail'] = $doctorDetail;
                $emailDetail['patientDetail'] = $patientDetail;
                $emailDetail['bookingDetail']  = $isBookingCreated;
                $emailConfigPatient = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.bookingsuccessfulpatient',
                    'subject'       => trans('Bookings::messages.booking_email_subject'),
                    'to' => $patientDetail->user_email
                ];
                $emailConfigDoctor = [
                    'viewData'  =>  [
                                        'emailDetail'=>$emailDetail,
                                        'app_name' => Config::get('constants.APP_NAME'),
                                        'app_url' => Config::get('constants.APP_URL'),
                                        'info_email' => Config::get('constants.INFO_EMAIL')
                                    ],
                    'emailTemplate' => 'emails.bookingsuccessfuldoctor',
                    'subject'       => trans('Bookings::messages.booking_email_subject'),
                    'to' => $doctorDetail->user_email
                ];

                try {
                    if (!$isSubmittedFromVisit)
                    {
                        ProcessEmail::dispatch($emailConfigDoctor);
                        if (!empty($patientDetail->user_email))
                        {
                            ProcessEmail::dispatch($emailConfigPatient);
                        }
                    }
                    DB::commit();
                    $bookingDetails = $this->bookingsModelObj->getBookingDetailsById($isBookingCreated->booking_id);
                    return  $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $bookingDetails,
                        [],
                        trans('Bookings::messages.booking_added'),
                        $this->http_codes['HTTP_OK']
                    );
                } catch (\Exception $ex) {
                    DB::rollback();
                    $eMessage = $this->exceptionLibObj
                                     ->reFormAndLogException($ex, 'BookingsController', 'createBooking');
                    return $this->resultResponse(
                        Config::get('restresponsecode.EXCEPTION'),
                        [],
                        [],
                        $eMessage,
                        $this->http_codes['HTTP_OK']
                    );
                }
            } else {
                DB::rollback();
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Bookings::messages.booking_failed'),
                    $this->http_codes['HTTP_OK']
                );
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $eMessage = $this->exceptionLibObj->reFormAndLogException($ex, 'BookingsController', 'createBooking');
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
    * @DateOfCreation        11 July 2018
    * @ShortDescription      This function is responsible for validating booking data
    * @param                 Array $data This contains full request data
    * @param                 Array $extra extra validation rules
    * @return                VIEW
    */
    protected function BookingsValidator(array $data, $extra = [])
    {
        $error = false;
        $errors = [];
        $userType = $extra['user_type'];
        $slotValidationCheck = $this->bookingsModelObj->isSlotAvailable($data['timing_id'], $data['booking_date'], $data['booking_time'], $extra['appointment_type']);
        $isSlotValid = Config::get('constants.SLOT_IS_VALID');
        if ($slotValidationCheck === true) {
            $patId = ($userType == Config::get('constants.USER_TYPE_PATIENT')) ? $data['pat_id'] : $data['user_id'];
            $userAlreadyBooked = $this->bookingsModelObj->userAlreadyBooked($data['timing_id'], $data['booking_date'], $data['booking_time'], $patId);
            if ($userAlreadyBooked == Config::get('constants.PATIENT_ALREADY_BOOKED_SLOT')) {
                $isSlotValid = trans('Bookings::messages.user_already_booked_slot_patient');
            } elseif ($userAlreadyBooked == Config::get('constants.PATIENT_ALREADY_BOOKED_DAY')) {
                $isSlotValid = Config::get('constants.SLOT_IS_VALID');
            }
        }
        $extra = [];
        $rules = [
            'user_id' => 'required',
            'pat_id'  => 'required',
            'clinic_id' => 'required',
            'timing_id' => 'required',
            'booking_date' => 'required',
            'booking_time' => 'required|booking_available_check:booking_date,'.$isSlotValid,
            'is_profile_visible' => 'required',
        ];
        $rules = array_merge($rules, $extra);
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * @DateOfCreation        24 July 2018
    * @ShortDescription      This function is responsible for checking slot availability
    * @param                 Array $data This contains full request data
    * @param                 Array $extra extra validation rules
    * @return                VIEW
    */
    protected function isSlotAvailable(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['timing_id'] = $this->securityLibObj->decrypt($requestData['timing_id']);

        $requestData['user_id'] = $this->securityLibObj->decrypt($requestData['user_id']);
        $isSlotValid = $isSlotTimeOver = false;
        $appointmentType = (isset($requestData['appointment_type']) && !empty($requestData['appointment_type'])) ? $requestData['appointment_type'] : 1;
        $isSlotTimeOver = $this->isSlotTimeOver($requestData['timing_id'], $requestData['booking_date'], $requestData['booking_time'], $appointmentType);
        if ($isSlotTimeOver) {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.booking_time_over'),
                $this->http_codes['HTTP_OK']
            );
        }
        $isSlotValid = $this->bookingsModelObj->isSlotAvailable($requestData['timing_id'], $requestData['booking_date'], $requestData['booking_time'], $appointmentType);
        if ($isSlotValid === true) {
            $userAlreadyBooked = $this->bookingsModelObj->userAlreadyBooked($requestData['timing_id'], $requestData['booking_date'], $requestData['booking_time'], $requestData['user_id']);
            if ($userAlreadyBooked == Config::get('constants.PATIENT_ALREADY_BOOKED_SLOT')) {
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Bookings::messages.user_already_booked_slot'),
                    $this->http_codes['HTTP_OK']
                );
            } elseif ($userAlreadyBooked == Config::get('constants.PATIENT_ALREADY_BOOKED_DAY')) {
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    trans('Bookings::messages.user_already_booked_day'),
                    [],
                    '',
                    $this->http_codes['HTTP_OK']
                );
            } else {
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $isSlotValid,
                    [],
                    '',
                    $this->http_codes['HTTP_OK']
                );
            }
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.booking_unavailable'),
                $this->http_codes['HTTP_OK']
              );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getAppointmentList(Request $request)
    {
        $user_id     = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        $user_type   = $request->user()->user_type;
        
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $user_id;
        $requestData['user_type'] = $user_type;
        $requestData['clinic_id'] = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $this->securityLibObj->decrypt($requestData['clinic_id']) : '';
        if ($user_type == Config::get('constants.USER_TYPE_PATIENT') && empty($requestData['date'])) {
            $date = date('Y-m-d');
        } elseif ($user_type == Config::get('constants.USER_TYPE_PATIENT') && !empty($requestData['date'])) {
            $type = strtotime($requestData['date']);
            if ($requestData['appointmentPage'] == 'next') {
                $type = strtotime($requestData['date'] . "+1 days");
            } elseif ($requestData['appointmentPage'] == 'previous') {
                $type = strtotime($requestData['date'] . "-1 days");
            }
            $date = date('Y-m-d', $type);
        } else {
            $date = $requestData['date'];
        }

        if(array_key_exists('dr_id', $requestData)){
            $requestData['dr_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        }

        if (!empty($date)) {
            $appointmentDate = $date;
            $requestData['appointmentDate'] = date('Y-m-d', strtotime($appointmentDate));

            $appointmentList = $this->bookingsModelObj->getAppointmentList($requestData);
            if ($appointmentList) {
                return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $appointmentList,
                    [],
                    trans('Bookings::messages.appointment_list_success'),
                    $this->http_codes['HTTP_OK']
                    );
            } else {
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Bookings::messages.appointment_list_error'),
                    $this->http_codes['HTTP_OK']
                );
            }
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.appointment_date_required'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getAppointmentListForApp(Request $request)
    {
        //first get token from header and and get user details using token
        
        $user_id     = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        
        $user_type   = $request->user()->user_type;
        
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $user_id;
        $requestData['user_type'] = $user_type;
        $requestData['clinic_id'] = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $this->securityLibObj->decrypt($requestData['clinic_id']) : '';
        if(array_key_exists('dr_id', $requestData) && !empty($requestData['dr_id'])){
            $requestData['dr_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        }

        $appointmentList = $this->bookingsModelObj->getAppointmentListForApp($requestData);
        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.appointment_list_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.appointment_list_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        17 Feb 2021
    * @ShortDescription      Get all appointments for a loggedin patient
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getAllAppointmentsForPatient(Request $request)
    {
        $user_id     = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        
        $user_type   = $request->user()->user_type;
        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = $user_id;
        $requestData['clinic_id'] = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $this->securityLibObj->decrypt($requestData['clinic_id']) : '';
        $requestData['appointmentDate'] = !empty($requestData['date']) ? date('Y-m-d', strtotime($requestData['date'])) : '';

        $appointmentList = $this->bookingsModelObj->getAllAppointmentsForPatient($requestData);
        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.appointment_list_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.appointment_list_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getTodayAppointmentList(Request $request)
    {
        $user_id     = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;
        $user_type   = $request->user()->user_type;

        $appointmentList = $this->bookingsModelObj->getTodayAppointmentList($user_id, $user_type);
        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.today_appointment_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.today_appointment_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getTodayAppointmentListForPatient(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $rules = [
            'dr_id' => 'required'
        ];
        $validator = Validator::make($requestData, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                $errors,
                trans('Bookings::messages.booking_validation_failed'),
                $this->http_codes['HTTP_OK']
            );
        }
        $requestData['pat_id'] = $request->user()->user_id;
        $requestData['user_id'] = $this->securityLibObj->decrypt($requestData['dr_id']);
        $user_type   = $request->user()->user_type;
        $appointmentList = $this->bookingsModelObj->getTodayAppointmentListForPatient($requestData);
        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.today_appointment_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.today_appointment_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getAppointmentListCalendar(Request $request)
    {
        $user_id     = $request->user()->user_id;
        $user_type   = $request->user()->user_type;

        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $user_id;

        $requestData['user_type'] = $user_type;
        $startDate = $requestData['startDate'];
        $endDate = $requestData['endDate'];
        $viewType = $requestData['view_type'];
        $userId = $requestData['user_id'];
        $extra =[];
        $extra['view_type'] = $viewType;
        $extra['clinic_id'] = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $this->securityLibObj->decrypt($requestData['clinic_id']) : '';
        $extra = array_filter($extra);
        $appointmentList = $this->bookingsModelObj->getAppointmentListCalendar($startDate, $endDate, $userId, $extra);

        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.appointment_list_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.appointment_list_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getAppointments(Request $request)
    {
        $user_id     = $request->user()->user_id;
        $user_type   = $request->user()->user_type;

        $requestData = $this->getRequestData($request);
        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $user_id;

        $requestData['user_type'] = $user_type;
        $userId = $requestData['user_id'];
        $extra =[];
        $extra['clinic_id'] = isset($requestData['clinic_id']) && !empty($requestData['clinic_id']) ? $this->securityLibObj->decrypt($requestData['clinic_id']) : '';
        $extra['start_date'] = $requestData['startDate'];
        $extra['end_date']   = $requestData['endDate'];
        $extra = array_filter($extra);
        $appointmentList = $this->bookingsModelObj->getAppointmentEvents($userId, $extra);

        if ($appointmentList) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $appointmentList,
                [],
                trans('Bookings::messages.appointment_list_success'),
                $this->http_codes['HTTP_OK']
                );
        } else {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Bookings::messages.appointment_list_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        30 July 2018
    * @ShortDescription      Get a validator for an incoming User request
    * @param                 \Illuminate\Http\Request  $request
    * @return                \Illuminate\Contracts\Validation\Validator
    */
    public function getPatientNextVisitSchedule(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $patId = $this->securityLibObj->decrypt($requestData['pat_id']);
        $nextbooking = $this->bookingsModelObj->getPatientNextVisitSchedule($patId);
        return $this->resultResponse(
            Config::get('restresponsecode.SUCCESS'),
            $nextbooking,
            [],
            trans('Bookings::messages.patient_next_booking_success'),
            $this->http_codes['HTTP_OK']
        );
    }

    /**
    * @DateOfCreation        21 Dec 2018
    * @ShortDescription      Check if the selected time slot is over
    * @return                boolean
    */
    public function isSlotTimeOver($timing_id, $booking_date, $booking_time, $appointment_type)
    {
        $serverTime = time();
        // $serverTime = strtotime("+330 minutes"); // Changed utc time to india GMT time
        
        $timingModelObj = new Timing();
        $timing = $timingModelObj->getTimingById($timing_id, $appointment_type);
        if (!empty($timing)) {
            $slotDuration = $timing->slot_duration;
            if (!empty($slotDuration)) {
                $nextSlotTime = strtotime($booking_date.' '.$booking_time.'+'.$slotDuration.' minutes');
                if ($nextSlotTime <= $serverTime) {
                    return true;
                }
            }
        }
        return false;
    }

    public function sendTestMsg(Request $request){
        $requestData = Bookings::select( 'appointment_cat_name', 'booking_id', "booking_reason", "bookings.created_by")
                                ->join("appointment_category as ac", "ac.appointment_cat_id", "=", "bookings.booking_reason")
                                ->where("booking_reason", "!=", 0)
                                ->get();
        foreach ($requestData as $key => $value) {
            $reasonArray = [
                ["id" => $value->booking_reason,
                                "reason" => $value->appointment_cat_name]
            ];
            PatientAppointmentReason::create([
                "appointment_reason" => json_encode($reasonArray),
                "booking_id" => $value->booking_id,
                "created_by" => $value->created_by,
                "updated_by" => $value->created_by
            ]);
        }
    }
}