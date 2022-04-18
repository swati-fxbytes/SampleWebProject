<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Traits\Encryptable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Libraries\SecurityLib;
use Config;

/**
 * Auth
 *
 * @package                Safe Health
 * @subpackage             Auth
 * @category               Model
 * @DateOfCreation         09 May 2018
 * @ShortDescription       This is model which need to perform the options related to
                           users table

 */
class DefaultSpcializationComponentList extends Model {

    use Notifiable,HasApiTokens,Encryptable;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // @var string $table
    // This protected member contains table name
    protected $table = 'default_spcialization_component_list';

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'spicialization_id', 'component', 'appointment_category', 'patient_groups', 'patient_at_a_glance', 'checkup_type', 'payment_mode', 'clinic_time', 'consent_form'
    ];
}
