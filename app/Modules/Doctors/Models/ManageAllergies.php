<?php

namespace App\Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use Config;

/**
 * ManageAllergies Class
 *
 * @package                ILD INDIA
 * @subpackage             ManageAllergies
 * @category               Model
 * @DateOfCreation         13 June 2018
 * @ShortDescription       This is model which need to perform the options related to
                           ManageAllergies info

 */
class ManageAllergies extends Model {

    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'allergies';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'allergy_id';

    protected $encryptable = [];

    protected $fillable = ['allergy_name', 'parent_id'];

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init exception library object
        $this->utilityLibObj = new UtilityLib();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();
    }

    /**
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get all Patients Records by user_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getAllergiesList($requestData)
    {
        $selectData = [$this->table.'.allergy_name',$this->table.'.allergy_id',$this->table.'.parent_id','parent.allergy_name as parent_allergy'];

        $whereData = array(
                        $this->table.'.is_deleted'      => Config::get('constants.IS_DELETED_NO'),
                    );
        $listQuery = DB::table($this->table)
                        ->leftjoin($this->table.' as parent', 'parent.allergy_id', '=', $this->table.'.parent_id')
                        ->select($selectData)
                        ->where($whereData)
                        ->where($this->table.'.parent_id', '!=', Config::get('dataconstants.PARENT_ALLERGY_PARENT_ID'));

        if(!empty($requestData['filtered'])){
            foreach ($requestData['filtered'] as $key => $value) {

                if(!empty($value['value'])){
                    $listQuery = $listQuery->where(function ($listQuery) use ($value){
                                    $listQuery->orWhere($this->table.'.allergy_name', 'ilike', "%".$value['value']."%");
                                    $listQuery->orWhere('parent.allergy_name', 'ilike', "%".$value['value']."%");
                                });
                }
            }
        }

        if(!empty($requestData['sorted'])){
            foreach ($requestData['sorted'] as $sortKey => $sortValue) {
                $orderBy = $sortValue['desc'] ? 'desc' : 'asc';
                $listQuery->orderBy($sortValue['id'], $orderBy);
            }
        }else{
            $listQuery->orderBy($this->table.'.allergy_name', 'asc');
        }

        if($requestData['page'] > 0){
            $offset = $requestData['page'] * $requestData['pageSize'];
        }else{
            $offset = 0;
        }

        $list  = $listQuery
                                ->offset($offset)
                                ->get()
                                ->map(function($listData){
                                    $listData->allergy_id = $this->securityLibObj->encrypt($listData->allergy_id);
                                    $listData->parent_id = $this->securityLibObj->encrypt($listData->parent_id);
                                    return $listData;
                                });
        return $list;
    }

    /**
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get all Patients Records by user_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getParentAllergiesList()
    {
        $list  =  DB::table($this->table)
                    ->select($this->table.'.allergy_name',$this->table.'.allergy_id')
                    ->where($this->table.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                    ->where($this->table.'.parent_id', Config::get('dataconstants.PARENT_ALLERGY_PARENT_ID'))
                    ->get()
                    ->map(function($listData){
                                    $listData->allergy_id = $this->securityLibObj->encrypt($listData->allergy_id);
                                    return $listData;
                                });
        return $list;
    }

    /**
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get all Patients Records by user_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getSubParentAllergiesByParentId($parentId)
    {
        $list  =  DB::table($this->table)
                    ->select($this->table.'.allergy_name',$this->table.'.allergy_id',$this->table.'.allergy_id')
                    ->where($this->table.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                    ->where($this->table.'.parent_id', $parentId)
                    ->whereIn('allergy_id', DB::table($this->table)->pluck('parent_id'))
                    ->get()
                    ->map(function($listData){
                                    $listData->allergy_id = $this->securityLibObj->encrypt($listData->allergy_id);
                                    return $listData;
                                });

        return $list;
    }

    /**
     * @DateOfCreation        19 June 2018
     * @ShortDescription      This function is responsible for get all Patients Records by user_id and list fillter and sorting apply for selected column
     * @param                 Array $data This contains full Patient user input data
     * @return                True/False
     */
    public function getAllergiesByParentId($parentId)
    {
        $list['result']  =  DB::table($this->table)
                    ->select($this->table.'.allergy_name',$this->table.'.allergy_id',$this->table.'.allergy_id')
                    ->where($this->table.'.is_deleted', Config::get('constants.IS_DELETED_NO'))
                    ->where($this->table.'.parent_id', $parentId)
                    ->whereNotIn('allergy_id', DB::table($this->table)->pluck('parent_id'))
                    ->get()
                    ->map(function($listData){
                                    $listData->allergy_id = $this->securityLibObj->encrypt($listData->allergy_id);
                                    return $listData;
                                });

        return $list;
    }

    /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to save record for the Patient Medication History
     * @param                 array $requestData
     * @return                integer Patient Medication History id
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to save record for the Patient Medication History
     * @param                 array $requestData
     * @return                integer Patient Medication History id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible to check the Visit  wefId exist in the system or not
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function isPrimaryIdExist($primaryId){
        $primaryIdExist = DB::table($this->table)
                        ->where($this->primaryKey, $primaryId)
                        ->exists();
        return $primaryIdExist;
    }
    /**
     * @DateOfCreation        11 June 2018
     * @ShortDescription      This function is responsible to Delete medicines data
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function doDeleteRequest($primaryId)
    {
        $queryResult = $this->dbUpdate( $this->table,
                                        [ 'is_deleted' => Config::get('constants.IS_DELETED_YES') ],
                                        [$this->primaryKey => $primaryId]
                                    );

        if($queryResult){
            return true;
        }
        return false;
    }

     /**
     * @DateOfCreation        18 June 2018
     * @ShortDescription      This function is responsible to save record for the medicine
     * @param                 array $requestData
     * @return                integer auto increment id
     */
    public function addRequest($inserData)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;

        // @var Array $inserData
        // This Array contains insert data for Patient
        $inserData = $this->utilityLibObj->fillterArrayKey($inserData, $this->fillable);

        // Prepair insert query
        $response = $this->dbInsert($this->table, $inserData);
        if($response){
            $id = DB::getPdo()->lastInsertId();
            return $id;

        }else{
            return $response;
        }
    }

    /**
    * @DateOfCreation        27 June 2018
    * @ShortDescription      This function is responsible to update medicine Record
    * @param                 Array  $requestData
    * @return                Array of status and message
    */
    public function updateRequest($requestData,$whereData)
    {
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        if($response){
            return true;
        }
        return false;
    }

    public function isParentAllergyIdExists($parentAllergyId){
        $parentAllergyIdExist = DB::table($this->table)
                        ->where($this->primaryKey, $parentAllergyId)
                        ->where('parent_id', Config::get('dataconstants.PARENT_ALLERGY_PARENT_ID'))
                        ->exists();
        return $parentAllergyIdExist;
    }

    public function isChildAllergyNameExists($allergyName, $parentAllergyId){
        $childNameExist = DB::table($this->table)
                        ->where('allergy_name','ilike',$allergyName)
                        ->where('parent_id', $parentAllergyId)
                        ->exists();
        return $childNameExist;
    }

   /**
    * @DateOfCreation        03 Sept 2018
    * @ShortDescription      This function is responsible to get the patient group id by name
    * @param                 String $pat_group_name
    * @return                Array of service
    */
    public function getParentAllergyIdByName($allergyName)
    {
        $queryResult = DB::table($this->table)
                        ->select('allergy_id')
                        ->where('allergy_name','ilike',$allergyName)
                        ->where('parent_id', Config::get('dataconstants.PARENT_ALLERGY_PARENT_ID'))
                        ->first();
        return $queryResult;
    }
}
