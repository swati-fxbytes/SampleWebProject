<?php
namespace App\Modules\Visits\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use App\Libraries\SecurityLib;
use Config;
use App\Libraries\UtilityLib;
use DB;

/**
 * SocialAddictionUse
 *
 * @package                ILD India Registry
 * @subpackage             SocialAddictionUse
 * @category               Model
 * @DateOfCreation         11 june 2018
 * @ShortDescription       This Model to handle database operation with current table
                           patient_domestic_factors_condition
 **/
class SocialAddictionUse extends Model {

    use HasApiTokens,Encryptable;

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init exception library object
        $this->utilityLibObj = new UtilityLib();
    }

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $table = 'social_addiction_use_pack_year';
    
    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'ip_address',
                            'sau_type',
                            'starting_date',
                            'stopping_date',
                            'quantitiy',
                            'quantitiy_unit',
                            'pack_year',
                            'resource_type'
                        ];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'sau_id';

    /**
     * @DateOfCreation        25 June 2018
     * @ShortDescription      This function is responsible to get the patient Social Addiction use record
     * @param                 integer $vistId   
     * @return                object Array of DomesticFactor records
     */
    public function getPatientSocialAddictionUseRecord($vistId) 
    {        
        $queryResult = DB::table($this->table)
            ->select( 'sau_id','sau_type', 'starting_date','stopping_date', 'quantitiy', 'quantitiy_unit', 'pack_year','resource_type', 'ip_address') 
            ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
            ->where('visit_id',$vistId);
               
        $queryResult = $queryResult->get()
            ->map(function($socialAddictionUseRecord){
            $socialAddictionUseRecord->sau_id = $this->securityLibObj->encrypt($socialAddictionUseRecord->sau_id);
            return $socialAddictionUseRecord;
        });
        return $queryResult;

    }

    /**
     * @DateOfCreation        26 June 2018
     * @ShortDescription      This function is responsible to check if fector record is exist or not
     * @param                 integer $patId   
     * @return                object Array of symptoms records
     */
    public function checkIfFectorExist($vistId, $fectorId) 
    {        
        $queryResult = DB::table($this->table)
            ->select( 'sau_id' ) 
            ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
            ->where('visit_id', $vistId)
            ->where('sau_type', $fectorId);
        return $queryResult->get()->count();
    }

    /**
    * @DateOfCreation        27 June 2018
    * @ShortDescription      This function is responsible to update Social Addiction use Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function updateSocialAddiction($requestData,$whereData)
    {
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        if($response){
            return true;
        }
        return false;
    }

    /**
    * @DateOfCreation        27 June 2018
    * @ShortDescription      This function is responsible to multiple add Social Addiction use Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function addSocialAddiction($insertData)
    {
        $response = $this->dbBatchInsert($this->table, $insertData);
        if($response){
            return true;
        }
        return false;
    }
}