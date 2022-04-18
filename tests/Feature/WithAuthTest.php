<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WithAuthTest extends TestCase
{
    //use RefreshDatabase;

    protected $accessToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiMTE5Y2UyYWE3Y2ZmZGQ1MmFkOTRkNDE3MmU1Yjc1ZDI5MjRjMjdhOTkwNDRiZDE1ZmM5YzVhOTU1MjFmZGI1YWFjYjQwMzQ5NmI4YWQyODMiLCJpYXQiOjE2MjU4MDY1MDcsIm5iZiI6MTYyNTgwNjUwNywiZXhwIjoxNjU3MzQyNTA3LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.ZasdQEa1mNKLWRJoMK7rWrzxUU2aRjM-ggkwCwBz_zIj9yG95_OL-p2K-JfLXBV28rS111nNky4ImC7gMrXbmKZ3aMw_R3IiJebqgVZfkpwx2CgdI7G-xkWfOnD_rOpgkp0NS8t8SP-leFNmaAtagWBBDhhhFwn9-sKWF_0wjyVQcrHacSD3EKNFQJNksFQnlUIa6CSe2X5l_vK3PcoyCuk845sPOgxs5YaOIZhtX_GF9nUojyfqmQX09yXhziNiDPmhRtEBSBP7ZxOY0_DGUVNS4b1lK4T-Jq81quoCZLPgjWJy9ohgP9H09PFavTQIRIbW8fnSdVhZHfN9oQNTzgJCzuV6qgDvfgcK0TRGejUx8FaAj_ThG2Rs22OG9oQHMjJcd2tYTBOPz7Z2eHfEGEBOCDxig7WxhmOfCmIwlv29HcF8irwxQo1jXJ003hdRmeqgpC-p_4whYWAmygL8qsY-1PmD1WNCg7TVPpm_BNOUTK1TmPOGWsPwEbj1RXL8dc5aSrPypdwboIacjzBnUot5PpqZWTMtLcagMX5QnCb-VaP4wY_zGwSrF7AOFsrS0kR6Whd1itWcxpeqdRg3ZbeUrLVXa8zkSIzC8QBeZ7JNokWrrhm2Cd3-jC9vB5S4uZnMO3Ghsld2wH8eOlJX9OIabcb7UNjsKRVJ2CYSybQ';

    /**
     * Test case for register a device on behalf of user & device type
     *
     * @return status : 200
     */
    public function testRegisterDevice()
    {
        $requestData = [
            "token" => "123456",
            "plateform" =>  "Web"
        ];

        //$response = $this->post(route('/api/device/register'), $requestData);
       
        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('post', '/api/device/register', $requestData);
        //echo'<pre>'; print_r($response);exit;
        $response->assertStatus(200);
    }

    /**
     * Test case for update a password
     *
     * @return status : 200
     */
    public function testPasswordUpdate()
    {
        $requestData = [
            "user_password" => "123321@789",
            "user_old_password" =>  "Fxbytes@123"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/password/update', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for update a profile data
     *
     * @return status : 200
     */
    public function testProfileUpdate()
    {
        $requestData = [
            "user_id" => "1",
            "user_firstname" =>  "Ratan",
            "user_lastname" =>  "Tata",
            "user_mobile" =>  "9876543210",
            "user_gender" =>  "1",
            "doc_address_line1" =>  "Vijay nagar, Indore",
            "doc_latitude" =>  "22.1234",
            "doc_longitude" =>  "23.1234",
            "doc_short_info" =>  "this is test",
            "doc_consult_fee" =>  "200",
            "doc_address_line2" =>  "Indore",
            "city_id" =>  "1",
            "state_id" =>  "1",
            "country_code" =>  "1",
            "doc_pincode" =>  "452001",
            "doc_facebook_url" =>  "https://www.facebook.com",
            "doc_twitter_url" =>  "https://www.twitter.com",
            "doc_linkedin_url" =>  "https://www.linkedin.com",
            "doc_google_url" =>  "https://www.google.com",
            "doc_reg_num" =>  "1",
            "doc_other_city" =>  "2"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/update', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get cities using state ID
     *
     * @return status : 200
     */
    public function testGetCitiesByState()
    {
        $requestData = [
            "state_id" =>  "1"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/cities', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get color code for logged-in doctor
     *
     * @return status : 200
     */
    public function testGetColorCode()
    {
        $requestData = [];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/color-code', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get doctor's experience list
     *
     * @return status : 200
     */
    public function testGetDoctorExperience()
    {
        $requestData = [
            "page" =>  "1",
            "pageSize" =>  "10"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/experience', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for insert doctor's experience
     *
     * @return status : 200
     */
    public function testInsertDoctorExperience()
    {
        $requestData = [
            "doc_exp_organisation_name" =>  "MGM Indore",
            "doc_exp_designation" =>  "Doctor",
            "doc_exp_start_year" =>  "1999",
            "doc_exp_start_month" =>  "03",
            "doc_exp_end_year" =>  "2003",
            "doc_exp_end_month" =>  "03",
            "doc_exp_organisation_type" =>  "1"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/experience/insert', $requestData);
        $response->assertStatus(200);
    }


    /**
     * Test case for update doctor's experience
     *
     * @return status : 200
     */
    public function testUpdateDoctorExperience()
    {
        $requestData = [
            "doc_exp_organisation_name" =>  "MGM Indore 1",
            "doc_exp_designation" =>  "Doctor",
            "doc_exp_start_year" =>  "1999",
            "doc_exp_start_month" =>  "03",
            "doc_exp_end_year" =>  "2003",
            "doc_exp_end_month" =>  "03",
            "doc_exp_organisation_type" =>  "1",
            "doc_exp_id" =>  "1"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/experience/update', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for delete doctor's experience
     *
     * @return status : 200
     */
    public function testDeleteDoctorExperience()
    {
        $requestData = [
            "doc_exp_id" =>  "1"
        ];

        $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])->json('POST', '/api/doctors/profile/experience/delete', $requestData);
        $response->assertStatus(200);
    }
}
