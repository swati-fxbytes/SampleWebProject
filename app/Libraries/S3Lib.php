<?php
namespace App\Libraries;
use Illuminate\Http\Request;
use File;
use Storage;
use Config;
/**
 * S3Lib Class
 *
 * @package                Safe Health
 * @subpackage             S3Lib
 * @category               Library
 * @DateOfCreation         06 Apr 2018
 * @ShortDescription       This class is responsible for all type of file manipulation functions
                           like Upload file, Copy file, Rename file, Move file etc.
 */
class S3Lib {

    // @var String $fileprefix
    // This protected member contains random 3 digit string
    /**
     * Create a new library instance.
     *
     * @return void
     */
    public function __construct()
    {
    }


    public function isFileExist($filepath){
        return Storage::disk('s3')->exists($filepath);
    }
   /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to upload file on S3 bucket.
    * @param                 String $filename
                             String $destinationPath
                             String $visibility (public or private)
    * @return                Array  ( With status of operation and message)
    */
    public function putObject($filename, $destinationPath, $visibility)
    {
        $result = false;
        $thumbUploadStatus = [];
        $upload = Storage::disk('s3')->put($destinationPath, $filename);
        // Check File uploaded or not
        if($upload){
            $this->setVisibility($destinationPath.'/'.$filename,$visibility);
            return ['code'=> '1000','message' => __('messages.1013')];
        }else{
            return ['code'=> '5000','message' => __('messages.5014')];
        }
    }


     /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to get the object of file from S3 bucket.
    * @param                 Object of the file
    * @return                Array  ( With status of operation and message)
    */
    public function getObject($filepath)
    {
        $exists = $this->isFileExist($filepath);
        if($exists){
            $contents = Storage::disk('s3')->get($filepath);
            return ['code' => '1000', 'message' => __('messages.1011'),'fileObject' => $contents];
        }else{
            return ['code' => '5000','message' => __('messages.5004'),'fileObject' => ''];
        }
    }
    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to rename file on S3 Bucket.
    * @param                 String $filePath
                             String $oldname
                             String $newname
    * @return               Array  ( With status of file and message )
    */
    public function renameFile($oldname, $newname, $filePath)
    {
       try{
            $check = Storage::disk('s3')->move($filePath.'/'.$oldname,$filePath.'/'.$newname);
            if($check){
                return ['code' => '1000', 'message' => __('messages.1002')];
            }else{
                return ['code' => '5000', 'message' => __('messages.5002')];
            }
        }catch (\Exception $e) {
                return ['code' => '5000', 'message' => $e->getMessage()];
            }
    }

    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to copy one file to any other
                             location according to the parameter on S3 Bucket.
    * @param                 String $sourcePath
                             String $destinationPath
    * @return                Array  ( With status of operation and message)
    */
    public function copyFile($sourcePath, $destinationPath)
    {
        if($this->isFileExist($sourcePath)){
            try{
                $check = Storage::disk('s3')->copy($sourcePath, $destinationPath);
                if($check){
                    return ['code' => '1000', 'message' => __('messages.1003')];
                }else{
                    return ['code' => '5000', 'message' => __('messages.5003')];
                }
            }catch (\Exception $e){
                return ['code' => '3000', 'message' => $e->getMessage()];
            }
        }else{
            return ['code' => '5000', 'message' => __('messages.5002')];
        }
    }

     /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to move one file to any other
                             location according to the parameter on S3 Bucket.
    * @param                 String $sourcePath
                             String $destinationPath
    * @return                Array  ( With status of operation and message)
    */
    public function moveFile($sourcePath, $destinationPath)
    {
        try{
            $check = Storage::disk('s3')->move($sourcePath, $destinationPath);
            if($check){
                return ['code' => '1000', 'message' => __('messages.1004')];
            }else{
                return ['code' => '5000', 'message' => __('messages.5005')];
            }
        }catch (\Exception $e) {
                return ['code' => '5000', 'message' => $e->getMessage()];
            }
    }

    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription       This function is responsible to download the file according to the
                              path given from s3 bucket.
    * @param                 String $sourcePath
                             String $outputName
    * @return                Array  ( With status of operation and message)
    */
    public function downloadFile($sourcePath, $outputName)
    {
       try{
            return Storage::disk('s3')->download($sourcePath, $outputName);
        }catch (\Exception $e){
            return ['code' => '3000', 'message' => $e->getMessage()];
        }
    }

    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription       This function is responsible to Delete file From S3 bucket
    * @param                 String $filepath
    * @return                Array  ( With status of operation and message)
    */
    public function deleteFile($filePath)
    {
        try{
            return Storage::disk('s3')->delete($filePath);
        }catch (\Exception $e){
            return ['code' => '3000', 'message' => $e->getMessage()];
        }
    }

    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to Get the visibility of file
    * @param                 String $filePath
    * @return                Array  ( With status of operation and message)
    */
    public function getVisibility($filePath)
    {
        try{
            $visibility = Storage::disk('s3')->getVisibility($filepath);
            return ['code' => '1000','message' =>__('messages.1009'),'visibility' => $visibility];
        }catch(\Exception $e){
            return ['code' => '3000', 'message' => $e->getMessage(),'visibility' => ''];
        }
    }

    /**
    * @DateOfCreation        06 Apr 2018
    * @ShortDescription      This function is responsible to set the visibility of file on S3
    * @param                 String $filepath
                             String $visibility
    * @return                Array  ( With status of operation and message)
    */
    public function setVisibility($filePath, $visibility = 'public')
    {
        try{
            $visibilityStatus = Storage::disk('s3')->setVisibility($filepath, $visibility);
            return ['code' => '1000', 'message' => __('messages.1010')];
        }catch (\Exception $e){
            return ['code' => '3000', 'message' => $e->getMessage()];
        }
    }

    /**
     * @DateOfCreation        21 Jan 2018
     * @ShortDescription      This function is responsible to trasnfer all data from folder to S3
     * @return                upload status
     */
    public function folderToS3Bucket($directory, $S3filePath){
        $images = glob($directory . "*.png");
        foreach($images as $image)
        {   
            $imageName = [];
            $S3Path = '';
            $imageName = explode('/', $image);
            $S3Path = $S3filePath.end($imageName);
            $upload  = $this->putObject(file_get_contents($image), $S3Path, 'public');
            if($upload['code'] != Config::get('restresponsecode.SUCCESS')){
                return ['code' => '5000', 'message' => __('messages.5034'),'result' => 'Got Error in'.$image];
            }
        }
        return ['code' => '1000','message' =>__('messages.1032'),'result' => __('messages.1032')];
    }

}
