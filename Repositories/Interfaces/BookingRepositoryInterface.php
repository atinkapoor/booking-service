<?php

namespace App\Repositories\Interfaces;

interface BookingRepositoryInterface
{
    public function find_a_time($data);
    public function reserve_booking($data);
    public function confirm_booking_payg($data);
    public function confirm_invite_unpaid_booking_payg($data);
    public function confirm_invite_paid_booking($data);
    public function confirm_booking_credit_pack($data);
    public function confirm_invite_unpaid_booking_credit_pack($data);
    public function cancel_booking($data);
    public function booking_session_create($data);
}
