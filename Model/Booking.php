<?php

namespace App\Model;

use App\Enumeration\BookingStatusTypesInterface;
use App\Enumeration\PaymentTypesInterface;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'updated_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'gym_id', 'user_id', 'start_time', 'end_time','start_time_label','end_time_label', 'booking_type', 'unlock_code', 'description', 'booking_status', 'booking_date','invite_id','purchase_id','maintenance_type','maintenance_status','lock_person_id',
    ];


    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->with(['paymentinfos']);
    }

    public function booking_session()
    {
        return $this->hasMany(BookingSession::class);;
    }

    public static function getBookedSlots($sTime, $dat, $gym_id)
    {
        $bookingResult = self::where('gym_id', $gym_id)->where('booking_date', $dat)->where('booking_status', BookingStatusTypesInterface::SUCCESS)
            ->whereRaw("? Between start_time and end_time", $sTime)
            ->get();
        return ($bookingResult->count() == 0);
    }

    public static function getDayWiseStats($startTime, $endTime, $area_id, $booking_type, $booing_status)
    {
        $daywise_booking = self::whereBetween('booking_date', array($startTime, $endTime))->where('booking_type', $booking_type)->where('booking_status', $booing_status);
        if(!empty($area_id)) {
            $daywise_booking = $daywise_booking->join('gyms', 'bookings.gym_id', '=', 'gyms.id');
            $daywise_booking = $daywise_booking->where('area_id', $area_id);
        }
        $daywise_booking = $daywise_booking->selectRaw('booking_date, COUNT(*) as count')
            ->groupBy("booking_date")
            ->orderBy("booking_date")
            ->get();
        $daywise_booking_array = array();
        foreach($daywise_booking as $k => $v) {
            $daywise_booking_array[$v['booking_date']] = $v['count'];
        }
        return $daywise_booking_array;
    }

    public static function getMonthWiseStats($startTime, $endTime, $area_id, $booking_type, $booing_status)
    {
        $monthwise_booking_array = array();
        //PAYG
        $monthwise_booking = self::join('purchases', 'purchases.id', 'bookings.purchase_id')->whereBetween('booking_date', array($startTime, $endTime));
        if(!empty($area_id)) {
            $monthwise_booking = $monthwise_booking->join('gyms', 'bookings.gym_id', '=', 'gyms.id');
            $monthwise_booking = $monthwise_booking->where('area_id', $area_id);
        }
        $monthwise_booking = $monthwise_booking->where('booking_type', $booking_type)
            ->where('booking_status', $booing_status)
            ->where('product', PaymentTypesInterface::SESSION_PAYG)
            ->selectRaw('date_format(booking_date, \'%m-%Y\') as month_year, COUNT(*) as count')
            ->groupBy("month_year")
            ->orderBy("booking_date")
            ->get();
        foreach($monthwise_booking as $k => $v) {
            $monthwise_booking_array['payg'][$v['month_year']] = $v['count'];
        }
        //Credit
        $monthwise_booking = self::join('purchases', 'purchases.id', 'bookings.purchase_id')->whereBetween('booking_date', array($startTime, $endTime));
        if(!empty($area_id)) {
            $monthwise_booking = $monthwise_booking->join('gyms', 'bookings.gym_id', '=', 'gyms.id');
            $monthwise_booking = $monthwise_booking->where('area_id', $area_id);
        }
        $monthwise_booking = $monthwise_booking->where('booking_type', $booking_type)
            ->where('booking_status', $booing_status)
            ->where('product', PaymentTypesInterface::SESSION_CREDIT_PACK)
            ->selectRaw('date_format(booking_date, \'%m-%Y\') as month_year, COUNT(*) as count')
            ->groupBy("month_year")
            ->orderBy("booking_date")
            ->get();
        foreach($monthwise_booking as $k => $v) {
            $monthwise_booking_array['credit'][$v['month_year']] = $v['count'];
        }
        return $monthwise_booking_array;
    }

    public static function getMostPopularTimeSlot($startTime, $endTime, $area_id, $booking_type, $booing_status)
    {
        $popular_booking_timeslot = self::where('booking_type', $booking_type)->where('booking_status', $booing_status);
        if(!empty($startTime) && !empty($endTime)) {
            $popular_booking_timeslot = $popular_booking_timeslot->whereBetween('booking_date', array($startTime, $endTime));
        }
        if(!empty($area_id)) {
            $popular_booking_timeslot = $popular_booking_timeslot->join('gyms', 'bookings.gym_id', '=', 'gyms.id');
            $popular_booking_timeslot = $popular_booking_timeslot->where('area_id', $area_id);
        }
        $popular_booking_timeslot = $popular_booking_timeslot->selectRaw('CONCAT(start_time,\' - \', end_time) AS time_slot, COUNT(*) as count')
            ->groupBy("time_slot")
            ->orderByDesc("count")
            ->limit(5)
            ->get();
        $most_popular_booking_timeslot = array();
        foreach($popular_booking_timeslot as $k => $v) {
            $most_popular_booking_timeslot[$v['time_slot']] = $v['count'];
        }
        return $most_popular_booking_timeslot;
    }

    public static function getBookedSessionTimer($user_id, $booking_date, $booking_time, $booking_type, $booking_status)
    {
        $bookingResult = self::selectRaw('bookings.*, TIMESTAMPDIFF(SECOND, start_time, NOW()) as time_elapsed_seconds')
            ->where('user_id', $user_id)->where('booking_type', $booking_type)->where('booking_status', $booking_status)
            ->where('booking_date', $booking_date)
            ->where('start_time',  '<=', $booking_time)
            ->where('end_time',  '>=', $booking_time)
            ->get();
        return $bookingResult;
    }

    public static function getLastBookingDetails($user_id, $booking_date, $booking_time, $booking_type, $booking_status)
    {
        //get current booking
        $bookingResult = self::where('user_id', $user_id)->where('booking_type', $booking_type)->where('booking_status', $booking_status)
            ->where('booking_date', $booking_date)
            ->where('start_time',  '<=', $booking_time)
            ->where('end_time',  '>=', $booking_time)
            ->get();
        
//print_r($bookingResult);
//echo "id = " . $bookingResult[0]['id'] . "<br>";
        if(!empty($bookingResult[0]['id'])) {
            //get last booking in this Gym
            $lastBookingResult = self::where('bookings.id', '<', $bookingResult[0]['id'])->where('booking_type', $booking_type)->where('booking_status', $booking_status)
            ->join('booking_sessions', 'booking_sessions.booking_id', '=', 'bookings.id')
            ->where('bookings.booking_date', $booking_date)
            ->where('bookings.end_time',  '<=', $bookingResult[0]['start_time'])
            ->where('bookings.gym_id',  $bookingResult[0]['gym_id'])
            ->orderByDesc("bookings.start_time")
            ->first();
//print_r($lastBookingResult->toArray());
//exit();
            return $lastBookingResult;
        } else {
            return "";
        }

    }

}
