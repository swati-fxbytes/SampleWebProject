<?php

namespace App\Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use App\Libraries\FileLib;
use App\Libraries\ImageLib;
use App\Libraries\S3Lib;
use Config, File, Uuid;

/**
 * Patients Class
 *
 * @package                ILD INDIA
 * @subpackage             Patients
 * @category               Model
 * @DateOfCreation         13 June 2018
 * @ShortDescription       This is model which need to perform the options related to
                           Patients info

 */
class DoctorPatientRelation extends Model {

    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'doctor_patient_relation';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'pat_id';

    protected $encryptable = [];

    protected $fillable = ['user_id','pat_id', 'assign_by_doc', 'ip_address', 'resource_type', 'created_by','updated_by', 'is_deleted'];
    
}
