<?php
namespace App\Modules\Visits\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Config;
use DB;
use Carbon\Carbon;
use App\Libraries\SecurityLib;
use App\Libraries\UtilityLib;
use App\Libraries\DateTimeLib;
use App\Modules\Setup\Models\StaticDataConfig as StaticData;
use App\Modules\Visits\Models\Vitals;
use App\Modules\Visits\Models\ClinicalNotes;
use App\Modules\Visits\Models\Medication;
use App\Modules\Visits\Models\Symptoms;
use App\Modules\Visits\Models\Diagnosis;
use App\Modules\Visits\Models\PhysicalExaminations;
use App\Modules\Visits\Models\LaboratoryReport;

/**
 * Prescription
 *
 * @package                Safe Health
 * @subpackage             Prescription
 * @category               Model
 * @DateOfCreation         23 Aug 2018
 * @ShortDescription       This Model to handle database operation of Visit Prescription
 **/

class Prescription extends Model {

    use HasApiTokens,Encryptable;

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();

        // Init exception library object
        $this->utilityLibObj = new UtilityLib();

        // Init DateTime library object
        $this->dateTimeLibObj = new DateTimeLib();

        // Init staticData Model Object
        $this->staticDataModelObj = new StaticData();

        // Init Vitals model object
        $this->vitalsObj = new Vitals();

        // Init ClinicalNotes model object
        $this->clinicalNotesModelObj = new ClinicalNotes();

        // Init Medication model object
        $this->medicationModelObj = new Medication();

        // Init Symptoms model object
        $this->symptomsModelObj = new Symptoms();

        // Init Symptoms model object
        $this->diagnosisModelObj = new Diagnosis();

        // Init Lab report Model object
        $this->laboratoryReportModelObj = new LaboratoryReport();

