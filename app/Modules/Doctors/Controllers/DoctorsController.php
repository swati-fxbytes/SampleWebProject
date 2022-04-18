<?php

namespace App\Modules\Doctors\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Doctors\Models\Doctors as Doctors;
use Auth;
use Session;
use App\Traits\RestApi;
use App\Libraries\SecurityLib;
use App\Libraries\FileLib;
use App\Libraries\S3Lib;
use App\Libraries\PdfLib;
use Config;
use File;
use Response;
use stdClass;
use Uuid;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Modules\Search\Models\Search;
use App\Modules\Doctors\Models\Reports as Reports;
use App\Modules\Visits\Models\Symptoms;
use App\Modules\Visits\Models\Diagnosis;
use App\Modules\Patients\Models\Patients;
use App\Modules\Setup\Models\StaticDataConfig;
use App\Modules\Doctors\Models\DoctorDiscount;

/**
 * DoctorsController
 *
 * @package                SafeHealth
 * @subpackage             DoctorsController
 * @category               Controller
 * @DateOfCreation         10 May 2018
 * @ShortDescription       Interaction with doctors info
 */
class DoctorsController extends Controller
{
    use RestApi;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->http_codes             = $this->http_status_codes();
        $this->doctorObj              = new Doctors();
        $this->securityLibObj         = new SecurityLib();
        $this->fileLibObj             = new FileLib();
        $this->pdfLibObj              = new PdfLib();
        $this->searchModelObj         = new Search();
        $this->reportsModelObj        = new Reports();
        $this->symptomsModelObj       = new Symptoms();
        $this->diagnosisModelObj      = new Diagnosis();
        $this->patientModelObj        = new Patients();
        $this->staticDataModelObj     = new StaticDataConfig();
        $this->doctorDiscountModelObj = new DoctorDiscount();
        $this->s3LibObj               = new S3Lib();
    }

    /**
     * @DateOfCreation        10 May 2018
     * @ShortDescription      Get a validator for an incoming User request
     * @param                 \Illuminate\Http\Request  $request
     * @return                \Illuminate\Contracts\Validation\Validator
     */
    public function getPatientsList(){
        return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                Auth::user(),
                [],
                trans('Auth::messages.user_verified'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
     * @DateOfCreation        16 july 2018
     * @ShortDescription      Get a doctor profile detail
     * @param                 \Illuminate\Http\Request  $request
     * @return                \Illuminate\Contracts\Validation\Validator
     */
    public function doctorPublicProfile($slug, Request $request){
        $requestData = $this->getRequestData($request);
        $userInfo = $this->doctorObj->getDoctorPublicProfile($slug, $requestData);
        if($userInfo){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $userInfo,
                    [],
                    trans('Doctors::messages.doctors_profile_detail'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.doctors_profile_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        26 March 2021
     * @ShortDescription      Get a doctor profile detail
     * @param                 \Illuminate\Http\Request  $request
     * @return                \Illuminate\Contracts\Validation\Validator
     */
    public function getDoctorProfileById(Request $request){
        $requestData = $this->getRequestData($request);
        $dr_id = $this->securityLibObj->decrypt($requestData['dr_id']);
        $doctor = Doctors::where([ 'user_id' => $dr_id, 'is_deleted' => Config::get('constants.IS_DELETED_NO')])->first();
        if(!empty($doctor)){
            $userInfo = $this->doctorObj->getDoctorPublicProfile($doctor->doc_slug);
            if($userInfo){
                return $this->resultResponse(
                        Config::get('restresponsecode.SUCCESS'),
                        $userInfo,
                        [],
                        trans('Doctors::messages.doctors_profile_detail'),
                        $this->http_codes['HTTP_OK']
                    );
            }else{
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Doctors::messages.doctors_profile_error'),
                    $this->http_codes['HTTP_OK']
                );
            }
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.doctors_profile_error'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        10 july 2018
     * @ShortDescription      Get a doctor booking detail as per date
     * @param                 \Illuminate\Http\Request  $request
     * @return                \Illuminate\Contracts\Validation\Validator
     */
    public function doctorBookingDetail(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $userId = $this->securityLibObj->decrypt($requestData['userId']);
        $clinicId = $this->securityLibObj->decrypt($requestData['clinicId']);
        $slot = $requestData['slot'];
        $slotDate = $requestData['slotDate'];
        $apt_type = (isset($requestData['appointment_type']) && !empty($requestData['appointment_type'])) ? $requestData['appointment_type'] : Null;
        if($slot == ''){
            $inputDate = date('Y/m/d', strtotime($slotDate));
            $slotDate = $this->searchModelObj->nextAvailableSlot($inputDate, $clinicId);
        }
        if($slot == Config::get('constants.PREVIOUS_SLOT')){
          $slotDate = date('Y-m-d', strtotime($slotDate .' -1 day'));
        }

        if($slot == Config::get('constants.NEXT_SLOT')){
          $slotDate = date('Y-m-d',strtotime($slotDate .' +1 day'));
        }

        $bookingSlots = $this->doctorObj->doctorBookingDetail($userId, $clinicId, $slotDate, $apt_type);
        if($bookingSlots){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $bookingSlots,
                    [],
                    trans('Doctors::messages.doctors_clinic_detail'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            $doctorsObj = new stdClass;
            $doctorsObj->clinic_id = $this->securityLibObj->encrypt($clinicId);
            $doctorsObj->date = $slotDate;
            $doctorsObj->user_id = $this->securityLibObj->encrypt($userId);
            $inputDate = date('Y/m/d', strtotime($slotDate));
            $availableDate = $this->searchModelObj->nextAvailableSlot($inputDate, $clinicId);
            if($availableDate){
                $doctorsObj->nextDate = date('d M', strtotime($availableDate));
                $doctorsObj->nextDay   = date('D', strtotime($doctorsObj->nextDate));
            }else{
                $doctorsObj->nextDate = 'N/A';
                $doctorsObj->nextDay   = 'N/A';
            }
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                [$doctorsObj],
                [],
                trans('Doctors::messages.doctors_time_slot_not_found'),
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
     * @DateOfCreation        29 June 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getProfileImage($imageName)
    {
        $destination = Config::get('constants.DOCTOR_MEDIA_PATH');
        $storagPath = Config::get('constants.STORAGE_MEDIA_PATH');
        $imageName = $this->securityLibObj->decrypt($imageName);
        $imagePath =  $storagPath.$destination;
        $imageName = empty($imageName) ? Config::get('constants.DEFAULT_IMAGE_NAME'):$imageName;
        $path = storage_path($imagePath) . $imageName;
        if(!File::exists($path)){
            $path = public_path(Config::get('constants.DEFAULT_IMAGE_PATH'));
        }
        $file = File::get($path);
        $type = File::mimeType($path);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * @DateOfCreation        29 June 2018
     * @ShortDescription      This function is responsible to get the image path
     * @param                 String $imageName
     * @return                response
     */
    public function getPatientsReport(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $reportsData['year'] = $requestData['year'];
        $userId = $this->securityLibObj->decrypt($requestData['user_id']);
        if(!empty($requestData['month'])){
            $reportsData['month'] = $requestData['month'];
            $getReports = $this->reportsModelObj->getPatientsReportForMonth($reportsData, $userId);
        }else{
            $getReports = $this->reportsModelObj->getPatientsReportForYear($reportsData, $userId);
        }
        if($getReports){

            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $getReports,
                    [],
                    trans('Doctors::messages.report_fetch_success'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.report_fail'),
                $this->http_codes['HTTP_OK']
            );

        }
    }

    /**
     * @DateOfCreation        04 Oct 2018
     * @ShortDescription      This function is responsible to get required filter data
     * @param                 String $imageName
     * @return                response
     */
    public function getPatientsReportFilterData(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $response = [];
        if(isset($requestData['get-data']) && !empty($requestData['get-data'])){
            if(in_array('symptoms', $requestData['get-data'])){
                $selectData = ['symptom_id', 'symptom_name'];
                $whereData  = ['is_deleted' => Config::get('constants.IS_DELETED_NO')];
                $response['symptoms'] = $this->symptomsModelObj->getSymptomsList($selectData, $whereData);
            }

            if(in_array('disease', $requestData['get-data'])){
                $selectData = ['symptom_id', 'symptom_name'];
                $whereData  = ['is_deleted' => Config::get('constants.IS_DELETED_NO')];
                $response['disease'] = $this->diagnosisModelObj->patientDiagnosisOptionList(array());
            }
        }

        if($response){
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $response,
                    [],
                    trans('Doctors::messages.report_filter_data_fetched_success'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.report_filter_data_fetched_fail'),
                $this->http_codes['HTTP_OK']
            );

        }
    }

    /**
     * @DateOfCreation        09 Oct 2018
     * @ShortDescription      This function is responsible to get Patient Records with filter
     * @param                 array $request Data
     * @return                response
     */
    public function getPatientsListData(Request $request)
    {
        $requestData = $this->getRequestData($request);
        
        $requestData['user_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $getPatientList = $this->patientModelObj->getPatientListForReportFilter($requestData);

        if(isset($requestData['export_type']) && !empty($requestData['export_type'])){
            $this->exportToFile($getPatientList, $requestData['export_type']);
        } else{

            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $getPatientList,
                    [],
                    trans('Patients::messages.patient_list_data'),
                    $this->http_codes['HTTP_OK']
                );
        }
    }

    /**
     * @DateOfCreation        10 Oct 2018
     * @ShortDescription      This function is responsible to generate Reports
     * @param                 Array $data, string $type
     * @return                response
     */
    public function exportToFile($data, $type = 'csv'){

        $time = time();
        if($type == 'csv'){
            $header = [];
            $header[] = ['Created Date', 'Patient Name', 'Patient Code', 'Mobile Number', 'Email Address', 'Gender', 'Group', 'Age'];
            $dataArray = [];
            if(!empty($data)){
                foreach ($data['result'] as $dataKey => $dataValue) {

                    $dataArray[] = array(
                                    date('d/m/Y', strtotime($dataValue->created_at)),
                                    $dataValue->user_firstname,
                                    $dataValue->pat_code,
                                    $dataValue->user_mobile,
                                    $dataValue->user_email,
                                    !empty($dataValue->user_gender) ? $this->staticDataModelObj->getGenderNameById($dataValue->user_gender) : $dataValue->user_gender,
                                    $dataValue->pat_group_name,
                                    $dataValue->pat_age,
                                );
                }
            }
            $header = array_merge($header,$dataArray);

            header("Content-type: application/csv");
            header("Content-Disposition: attachment; filename=test.csv");
            $fp = fopen('php://output', 'w');

            foreach ($header as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);

            $storage_path = storage_path(Config::get('constants.STORAGE_MEDIA_PATH').Config::get('constants.PATIENT_REPORT_PATH'));
            if (! File::exists($storage_path)) {
                File::makeDirectory($storage_path, 0775, true);
            }
            $storage_path .= '/'.Config::get('constants.EXPORT_FILE_NAME').$time.Config::get('constants.EXPORT_CSV_FILE_EXTENSTION');
            $out = fopen($storage_path, 'w');
            foreach($header as $line)
            {
                fputcsv($out, $line);
            }
            fclose($out);
            $headers = ['Content-Type: text/csv'];
            return response()->download($storage_path, Config::get('constants.EXPORT_DEFAULT_FILE_NAME'), $headers)->deleteFileAfterSend(true);
        } else if($type == 'pdf'){
            $destination_path = storage_path(Config::get('constants.STORAGE_MEDIA_PATH').Config::get('constants.PATIENT_REPORT_PATH'));
            if (! File::exists($destination_path)) {
                File::makeDirectory($destination_path, 0775, true);
            }
            $storage_file_name = Config::get('constants.EXPORT_FILE_NAME').$time.Config::get('constants.EXPORT_PDF_FILE_EXTENSTION');

            $headers = ['Content-Type' => 'application/pdf'];
            $defaultFileName = Config::get('constants.EXPORT_DEFAULT_PDF_FILE_NAME');
            if(!empty($data)){
                foreach ($data['result'] as $dataKey => $dataValue) {

                    $dataArray[] = array(
                                    'created_at' => date('d/m/Y', strtotime($dataValue->created_at)),
                                    'user_firstname' => $dataValue->user_firstname,
                                    'pat_code' => $dataValue->pat_code,
                                    'user_mobile' => $dataValue->user_mobile,
                                    'user_email' => $dataValue->user_email,
                                    'user_gender' => !empty($dataValue->user_gender) ? $this->staticDataModelObj->getGenderNameById($dataValue->user_gender) : $dataValue->user_gender,
                                    'pat_group_name' => $dataValue->pat_group_name,
                                    'pat_age' => $dataValue->pat_age,
                                );
                }
            }
            $this->pdfLibObj->genrateAndSavePdf('Visits::patient_report_pdf', ['pdf_data' => $dataArray], $destination_path, $storage_file_name);

            echo $storage_path = url('/').'/api/report/'.$this->securityLibObj->encrypt($storage_file_name);
        }
    }

    /**
     * @DateOfCreation        10 Oct 2018
     * @ShortDescription      This function is responsible to open PDF Reports
     * @param                 String $fileName
     * @return                response
     */
    public function openPdfReport($fileName){
        $destination_path = storage_path(Config::get('constants.STORAGE_MEDIA_PATH').Config::get('constants.PATIENT_REPORT_PATH'));
        $filePath = $destination_path.$this->securityLibObj->decrypt($fileName);
        return response()->file($filePath);
    }

    /**
     * @DateOfCreation        29 June 2018
     * @ShortDescription      This function is responsible to show the income report
     * @param                 String $imageName
     * @return                response
     */
    public function getIncomeReport(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $reportsData['year'] = $requestData['year'];
        $userId = $this->securityLibObj->decrypt($requestData['user_id']);
        if(!empty($requestData['month'])){
            $reportsData['month'] = $requestData['month'];
            $getReports = $this->reportsModelObj->getIncomeReportForMonth($reportsData, $userId);
        }else{
            $getReports = $this->reportsModelObj->getIncomeReportForYear($reportsData, $userId);
        }
        if($getReports){

            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    $getReports,
                    [],
                    trans('Doctors::messages.report_fetch_success'),
                    $this->http_codes['HTTP_OK']
                );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.report_fail'),
                $this->http_codes['HTTP_OK']
            );

        }
    }

    /**
     * @DateOfCreation      10 August 2021
     * @ShortDescription    This function is responsible for creating new discount for doctor.
     * @param               $request - Request object for request data
     */
    public function saveDoctorDiscount(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['doctor_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $validate = $this->DiscountValidator($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $validate['errors'],
                    trans('Doctors::messages.discount_validation_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }

        /* In case if discount_type: 1, 2 then coupon_name, coupon_image will be blank
        * In case if discount_type: 3 then coupon_name, coupon_image have values, need to upload
        * coupon image on AWS-S3 or local
        */
        if($requestData['discount_type'] == 3)
        {
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');

            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION'))
            {
                $data = $requestData['coupon_image'];

                // To convert base64 to image file
                list($type, $data) = explode(';', $data);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                $randomString = Uuid::generate();
                $filename = $randomString.'.png';
                // To convert base64 to image file

                $filePath = Config::get('constants.DISCOUNT_COUPON_S3_PATH').$filename;
                $upload  = $this->s3LibObj->putObject($data, $filePath, 'public');

                if($upload['code'] = Config::get('restresponsecode.SUCCESS'))
                {
                    $requestData['coupon_name']  = $requestData['coupon_name'];
                    $requestData['coupon_image'] = $filename;
                }
            }
            else
            {
                $destination = storage_path('app/public/'.Config::get('constants.DISCOUNT_COUPON_IMG_PATH'));

                $this->fileLibObj->createDirectory($destination);
                $data = $this->fileLibObj->base64ToPng($requestData['coupon_image'], $destination, 'png');

                if(!empty($data['uploaded_file']))
                {
                    $requestData['coupon_name']  = $requestData['coupon_name'];
                    $requestData['coupon_image'] = $data['uploaded_file'];
                }
            }
        }
        else
        {
            $requestData['coupon_name']  = '';
            $requestData['coupon_image'] = '';
        }

        $requestData['resource_type'] = Config::get('constants.RESOURCE_TYPE_WEB');

        //convert dates into Y-m-d format
        $requestData['discount_start_date'] = date('Y-m-d', strtotime($requestData['discount_start_date']));
        $requestData['discount_end_date'] = date('Y-m-d', strtotime($requestData['discount_end_date']));

        $discountSave = $this->doctorDiscountModelObj->insertDoctorDiscount($requestData);

        // validate, is query executed successfully
        if($discountSave) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $discountSave,
                [],
                trans('Doctors::messages.discount_added'),
                '',
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                trans('Doctors::messages.discount_failed'),
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        10 August 2021
    * @ShortDescription      This function is responsible for validating discount data
    * @param                 Array $requestData This contains full request data
    * @return                errors
    */
    protected function DiscountValidator(array $requestData)
    {
        /* In case if discount_type: 1, 2 then coupon_name, coupon_image not required
        * In case if discount_type: 3 then coupon_name, coupon_image required
        */

        $error = false;
        $errors = [];
        
        switch($requestData['discount_type'])
        {
            case 1:
            case 2:
                $validationData = [
                    'discount_type'         => 'required',
                    'discount_start_date'   => 'required',
                    'discount_end_date'     => 'required',
                    'discount_usage'        => 'required|integer',
                    'discount_availability' => 'required|integer',
                ];
            break;

            case 3:
                $validationData = [
                    'coupon_name'           => 'required',
                    'coupon_image'          => 'required',
                    'discount_type'         => 'required',
                    'discount_start_date'   => 'required',
                    'discount_end_date'     => 'required',
                    'discount_usage'        => 'required|integer',
                    'discount_availability' => 'required|integer',
                ];
            break;
        }

        $validator = Validator::make($requestData, $validationData);
        if($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
     * @DateOfCreation      11 August 2021
     * @ShortDescription    This function is responsible for update existing discount for doctor.
     * @param               $request - Request object for request data
     */
    public function updateDoctorDiscount(Request $request)
    {
        $requestData = $this->getRequestData($request);
        $requestData['doctor_id'] = (in_array($request->user()->user_type, Config::get('constants.USER_TYPE_STAFF'))) ? $request->user()->created_by : $request->user()->user_id;

        $validate = $this->UpdateDiscountValidator($requestData);

        if($validate["error"]){
            return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $validate['errors'],
                    trans('Doctors::messages.discount_validation_failed'),
                    $this->http_codes['HTTP_OK']
                  );
        }

        /* In case if discount_type: 1, 2 then coupon_name, coupon_image will be blank
        * In case if discount_type: 3 then coupon_name, coupon_image have values, need to upload
        * coupon image on AWS-S3 or local
        */
        if($requestData['discount_type'] == 3)
        {
            $environment = Config::get('constants.ENVIRONMENT_CURRENT');

            if($environment == Config::get('constants.ENVIRONMENT_PRODUCTION'))
            {
                $data = $requestData['coupon_image'];

                // To convert base64 to image file
                list($type, $data) = explode(';', $data);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                $randomString = Uuid::generate();
                $filename = $randomString.'.png';
                // To convert base64 to image file

                $filePath = Config::get('constants.DISCOUNT_COUPON_S3_PATH').$filename;
                $upload  = $this->s3LibObj->putObject($data, $filePath, 'public');

                if($upload['code'] = Config::get('restresponsecode.SUCCESS'))
                {
                    $requestData['coupon_name']  = $requestData['coupon_name'];
                    $requestData['coupon_image'] = $filename;
                }
            }
            else
            {
                $destination = storage_path('app/public/'.Config::get('constants.DISCOUNT_COUPON_IMG_PATH'));

                $this->fileLibObj->createDirectory($destination);
                $data = $this->fileLibObj->base64ToPng($requestData['coupon_image'], $destination, 'png');

                if(!empty($data['uploaded_file']))
                {
                    $requestData['coupon_name']  = $requestData['coupon_name'];
                    $requestData['coupon_image'] = $data['uploaded_file'];
                }
            }
        }
        else
        {
            $requestData['coupon_name']  = '';
            $requestData['coupon_image'] = '';
        }

        $requestData['resource_type'] = Config::get('constants.RESOURCE_TYPE_WEB');

        //convert dates into Y-m-d format
        $requestData['discount_start_date'] = date('Y-m-d', strtotime($requestData['discount_start_date']));
        $requestData['discount_end_date'] = date('Y-m-d', strtotime($requestData['discount_end_date']));

        $discountUpdate = $this->doctorDiscountModelObj->updateDoctorDiscount($requestData);

        // validate, is query executed successfully
        if($discountUpdate) {
            return $this->resultResponse(
                Config::get('restresponsecode.SUCCESS'),
                $discountUpdate,
                [],
                trans('Doctors::messages.discount_updated'),
                '',
                $this->http_codes['HTTP_OK']
            );
        }else{
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                trans('Doctors::messages.discount_failed'),
                [],
                $this->http_codes['HTTP_OK']
            );
        }
    }

    /**
    * @DateOfCreation        11 August 2021
    * @ShortDescription      This function is responsible for validating discount data at update
    * @param                 Array $requestData This contains full request data
    * @return                errors
    */
    protected function UpdateDiscountValidator(array $requestData)
    {
        /* In case if discount_type: 1, 2 then coupon_name, coupon_image not required
        * In case if discount_type: 3 then coupon_name, coupon_image required
        */

        $error = false;
        $errors = [];
        
        switch($requestData['discount_type'])
        {
            case 1:
            case 2:
                $validationData = [
                    'doctor_discount_id'    => 'required',
                    'discount_type'         => 'required',
                    'discount_start_date'   => 'required',
                    'discount_end_date'     => 'required',
                    'discount_usage'        => 'required|integer',
                    'discount_availability' => 'required|integer',
                ];
            break;

            case 3:
                $validationData = [
                    'doctor_discount_id'    => 'required',
                    'coupon_name'           => 'required',
                    'coupon_image'          => 'required',
                    'discount_type'         => 'required',
                    'discount_start_date'   => 'required',
                    'discount_end_date'     => 'required',
                    'discount_usage'        => 'required|integer',
                    'discount_availability' => 'required|integer',
                ];
            break;
        }

        $validator = Validator::make($requestData, $validationData);
        if($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }
        return ["error" => $error,"errors" => $errors];
    }

    /**
    * @DateOfCreation        11 August 2021
    * @ShortDescription      This function is responsible for delete discount data
    * @param                 Array $doctor_discount_id
    * @return                Array of status and message
    */
    public function deleteDoctorDiscount(Request $request)
    {
        $requestData = $this->getRequestData($request);

        $primaryKey       = $this->doctorDiscountModelObj->getTablePrimaryIdColumn();
        $primaryId        = $this->securityLibObj->decrypt($requestData[$primaryKey]);
        $isPrimaryIdExist = $this->doctorDiscountModelObj->isPrimaryIdExist($primaryId);

        if(!$isPrimaryIdExist){
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [$primaryKey=> [trans('Doctors::messages.discount_not_exist')]],
                trans('Doctors::messages.discount_not_exist'),
                $this->http_codes['HTTP_OK']
            );
        }

        $deleteDiscountData = $this->doctorDiscountModelObj->deleteDoctorDiscount($primaryId);

        if($deleteDiscountData)
        {
            return $this->resultResponse(
                    Config::get('restresponsecode.SUCCESS'),
                    [],
                    [],
                    trans('Doctors::messages.discount_data_deleted'),
                    $this->http_codes['HTTP_OK']
                );
        }

        return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.discount_data_not_deleted'),
                $this->http_codes['HTTP_OK']
            );
    }

    /**
    * @DateOfCreation        31 August 2021
    * @ShortDescription      This function is responsible for check discount data
    * @param                 Array $discount_code, $doctor_id
    * @return                Array of status and message
    */
    public function checkDiscount(Request $request)
    {
        /* LOGIC : when any users wants to do booking for a doctor and then put a discount code from * app-web then we will check this code is available in DB for doctor if code not available
        * then send message otherwise check code is valid and code is valid then check how many
        * times users will use this. If it is less then usage then send DB row and discounted amount * in response
        */
        $requestData = $this->getRequestData($request);

        $discountCode = $requestData['discount_code'];
        $doctorId     = $this->securityLibObj->decrypt($requestData['doctor_id']);
        $userId       = $request->user()->user_id;

        //check discount is exist for doctor
        $isDiscountExist = $this->doctorDiscountModelObj->isDiscountExist($discountCode, $doctorId);

        if($isDiscountExist->isEmpty())
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.discount_not_available'),
                $this->http_codes['HTTP_OK']
            );
        }

        //check code is valid till date or not
        $currentDate       = Carbon::now()->format(Config::get('constants.DB_SAVE_DATE_FORMAT'));
        $discountStartDate = date('Y-m-d', strtotime($isDiscountExist[0]->discount_start_date));
        $discountEndDate   = date('Y-m-d', strtotime($isDiscountExist[0]->discount_end_date));

        if(($currentDate >= $discountStartDate) && ($currentDate <= $discountEndDate))
        {
            //specific user can not use a coupon code more then coupon usage
            $userDiscountCount = $this->doctorDiscountModelObj->userDiscountCount($isDiscountExist[0]->doctor_discount_id, $userId);

            if($userDiscountCount > $isDiscountExist[0]->discount_usage)
            {
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    [],
                    trans('Doctors::messages.discount_usage_exceed'),
                    $this->http_codes['HTTP_OK']
                );
            }
            else
            {
                return $this->resultResponse(
                    Config::get('restresponsecode.ERROR'),
                    [],
                    $isDiscountExist,
                    trans('Doctors::messages.discount_success'),
                    $this->http_codes['HTTP_OK']
                );
            }
        }
        else
        {
            return $this->resultResponse(
                Config::get('restresponsecode.ERROR'),
                [],
                [],
                trans('Doctors::messages.discount_not_available'),
                $this->http_codes['HTTP_OK']
            );
        }
    }
}