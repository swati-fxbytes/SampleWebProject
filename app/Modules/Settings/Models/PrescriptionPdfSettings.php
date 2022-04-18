<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\Encryptable;
use Config;

/**
 * Settings Class
 *
 * @package                Settings
 * @subpackage             Doctor Settings
 * @category               Model
 * @DateOfCreation         7 june 2018
 * @ShortDescription       This is model which need to perform the options related to 
                           Setting of doctors
 */
class PrescriptionPdfSettings extends Model 
{
	use Encryptable;
    /**
     * The attributes that should be override default primary key.
     *
     * @var string 
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that should be override default table name.
     *
     * @var string 
     */
    protected $table = 'prescription_pdf_settings';

    protected $fillable = ['user_id', 'pre_type', 'pre_header_image', 'pre_logo', 'pre_footer', 'is_deleted', 'ip_address'];

    // @var Array $encryptedFields
    // This protected member contains fields that need to encrypt while saving in database
    protected $encryptable = [];
}
