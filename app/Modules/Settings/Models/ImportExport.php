<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use App\Libraries\SecurityLib;
use Illuminate\Support\Facades\DB;
use App\Traits\Encryptable;
use Config;
use App\Modules\Patients\Models\Patients;
use App\Modules\Referral\Models\Referral as Referral;
use App\Modules\PatientGroups\Models\PatientGroups as PatientGroups;
use App\Modules\Doctors\Models\DrugDoseUnit as DrugDoseUnit;
use App\Modules\Doctors\Models\DrugType as DrugType;
use App\Modules\Bookings\Models\Bookings as Bookings;
use App\Modules\Accounts\Models\Accounts as Accounts;
use App\Modules\Doctors\Models\ManageDrugs as ManageDrugs;

/**
 * AppointmentCategory Class
 *
 * @package                AppointmentCategory
 * @subpackage             Doctor AppointmentCategory
 * @category               Model
 * @DateOfCreation         7 june 2018
 * @ShortDescription       This is model which need to perform the options related to
                           AppointmentCategory table
 */
class ImportExport extends Model
{
    use Encryptable;
    /**
     * The attributes that should be override default primary key.
     *
     * @var string
     */

    /**
     * The attributes that should be override default table name.
     *
     * @var string
     */
    protected $tablePatients        = 'patients';
    protected $tableUsers           = 'users';
    protected $tableMedicines       = 'medicines';
    protected $tableDrugDoseUnit    = 'drug_dose_unit';
    protected $tableDrugType        = 'drug_type';
    protected $tablePatientVisits   = 'patients_visits';
    protected $tableDoctorMedicieRelation = 'doctor_medicines_relation';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Init security library object
        $this->securityLibObj = new SecurityLib();
        // Init referral model object
        $this->referralModelObj = new Referral();

        // Init patient groups model object
        $this->patientGroupsModelObj = new PatientGroups();

         // Init Patient model object
        $this->patientModelObj = new Patients();

         // Init DrugDoseUnit model object
        $this->drugDoseUnitModelObj = new DrugDoseUnit();

         // Init DrugDoseUnit model object
        $this->drugTypeModelObj = new DrugType();

