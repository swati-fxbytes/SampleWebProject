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
 * ClinicalNotes
 *
 * @package                Safe Health
 * @subpackage             ClinicalNotes
 * @category               Model
 * @DateOfCreation         21 Aug 2018
 * @ShortDescription       This Model to handle database operation of Clinical Notes
 **/

class ClinicalNotes extends Model {

    use HasApiTokens, Encryptable;

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
    protected $table          = 'clinical_notes';
    
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 
                            'pat_id',
                            'visit_id',
                            'clinical_notes',
                            'ip_address',
                            'resource_type',
                            'is_deleted',
                        ];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = 'clinical_notes_id';

    /**
     * @DateOfCreation        21 Aug 2018
     * @ShortDescription      This function is responsible to get Clinical Notes list
     * @param                 
     * @return                object Array of all medicines
     */
    public function getClinicalNotesListData($requestData) 
    {   
        if(!array_key_exists('notes_type', $requestData)){
            $requestData['notes_type'] = Config::get('constants.NOTES_TYPE_CLINICAL');
        }
        $queryResult = DB::table( $this->table )
                        ->select( 
                                'clinical_notes_id',
                                'clinical_notes'
                            ) 
                        ->where([ 'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                  'pat_id' => $this->securityLibObj->decrypt($requestData['pat_id']),
                                  'visit_id' => $this->securityLibObj->decrypt($requestData['visit_id']),
                                  'notes_type' => $requestData['notes_type']
                        ])
                        ->first();
               
        if(!empty($queryResult)){
            $queryResult->clinical_notes_id = $this->securityLibObj->encrypt($queryResult->clinical_notes_id);
            $queryResult->clinical_notes    = !empty($queryResult->clinical_notes) ? json_decode($queryResult->clinical_notes) : [];
            return $queryResult;
        }else{
            return [];
        }
    }

    /**
     * @DateOfCreation        06 August 2021
     * @ShortDescription      This function is responsible to get Clinical Notes list
     * @param                 
     * @return                object Array of all medicines
     */
    public function getAllClinicalNotesListData($requestData) 
    {   
        return $queryResult = DB::table( $this->table )
                        ->select( 
                                'clinical_notes_id',
                                'notes_type',
                                'clinical_notes'
                            ) 
                        ->where([ 'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                                  'pat_id' => $this->securityLibObj->decrypt($requestData['pat_id']),
                                  'visit_id' => $this->securityLibObj->decrypt($requestData['visit_id'])
                        ])
                        ->get()
                        ->map(function($list){
                            $list->clinical_notes_id = $this->securityLibObj->encrypt($list->clinical_notes_id);
                            $list->clinical_notes    = !empty($list->clinical_notes) ? json_decode($list->clinical_notes) : [];
                            return $list;
                        });
    }

    /**
    * @DateOfCreation        21 Aug 2018
    * @ShortDescription      This function is responsible to update Clinical Notes Record
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function saveClinicalNotesData($requestData)
    {
        $response  = $this->dbInsert($this->table, $requestData);

        if($response){
            $id = DB::getPdo()->lastInsertId();
            return $id;
            
        }else{
            return $response;
        }
    }

    /**
    * @DateOfCreation        14 July 2018
    * @ShortDescription      This function is responsible to update Clinical Notes data
    * @param                 Array  $requestData   
    * @return                Array of status and message
    */
    public function updateClinicalNotesData($requestData, $clinicalNotesId)
    {
        $whereData = [ 'clinical_notes_id' => $clinicalNotesId ];
        
        // Prepare update query
        $response = $this->dbUpdate($this->table, $requestData, $whereData);
        
        if($response){
            return true;
        }
        return false;
    }
}
