<?php
namespace App\Libraries;
use Illuminate\Http\Request;
use File;
use Storage;
use Config;
use Uuid;

/**
 * PushNotificationLib Class
 *
 * @package                RxHealth
 * @subpackage             PushNotificationLib
 * @category               Library
 * @DateOfCreation         21 Jan 2019
 * @ShortDescription       This class is responsible for all type of Push notification that we need to send on
                            IOS and Android
 */
class PushNotificationLib {
    

    /**
     * Create a new library instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
    * @DateOfCreation        21 Jan 2019
    * @ShortDescription      This function is responsible for send push notification to user IOS device
    * @param                 String $title
                             String key
                             Array  $body
                             Array  $deviceList
    * @return                Response True/False
    */
    public function sendIosPushNotification($key, $title, $body, $deviceList, $extraKey = []){
        $push = new PushNotification('apn');
        $push->setMessage([
            'fcm' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body
                ],
                'sound' => 'default',
                'badge' => 1

            ],
            'extraPayLoad' => [
                'extra_key' => $extraKey
            ]
        ])->setDevicesToken($deviceList);
        
        $output = $push->send()->getFeedback();
        
        if($output->success){
            return true;
        } else {
            return false;
        }
    }


    /**
    * @DateOfCreation        21 Jan 2019
    * @ShortDescription      This function is responsible for send push notification to user ANDROID device
    * @param                 String $title
                             String key
                             Array  $body
                             Array  $deviceList
    * @return                Response True/False
    */
    public function sendAndroidPushNotification($key, $title, $body, $deviceList, $extraKey = []){
        $push = new PushNotification('apn');
        $push->setMessage([
            'fcm' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body
                ],
                'sound' => 'default',
                'badge' => 1

            ],
            'extraPayLoad' => [
                'extra_key' => $extraKey
            ]
        ])
            ->setDevicesToken($deviceList);
        $output = $push->send()->getFeedback();
        if($output->success){
            return true;
        } else {
            return false;
        }
    }
}

