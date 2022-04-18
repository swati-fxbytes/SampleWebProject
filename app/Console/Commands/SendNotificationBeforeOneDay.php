<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\SecurityLib;

use App\Modules\Bookings\Models\Bookings;
use App\Modules\Patients\Models\Patients;
use App\Modules\Auth\Models\UserDeviceToken;
// use App\Traits\Notification as NotificationTrait;
use App\Jobs\ProcessPushNotification;
use DB, Config;

class SendNotificationBeforeOneDay extends Command
{
    // use NotificationTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SendNotification:beforeOneDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification which are scheduled tomorrow.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Init security library object
        $this->securityLibObj = new SecurityLib();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nextDate = date('Y-m-d', strtotime("tomorrow"));

        // Send notification to confirmed appointments
        $query = "SELECT
                    dr.*,
                    pt.*,
                    booking_id,
                    timing_id,
                    clinic_id,
                    booking_date,
                    booking_time,
                    booking_status
                    FROM bookings
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                    'SELECT user_id AS dr_id,user_firstname AS dr_user_firstname,user_lastname AS dr_user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(
                    dr_id int,dr_user_firstname text,dr_user_lastname text)) AS dr ON dr.dr_id= bookings.user_id 
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                    'SELECT user_id AS pat_id,user_firstname AS pt_user_firstname,user_lastname AS pt_user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(
                    pat_id int,pt_user_firstname text,pt_user_lastname text)) AS pt ON pt.pat_id= bookings.pat_id
                    WHERE bookings.is_deleted =".Config::get('constants.IS_DELETED_NO')." 
                    AND booking_status =".Config::get('constants.BOOKING_NOT_STARTED')." 
                    AND patient_appointment_status =".Config::get('constants.PATIENT_STATUS_GOING')."
                    AND booking_date = '".$nextDate."'";
        $getBookings = DB::select(DB::raw($query));
        if($getBookings){
            foreach($getBookings as $bk){
                $userToken = UserDeviceToken::where('user_id', $bk->pat_id)
                                            ->where([ 
                                                'is_deleted' =>  Config::get('constants.IS_DELETED_NO')
                                            ])
                                            ->get();
                if($userToken){
                    $dr_name = $bk->dr_user_firstname." ".$bk->dr_user_lastname;
                    $pt_name = $bk->pt_user_firstname." ".$bk->pt_user_lastname;
                    $booking_date = date("d, M Y", strtotime($bk->booking_date));
                    $booking_time = date("h:i a", strtotime($bk->booking_time));
                    $tokens = [];
                    foreach($userToken as $tk){
                        $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
                    }
                    $message = "Hi ".$pt_name.", You have an appointment with Dr. ".$dr_name." on date ".$booking_date." at ".$booking_time;
                    $notifData = [
                        "tokens" => $tokens,
                        "title" => 'Rxhealth',
                        "body" => $message,
                        "extra" => [ 
                            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                            "title" => 'Rxhealth',
                            "body" => $message,
                            "type" => "tomorrow-appointment",
                            "booking_id" => $this->securityLibObj->encrypt($bk->booking_id)
                        ]
                    ];
                    ProcessPushNotification::dispatch($notifData);
                }
            }
        }

        //Send notification to unconfirmed appointment
        $query = "SELECT
                    dr.*,
                    pt.*,
                    booking_id,
                    timing_id,
                    clinic_id,
                    booking_date,
                    booking_time,
                    booking_status
                    FROM bookings
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                    'SELECT user_id AS dr_id,user_firstname AS dr_user_firstname,user_lastname AS dr_user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(
                    dr_id int,dr_user_firstname text,dr_user_lastname text)) AS dr ON dr.dr_id= bookings.user_id 
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."',
                    'SELECT user_id AS pat_id,user_firstname AS pt_user_firstname,user_lastname AS pt_user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(
                    pat_id int,pt_user_firstname text,pt_user_lastname text)) AS pt ON pt.pat_id= bookings.pat_id
                    WHERE bookings.is_deleted =".Config::get('constants.IS_DELETED_NO')." 
                    AND booking_status =".Config::get('constants.BOOKING_NOT_STARTED')." 
                    AND patient_appointment_status =".Config::get('constants.PATIENT_STATUS_PENDING')."
                    AND booking_date = '".$nextDate."'";
        $getBookings = DB::select(DB::raw($query));
        if($getBookings){
            foreach($getBookings as $bk){
                $userToken = UserDeviceToken::where('user_id', $bk->pat_id)
                                            ->where([ 
                                                'is_deleted' =>  Config::get('constants.IS_DELETED_NO')
                                            ])
                                            ->get();
                if($userToken){
                    $dr_name = $bk->dr_user_firstname." ".$bk->dr_user_lastname;
                    $pt_name = $bk->pt_user_firstname." ".$bk->pt_user_lastname;
                    $booking_date = date("d, M Y", strtotime($bk->booking_date));
                    $booking_time = date("h:i a", strtotime($bk->booking_time));
                    $tokens = [];
                    foreach($userToken as $tk){
                        $tokens[] = ["plateform" => $tk->plateform, 'token'=> $tk->token];
                    }
                    $message = "Hi ".$pt_name.", Please confirm your appointment with Dr. ".$dr_name." on date ".$booking_date." at ".$booking_time;
                    $notifData = [
                        "tokens" => $tokens,
                        "title" => 'Rxhealth',
                        "body" => $message,
                        "extra" => [ 
                            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                            "title" => 'Rxhealth',
                            "body" => $message,
                            "type" => "confirm-appointment",
                            "booking_date" => $bk->booking_date,
                            "booking_id" => $this->securityLibObj->encrypt($bk->booking_id)
                        ]
                    ];
                    ProcessPushNotification::dispatch($notifData);
                }
            }
        }
    }
}
