<?php

namespace App\Modules\ReviewRating\Models;

use Illuminate\Database\Eloquent\Model;
use App\Libraries\SecurityLib;
use Illuminate\Support\Facades\DB;
use App\Traits\Encryptable;
use Config;
use Carbon\Carbon;

/**
 * Review Rating Class
 *
 * @package                Review Rating
 * @subpackage             Doctor ReviewRating
 * @category               Model
 * @DateOfCreation         7 june 2018
 * @ShortDescription       This is model which need to perform the options related to
                           Review Rating table
 */
class ReviewRating extends Model
{
    use Encryptable;
    /**
     * The attributes that should be override default primary key.
     *
     * @var string
     */
    protected $primaryKey = 'rev_rat_id';

    /**
     * The attributes that should be override default table name.
     *
     * @var string
     */
    protected $table = 'review_rating';

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
    }

    /**
     * Create doctor service with regarding details
     *
     * @param array $data service data
     * @return Array review and rating if inserted otherwise false
     */

    public function createReviewRating($requestData=array())
    {
        $requestData['user_id'] = $this->securityLibObj->decrypt($requestData['user_id']);
        unset($requestData['rev_rat_id']);
        $queryResult = $this->dbInsert($this->table, $requestData);
        if($queryResult){
            $reviewRatingData = $this->getReviewRatingById(DB::getPdo()->lastInsertId());
            return $reviewRatingData;
        }
        return false;
    }

   /**
    * @DateOfCreation        22 May 2018
    * @ShortDescription      This function is responsible to get the service by id
    * @param                 String $rev_rat_id
    * @return                Array of service
    */
    public function getReviewRatingById($rev_rat_id='',$reviewer_id='',$doctor_id='')
    {
        $query = "SELECT
                    review_rating.overall,
                    review_rating.wait_time,
                    review_rating.manner,
                    review_rating.comment,
                    users.user_firstname,
                    users.user_lastname,
                    review_rating.created_at
                    FROM review_rating
                    JOIN ( SELECT * FROM dblink('user=".Config::get('database.connections.masterdb.username')." password=".Config::get('database.connections.masterdb.password')." dbname=".Config::get('database.connections.masterdb.database')."','SELECT user_id,user_firstname,user_lastname from users where users.is_deleted=".Config::get('constants.IS_DELETED_NO')."') AS users(user_id int,user_firstname text,user_lastname text)) AS users ON users.user_id = review_rating.review_user_id";
        if(!empty($rev_rat_id)){
            $query .= " WHERE review_rating.rev_rat_id=".$rev_rat_id."
                    AND review_rating.is_deleted=".Config::get('constants.IS_DELETED_NO');
            $queryResult = DB::select(DB::raw($query));
            $queryResult = $queryResult[0];
            $queryResult->created_at = Carbon::parse($queryResult->created_at)->diffForHumans();
            return $queryResult;
        }else if(!empty($reviewer_id)){ //check reviewer id exist
            $doctor_id = $this->securityLibObj->decrypt($doctor_id);
            $query .= " WHERE review_rating.review_user_id=".$reviewer_id." 
                    AND review_rating.user_id=".$doctor_id." 
                    AND review_rating.is_deleted=".Config::get('constants.IS_DELETED_NO');
            $queryResult = count(DB::select(DB::raw($query)));
            if($queryResult >= 1){
                return true;
            }else{
                return false;
            }
        }else{
            return true;
        }
    }
}
