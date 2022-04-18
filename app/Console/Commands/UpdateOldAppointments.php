<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libraries\SecurityLib;
use App\Modules\Bookings\Models\Bookings;
use Config, DB;

class UpdateOldAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Appointment:updateStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This job will update passed date appointment status to visited if visit id availale and passed if not attend.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $todayDate = date('Y-m-d', strtotime("Now"));
        
        //Send notification to confirmed appointments
        $getBookings = Bookings::select([
                                'bookings.booking_id',
                                'patient_appointment_status as aps',
                                "patients_visits.visit_id"
                            ])
                            ->leftJoin("booking_visit_relation AS bvr", "bvr.booking_id", "=", "bookings.booking_id")
                            ->leftJoin("patients_visits", "patients_visits.visit_id", "=", "bvr.visit_id")
                            ->where([
                                'bookings.is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                'patient_appointment_status' => Config::get('constants.PATIENT_STATUS_GOING')
                            ])
                            ->whereNotIn("patient_appointment_status", [
                                Config::get('constants.PATIENT_STATUS_VISITED'),
                                Config::get('constants.PATIENT_STATUS_CANCELLED'),
                                Config::get('constants.PATIENT_STATUS_PASSED')
                            ])
                            ->where("bookings.booking_date", "<", $todayDate)
                            ->get();
        foreach($getBookings as $bk){
            if(!empty($bk->visit_id)){
                $updateData = [
                    "patient_appointment_status" => Config::get("constants.PATIENT_STATUS_VISITED"),
                    "booking_status" => Config::get("constants.BOOKING_COMPLETED")
                ];
            }else{
                $status = Config::get("constants.PATIENT_STATUS_PASSED");
                $updateData = [
                    "patient_appointment_status" => Config::get("constants.PATIENT_STATUS_PASSED"),
                    "booking_status" => Config::get("constants.BOOKING_PASSED")
                ];
            }

            Bookings::where("booking_id", $bk->booking_id)
                    ->update($updateData);
        }
    }
}