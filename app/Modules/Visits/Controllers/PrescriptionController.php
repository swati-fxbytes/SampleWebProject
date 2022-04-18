<?php

namespace App\Modules\Visits\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Session;
use App\Traits\SessionTrait;
use App\Traits\RestApi;
use Config;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SecurityLib;
use App\Libraries\ExceptionLib;
use App\Libraries\FileLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use App\Libraries\PdfLib;
use DB;
use File;
use PDF;
use Response;
use Carbon\Carbon;
use App\Modules\Visits\Models\Prescription;
use Cookie;
use App\Modules\Bookings\Models\Bookings;

/**
 * PrescriptionController
 *
 * @package                Safe Health
 * @subpackage             PrescriptionController
 * @category               Controller
 * @DateOfCreation         23 Aug 2018
 * @ShortDescription       This controller to handle all the operation related to prescription
 */
class PrescriptionController extends Controller
{

    use SessionTrait, RestApi;

    // @var Array $http_codes
    // This protected member contains Http Status Codes
    protected $http_codes = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->http_codes = $this->http_status_codes();

        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init Utility Library object
        $this->utilityLibObj = new UtilityLib();

        // Init PDF Library object
        $this->pdfLibObj = new PdfLib();

        // Init exception library object
        $this->exceptionLibObj = new ExceptionLib();

        // Init dateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init Prescription Model Object
        $this->prescriptionModelObj = new Prescription();

        // Init Bookings model object
        $this->bookingsModelObj = new Bookings();
    }

    /**
     * @DateOfCreation        23 Aug 2018
     * @ShortDescription      This function is responsible to create patient visit prescription
     * @return
     */
    public function generatePrescriptionPdf(Request $request, $user_id=NULL, $visit_id=NULL, $isPrintSymptom=NULL, $isPrintDiagnosis=NULL, $isPrintLabTest=null, $isPrintPublicNotes=null)
    {
        $user_id  = $this->securityLibObj->decrypt($user_id);
        $visit_id = $this->securityLibObj->decrypt($visit_id);

        $getPrescriptionData = $this->prescriptionModelObj->generatePrescriptionPdf($user_id, $visit_id, $isPrintSymptom, $isPrintDiagnosis, $isPrintLabTest, $isPrintPublicNotes);
        $getPrescriptionData['next_booking'] = $this->bookingsModelObj->getPatientNextVisitSchedule($user_id);

        if(!empty($getPrescriptionData['next_booking'])){
            $getPrescriptionData['next_booking']->booking_date = Carbon::parse($getPrescriptionData['next_booking']->booking_date)->format('d M, Y');
            $getPrescriptionData['next_booking']->booking_time = $this->utilityLibObj->changeTimingFormat($getPrescriptionData['next_booking']->booking_time);
        }

        // return view('Visits::prescription_pdf',$getPrescriptionData);
        return $this->pdfLibObj->genrateAndShowPdf('Visits::prescription_pdf',$getPrescriptionData);
    }

    /**
     * @DateOfCreation        09 June 2021
     * @ShortDescription      This function is responsible to create patient visit prescription with Header
     * @return
     */
    public function generatePrescriptionPdfWithHeader(Request $request, $user_id=NULL, $visit_id=NULL, $isPrintSymptom=NULL, $isPrintDiagnosis=NULL, $isPrintLabTest=null, $isPrintPublicNotes=null)
    {
        $user_id  = $this->securityLibObj->decrypt($user_id);
        $visit_id = $this->securityLibObj->decrypt($visit_id);

        $getPrescriptionData = $this->prescriptionModelObj->generatePrescriptionPdfWithHeader($user_id, $visit_id, $isPrintSymptom, $isPrintDiagnosis, $isPrintLabTest, $isPrintPublicNotes);
        $getPrescriptionData['next_booking'] = $this->bookingsModelObj->getPatientNextVisitSchedule($user_id);

        if(!empty($getPrescriptionData['next_booking'])){
            $getPrescriptionData['next_booking']->booking_date = Carbon::parse($getPrescriptionData['next_booking']->booking_date)->format('d M, Y');
            $getPrescriptionData['next_booking']->booking_time = $this->utilityLibObj->changeTimingFormat($getPrescriptionData['next_booking']->booking_time);
        }

        // return view('Visits::prescription_pdf_with_header',$getPrescriptionData);
        return $this->pdfLibObj->genrateAndShowPdf('Visits::prescription_pdf_with_header',$getPrescriptionData);
    }

    /**
     * @DateOfCreation        09 June 2021
     * @ShortDescription      This function is responsible to create patient visit prescription with Header
     * @return
     */
    public function generatePrescriptionPdfWithHeaderv1(Request $request, $user_id=NULL, $visit_id=NULL, $isPrintSymptom=NULL, $isPrintDiagnosis=NULL, $isPrintLabTest=null)
    {
        $user_id  = $this->securityLibObj->decrypt($user_id);
        $visit_id = $this->securityLibObj->decrypt($visit_id);

        $getPrescriptionData = $this->prescriptionModelObj->generatePrescriptionPdfWithHeader($user_id, $visit_id, $isPrintSymptom, $isPrintDiagnosis, $isPrintLabTest);
        $getPrescriptionData['next_booking'] = $this->bookingsModelObj->getPatientNextVisitSchedule($user_id);

        if(!empty($getPrescriptionData['next_booking'])){
            $getPrescriptionData['next_booking']->booking_date = Carbon::parse($getPrescriptionData['next_booking']->booking_date)->format('d M, Y');
            $getPrescriptionData['next_booking']->booking_time = $this->utilityLibObj->changeTimingFormat($getPrescriptionData['next_booking']->booking_time);
        }

        return view('Visits::prescription_pdf_with_header',$getPrescriptionData);
        return $this->pdfLibObj->genrateAndShowPdf('Visits::prescription_pdf_with_header',$getPrescriptionData);
    }

    public function checkCookies()
    {
        echo Config::get('constants.AUTH_TOKEN_NAME');
        print_r(Cookie::get());
        echo $authToken = Cookie::get(Config::get('constants.AUTH_TOKEN_NAME'));
        die;
    }
}
