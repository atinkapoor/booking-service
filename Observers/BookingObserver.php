<?php

namespace App\Observers;

use App\Enumeration\BookingTypesInterface;
use App\Model\Booking;
use App\Mail\SessionBooking;
use Illuminate\Support\Facades\Mail;

class BookingObserver
{
    /**
     * Handle the booking "created" event.
     *
     * @param  \App\Model\Booking $booking
     * @return void
     */
    public function created(Booking $booking)
    {
        if ( $booking->booking_type == BookingTypesInterface::SESSION ) {
            Mail::send(
                new SessionBooking($booking)
            );
        }
    }
}
