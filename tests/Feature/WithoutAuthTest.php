<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WithoutAuthTest extends TestCase
{
    //use RefreshDatabase;

    protected $accessToken;

    /**
     * Test case for doctor registration
     *
     * @return status : 200
     */
    public function testDoctorRegister()
    {
        $requestData = [
            "client_id" => "9e8a4eb2-5f2d-431a-8fc5-ef13384d83a9",
            "client_secret" =>  "2USB7ATsGZCbf2XwJPRtOanbOJXAA1qQU2Bqs9VHLNddPzZ9BhKOjt58UWGy",
            "resource_type" => "1",
            "user_firstname" =>  "Rest1",
            "user_lastname" =>  "Rest1",
            "user_gender" => "1",
            "user_mobile" => "9876543211",
            "user_password" => "Fxbytes@123",
            "user_otp" => "123456",
            "user_country_code" => "91",
            "send_otp" => "n",
            "user_type" => "2",
            "user_email" => "rest1@mailinator.com"
        ];
        $response = $this->json('post', '/api/doctor/registration', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for doctor registration in which clientID-secret is wrong
     *
     * @return status : 200
     */
    public function testDoctorRegisterWrongClientID()
    {
        $requestData = [
            "client_id" => "9e8a4eb2-5f2d-431a-8fc5-ef13384d83a9123456",
            "client_secret" =>  "2USB7ATsGZCbf2XwJPRtOanbOJXAA1qQU2Bqs9VHLNddPzZ9BhKOjt58UWGy123456",
            "resource_type" => "1",
            "user_firstname" =>  "Rest",
            "user_lastname" =>  "Rest",
            "user_gender" => "1",
            "user_mobile" => "9876543210",
            "user_password" => "Fxbytes@123",
            "user_otp" => "123456",
            "user_country_code" => "91",
            "send_otp" => "n",
            "user_type" => "3",
            "user_email" => "rest@mailinator.com"
        ];
        $response = $this->json('post', '/api/doctor/registration', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for doctor login
     *
     * @return status : 200
     */
    public function testDoctorLogin()
    {
        $requestData = [
            "client_id" => "9e8a4eb2-5f2d-431a-8fc5-ef13384d83a9",
            "client_secret" =>  "2USB7ATsGZCbf2XwJPRtOanbOJXAA1qQU2Bqs9VHLNddPzZ9BhKOjt58UWGy",
            "resource_type" => "1",
            "user_username" =>  "9876543211",
            "user_password" =>  "Fxbytes@123"
        ];
        $response = $this->json('post', '/api/login', $requestData);
        $response->assertStatus(200);
        //$data = $response->getOriginalContent();
    }

    /**
     * Test case for doctor login in which credentials are wrong
     *
     * @return status : 200
     */
    public function testDoctorLoginWrongCredentials()
    {
        $requestData = [
            "client_id" => "9e8a4eb2-5f2d-431a-8fc5-ef13384d83a9123456",
            "client_secret" =>  "2USB7ATsGZCbf2XwJPRtOanbOJXAA1qQU2Bqs9VHLNddPzZ9BhKOjt58UWGy123456",
            "resource_type" => "1",
            "user_username" =>  "9999999999",
            "user_password" =>  "Fxbytes@123456"
        ];
        $response = $this->json('POST', '/api/login', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for generate ClientID-Secret using tenant name
     *
     * @return status : 200
     */
    public function testClientIDSecret()
    {
        $requestData = [
            "tenant_name" => "Rxhealth"
        ];
        $response = $this->json('POST', '/api/user-secret', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for logout login user
     *
     * @return status : 200
     */
    public function testLogout()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/logout/SUg2RVA2Z1F6dkdtWElVSFJiYjh5Zz09', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get doctor details using name
     *
     * @return status : 200
     */
    public function testGetDoctorDetails()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/doctor/Rest', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get country
     *
     * @return status : 200
     */
    public function testGetCountry()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/get-country', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get states using countryID
     *
     * @return status : 200
     */
    public function testGetStates()
    {
        $requestData = [
            "country_id" => "91"
        ];
        $response = $this->json('POST', '/api/get-states', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get city using stateID
     *
     * @return status : 200
     */
    public function testGetCity()
    {
        $requestData = [
            "state_id" => "1"
        ];
        $response = $this->json('POST', '/api/get-city', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for search city
     *
     * @return status : 200
     */
    public function testSearchCity()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/search/cities', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for search doctor specialization in particular city
     *
     * @return status : 200
     */
    public function testSearchDoctorSpecialization()
    {
        $requestData = [
            "city_id" => "1"
        ];
        $response = $this->json('POST', '/api/search/doctors/specialisation', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for search doctor by appointment type
     *
     * @return status : 200
     */
    public function testSearchDoctorsByAppointment()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/search/doctors-by-appointment-type', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for search clinics
     *
     * @return status : 200
     */
    public function testSearchClinics()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/search/clinics', $requestData);
        $response->assertStatus(200);
    }


    /**
     * Test case for search doctors timeslots
     *
     * @return status : 200
     * @return : In progress -- not working
     */
    public function testSearchDoctorTimeslots()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/search/doctors/timeslots', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for slot available for given date-time
     *
     * @return status : 200
     */
    public function testSlotAvailableOrNot()
    {
        $requestData = [
            "timing_id" => "1",
            "user_id" => "1",
            "booking_date" => "12-07-2021",
            "booking_time" => "10:00 AM"
        ];
        $response = $this->json('POST', '/api/bookings/isSlotAvailable', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get logo image
     *
     * @return status : 200
     */
    public function testGetLogo()
    {
        $requestData = [];
        $response = $this->json('POST', '/api/logo', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get media image using name and path
     *
     * @return status : 200
     */
    public function testGetMedia()
    {
        $requestData = [];
        $response = $this->json('GET', '/api/media/jpg/doctor-media', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get prescription media image using name and path
     *
     * @return status : 200
     */
    public function testGetPrescriptionMedia()
    {
        $requestData = [];
        $response = $this->json('GET', '/api/previous-prescription/pdf/doctor-media', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get profile image using name
     *
     * @return status : 200
     */
    public function testGetProfileImage()
    {
        $requestData = [];
        $response = $this->json('GET', '/api/profile-image/doctor-media', $requestData);
        $response->assertStatus(200);
    }

    /**
     * Test case for get profile thumb image using name
     *
     * @return status : 200
     */
    public function testGetProfileThumbImage()
    {
        $requestData = [];
        $response = $this->json('GET', '/api/doctor-profile-thumb-image/small/doctor-media', $requestData);
        $response->assertStatus(200);
    }

}