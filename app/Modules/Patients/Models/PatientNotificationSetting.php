<?php
namespace App\Modules\Patients\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use Config;
use DB;

/**
 * Medicines
 *
 * @package                ILD India Registry
 * @subpackage             Medicines
 * @category               Model
 * @DateOfCreation         11 june 2018
 * @ShortDescription       This Model to handle database operation of Medicines
 **/

class PatientNotificationSetting extends Model {
    
    use HasApiTokens,Encryptable;

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $table = 'patient_notification_setting';
    
    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['pat_id','medicine_notification',
        'vital_notification',
        'lab_test_notification',
        'morning_medicine_notification_before_breakfast',
        'morning_medicine_notification_after_breakfast',
        'afternoon_medicine_notification_before_lunch',
        'afternoon_medicine_notification_after_lunch',
        'night_medicine_notification_before_dinner',
        'night_medicine_notification_after_dinner',
        'resource_type',
        'ip_address',
        'created_by',
        'updated_by',
        'is_deleted'
    ];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'patient_notification_id';
}