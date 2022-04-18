<?php
/**
 * @DateOfCreation        12 July 2018
 *
 *@ShortDescription messages in english return as a array.
 *
 * @var String
 */
return [
    // Booking Messages
    'booking_added'                 => 'Appointment added successfully',
    'booking_updated'               => 'Appointment updated successfully',
    'booking_updated_failed'        => 'Appointment not updated, Please try again',
    'booking_deleted'               => 'Appointment deleted successfully',
    'booking_failed'                => 'Appointment failed, please try again',
    'booking_delete_failed'         => 'Appointment deletion failed, please try again',
    'booking_not_found'             => 'Appointment not found',
    'booking_validation_failed'     => 'Appointment validation failed',
    'booking_unavailable'   	    => 'No appointment are available for the selected slot',
    'try_again'   	  			    => 'Some error occured. Please try again',
    'booking_email_subject'   	    => trans('frontend.site_title').' Book Appointment Successful',
    'appointment_list_success'      => 'Appointment list fetched successfully.',
    'appointment_list_error'        => 'Appointment list not found.',
    'today_appointment_success'     => 'Today appointment list fetched successfully.',
    'today_appointment_error'       => 'Today appointment list not found.',
    'user_already_booked_slot'      => 'You have already booked an appointment.',
    'user_already_booked_slot_patient' => 'Patient has already booked an appointment.',
    'user_already_booked_day'       => 'PATIENT_ALREADY_BOOKED_DAY',
    'appointment_date_required'     => 'The date for the appointments is missing',
    'next_booking_unavailable'      => 'Next appointment unavailable',
    'patient_next_booking_success'  => 'Patient next appointment successfully',
    'booking_time_over'             => 'The selected slot time is over',
];