        // Init ManageDrugs Model Object
        $this->manageDrugsObj = new ManageDrugs();
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to get the Last inserted Patient Code for the doctor
    * @param                 Array $request
    * @return                Array of status and message
    */
    public function getPatientCode($doctorUserId)
    {
        if(!empty($this->patientModelObj->getPatientsRegistrationNumberByDoctorId($doctorUserId)->pat_code)){

            $lastPatientCode    = $this->patientModelObj->getPatientsRegistrationNumberByDoctorId($doctorUserId)->pat_code;
            $validLastCode      = explode(Config::get('constants.PATIENT_CODE_PREFIX_DEFAULT'), $lastPatientCode);
            $newPatientCode     = array_key_exists('1', $validLastCode) ? $validLastCode[1]+1 : Config::get('constants.FIRST_PATIENT_CODE_DEFAULT');

            if(strlen($newPatientCode) == 1){
                $validCodePrefix = '000';
            } else if(strlen($newPatientCode) == 2){
                $validCodePrefix = '00';
            } else if(strlen($newPatientCode) == 3){
                $validCodePrefix = '0';
            } else {
                $validCodePrefix = '';
            }

            $pat_code = Config::get('constants.PATIENT_CODE_PREFIX_DEFAULT').($validCodePrefix.$newPatientCode);
        } else {
            $pat_code = Config::get('constants.PATIENT_CODE_PREFIX_DEFAULT').Config::get('constants.FIRST_PATIENT_CODE_DEFAULT');
        }
        return $pat_code;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to getr blood group Id from name
    * @param                 Array $request
    * @return                Integer
    */
    public function getBloodgroup($blood_group)
    {
        $bloodGroup = 0;
        switch ($blood_group) {
            case trans('Setup::StaticDataConfigMessage.a_negative'):
                $bloodGroup = 1;
                break;
            case trans('Setup::StaticDataConfigMessage.a_posative'):
                $bloodGroup = 2;
                break;
            case trans('Setup::StaticDataConfigMessage.b_negative'):
                $bloodGroup = 3;
                break;
            case trans('Setup::StaticDataConfigMessage.b_posative'):
                $bloodGroup = 4;
                break;
            case trans('Setup::StaticDataConfigMessage.o_negative'):
                $bloodGroup = 5;
                break;
            case trans('Setup::StaticDataConfigMessage.o_posative'):
                $bloodGroup = 6;
                break;
            case trans('Setup::StaticDataConfigMessage.ab_negative'):
                $bloodGroup = 7;
                break;
            case trans('Setup::StaticDataConfigMessage.ab_posative'):
                $bloodGroup = 8;
                break;
        }
        return $bloodGroup;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to get patient group Id
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getPatientGroup($pat_group_name, $doctorUserId)
    {
        $pat_group_id = NULL;
        if(!empty($pat_group_name)){
            $patGroupResult = $this->patientGroupsModelObj->getPatientGroupIdByName($pat_group_name);
            if(!empty($patGroupResult)){
               $pat_group_id = $patGroupResult->pat_group_id;
            }else{
                $groupData = ['pat_group_name' => $pat_group_name, 'user_id' => $doctorUserId];
                $patientGroup = $this->patientGroupsModelObj->createPatientGroup($groupData);
                if(!empty($patientGroup->pat_group_id)){
                    $pat_group_id = $this->securityLibObj->decrypt($patientGroup->pat_group_id);
                }
            }
        }
        return $pat_group_id;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to get referral person of a patient
    * @param                 $pat_ref_name, $doctorUserId
    * @return                Integer
    */
    public function getPatientRefferedby($doc_ref_name, $doctorUserId)
    {
        $doc_ref_id = null;
        if(!empty($doc_ref_name)){
        $referralResult = $this->referralModelObj->getReferralIdByName($doc_ref_name);
            if(!empty($referralResult)){
               $doc_ref_id = $referralResult->doc_ref_id;
            }else{
                $refferalData = ['doc_ref_name' => $doc_ref_name, 'user_id' => $doctorUserId];
                $referal = $this->referralModelObj->createReferral($refferalData);
                if(!empty($referal->doc_ref_id)){
                    $doc_ref_id = $this->securityLibObj->decrypt($referal->doc_ref_id);
                }
            }
        }
        return $doc_ref_id;
    }

    public function updatePatients($patientData, $user_data,  $external_pat_number)
    {   
        $whereData = ['external_pat_number' => $external_pat_number];
        $user_id  = $this->dbSelect($this->tablePatients, ['user_id'], $whereData);
        if(!empty($user_id)){
            $this->dbUpdate($this->tablePatients, $patientData, $whereData);
            $this->dbUpdate($this->tableUsers, $user_data, array('user_id' => $user_id->user_id));
        }
        return true;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to make default entries of a new patient from imported data
    * @param                 Array $data, $doctorUserId, $patientUserId, $ip_address
    * @return                boolean
    */
    public function doPatientDefaultEntries($doctorUserId, $patientUserId, $ip_address)
    {
        $relationData = [
            'user_id'       => $doctorUserId,
            'pat_id'        => $patientUserId,
            'assign_by_doc' => $doctorUserId,
            'resource_type' => Config::get('constants.RESOURCE_TYPE_WEB'),
            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
            'ip_address'    => $ip_address,
        ];
        $this->patientModelObj->createPatientDoctorRelation('doctor_patient_relation',$relationData);

        $defaultVisitData = [
            'user_id'       => Config::get('constants.DEFAULT_USER_VISIT_ID'),
            'pat_id'        => $patientUserId,
            'visit_type'    => Config::get('constants.PROFILE_VISIT_TYPE'),
            'visit_number'  => Config::get('constants.INITIAL_VISIT_NUMBER'),
            'resource_type' => Config::get('constants.RESOURCE_TYPE_WEB'),
            'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
            'status'        => Config::get('constants.VISIT_COMPLETED'),
            'ip_address'    => $ip_address,
        ];
        $this->patientModelObj->createPatientDoctorVisit('patients_visits',$defaultVisitData);

        // SEND THANK YOU MESSAGE TO REFFERAL CONTACT NUMBER HERE==========
        if(!empty($doc_ref_id)){
            $referralResult = $this->referralModelObj->getReferralById($doc_ref_id);

            if(!empty($referralResult->doc_ref_mobile)){
                $referralContactNumber = $referralResult->doc_ref_mobile;
            }
        }
        return true;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to get user Id from his full or first name
    * @param                 $userFirstname, $userLastname
    * @return                Integer
    */
    public function getUserIdByName($userFirstname, $userLastname='')
    {
        $userId = NULL;
        if(!empty($userFirstname)){
            $selectData = ['user_id'];
            $whereData = array(
                        'user_firstname'  => $userFirstname,
                        'is_deleted'      => Config::get('constants.IS_DELETED_NO'),
                    );
            if(!empty($userLastname)){
                $whereData['user_lastname'] = $userLastname;
            }

            $result = DB::table($this->tableUsers)
                            ->select($selectData)
                            ->where($whereData)
                            ->first();
            if($result && array_key_exists('user_id', $result)){
                $userId = $result->user_id;
            }
        }
        return $userId;
    }

    /**
    * @DateOfCreation        21 Nov 2018
    * @ShortDescription      This function is responsible to get patient user Id from his External Pat Number
    * @param                 $external_pat_number
    * @return                Integer
    */
    public function getUserIdByExternalPatNumber($external_pat_number)
    {
        $userId = NULL;
        if(!empty($external_pat_number)){
            $selectData = ['user_id'];
            $whereData = array(
                        'external_pat_number'   => $external_pat_number,
                        'is_deleted'            => Config::get('constants.IS_DELETED_NO'),
                    );
            $result = DB::table($this->tablePatients)
                        ->select($selectData)
                        ->where($whereData)
                        ->first();
            if($result && array_key_exists('user_id', $result)){
                $userId = $result->user_id;
            }
        }
        return $userId;
    }

    /**
    * @DateOfCreation        13 Nov 2018
    * @ShortDescription      This function is responsible to get Medicine Id from name
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getMedicineIdByName($medicineName)
    {
        $medicineId = NULL;
        if(!empty($medicineName)){
            $selectData = ['medicine_id'];
            $whereData = array(
                        'medicine_name' => $medicineName,
                        'is_deleted'    => Config::get('constants.IS_DELETED_NO'),
                    );

            $result = DB::table($this->tableMedicines)
                            ->select($selectData)
                            ->where($whereData)
                            ->first();
            if($result && array_key_exists('medicine_id', $result)){
                $medicineId = $result->medicine_id;
            }
        }
        return $medicineId;
    }

    /**
    * @DateOfCreation        14 Nov 2018
    * @ShortDescription      This function is responsible to get Medicine Duration Unit Id from Unit Name
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getMedicineDurationUnitId($durationUnit)
    {
        $durationUnitId = NULL;
        switch ($durationUnit) {
            case trans('Setup::StaticDataConfigMessage.days'):
                $durationUnitId = 1;
                break;
            case trans('Setup::StaticDataConfigMessage.weeks'):
                $durationUnitId = 2;
                break;
            case trans('Setup::StaticDataConfigMessage.months'):
                $durationUnitId = 3;
                break;
            case trans('Setup::StaticDataConfigMessage.years'):
                $durationUnitId = 4;
                break;
        }
        return $durationUnitId;
    }

    /**
    * @DateOfCreation        14 Nov 2018
    * @ShortDescription      This function is responsible to get Drug Dose Unit Id from Drug Dose Name
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getDrugDoseUnitIdByName($drugDoseUnit)
    {
        $drugDoseUnitId = NULL;
        if(!empty($drugDoseUnit)){
            $selectData = ['drug_dose_unit_id'];
            $whereData = array(
                        'drug_dose_unit_name'   => $drugDoseUnit,
                        'is_deleted'            => Config::get('constants.IS_DELETED_NO'),
                    );

             $result = DB::table($this->tableDrugDoseUnit)
                            ->select($selectData)
                            ->where($whereData)
                            ->first();
            if($result && array_key_exists('drug_dose_unit_id', $result)){
                $drugDoseUnitId = $result->drug_dose_unit_id;
            }else{
                $drugDoseUnitId = $this->drugDoseUnitModelObj->saveDrugDoseUnit(['drug_dose_unit_name'=>$drugDoseUnit]);
            }
        }
        return $drugDoseUnitId;
    }

    /**
    * @DateOfCreation        14 Nov 2018
    * @ShortDescription      This function is responsible to get Drug Type Id by Drug Type
    * @param                 $pat_group_name, $doctorUserId
    * @return                Integer
    */
    public function getDrugTypeIdByName($drugType)
    {
        $drugTypeId = NULL;
        if(!empty($drugType)){
            $selectData = ['drug_type_id'];
            $whereData = array(
                        'drug_type_name'  => $drugType,
                        'is_deleted'      => Config::get('constants.IS_DELETED_NO'),
                    );

            $result = DB::table($this->tableDrugType)
                            ->select($selectData)
                            ->where($whereData)
                            ->first();

            if($result && array_key_exists('drug_type_id', $result)){
                $drugTypeId = $result->drug_type_id;
            }else{
                $drugTypeId = $this->drugTypeModelObj->saveDrugType(['drug_type_name' => $drugType]);
            }
        }
        return $drugTypeId;
    }

    /**
    * @DateOfCreation        21 Nov 2018
    * @ShortDescription      This function is responsible to get the visit id for the medication date
    *                        if visit already exists for the patient
    * @param                 $userId, $medicationStartDate
    * @return                Integer
    */
    public function checkVisitExistsForDate($userId,$medicationStartDate){
        $selectData = ['visit_id'];
        $whereData = array(
                    'pat_id'            => $userId,
                    'is_deleted'        => Config::get('constants.IS_DELETED_NO')
                );

        $result = DB::table($this->tablePatientVisits)
                        ->select($selectData)
                        ->where($whereData)
                        ->where('status', '!=' ,Config::get('constants.PROFILE_VISIT_TYPE'))
                        ->whereRaw("Date(visit_date) = '$medicationStartDate'")
                        ->first();
        if($result && array_key_exists('visit_id', $result)){
            return $result;
        }
        return false;
    }

    /**
     * @DateOfCreation        21 June 2018
     * @ShortDescription      This function is responsible for get patient's visit id
     * @param                 Array $data This contains full Patient user input data
     * @return                String {patient visit id}
     */
    public function getPatientFollowUpVisitId($requestData)
    {
        //Init StaticDataConfig model object
        $this->bookingsObj = new Bookings();
        $this->accountsModelObj = new Accounts();

        $patientUserId = $this->securityLibObj->decrypt($requestData['patientUserId']);
        $patientBookingId = !empty($requestData['patientBookingId']) ? $this->securityLibObj->decrypt($requestData['patientBookingId']) : '';
        $userId = $requestData['user_id'];

        $visitIdQuery = $this->patientModelObj->checkPatientVisitId($patientUserId, $userId);
        if( !empty($visitIdQuery) )
        {
            $visitId = $visitIdQuery->visit_id;
            $insertData = ['user_id'        => $userId,
                          'pat_id'          => $patientUserId,
                          'visit_type'      => Config::get('constants.FOLLOW_VISIT_TYPE'),
                          'visit_number'    => $visitIdQuery->visit_number+1,
                          'resource_type'   => $requestData['resource_type'],
                          'ip_address'      => $requestData['ip_address']
                        ];
            $visitType = Config::get('constants.FOLLOW_VISIT_TYPE');
        } else {
            // Insert New Visit
            $insertData = ['user_id'        => $requestData['user_id'],
                          'pat_id'          => $patientUserId,
                          'visit_type'      => Config::get('constants.INITIAL_VISIT_TYPE'),
                          'visit_number'    => Config::get('constants.INITIAL_VISIT_NUMBER'),
                          'resource_type'   => $requestData['resource_type'],
                          'ip_address'      => $requestData['ip_address']
                        ];
            $visitType = Config::get('constants.INITIAL_VISIT_TYPE');
        }
        if(array_key_exists('visit_date', $requestData) && !empty($requestData['visit_date'])){
            $insertData['visit_date'] = $requestData['visit_date'];
        }
        $newVisit = $this->dbInsert('patients_visits', $insertData);
        if($newVisit){
            $visitId = DB::getPdo()->lastInsertId();
            $createPaymentsHistory = $this->accountsModelObj->createPaymentsHistoryFromVisit($insertData);
            if(!empty($patientBookingId)){
                $bookingVisitRelationData = ['visit_id' => $visitId,
                                             'booking_id' => $patientBookingId
                                            ];
                $bookingVisitRelation = $this->dbInsert('booking_visit_relation', $bookingVisitRelationData);
                $bookingInProgress = $this->bookingsObj->updateBookingState($patientBookingId, Config::get('constants.BOOKING_IN_PROGRESS'));
            }
        } else {
            $visitId = 0;
        }

        return ['visit_id'=> $this->securityLibObj->encrypt($visitId),'visit_type'=> $visitType, 'is_pending' => false];
    }

    /**
    * @DateOfCreation        21 Nov 2018
    * @ShortDescription      This function is responsible to get the visit id for the medication date
    *                        if visit already exists for the patient
    * @param                 $userId, $medicationStartDate
    * @return                Integer
    */
    public function checkAndSetMedicineRelation($medicineId, $userId){
        $insertData = [];
        $selectData = ['dmr_id'];
        $whereData = array(
                    'medicine_id'            => $medicineId,
                    'user_id'            => $userId,
                    'is_deleted'        => Config::get('constants.IS_DELETED_NO')
                );

        $result = DB::table($this->tableDoctorMedicieRelation)
                        ->select($selectData)
                        ->where($whereData)
                        ->first();
        if($result && array_key_exists('dmr_id', $result)){
            return $result;
        }else{
            $insertData['medicine_id'] = $medicineId;
            $insertData['user_id'] = $userId;
            $dmr_id = $this->manageDrugsObj->addRequest($insertData);
            return $dmr_id;
        }
        return false;
    }
}