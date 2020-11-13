<?php

namespace App\Repositories;

use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Utilities\BookingSlot;
use App\Utilities\Stripe\StripeCreditPayment;
use App\Validators\BookingValidator;
use App\Model\BookingSession;
use App\Model\Booking;
use Illuminate\Support\Carbon;


class BookingRepository implements BookingRepositoryInterface
{

    public function find_a_time($data)
    {
        return (new BookingSlot())->find_a_time($data);
    }
    public function create($data)
    {
        return (new BookingSlot())->create($data);
    }
    public function delete($id)
    {
        return (new BookingSlot())->delete($id);
    }

    public function reserve_booking($data)
    {
        return (new BookingValidator())->reserve_booking($data);
    }

    public function confirm_booking_payg($data)
    {
        return (new BookingValidator())->confirm_booking_payg($data);
    }

    public function confirm_invite_paid_booking($data)
    {
        return (new BookingValidator())->confirm_invite_paid_booking($data);
    }

    public function confirm_invite_unpaid_booking_credit_pack($data)
    {
        return (new BookingValidator())->confirm_invite_unpaid_booking_credit_pack($data);
    }

    public function confirm_invite_unpaid_booking_payg($data)
    {
        return (new BookingValidator())->confirm_invite_unpaid_booking_payg($data);
    }

    public function confirm_booking_credit_pack($data)
    {
        return (new BookingValidator())->confirm_booking_credit_pack($data);
    }

    public function cancel_booking($data)
    {
        return (new BookingValidator())->cancel_booking($data);
    }

    public function booking_session_create($data)
    {
        $bookingObj = Booking::where(['unlock_code' => $data['pin']])->get();
        if ( $bookingObj->count() > 0 ) {
            $bookingObj = $bookingObj->first();
            $booking_id = $bookingObj->id;
            $bookingSessionObj = BookingSession::where(['booking_id' => $booking_id])->get();
            if ( $bookingSessionObj->count() == 0 ) {
                //1st time entry
                $session_date = Carbon::parse($data['occurred_at'])->format('Y-m-d');
                $in_time = Carbon::parse($data['occurred_at'])->format('Y-m-d h:i:s');

                $bookingSessionData = BookingSession::create([
                    'booking_id' => $booking_id,
                    'session_date' => $session_date,
                    'in_time' => $in_time,
                ]);
                return $bookingSessionData;
            } else {
                //Further entries
                $out_time = Carbon::parse($data['occurred_at'])->format('Y-m-d h:i:s');
                $bookingSessionObj = $bookingSessionObj->first();
                $bookingSessionObj->out_time = $out_time;
                $bookingSessionObj->save();                
                return true;
            }
        } else {
            return true;
        }
    }
}
