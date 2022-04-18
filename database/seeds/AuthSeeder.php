<?php

use Illuminate\Database\Seeder;
use App\Modules\Auth\Models\Auth;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
        * USER_TYPE_ADMIN - 1
        * USER_TYPE_DOCTOR - 2
        * USER_TYPE_PATIENT - 3
        * USER_TYPE_STAFF - 5,6,7,8
        * USER_TYPE_LAB_MANAGER - 9
        */

        // Get patient, doctor, staff data from .json file
        $jsonData = File::get("database/testdata/users.json");
		$data = json_decode($jsonData);

        $this->authObj = new Auth();
        $this->authObj->prepareTestData($data);

        // Get country data from .json file
        $countryJsonData = File::get("database/testdata/country.json");
        $countryData     = json_decode($countryJsonData);
        $this->authObj->prepareLocationData('country', $countryData);

        // Get state data from .json file
        $stateJsonData = File::get("database/testdata/state.json");
        $stateData     = json_decode($stateJsonData);
        $this->authObj->prepareLocationData('states', $stateData);

        // Get city data from .json file
        $cityJsonData = File::get("database/testdata/city.json");
        $cityData     = json_decode($cityJsonData);
        $this->authObj->prepareLocationData('cities', $cityData);
    }
}