        // Init PhysicalExaminations model object
        $this->physicalExaminationsObj = new PhysicalExaminations();
    }

    /**
    *@ShortDescription Table for the Users.
    *
    * @var String
    */
    protected $tableVitals = 'vitals';
    protected $tablePatientVisit = 'patients_visits';
    protected $tablePatients     = 'patients';
    protected $tableUsers        = 'users';
    protected $tableDoctors      = 'doctors';

    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     *@ShortDescription Override the primary key.
     *
     * @var string
     */
    protected $primaryKey = '';

    /**
     * @DateOfCreation        23 Aug 2018
     * @ShortDescription      This function is responsible to get the prescription data
     * @param                 integer $visitId, $patientId, $encrypt
     * @return                object Array of Physical Vitals records
     */
    public function generatePrescriptionPdf($userId, $visitId, $isPrintSymptom = 0, $isPrintDiagnosis = 0, $isPrintLabTest = 0, $isPrintPublicNotes = 0)
    {
        // GET PATIENT Info
        $getPatientInfo = DB::connection('masterdb')
                            ->table($this->tableUsers)
                            ->select(
                                'user_firstname as patient_firstname',
                                'user_lastname as patient_lastname',
                                'user_mobile as patient_mobile',
                                'user_gender'
                            )
                            ->where(['user_id' => $userId])
                            ->first();
        $getGender      =  $this->utilityLibObj->changeArrayKey($this->staticDataModelObj->getGenderData(), 'id');
        $getPatientInfo->user_gender = $getGender[$getPatientInfo->user_gender]['value'];

        // GET DOCTOR info, patient basic info and visit info
        $visitQuery = "SELECT 
                        ".$this->tablePatientVisit.".status,
                        ".$this->tablePatientVisit.".visit_type,
                        ".$this->tablePatientVisit.".pat_id,
                        ".$this->tablePatientVisit.".user_id,
                        ".$this->tablePatientVisit.".created_at,
                        ".$this->tablePatients.".pat_code,
                        ".$this->tablePatients.".pat_title,
                        ".$this->tablePatients.".pat_blood_group,
                        ".$this->tablePatients.".pat_phone_num,
                        ".$this->tablePatients.".pat_dob,
                        ".$this->tablePatients.".pat_code,
                        ".$this->tableDoctors.".doc_reg_num,
                        doctor.*
                        FROM ".$this->tablePatientVisit."
                        JOIN ".$this->tablePatients." on ".$this->tablePatients.".user_id = ".$this->tablePatientVisit.".pat_id AND ".$this->tablePatients.".is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                        JOIN ".$this->tableDoctors." on ".$this->tableDoctors.".user_id = ".$this->tablePatientVisit.".user_id AND ".$this->tableDoctors.".is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                        JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname, user_mobile AS doctor_mobile  from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                        doctor_firstname text,
                        doctor_lastname text,
                        doctor_mobile text
                        )) AS doctor ON doctor.doc_id= ".$this->tableDoctors.".user_id
                        WHERE ".$this->tablePatientVisit.".is_deleted=".Config::get('constants.IS_DELETED_NO')."
                        AND ".$this->tablePatientVisit.".visit_id = ".$visitId;
        
        $queryResult = DB::select(DB::raw($visitQuery));
        if(count($queryResult) > 0){
            $visitInfo = $queryResult[0];
            // $visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->age.' Year' : '';
            //$visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->diff(Carbon::now())->format('%y years, %m months') : '';
            $visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->diff(Carbon::now())->format('%y Y') : '';
            $visitInfo->created_at = !empty($visitInfo->created_at) ? Carbon::parse($visitInfo->created_at)->format('d M, Y') : Carbon::parse(Carbon::today())->format('d M, Y');
        }else{
            $visitInfo = [];
        }
        $visitInfo = !empty($visitInfo) ? json_decode(json_encode($visitInfo), true) : $visitInfo;

        // GET VITALS Weight
        $vitalsArr = [];
        $getVitalsWeightFormData = $this->staticDataModelObj->getStaticDataFunction(['getWeight']);
        $weightData = !empty($visitId) ? $this->physicalExaminationsObj->getPhysicalExaminationsByVistID($visitId, $userId,true) : [];
        $weightData = !empty($weightData) ? $this->utilityLibObj->changeArrayKey($weightData,'fector_id') : [];
        $encryptedFactorId = $this->securityLibObj->encrypt(Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'));
        $weightVitals = 0;
        if(!empty($weightData)){
            $weightVitals = $weightData[$encryptedFactorId]['fector_value'];
        }
        $vitalsArr[] = ['value' => $weightVitals, 'label' => 'Weight', 'unit' => 'kg'];

        // GET Vitals
        $getVitalsFormData = $this->staticDataModelObj->getStaticDataFunction(['vitalsFectorData']);
        $formValuData = !empty($visitId) ? $this->vitalsObj->getPatientVitalsInfo($visitId, $userId, true) : [];
        $formValuData = !empty($formValuData) ? $this->utilityLibObj->changeArrayKey($formValuData,'fector_id') : [];
        foreach ($getVitalsFormData as $key => $value) {
            $encryptFactorId = $this->securityLibObj->encrypt($value['id']);
            $factorValue = 0;
            if(array_key_exists($encryptFactorId, $formValuData)){
                $factorValue = $getVitalsFormData[$key]['value'] = $formValuData[$encryptFactorId]['fector_value'];
            }
            $vitalsArr[] = ['value' => $factorValue, 'label' => $value['lable'], 'unit' => $value['unit']];
        }

        // GET Physical BMI
        $encryptedFactorId = $this->securityLibObj->encrypt(Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI'));
        $bmi = 0;
        if(isset($weightData[$encryptedFactorId]) && !empty($weightData)){
            $bmi = $weightData[$encryptedFactorId]['fector_value'];
        }
        $vitalsArr[] = ['value' => $bmi, 'label' => trans('Setup::StaticDataConfigMessage.visit_physical_examination_label_bmi'), 'unit' => ''];

        // GET CLINICAL NOTES
        $encryptedPatientId = $this->securityLibObj->encrypt($userId);
        $encryptedVisitId   = $this->securityLibObj->encrypt($visitId);
        if($isPrintPublicNotes==1){
            $clinicalNotesData  = $this->clinicalNotesModelObj->getClinicalNotesListData(['pat_id' => $encryptedPatientId, 'visit_id' => $encryptedVisitId, "notes_type" => Config::get('constants.NOTES_TYPE_PUBLICAL')]);
            $clinicalNotesData  = !empty($clinicalNotesData) ? $clinicalNotesData->clinical_notes : [];
        }else{
            $clinicalNotesData = [];
        }

        // GET PRESCRIBED MEDICINE
        $prescribedMedicines = $this->medicationModelObj->getPatientMedicationData($visitId, $userId);
        $prescribedMedicines = !empty($prescribedMedicines) ? json_decode(json_encode($prescribedMedicines), true) : $prescribedMedicines;

        // GET PATIENT SYMPTOMS
        $symptomData = [];
        if($isPrintSymptom == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visitId' => $encryptedVisitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $symptomData = $this->symptomsModelObj->getSymptomsDataByPatientIdAndVistId($whereData);
            $symptomData = !empty($symptomData['result']) ? json_decode(json_encode($symptomData['result']), true) : $symptomData;
        }

        // GET PATIENT DIAGNOSIS
        $patientDiagnosis = [];
        if($isPrintDiagnosis == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visit_id' => $visitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $patientDiagnosis = $this->diagnosisModelObj->getPatientDiagnosisHistoryList($whereData);
            $patientDiagnosis = !empty($patientDiagnosis['result']) ? json_decode(json_encode($patientDiagnosis['result']), true) : $patientDiagnosis;
        }

        $patientLabTest = [];
        if($isPrintLabTest == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visitId' => $encryptedVisitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $patientLabTest = $this->laboratoryReportModelObj->getListData($whereData);
            $patientLabTest = !empty($patientLabTest['result']) ? json_decode(json_encode($patientLabTest['result']), true) : $patientLabTest;
        }

        return ['vital' => $vitalsArr, 'clinical_notes' => $clinicalNotesData, 'medicines' => $prescribedMedicines, 'patient_info' => $getPatientInfo, 'visit_info' => $visitInfo, 'symptom_data' => $symptomData, 'diagnosis_data' => $patientDiagnosis, 'labtest_data' => $patientLabTest];
    }

    /**
     * @DateOfCreation        23 Aug 2018
     * @ShortDescription      This function is responsible to get the prescription data
     * @param                 integer $visitId, $patientId, $encrypt
     * @return                object Array of Physical Vitals records
     */
    public function generatePrescriptionPdfWithHeader($userId, $visitId, $isPrintSymptom = 0, $isPrintDiagnosis = 0, $isPrintLabTest = 0, $isPrintPublicNotes = 0)
    {
        // GET PATIENT Info
        $getPatientInfo = DB::connection('masterdb')
                            ->table($this->tableUsers)
                            ->select(
                                'user_firstname as patient_firstname',
                                'user_lastname as patient_lastname',
                                'user_mobile as patient_mobile',
                                'user_gender'
                            )
                            ->where(['user_id' => $userId])
                            ->first();
        $getGender      =  $this->utilityLibObj->changeArrayKey($this->staticDataModelObj->getGenderData(), 'id');
        $getPatientInfo->user_gender = $getGender[$getPatientInfo->user_gender]['value'];

        // GET DOCTOR info, patient basic info and visit info
        // GET DOCTOR info, patient basic info and visit info
        $visitQuery = "SELECT 
                        ".$this->tablePatientVisit.".status,
                        ".$this->tablePatientVisit.".visit_type,
                        ".$this->tablePatientVisit.".pat_id,
                        ".$this->tablePatientVisit.".user_id,
                        ".$this->tablePatientVisit.".created_at,
                        ".$this->tablePatients.".pat_code,
                        ".$this->tablePatients.".pat_title,
                        ".$this->tablePatients.".pat_blood_group,
                        ".$this->tablePatients.".pat_phone_num,
                        ".$this->tablePatients.".pat_dob,
                        ".$this->tablePatients.".pat_code,
                        ".$this->tableDoctors.".doc_reg_num,
                        clinic_address_line1,
                        clinic_address_line2,
                        clinic_landmark,
                        clinic_pincode,
                        clinics.clinic_name,
                        doctor.*
                        FROM ".$this->tablePatientVisit."
                        JOIN ".$this->tablePatients." on ".$this->tablePatients.".user_id = ".$this->tablePatientVisit.".pat_id AND ".$this->tablePatients.".is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                        JOIN ".$this->tableDoctors." on ".$this->tableDoctors.".user_id = ".$this->tablePatientVisit.".user_id AND ".$this->tableDoctors.".is_deleted = ".Config::get('constants.IS_DELETED_NO')."
                        JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id AS doc_id,user_firstname AS doctor_firstname,user_lastname AS doctor_lastname, user_email AS doctor_email, user_mobile AS doctor_mobile  from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(doc_id int,
                        doctor_firstname text,
                        doctor_lastname text,
                        doctor_email text,
                        doctor_mobile text
                        )) AS doctor ON doctor.doc_id= ".$this->tableDoctors.".user_id
                        LEFT JOIN booking_visit_relation AS bvr on bvr.visit_id = ".$this->tablePatientVisit.".visit_id
                        LEFT JOIN bookings on bookings.booking_id = bvr.booking_id
                        LEFT JOIN clinics on clinics.clinic_id = bookings.clinic_id
                        WHERE ".$this->tablePatientVisit.".is_deleted=".Config::get('constants.IS_DELETED_NO')."
                        AND ".$this->tablePatientVisit.".visit_id = ".$visitId;

        $queryResult = DB::select(DB::raw($visitQuery));
        if(count($queryResult) > 0){
            $visitInfo = $queryResult[0];
            // $visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->age.' Year' : '';
            //$visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->diff(Carbon::now())->format('%y years, %m months') : '';
            $visitInfo->pat_dob = !empty($visitInfo->pat_dob) ? Carbon::parse($visitInfo->pat_dob)->diff(Carbon::now())->format('%y Y') : '';
            $visitInfo->created_at = !empty($visitInfo->created_at) ? Carbon::parse($visitInfo->created_at)->format('d M, Y') : Carbon::parse(Carbon::today())->format('d M, Y');
            
            $drId = $visitInfo->user_id;
            $doctorDegree = DB::table('doctors_degrees')
                                ->select('doc_deg_name')
                                ->where('user_id',$drId)
                                ->where('is_deleted', Config::get('constants.IS_DELETED_NO'))
                                ->groupBy('doc_deg_name')
                                ->get();
            if(!empty($doctorDegree)){
                $degArr  = array();
                foreach ($doctorDegree as $deg) {
                    $degArr[] = $deg->doc_deg_name;
                }
                $doc_deg = implode(', ', $degArr);
                $visitInfo->doc_deg_name = $doc_deg;
            }
        }else{
            $visitInfo = [];
        }

        $visitInfo = !empty($visitInfo) ? json_decode(json_encode($visitInfo), true) : $visitInfo;

        // GET VITALS Weight
        $vitalsArr = [];
        $getVitalsWeightFormData = $this->staticDataModelObj->getStaticDataFunction(['getWeight']);
        $weightData = !empty($visitId) ? $this->physicalExaminationsObj->getPhysicalExaminationsByVistID($visitId, $userId,true) : [];
        $weightData = !empty($weightData) ? $this->utilityLibObj->changeArrayKey($weightData,'fector_id') : [];
        $encryptedFactorId = $this->securityLibObj->encrypt(Config::get('dataconstants.VISIT_PHYSICAL_WEIGHT'));
        $weightVitals = 0;
        if(!empty($weightData)){
            $weightVitals = $weightData[$encryptedFactorId]['fector_value'];
        }
        $vitalsArr[] = ['value' => $weightVitals, 'label' => 'Weight', 'unit' => 'kg'];

        // GET Vitals
        $getVitalsFormData = $this->staticDataModelObj->getStaticDataFunction(['vitalsFectorData']);
        $formValuData = !empty($visitId) ? $this->vitalsObj->getPatientVitalsInfo($visitId, $userId, true) : [];
        $formValuData = !empty($formValuData) ? $this->utilityLibObj->changeArrayKey($formValuData,'fector_id') : [];
        foreach ($getVitalsFormData as $key => $value) {
            $encryptFactorId = $this->securityLibObj->encrypt($value['id']);
            $factorValue = 0;
            if(array_key_exists($encryptFactorId, $formValuData)){
                $factorValue = $getVitalsFormData[$key]['value'] = $formValuData[$encryptFactorId]['fector_value'];
            }
            $vitalsArr[] = ['value' => $factorValue, 'label' => $value['lable'], 'unit' => $value['unit']];
        }

        // GET Physical BMI
        $encryptedFactorId = $this->securityLibObj->encrypt(Config::get('dataconstants.VISIT_PHYSICAL_EXAMINATION_BMI'));
        $bmi = 0;
        if(isset($weightData[$encryptedFactorId]) && !empty($weightData)){
            $bmi = $weightData[$encryptedFactorId]['fector_value'];
        }
        $vitalsArr[] = ['value' => $bmi, 'label' => trans('Setup::StaticDataConfigMessage.visit_physical_examination_label_bmi'), 'unit' => ''];

        // GET CLINICAL NOTES
        $encryptedPatientId = $this->securityLibObj->encrypt($userId);
        $encryptedVisitId   = $this->securityLibObj->encrypt($visitId);
        if($isPrintPublicNotes==1){
            $clinicalNotesData  = $this->clinicalNotesModelObj->getClinicalNotesListData(['pat_id' => $encryptedPatientId, 'visit_id' => $encryptedVisitId, "notes_type" => Config::get('constants.NOTES_TYPE_PUBLICAL')]);
            $clinicalNotesData  = !empty($clinicalNotesData) ? $clinicalNotesData->clinical_notes : [];
        }else{
            $clinicalNotesData = [];
        }

        // GET PRESCRIBED MEDICINE
        $prescribedMedicines = $this->medicationModelObj->getPatientMedicationData($visitId, $userId);
        $prescribedMedicines = !empty($prescribedMedicines) ? json_decode(json_encode($prescribedMedicines), true) : $prescribedMedicines;

        // GET PATIENT SYMPTOMS
        $symptomData = [];
        if($isPrintSymptom == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visitId' => $encryptedVisitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $symptomData = $this->symptomsModelObj->getSymptomsDataByPatientIdAndVistId($whereData);
            $symptomData = !empty($symptomData['result']) ? json_decode(json_encode($symptomData['result']), true) : $symptomData;
        }

        // GET PATIENT DIAGNOSIS
        $patientDiagnosis = [];
        if($isPrintDiagnosis == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visit_id' => $visitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $patientDiagnosis = $this->diagnosisModelObj->getPatientDiagnosisHistoryList($whereData);
            $patientDiagnosis = !empty($patientDiagnosis['result']) ? json_decode(json_encode($patientDiagnosis['result']), true) : $patientDiagnosis;
        }

        $patientLabTest = [];
        if($isPrintLabTest == 1){
            $whereData = ['patId' => $encryptedPatientId, 'visitId' => $encryptedVisitId, 'filtered' => [], 'sorted' => '', 'page' => 0, 'pageSize' => -1];
            $patientLabTest = $this->laboratoryReportModelObj->getListData($whereData);
            $patientLabTest = !empty($patientLabTest['result']) ? json_decode(json_encode($patientLabTest['result']), true) : $patientLabTest;
        }

        return ['vital' => $vitalsArr, 'clinical_notes' => $clinicalNotesData, 'medicines' => $prescribedMedicines, 'patient_info' => $getPatientInfo, 'visit_info' => $visitInfo, 'symptom_data' => $symptomData, 'diagnosis_data' => $patientDiagnosis, 'labtest_data' => $patientLabTest];
    }

}
