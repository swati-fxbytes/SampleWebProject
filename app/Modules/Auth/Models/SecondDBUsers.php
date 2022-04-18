<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\Hash;

use DB,Config;
use App\Libraries\DateTimeLib;
use App\Libraries\SecurityLib;

class SecondDBUsers extends Model
{
    use Encryptable;

    protected $table      = 'users';
    protected $tokenTable = 'access_tokens';
    protected $secretTable = 'user_secret';
    protected $primaryKey = 'user_id';
    //protected $keyType    = 'uuid';

    //public $incrementing = false;
    //public $timestamps   = true;

    protected $connection = 'masterdb';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init DateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init security library object
        $this->securityLibObj = new SecurityLib();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'user_type', 'user_gender', 'user_firstname', 'user_lastname', 'user_country_code', 'user_mobile', 'user_email', 'user_password', 'user_status', 'user_is_mob_verified', 'user_is_email_verified', 'ip_address', 'resource_type', 'created_by', 'updated_by', 'is_deleted', 'created_at', 'updated_at', 'remember_token', 'user_adhaar_number', 'tenant_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'string'
    ];

    /**
    * @DateOfCreation        18 May 2018
    * @ShortDescription      This function is responsible for creating new user in 2nd DB
    * @param                 Array $data This contains full user input data
    * @return                True/False
    */
    public function createUser($data)
    {
        // @var Boolean $response
        // This variable contains insert query response
        $response = false;
        // @var Array $inserData
        // This Array contains insert data for users
        $insertData = array(
            'user_firstname'    => $data['user_firstname'],
            'user_lastname'     => $data['user_lastname'],
            'user_mobile'       => $data['user_mobile'],
            'user_country_code' => $data['user_country_code'],
            'user_gender'       => $data['user_gender'],
            'user_status'       => $data['user_status'],
            'user_password'     => Hash::make($data['user_password']),
            'user_type'         => $data['user_type'],
            'tenant_id'         => $data['tenant_id'],
            'resource_type'     => $data['resource_type'],
            'ip_address'        => $data['ip_address']
        );

        if(array_key_exists('user_email', $data))
            $insertData['user_email'] = $data['user_email'];
        if(array_key_exists('user_adhaar_number', $data))
            $insertData['user_adhaar_number'] = $data['user_adhaar_number'];

        // Prepair insert query
        $response = $this->secondDBInsert($this->table, $insertData);
        if($response){
            $id = DB::connection('masterdb')->getPdo()->lastInsertId();
            return $id;
        }else{
            return $response;
        }
    }

    /**
     * The function for get result from that should be cast to native types.
     *
     * @var array
     */
    public function registerUser($data){
        $user = DB::table($this->table)->insertGetId($data, 'user_id');
        return $user;
    }

    public function getUsers(){
        $users = DB::table($this->table)->get();
        return $users;
    }

    public function getUser($email){
        $user = DB::table($this->table)->where('email', $email)->first();
        return $user;
    }

    public function insertToken($data){
        $token = DB::connection('masterdb')->table($this->tokenTable)->insertGetId($data, 'access_token_id');
        return $token;
    }

    public function updateToken($whereData, $updateData){
        $token = DB::connection('masterdb')->table($this->tokenTable)->where([
                    'user_id'      => $whereData['user_id'],
                    'access_token' => $whereData['access_token'],
                    'device_type'  => $whereData['device_type']
        ])->update(array(
                'access_token' => $updateData['access_token'],
                'expires_at'   => $updateData['expires_at'],
            ));
        return true;
    }

    public function getToken($whereData){
        $token = DB::connection('masterdb')->table($this->tokenTable)->where([
                    'user_id'      => $whereData['user_id'],
                    'access_token' => $whereData['access_token'],
                    'device_type'  => $whereData['device_type']
        ])->exists();
        return $token;
    }

    public function deleteToken($whereData){
        $token = DB::table($this->tokenTable)->where([
                    'user_id'      => $whereData['user_id'],
                    'access_token' => $whereData['access_token'],
                    'device_type'  => $whereData['device_type']
        ])->delete();
        return $token;
    }

    public function getDBToken($whereData){
        $token = DB::connection('masterdb')->table($this->tokenTable)->where([
                    'access_token' => $whereData['access_token']
        ])->exists();
        return $token;
    }

    public function getUserByToken($barearToken)
    {
        //first get token from header and remove barear
        $removeBarear = $this->securityLibObj->removeBarear($barearToken);

        //get actual token
        $actualToken  = $this->securityLibObj->hex2BinConversion($removeBarear);

        //get client id using actual token
        $userTokenDetails = DB::connection('masterdb')->table($this->tokenTable)->where('access_token', $actualToken)->first();

        //get user details using ID
        $userDetail = DB::connection('masterdb')->table($this->table)->where('user_id', $userTokenDetails->user_id)->first();

        return $userDetail;
    }

    public function checkTenant($whereData)
    {
        $tenant = DB::connection('masterdb')
                    ->table($this->secretTable)
                    ->where([
                        'client_id'      => $whereData['client_id'],
                        'client_secret' => $whereData['client_secret']
                    ])
                    ->first();
        return $tenant;
    }
}