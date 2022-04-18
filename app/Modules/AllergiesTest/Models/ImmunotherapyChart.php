<?php

namespace App\Modules\AllergiesTest\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use App\Libraries\SecurityLib;
use Config;

class ImmunotherapyChart extends Model {

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
    protected $table = 'immunotherapy_chart';
    protected $tableAllergies = 'allergies';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id','pat_id','visit_id','dose_conc_of_antigen', 'dose_date', 'dose', 'type'];
    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */

    protected $primaryKey = 'immunotherapy_chart_id';

    /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the ImmunotherapyChart list
    * @param                 String $user_id
    * @return                Array of status and message
    */
    public function getImmunotherapyChartList($requestData)
    {
        // GRID LISTING QUERY
        $selectData  =  [$this->table.'.immunotherapy_chart_id',$this->table.'.dose_conc_of_antigen', $this->table.'.dose_date', $this->table.'.dose', $this->table.'.type'];
        $requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $whereData   =  array(
                            $this->table.'.user_id' =>  $requestData['user_id'],
                            $this->table.'.visit_id' => $requestData['visit_id'],
                            $this->table.'.pat_id'   => $requestData['pat_id'],
                            $this->table.'.is_deleted' => Config::get('constants.IS_DELETED_NO')
                        );
        $query =  DB::table($this->table)
                    ->select($selectData)
                    ->where($whereData);

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            foreach ($requestData['filtered'] as $key => $value) {
                $query = $query->where(function ($query) use ($value){
                                $query
                                ->where(DB::raw('CAST(dose_conc_of_antigen AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(dose AS TEXT)'), 'ilike', "%".$value['value']."%")
                                ->orWhere(DB::raw('CAST(dose_date AS TEXT)'), 'ilike', "%".$value['value']."%");
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
                        $immunotherapy->immunotherapy_chart_id = $this->securityLibObj->encrypt($immunotherapy->immunotherapy_chart_id);
                        return $immunotherapy;
                    });
            return $queryResult;
    }

     /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to get the ImmunotherapyChart by id
    * @param                 String $immunotherapy_chart_id
    * @return                Array of immunotherapy
    */
    public function getImmunotherapyChartById($immunotherapy_chart_id)
    {
    	$selectData  =  [$this->table.'.dose_conc_of_antigen', $this->table.'.dose_date', $this->table.'.dose', $this->table.'.type'];

        $whereData = array(
                        'immunotherapy_chart_id' =>  $immunotherapy_chart_id,
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    );
        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to update ImmunotherapyChart data
    * @param                 String $immunotherapy_chart_id
                             Array  $requestData
    * @return                Array of status and message
    */
    public function doUpdateImmunotherapyChart($requestData)
    {
    	$requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        $immunotherapy_chart_id = $this->securityLibObj->decrypt($requestData['immunotherapy_chart_id']);
        unset($requestData['immunotherapy_chart_id']);

        $whereData =  array('immunotherapy_chart_id' => $immunotherapy_chart_id);
        $queryResult =  $this->dbUpdate($this->table, $requestData, $whereData);

        if($queryResult){
            $allergyTestUpdateData = $this->getImmunotherapyChartById($immunotherapy_chart_id);
            $allergyTestUpdateData->immunotherapy_chart_id = $this->securityLibObj->encrypt($immunotherapy_chart_id);
            return $allergyTestUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to insert ImmunotherapyChart data
    * @param                 Array $requestData
    * @return                Array of status and message
    */
    public function doInsertImmunotherapyChart($requestData)
    {
    	$requestData['visit_id'] = $this->securityLibObj->decrypt($requestData['visit_id']);
        $requestData['pat_id'] = $this->securityLibObj->decrypt($requestData['pat_id']);
        unset($requestData['immunotherapy_chart_id']);
        $queryResult = $this->dbInsert($this->table, $requestData);

        if($queryResult){
            $immunotherapyUpdateData = $this->getImmunotherapyChartById(DB::getPdo()->lastInsertId());
            // Encrypt the ID
            $immunotherapyUpdateData->immunotherapy_chart_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $immunotherapyUpdateData;
        }
        return false;
    }

    /**
    * @DateOfCreation        23 Jan 2019
    * @ShortDescription      This function is responsible to Delete Immunotherapy Chart data
    * @param                 Array $immunotherapy_chart_id
    * @return                Array of status and message
    */
    public function doDeleteImmunotherapyChart($immunotherapy_chart_id)
    {
        $updateData = array(
                        'is_deleted' => Config::get('constants.IS_DELETED_YES')
                        );
        $whereData = array( 'immunotherapy_chart_id' => $immunotherapy_chart_id );

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