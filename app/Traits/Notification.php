<?php 
namespace App\Traits;
use Response;
use Config;
use Illuminate\Http\Request;
use Edujugon\PushNotification\PushNotification;
use App\Jobs\ProcessNotification;

/**
 * Notification
 *
 * @package                Laravel Base Setup
 * @subpackage             Notification
 * @category               Trait
 * @DateOfCreation         18 May 2021
 * @ShortDescription       This trait is responsible to manage push notification related operations.
 **/
trait Notification
{
	/**
    * @DateOfCreation        18 May 2021
    * @ShortDescription      This will process notification in queue
    * @param                 Integer $code
    *                        Array $data
    *                        String $error - Default "Unknown Error"
    *                        String $msg
    *                        Integer $http_status - Default 3000   
    * @return                Response (Submit attributes)
    */
    public function sendNotification(Array $data){
    	if( !empty($data) ){
            dispatch(new ProcessNotification($data));
        }
        return true;
    }


    /**
    * @DateOfCreation        18 May 2021
    * @ShortDescription      This function is used for admin and responsible for generating the response for each array 
    * @param                 Integer $code
    *                        Array $data
    *                        String $error - Default "Unknown Error"
    *                        String $msg
    *                        Integer $http_status - Default 3000   
    * @return                Response (Submit attributes)
    */
    public function notify($data){
    	$android_tokens=[];
	    $ios_tokens=[];
	    foreach ($data['tokens'] as $value) {
	        if( $value['plateform'] == Config::get('constants.DEVICE_ANDROID'))
	            $android_tokens[] = $value['token'];
	        if( $value['plateform'] == Config::get('constants.DEVICE_IOS'))
	            $ios_tokens[] = $value['token'];
	    }
	    
    	//iOS notification code start
	        if(!empty($ios_tokens)){
	            $push_ios = new PushNotification('apn');
	            $push_ios->setMessage([
	                        'aps' => [
	                            'alert' => [
	                                'title' => $data['title'],
	                                'body' => $data['body']
	                            ],
	                            'sound' => 'default',
	                            'badge' => 1
	                        ],
	                        'extraPayLoad' => $data['extra']
	                    ])
	                    ->setDevicesToken($ios_tokens);
	            $push_data_ios = $push_ios->send()->getFeedback();
	        }
	    //iOS notification code end
	    
	    //Android notification code start
	        if(!empty($android_tokens)){
	            $push_android = new PushNotification('fcm');
	            $notifArray = [
				                'title' => $data['title'],
				                'body' => $data['body'],
				                'sound' => 'default'
			                ];
			    $push_android->setMessage([
	                            'notification' => $notifArray,
	                            'data' => $data['extra']
	                            ])
	                         ->setApiKey(Config::get('pushnotification.fcm.apiKey'))  
	                         ->setDevicesToken($android_tokens)
	                         ->setConfig(['dry_run' => false]);
	            $push_data_android = $push_android->send()->getFeedback();
	        }
	    // Android notification code end
    }
}