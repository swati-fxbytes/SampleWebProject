<?php

namespace App\Modules\AllergiesTest\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use Config;

class Immunotherapy extends Model {

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

    }

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $table = 'immunotherapy';
    protected $tableAllergies = 'allergies';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id','pat_id','visit_id','parent_allergy_id', 'sub_parent_allergy_id', 'allergy_id', 'quantity'];
    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */

    protected $primaryKey = 'immunotherapy_id';

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the Immunotherapy list
    * @param                 String $user_id
    * @return                Array of status and message
    */
    public function getImmunotherapyList($requestData)
    {
        // GRID LISTING QUERY
        $selectData  =  [$this->table.'.immunotherapy_id','parent.allergy_name as parent_allergy_name','parent.allergy_id as parent_allergy_id', 'sub_parent.allergy_name as sub_parent_allergy_name', 'sub_parent.allergy_id as sub_parent_allergy_id', 'allergy.allergy_name', 'allergy.local_name', 'allergy.allergy_id', $this->table.'.quantity'];
        $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $whereData   =  array(
                            $this->table.'.user_id' =>  $requestData['user_id'],
                            $this->table.'.visit_id' => $requestData['visit_id'],
                            $this->table.'.pat_id'   => $requestData['pat_id'],
                            $this->table.'.is_deleted' => Config::get('constants.IS_DELETED_NO')
                        );
        // DB::enableQueryLog();
        $query =  DB::table($this->table)
                    ->select($selectData)
                    ->leftJoin($this->tableAllergies.' as parent',function($join){
                                $join->on('parent.allergy_id', '=', $this->table.'.parent_allergy_id')
                                ->where($this->table.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                    ->leftJoin($this->tableAllergies.' as sub_parent',function($join){
                                $join->on('sub_parent.allergy_id', '=', $this->table.'.sub_parent_allergy_id')
                                ->where($this->table.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                    ->leftJoin($this->tableAllergies.' as allergy',function($join){
                                $join->on('allergy.allergy_id', '=', $this->table.'.allergy_id')
                                ->where($this->table.'.is_deleted', '=', Config::get('constants.IS_DELETED_NO'), 'and');
                            })
                    ->where($whereData);

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            foreach ($requestData['filtered'] as $key => $value) {
                $query = $query->where(function ($query) use ($value){
                                $query
                                ->where(DB::raw('CAST(quantity AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere('allergy.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('sub_parent.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('parent.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('allergy.local_name', 'ilike', "%".$value['value']."%");
                            });

            }
        }

        /* Condition for Sorting the result */
        if(!empty($requestData['sorted'])){
            foreach ($requestData['sorted'] as $key => $value) {
                $orderBy = $value['desc'] ? 'desc' : 'asc';
                $query = $query->orderBy($value['id'], $orderBy);
            }
        }
        if($requestData['page'] > 0){
            $offset = $requestData['page']*$requestData['pageSize'];
        }else{
            $offset = 0;
        }
        $queryResult['pages'] = ceil($query->count()/$requestData['pageSize']);
        $queryResult['result'] = $query
                    ->offset($offset)
                    ->limit($requestData['pageSize'])
                    ->get()
                    ->map(function ($immunotherapy) {
                        $immunotherapy->immunotherapy_id = $this->securityLibObj->encrypt($immunotherapy->immunotherapy_id);
                        $immunotherapy->parent_allergy_id = $this->securityLibObj->encrypt($immunotherapy->parent_allergy_id);
                        $immunotherapy->sub_parent_allergy_id = $this->securityLibObj->encrypt($immunotherapy->sub_parent_allergy_id);
                        $immunotherapy->allergy_id = $this->securityLibObj->encrypt($immunotherapy->allergy_id);
                        return $immunotherapy;
                    });
            return $queryResult;
    }

     /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to get the Immunotherapy by id
    * @param                 String $immunotherapy_id
    * @return                Array of immunotherapy
    */
    public function getImmunotherapyById($immunotherapy_id)
    {
    	$selectData  =  ['parent_allergy_id', 'sub_parent_allergy_id', 'allergy_id', 'quantity'];

        $whereData = array(
                        'immunotherapy_id' =>  $immunotherapy_id,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    );
        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to update Immunotherapy data
    * @param                 String $immunotherapy_id
                             Array  $requestData
    * @return                Array of status and message
    */
    public function doUpdateImmunotherapy($requestData)
    {
    	$requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['parent_allergy_id']);
        $requestData['sub_parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['sub_parent_allergy_id']);
        $requestData['allergy_id'] = $this->securityLibObj->decrypt($requestData['allergy_id']);
        $immunotherapy_id = $this->securityLibObj->decrypt($requestData['immunotherapy_id']);
        unset($requestData['immunotherapy_id']);

        $whereData =  array('immunotherapy_id' => $immunotherapy_id);
        $queryResult =  $this->dbUpdate($this->table, $requestData, $whereData);

        if($queryResult){
            $allergyTestUpdateData = $this->getImmunotherapyById($immunotherapy_id);
            $allergyTestUpdateData->immunotherapy_id = $this->securityLibObj->encrypt($immunotherapy_id);
            return $allergyTestUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to insert Immunotherapy data
    * @param                 Array $requestData
    * @return                Array of status and message
    */
    public function doInsertImmunotherapy($requestData)
    {
    	$requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['parent_allergy_id']);
        $requestData['sub_parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['sub_parent_allergy_id']);
        $requestData['allergy_id'] = $this->securityLibObj->decrypt($requestData['allergy_id']);
        unset($requestData['immunotherapy_id']);
        $queryResult = $this->dbInsert($this->table, $requestData);

        if($queryResult){
            $immunotherapyUpdateData = $this->getImmunotherapyById(DB::getPdo()->lastInsertId());
            // Encrypt the ID
            $immunotherapyUpdateData->immunotherapy_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $immunotherapyUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to Delete Immunotherapy data
    * @param                 Array $immunotherapy_id
    * @return                Array of status and message
    */
    public function doDeleteImmunotherapy($immunotherapy_id)
    {
        $updateData = array(
                        'is_deleted' => Config::get('constants.IS_DELETED_YES')
                        );
        $whereData = array( 'immunotherapy_id' => $immunotherapy_id );

        $queryResult =  $this->dbUpdate($this->table, $updateData, $whereData);
        if($queryResult){
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        24 Jan 2019
     * @ShortDescription      This function is to get the Primary key name
     * @return                integer primary key name id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        24 Jan 2019
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
}