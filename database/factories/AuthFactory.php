<?php

use Faker\Generator as Faker;
use App\Modules\Auth\Models\Auth;

$factory->define(Auth::class, function (Faker $faker) {
    return [
		'user_firstname'        => $faker->name,
		'user_lastname'         => $faker->name,
		'user_mobile'           => $faker->phoneNumber,
		'user_country_code'     => $faker->countryCode,
		'user_gender'           => "1",
		'user_status'           => "1",
		'user_password'         => '$2y$10$RNLiLv/nmqPzIPaUigKkl.HwhoVK.400HguqNZWFMbx.bJZ32AuwC',
		'user_type'             => $faker->boolean,
		'resource_type'         => "1",
		'ip_address'            => $faker->ipv4,
		'user_email' 			=> $faker->unique()->email,
		'user_adhaar_number' 	=> $faker->creditCardNumber
    ];
});
