<?php

namespace App\Modules\DoctorProfile\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\Encryptable;
use App\Libraries\SecurityLib;
use Config;
/**
 * Cities
 *
 * @package                SafeHealth
 * @subpackage             Cities
 * @category               Model
 * @DateOfCreation         18 May 2018
 * @ShortDescription       This class is responsiable for Cities
 */
class DoctorColorSetting extends Model {
    use Encryptable;
    
    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
    */
    protected $primaryKey = 'id';

    /**
     *@ShortDescription Override the Table.
     *
     * @var string
    */
    protected $table = 'doctor_color_setting';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dr_id', 'primary_color_code', 'secondary_color_code', 'is_deleted', 'created_by', 'updated_by'
    ];
}
