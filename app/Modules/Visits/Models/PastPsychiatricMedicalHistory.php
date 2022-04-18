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
 * LaboratoryTest
 *
 * @package                ILD India Registry
 * @subpackage             LaboratoryTest
 * @category               Model
 * @DateOfCreation         11 june 2018
 * @ShortDescription       This Model to handle database operation with current table
                           patient_domestic_factors_condition
 **/
class PastPsychiatricMedicalHistory extends Model {

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
    protected $table = 'past_psychiatric_medical_history';
    
    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'pat_id', 
                            'visit_id',
                            'ppmh_type_id',
                            'ppmh_value',
                            'resource_type',
                            'ip_address'
                        ];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'ppmh_id';

    /**
     * @DateOfCreation        25 June 2018
     * @ShortDescription      This function is responsible to get the patient domestic fector record
     * @param                 integer $vistId   
     * @return                object Array of DomesticFactor records
     */
    public function getPatientRecord($vistId)
    {        
        $queryResult = DB::table($this->table)
            ->select( 'ppmh_id','ppmh_type_id', 'ppmh_value','resource_type', 'ip_address') 
            ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
            ->where('visit_id',$vistId);
               
        $queryResult = $queryResult->get()
            ->map(function($queryRecord){
            $queryRecord->ppmh_id = $this->securityLibObj->encrypt($queryRecord->ppmh_id);
            return $queryRecord;
        });
        return $queryResult;
    }

    /**
    * @DateOfCreation        27 June 2018
    * @ShortDescription      This function is responsible to update Domestic Factor Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function updateLaboratoryTest($requestData,$whereData)
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
    * @ShortDescription      This function is responsible to multiple add Domestic Factor Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function addLaboratoryTest($insertData)
    {
        $response = $this->dbBatchInsert($this->table, $insertData);
        if($response){
            return true;
        }
        return false;
    }

    /**
    * @DateOfCreation        01 March 2021
    * @ShortDescription      This function is responsible to multiple add Domestic Factor Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function addDetails($insertData)
    {
        $response = $this->dbBatchInsert($this->table, $insertData);
        if($response){
            return true;
        }
        return false;
    }
    
    /**
    * @DateOfCreation        01 March 2021
    * @ShortDescription      This function is responsible to update Domestic Factor Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function updateDetails($requestData,$whereData)
    {
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        if($response){
            return true;
        }
        return false;
    }
}
