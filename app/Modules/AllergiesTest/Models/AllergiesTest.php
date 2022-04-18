<?php

namespace App\Modules\AllergiesTest\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use Config;

class AllergiesTest extends Model {

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
    protected $table = 'allergies_test';
    protected $tableAllergies = 'allergies';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id','pat_id','visit_id','parent_allergy_id', 'sub_parent_allergy_id', 'allergy_id', 'start_month', 'end_month', 'percutaneous_start_month_w', 'percutaneous_start_month_f', 'percutaneous_end_month_w', 'percutaneous_end_month_f'];
    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */

    protected $primaryKey = 'allergy_test_id';

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the Allergies test list
    * @param                 String $user_id
    * @return                Array of status and message
    */
    public function getAllergiesTestList($requestData)
    {
        // GRID LISTING QUERY
        $selectData  =  [$this->table.'.allergy_test_id','parent.allergy_name as parent_allergy_name','parent.allergy_id as parent_allergy_id', 'sub_parent.allergy_name as sub_parent_allergy_name', 'sub_parent.allergy_id as sub_parent_allergy_id', 'allergy.allergy_name', 'allergy.allergy_id', $this->table.'.start_month', $this->table.'.end_month', $this->table.'.percutaneous_start_month_w', $this->table.'.percutaneous_start_month_f', $this->table.'.percutaneous_end_month_w', $this->table.'.percutaneous_end_month_f', 'allergy.local_name'];
        $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $whereData   =  array(
                            $this->table.'.user_id' =>  $requestData['user_id'],
                            $this->table.'.visit_id' => $requestData['visit_id'],
                            $this->table.'.pat_id'   => $requestData['pat_id'],
                            $this->table.'.is_deleted' => Config::get('constants.IS_DELETED_NO')
                        );
        DB::enableQueryLog();
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
                                ->where('allergy.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('sub_parent.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('parent.allergy_name', 'ilike', "%".$value['value']."%")
                                ->orWhere('allergy.local_name', 'ilike', "%".$value['value']."%")
                                ->orWhere(DB::raw('CAST(percutaneous_end_month_w AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(percutaneous_end_month_f AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(percutaneous_start_month_w AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(percutaneous_start_month_f AS TEXT)'), 'like', '%'.$value['value'].'%');
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
                    ->map(function ($allerigestest) {
                        $allerigestest->allergy_test_id = $this->securityLibObj->encrypt($allerigestest->allergy_test_id);
                        $allerigestest->parent_allergy_id = $this->securityLibObj->encrypt($allerigestest->parent_allergy_id);
                        $allerigestest->sub_parent_allergy_id = $this->securityLibObj->encrypt($allerigestest->sub_parent_allergy_id);
                        $allerigestest->allergy_id = $this->securityLibObj->encrypt($allerigestest->allergy_id);
                        return $allerigestest;
                    });
            return $queryResult;
    }

     /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to get the Allergies test by id
    * @param                 String $allergy_test_id
    * @return                Array of allergies_test
    */
    public function getAllergiesTestById($allergy_test_id)
    {
    	$selectData  =  [$this->table.'.parent_allergy_id',$this->table.'.sub_parent_allergy_id',$this->table.'.allergy_id',$this->table.'.start_month', $this->table.'.end_month', $this->table.'.percutaneous_start_month_w', $this->table.'.percutaneous_start_month_f', $this->table.'.percutaneous_end_month_w', $this->table.'.percutaneous_end_month_f'];

        $whereData = array(
                        'allergy_test_id' =>  $allergy_test_id,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    );
        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to update Allergies test data
    * @param                 String $allergy_test_id
                             Array  $requestData
    * @return                Array of status and message
    */
    public function doUpdateAllergiesTest($requestData)
    {
    	$requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['parent_allergy_id']);
        $requestData['sub_parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['sub_parent_allergy_id']);
        $requestData['allergy_id'] = $this->securityLibObj->decrypt($requestData['allergy_id']);
        $allergy_test_id = $this->securityLibObj->decrypt($requestData['allergy_test_id']);
        unset($requestData['allergy_test_id']);

        $whereData =  array('allergy_test_id' => $allergy_test_id);
        $queryResult =  $this->dbUpdate($this->table, $requestData, $whereData);

        if($queryResult){
            $allergyTestUpdateData = $this->getAllergiesTestById($allergy_test_id);
            $allergyTestUpdateData->allergy_test_id = $this->securityLibObj->encrypt($allergy_test_id);
            return $allergyTestUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to insert allergies Test data
    * @param                 Array $requestData
    * @return                Array of status and message
    */
    public function doInsertAllergiesTest($requestData)
    {
        $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $requestData['parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['parent_allergy_id']);
        $requestData['sub_parent_allergy_id'] = $this->securityLibObj->decrypt($requestData['sub_parent_allergy_id']);
        $requestData['allergy_id'] = $this->securityLibObj->decrypt($requestData['allergy_id']);
        unset($requestData['allergy_test_id']);
        $queryResult = $this->dbInsert($this->table, $requestData);

        if($queryResult){
            $allergyTestUpdateData = $this->getAllergiesTestById(DB::getPdo()->lastInsertId());
            // Encrypt the ID
            $allergyTestUpdateData->allergy_test_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $allergyTestUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to Delete Allergies data
    * @param                 Array $allergy_test_id
    * @return                Array of status and message
    */
    public function doDeleteAllergiesTest($allergy_test_id)
    {
        $updateData = array(
                        'is_deleted' => Config::get('constants.IS_DELETED_YES')
                        );
        $whereData = array( 'allergy_test_id' => $allergy_test_id );

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