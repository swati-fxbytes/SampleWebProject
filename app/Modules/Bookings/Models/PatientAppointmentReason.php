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
class PatientAppointmentReason extends Model {

    use HasApiTokens,Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'patient_appointment_reasons';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'id';

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'appointment_reason', 'booking_id', 'created_by', 'updated_by'
    ];

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
}