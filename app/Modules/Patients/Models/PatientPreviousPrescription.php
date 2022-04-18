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
class PatientPreviousPrescription extends Model {

    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'patient_previous_prescription';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'pre_prescription_id';

    protected $encryptable = [];

    protected $fillable = [
        'user_id',
        'user_type',
        'doc_media_file',
        'doctor_name',
        'prescription_date',
        'doc_media_status',
        'ip_address',
        'resource_type',
        'created_by',
        'updated_by',
        'is_deleted'
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();
    }

    /**
     * @DateOfCreation        11 May 2021
     * @ShortDescription      This function is to get the Primary key name
     * @return                integer primary key name id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        11 May 2021
     * @ShortDescription      This function is responsible to check the primary value exist in the system or not
     * @param                 integer $primaryId
     * @return                boolean
     */
    public function isPrimaryIdExist($primaryId){
        $primaryIdExist = DB::table($this->table)
                        ->where($this->primaryKey, $primaryId)
                        ->exists();
        return $primaryIdExist;
    }
    
    public function insertPrescription($data)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // Prepair insert query
        $response = $this->dbInsert($this->table, $data);

        return DB::getPdo()->lastInsertId();
    }

    public function getDetails($primaryId){
        return PatientPreviousPrescription::select('doc_media_file')
                                            ->where('pre_prescription_id', $primaryId)
                                            ->first();
    }

    /**
     * @DateOfCreation        14 May 2018
     * @ShortDescription      This function is responsible for deleting media in DB
     * @param                 Array $data This contains full user input data
     * @return                True/False
     */
    public function deleteMedia($pre_prescription_id)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // @var Array $updateData
        // This Array contains update data for blog
        $updateData = array(
            'doc_media_status' => Config::get('constants.PATIENT_PREV_PRESCRIPTION_INACTIVE'),
            'is_deleted' => Config::get('constants.IS_DELETED_YES')
        );
        $whereData = array(
                        'pre_prescription_id' => $pre_prescription_id
                    );

        // Prepair update query
        $response = $this->dbUpdate($this->table, $updateData, $whereData);

        return $response;
    }

    /**
     * @DateOfCreation        14 May 2018
     * @ShortDescription      This function is responsible for deleting media in DB
     * @param                 Array $data This contains full user input data
     * @return                True/False
     */
    public function getAllPreviousPrescription($requestData) {
        $queryResult['pages'] = Config::get('constants.DATA_LIMIT'); //donot use its only for mobile structure
        $query = "SELECT 
                    pp.*,
                    (SELECT string_agg(media_name, ',') FROM patient_previous_prescription_media as pppm where pppm.media_Id = pp.pre_prescription_id AND is_deleted=".Config::get('constants.IS_DELETED_NO').") AS media
                    FROM ".$this->table." AS pp";
        if(array_key_exists('dr_id', $requestData)){
            $query .= " JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id,created_by from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(user_id int,
                    created_by int
                    )) AS users ON users.user_id= pp.user_id AND users.created_by=".$requestData['dr_id'];
        }
        $query .= " WHERE pp.doc_media_status=".Config::get('constants.PATIENT_PREV_PRESCRIPTION_ACTIVE')."
                    AND pp.user_id = ".$requestData['patientId']."
                    ORDER BY pp.created_at DESC";
        $result = DB::select(DB::raw($query));
        $queryResult['result'] = [];
        foreach($result as $list){
            $list->pre_prescription_id = $this->securityLibObj->encrypt($list->pre_prescription_id);
            $list->user_id = $this->securityLibObj->encrypt($list->user_id);
            $list->created_by = $this->securityLibObj->encrypt($list->created_by);
            $list->updated_by = $this->securityLibObj->encrypt($list->updated_by);
            if(!empty($list->media)){
                $tempMedia = explode(",", $list->media);
                $list->media = [];
                foreach ($tempMedia as $md) {
                    $doc_type = substr(strrchr($md, '.'),1);
                    $list->media[] = ["media_name" => $this->securityLibObj->encrypt($md), "doc_type"=> $doc_type];
                }
            }else{
                $list->media = [];
            }
            $list->doc_type = substr(strrchr($list->doc_media_file, '.'),1);
            $list->doc_media_file = $this->securityLibObj->encrypt($list->doc_media_file);
            $queryResult['result'][] = $list;
        }
        return $queryResult;
    }

    /**
     * @DateOfCreation        04 June 2018
     * @ShortDescription      This function is responsible for deleting media in DB
     * @param                 Array $data This contains full user input data
     * @return                True/False
     */
    public function getPreviousPrescriptionDetails($requestData) {
        $query =  DB::table($this->table." AS pp")
                    ->select(
                        'pp.*', 
                        DB::raw("(SELECT string_agg(media_name, ',') FROM patient_previous_prescription_media as pppm where pppm.media_Id = pp.pre_prescription_id AND is_deleted=".Config::get('constants.IS_DELETED_NO').") AS media")
                    )
                    ->where('pre_prescription_id', $requestData['pre_prescription_id'])
                    ->get()
                    ->map(function ($list) {
                        $list->pre_prescription_id = $this->securityLibObj->encrypt($list->pre_prescription_id);
                        $list->user_id = $this->securityLibObj->encrypt($list->user_id);
                        $list->created_by = $this->securityLibObj->encrypt($list->created_by);
                        $list->updated_by = $this->securityLibObj->encrypt($list->updated_by);
                        if(!empty($list->media)){
                            $tempMedia = explode(",", $list->media);
                            $list->media = [];
                            foreach ($tempMedia as $md) {
                                $doc_type = substr(strrchr($md, '.'),1);
                                $list->media[] = ["media_name" => $this->securityLibObj->encrypt($md), "doc_type"=> $doc_type];
                            }
                        }else{
                            $list->media = [];
                        }
                        return $list;
                    });
        return $query;
    }
}
