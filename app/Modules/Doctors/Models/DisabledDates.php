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
 * DisabledDates Class
 *
 * @package                ILD INDIA
 * @subpackage             DisabledDates
 * @category               Model
 * @DateOfCreation         18 June 2018
 * @ShortDescription       This is model which need to perform the options related to DisabledDates info
 */
class DisabledDates extends Model
{
    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table = 'doctor_disabled_dates';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'disabled_date_id';

    protected $encryptable = [];

    protected $fillable = ['disabled_date_id', 'user_id', 'from_date', 'to_date', 'ip_address', 'resource_type'];

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
     * @DateOfCreation        18 June 2019
     * @ShortDescription      This function is responsible for get all disabled dates operations
     * @param                 Array $data
     * @return                Collection
     */
    public function getDatesList($requestData)
    {
        $selectData = ['disabled_date_id', 'user_id', 'from_date', 'to_date'];

        $whereData = [ 'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                       'user_id' => $requestData['user_id']
                    ];

        $listQuery = DB::table($this->table)
                        ->select($selectData)
                        ->where($whereData);

        if (!empty($requestData['filtered'])) {
            foreach ($requestData['filtered'] as $key => $value) {
                if (!empty($value['value'])) {
                    $listQuery = $listQuery->where(function ($listQuery) use ($value) {
                        $listQuery
                                    ->where('from_date', 'ilike', "%".$value['value']."%")
                                    ->orWhere('to_date', 'ilike', "%".$value['value']."%");
                    });
                }
            }
        }

        if (!empty($requestData['sorted'])) {
            foreach ($requestData['sorted'] as $sortKey => $sortValue) {
                $orderBy = $sortValue['desc'] ? 'desc' : 'asc';
                $listQuery->orderBy($sortValue['id'], $orderBy);
            }
        }

        if ($requestData['page'] > 0) {
            $offset = $requestData['page'] * $requestData['pageSize'];
        } else {
            $offset = 0;
        }

        $list['pages']   = ceil($listQuery->count()/$requestData['pageSize']);

        $list['result']  = $listQuery
                                ->offset($offset)
                                ->limit($requestData['pageSize'])
                                ->get()
                                ->map(function ($listData) {
                                    $listData->disabled_date_id = $this->securityLibObj->encrypt($listData->disabled_date_id);
                                    $listData->user_id = $this->securityLibObj->encrypt($listData->user_id);
                                    return $listData;
                                });
        return $list;
    }

    /**
     * @DateOfCreation        04 Oct 2018
     * @ShortDescription      This function is responsible to Delete Payment Type data
     * @param                 integer $wefId
     * @return                Array of status and message
     */
    public function doDeleteRequest($primaryId)
    {
        $queryResult = $this->dbUpdate(
            $this->table,
            [ 'is_deleted' => Config::get('constants.IS_DELETED_YES') ],
            [$this->primaryKey => $primaryId]
                                    );

        if ($queryResult) {
            return true;
        }
        return false;
    }

    /**
    * @DateOfCreation        04 Oct 2018
    * @ShortDescription      This function is responsible to update Payment Type Record
    * @param                 Array  $requestData
    * @return                Array of status and message
    */
    public function updateRequest($requestData, $whereData)
    {
        $updateData = $this->utilityLibObj->fillterArrayKey($requestData, $this->fillable);
        $response = $this->dbUpdate($this->table, $updateData, $whereData);
        if ($response) {
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        04 Oct 2018
     * @ShortDescription      This function is responsible to save record for the Payment Type
     * @param                 array $requestData
     * @return                integer auto increment id
     */
    public function saveDisabledDate($insertData)
    {
        // This variable contains insert query response
        $response = false;
        unset($insertData['disabled_date_id']);

        // @var Array $insertData
        // This Array contains insert data for Patient
        $insertData = $this->utilityLibObj->fillterArrayKey($insertData, $this->fillable);

        // Prepair insert query
        $response = $this->dbInsert($this->table, $insertData);
        if ($response) {
            $id = DB::getPdo()->lastInsertId();
            return $id;
        } else {
            return $response;
        }
    }

    public function getUserDisabledDates($requestData)
    {
        $selectData = [
            DB::raw("CONCAT (generate_series(from_date, to_date, '1 day'::interval)) AS disabled_dates")
        ];

        $whereData = [ 'is_deleted' => Config::get('constants.IS_DELETED_NO'),
                       'user_id' => $requestData['user_id']
                    ];

        $dataQuery = DB::table($this->table)
                        ->select($selectData)
                        ->where($whereData);

        $result = $dataQuery
                    ->groupBy('disabled_dates')
                    ->orderBy('disabled_dates')
                    ->get()
                    ->map(function ($listData) {
                        $listData->disabled_dates = date('Y-m-d', strtotime($listData->disabled_dates));
                        return $listData;
                    });
        return $result;
    }

    /**
     * @DateOfCreation        16 June 2019
     * @ShortDescription      This function is responsible to check the data exist or not
     * @param                 integer $primaryId
     * @return                Array of status and message
     */
    public function isRecordExist($primaryId)
    {
        $recordExist = DB::table($this->table)
                        ->where($this->primaryKey, $primaryId)
                        ->exists();
        return $recordExist;
    }
}
