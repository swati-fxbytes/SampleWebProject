<?php 
namespace App\Traits;
use Illuminate\Support\Facades\Auth;
use App\Traits\RestApi;
use Config;
use Illuminate\Http\Request;
use Twilio\Rest\Client;

/**
 * SessionTrait
 *
 * @package                Safe Health
 * @subpackage             SessionTrait
 * @category               Trait
 * @DateOfCreation         23 April 2018
 * @ShortDescription       This trait is responsible to Check he use is valid or not
 **/
trait SmsTrait
{
     /**
    * @DateOfCreation        23 Apr 2018
    * @ShortDescription      This function is responsible to get the check user  
    * @return                Array with status and user intance
    */
     protected function sendMsg($data){
        $body = view($data['smsTemplate'], $data['viewData']);
        $account_sid = Config::get("constants.TWILIO_SID");
        $auth_token = Config::get("constants.TWILIO_AUTH_TOKEN");
        $twilio_number = Config::get("constants.TWILIO_NUMBER");
        $client = new Client($account_sid, $auth_token);
        $client->messages->create($data['receipent'], ['from' => $twilio_number, 'body' => $body]);
        return true;
     }
} 	
