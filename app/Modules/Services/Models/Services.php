<?php

namespace App\Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use App\Libraries\SecurityLib;
use Illuminate\Support\Facades\DB;
use App\Traits\Encryptable;
use Config;

/**
 * Services Class
 *
 * @package                Services
 * @subpackage             Doctor Services
 * @category               Model
 * @DateOfCreation         7 june 2018
 * @ShortDescription       This is model which need to perform the options related to 
                           services table
 */
class Services extends Model 
{
	use Encryptable;
    /**
     * The attributes that should be override default primary key.
     *
     * @var string 
     */
    protected $primaryKey = 'srv_id';

    /**
     * The attributes that should be override default table name.
     *
     * @var string 
     */
    protected $table = 'services';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

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
     * Create doctor membership list with regarding details
     *
     * @param array $data membership data
     * @return int doctor member id if inserted otherwise false
     */
    public function servicesList($requestData)
    {
       	$selectData  =  ['srv_id', 'srv_name', 'srv_cost', 'srv_duration','srv_unit'];
        $whereData   =  [
                        'user_id'=> $requestData['user_id'],
                        'is_deleted'=>  Config::get('constants.IS_DELETED_NO'),
                        ];
        $query =  DB::table($this->table)
                    ->select($selectData)
                    ->where($whereData);

        /* Condition for Filtering the result */
        if(!empty($requestData['filtered'])){
            foreach ($requestData['filtered'] as $key => $value) {
                $query = $query->where(function ($query) use ($value){
                                $query
                                ->where('srv_name', 'ilike', "%".$value['value']."%")
                                ->orWhere(DB::raw('CAST(srv_cost AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(srv_duration AS TEXT)'), 'like', '%'.$value['value'].'%')
                                ->orWhere(DB::raw('CAST(srv_unit AS TEXT)'), 'like', '%'.$value['value'].'%');
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
                    ->map(function ($service) {
                            $service->srv_id = $this->securityLibObj->encrypt($service->srv_id);
                            return $service;
                        });
        return $queryResult;
    }

    /**
     * Create doctor service with regarding details
     *
     * @param array $data service data
     * @return Array doctor member if inserted otherwise false
     */

    public function createService($requestData=array())
    {
        unset($requestData['srv_id']);
		$queryResult = $this->dbInsert($this->table, $requestData);
        if($queryResult){
            $serviceData = $this->getServiceById(DB::getPdo()->lastInsertId());
            // Encrypt the ID
            $serviceData->srv_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $serviceData;
        }
        return false;
    }

   /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the service by id
    * @param                 String $srv_id   
    * @return                Array of service
    */
    public function getServiceById($srv_id)
    {   
    	$selectData = ['srv_id', 'srv_name', 'srv_cost', 'srv_duration','srv_unit'];
        $whereData = array(
                        'srv_id' =>  $srv_id, 
                        'is_deleted' => Config::get('constants.IS_DELETED_NO')
                    );
        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }

    /**
     * Update doctor service with regarding details
     *
     * @param array $data service data
     * @return boolean true if updated otherwise false
     */
    public function updateService($requestData=array())
    {
        $srv_id = $this->securityLibObj->decrypt($requestData['srv_id']);
        unset($requestData['srv_id']);
        $whereData =  array('srv_id' => $srv_id);
        $queryResult =  $this->dbUpdate($this->table, $requestData, $whereData);
        if($queryResult){
            $serviceUpdateData = $this->getServiceById($srv_id);
            $serviceUpdateData->srv_id = $this->securityLibObj->encrypt($srv_id);
            return $serviceUpdateData;
        }
        return false;
    }

    /**
     * delete doctor service with regarding id
     *
     * @param int $id service id
     * @return boolean particular doctor service detail delete or not
     */
    public function deleteService($srv_id='')
    {
        $updateData = array('is_deleted' => Config::get('constants.IS_DELETED_YES'));
        $whereData = array('srv_id' => $srv_id );
        $queryResult =  $this->dbUpdate($this->table, $updateData, $whereData);
        if($queryResult){
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        08 Sept 2018
     * @ShortDescription      This function is to get the Primary key name
     * @return                integer primary key name id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }
    
    /**
     * @DateOfCreation        08 Sept 2018
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
