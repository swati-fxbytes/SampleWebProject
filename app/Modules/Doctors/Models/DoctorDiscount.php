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
 * DoctorDiscount Class
 *
 * @package                ILD INDIA
 * @subpackage             DoctorDiscount
 * @category               Model
 * @DateOfCreation         10 August 2021
 * @ShortDescription       This is model which need to perform the oprations related to DoctorDiscount info
 */
class DoctorDiscount extends Model
{
    use Encryptable;

    // @var string $table
    // This protected member contains table name
    protected $table                = 'doctor_discount';
    protected $tablePaymentsHistory = 'payments_history';

    // @var string $primaryKey
    // This protected member contains primary key
    protected $primaryKey = 'doctor_discount_id';

    protected $encryptable = [];

    protected $fillable = ['doctor_id', 'coupon_name', 'coupon_image','discount_type', 'discount_start_date', 'discount_end_date', 'discount_usage', 'discount_availability', 'ip_address', 'resource_type', 'is_deleted'];

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
     * @DateOfCreation        10 August 2021
     * @ShortDescription      This function is responsible for insert doctor discount
     * @param                 Array $data
     * @return                Collection
     */
    public function insertDoctorDiscount($data)
    {
        // This variable contains insert query response
        $response   = false;
        $resultData = [];

        // Prepair insert query
        $response = $this->dbInsert($this->table, $data);

        if ($response)
        {
            $doctorDiscountData = $this->getDoctorDiscountById(DB::getPdo()->lastInsertId());

            // Encrypt the ID
            $doctorDiscountData->doctor_discount_id = $this->securityLibObj->encrypt(DB::getPdo()->lastInsertId());
            return $doctorDiscountData;
        }else
        {
            return $response;
        }
    }

    /**
     * @DateOfCreation        11 August 2021
     * @ShortDescription      This function is responsible for update doctor discount
     * @param                 Array $data
     * @return                Collection
     */
    public function updateDoctorDiscount($data)
    {
        // This variable contains update query response
        $response   = false;
        $resultData = [];

        $data['doctor_discount_id'] = $this->securityLibObj->decrypt($data['doctor_discount_id']);
        $whereData = ['doctor_discount_id' => $data['doctor_discount_id']];

        // Prepair update query
        $response = $this->dbUpdate($this->table, $data, $whereData);

        if ($response)
        {
            $doctorDiscountData = $this->getDoctorDiscountById($data['doctor_discount_id']);

            // Encrypt the ID
            $doctorDiscountData->doctor_discount_id = $this->securityLibObj->encrypt($data['doctor_discount_id']);
            return $doctorDiscountData;
        }else
        {
            return $response;
        }
    }

    /**
    * @DateOfCreation        10 August 2021
    * @ShortDescription      This function is responsible to get the doctor_discount by id
    * @param                 String $doctor_discount_id
    * @return                Array of doctor_discount
    */
    public function getDoctorDiscountById($doctor_discount_id)
    {
        $selectData  =  ['coupon_name', 'coupon_image', 'discount_type', 'discount_start_date', 'discount_end_date', 'discount_usage', 'discount_availability'];

        $whereData = array('doctor_discount_id' =>  $doctor_discount_id);

        $queryResult = $this->dbSelect($this->table, $selectData, $whereData);
        return $queryResult;
    }

    /**
     * @DateOfCreation        11 August 2021
     * @ShortDescription      This function is to get the Primary key name
     * @return                integer primary key name id
     */
    public function getTablePrimaryIdColumn()
    {
        return $this->primaryKey;
    }

    /**
     * @DateOfCreation        11 August 2021
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

    /**
    * @DateOfCreation        11 August 2021
    * @ShortDescription      This function is responsible to Delete Discount data
    * @param                 Array $doctor_discount_id
    * @return                Array of status and message
    */
    public function deleteDoctorDiscount($doctor_discount_id)
    {
        $updateData = array('is_deleted' => Config::get('constants.IS_DELETED_YES'));
        $whereData  = array( 'doctor_discount_id' => $doctor_discount_id );

        $queryResult =  $this->dbUpdate($this->table, $updateData, $whereData);
        if($queryResult)
        {
            return true;
        }
        return false;
    }

    /**
     * @DateOfCreation        31 August 2021
     * @ShortDescription      This function is responsible to check the discount exist in the DB or not
     * @param                 integer $primaryId
     * @return                boolean
     */
    public function isDiscountExist($discountCode, $doctorId){
        $discountExist = DB::table($this->table)
                        ->where('doctor_id', '=', $doctorId)
                        ->where('coupon_name', '=', $discountCode)
                        ->get();
        return $discountExist;
    }

    /**
     * @DateOfCreation        31 August 2021
     * @ShortDescription      This function is responsible to check the discount count which is used by a user
     * @param                 integer $primaryId
     * @return                boolean
     */
    public function userDiscountCount($discountId, $userId){
        $discountCount = DB::table($this->tablePaymentsHistory)
                        ->where('pat_id', '=', $userId)
                        ->where('discount_id', '=', $discountId)
                        ->where('user_payment_status', '=', Config::get('constants.USER_PAYMENT_SUCCESS'))
                        ->count();
        return $discountCount;
    }
}