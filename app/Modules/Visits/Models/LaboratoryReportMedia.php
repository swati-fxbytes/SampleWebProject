<?php
namespace App\Modules\Visits\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use Config;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use Response;
use App\Modules\Visits\Models\MedicationHistory;

/**
 * LaboratoryReport
 *
 * @package                ILD
 * @subpackage             LaboratoryReport
 * @category               Model
 * @DateOfCreation         11 june 2018
 * @ShortDescription       This Model to handle database operation with current table
                           City
 **/
class LaboratoryReportMedia extends Model {

    use HasApiTokens,Encryptable;

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $table = 'laboratory_report_media';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['lr_media_id','lr_media_name','is_deleted','created_at','updated_at', 'created_by', 'updated_by'];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    
}
