<?php

namespace App\Utilities;

use App\Enumeration\BookingTypesInterface;
use App\Model\GlobalAreaDateRange;
use App\Model\GlobalAreaDaySlots;
use App\Model\GymSpecialDateSlot;
use App\Model\GymSpecialDaySlot;
use App\Model\Slot;
use App\Model\Gym;
use App\Model\User;
use App\Model\Day;
use App\Model\Booking;
use App\Model\TempBooking;
use App\Model\UserType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Utilities\UnlockCodes;

class BookingSlot
{
    public function create($request)
    {
        $recurrence_pattern = $request->get('recurrence_pattern');
        $gym_id = $request->get('gym_id');
        $dat = $request->get('dat');
        $slotData = unserialize(base64_decode($request->get('slot')));
        if ( $recurrence_pattern == "once" ) {
            $result = $this->booking_for_maintenance($slotData, $gym_id, $dat, $request->get('maintenance_type'), $request->get('description'));
        } else {
            $result = $this->booking_for_maintenance($slotData, $gym_id, $dat, $request->get('maintenance_type'), $request->get('description'), $recurrence_pattern, $request->get('enddat'));
        }
        return $result;
    }

    public function delete($id)
    {
        $bookingData = Booking::where('id', $id)->get();

        if ( $bookingData->count() == 0 ) {
            return false;
        }
        $bookingData = $bookingData->first();

        //Remove access from Remote Lock
        if(!empty($bookingData->lock_person_id)) {
            UnlockCodes::removecode($bookingData->lock_person_id);
        }

        Booking::destroy($id);
        return true;
    }

    private function booking_for_maintenance($slotData, $gym_id, $dat, $maintenance_type, $desc, $recurrence_pattern = '', $enddate = '')
    {
        $slotNotAvailable = array();
        $timeIntervalData[0]['start_time'] = $slotData['start_time'];
        $timeIntervalData[0]['end_time'] = $slotData['end_time'];

        $s = 0;
        if ( empty($enddate) ) {
            $enddate = $dat;
        }
        $period = CarbonPeriod::create($dat, $enddate);
        $next_date = Carbon::createFromFormat('Y-m-d', $dat);
        foreach ($period as $date) {
            $sdate = $date->format('Y-m-d');
            $dayDiff = $next_date->diffInDays($date, false);
            if ( $dayDiff == 0 ) {
                $timeIntervalData[0]['date'] = $sdate;
                $slotAviableData = $this->slotAvailable($timeIntervalData, $gym_id, 0);
                foreach ($slotAviableData as $slot) {
                    if ( $slot['available'] ) {
                        $newBookingData['purchase_id'] = 0;
                        $newBookingData['gym_id'] = $gym_id;
                        $newBookingData['user_id'] = 0;
                        $newBookingData['start_time'] = $slotData['start_time'];
                        $newBookingData['end_time'] = $slotData['end_time'];
                        $newBookingData['start_time_label'] = $slotData['start_time_label'];
                        $newBookingData['end_time_label'] = $slotData['end_time_label'];
                        $newBookingData['booking_type'] = BookingTypesInterface::MAINTENANCE;
                        $newBookingData['booking_date'] = $sdate;
                        $newBookingData['maintenance_status'] = 'open';
                        $newBookingData['maintenance_type'] = $maintenance_type;
                        $newBookingData['description'] = $desc;

                        $gymData = Gym::where('id', $gym_id)->get()->first();
                        list($lock_person_id, $pin) = UnlockCodes::codes($gymData->lock_id, $sdate, $slotData['start_time'], $slotData['end_time'], "Maintenance - ".$maintenance_type, '');
                        $newBookingData['unlock_code'] = $pin;
                        $newBookingData['lock_person_id'] = $lock_person_id;

                        Booking::create($newBookingData);
                    } else {
                        $slotNotAvailable[$s]['slot'] = $dat . ' ' . $slotData['start_time_label'] . ' - ' . $slotData['end_time_label'];
                        $s++;
                    }
                }
            }
            if ( $recurrence_pattern == "weekly" && $dayDiff == 0 ) {
                $next_date = $next_date->addDays(7);
            } elseif ( $recurrence_pattern == "monthly" && $dayDiff == 0 ) {
                $next_date = $next_date->addMonth(1);
            }
        }
        return $slotNotAvailable;
    }

    public function reserve_booking($request)
    {
        $tempBookingObj = TempBooking::where('gym_id', $request->get('gym_id'))
            ->where('user_id', $request->get('user_id'))
            ->where('booking_date', $request->get('booking_date'))
            ->where('start_time', $request->get('start_time'))
            ->where('end_time', $request->get('end_time'))->get();
        $price = $request->get('price');
        $credits = env('SESSION_BOOKING_CREDITS');
        if ( $tempBookingObj->count() == 0 ) {

            if ( $request->get('invite') > 0 ) {
                $invites = $request->get('invite');
                $user_id = $request->get('user_id');
                $userModel = User::select('user_types.allow_reduction')
                    ->join('user_types', 'users.user_type_id', 'user_types.id')
                    ->where('users.id', $user_id)->get()->first();

                if ( $userModel->allow_reduction == 1 ) {
                    $price = $price + ($request->get('price') * $invites);
                    $credits = $credits + ($credits * $invites);
                }
            }
            $request->merge(['price' => $price]);
            $request->merge(['charge_credits' => $credits]);
            $tempBookingObj = TempBooking::create($request->all());
        } else {
            $tempBookingObj = $tempBookingObj->first();
        }
        $reserveInfo = array();
        $reserveInfo['id'] = $tempBookingObj->id;
        $reserveInfo['gym_id'] = $tempBookingObj->gym_id;
        $reserveInfo['user_id'] = $tempBookingObj->user_id;
        $reserveInfo['slot_id'] = $tempBookingObj->slot_id;
        $reserveInfo['day_id'] = $tempBookingObj->day_id;
        $reserveInfo['start_time'] = $tempBookingObj->start_time;
        $reserveInfo['end_time'] = $tempBookingObj->end_time;
        $reserveInfo['start_time_label'] = $tempBookingObj->start_time_label;
        $reserveInfo['end_time_label'] = $tempBookingObj->end_time_label;
        $reserveInfo['booking_date'] = $tempBookingObj->booking_date;
        $reserveInfo['price'] = $tempBookingObj->price;
        return $reserveInfo;
    }

    private function isUserCreditsSubscriber($user_id)
    {
        $userModel = User::select('users.credits', 'users.credits_used')
            ->where('users.id', $user_id)->get()->first();
        return (($userModel->credits > 0) && ($userModel->credits > $userModel->credits_used) && (($userModel->credits - $userModel->credits_used)) >= env('SESSION_BOOKING_CREDITS'));
    }

    public function find_a_time($request)
    {
        $gym_id = $request->get('gym_id');
        $user_id = $request->auth->id;
        $dat = $request->get('dat');
        if ( empty($dat) ) {
            $dat = date('Y-m-d');
        }
        $nextDays = $request->get('nextDays');
        if ( empty($nextDays) ) {
            if ( $this->isUserCreditsSubscriber($user_id) ) {
                $nextDays = env('BOOKING_DAYS_RANGE_CREDIT');
            } else {
                $nextDays = env('BOOKING_DAYS_RANGE_PAYG');
            }
        }
        $nextDays = $nextDays + 1;
        $dateTime = Carbon::createFromFormat('Y-m-d', $dat);
        $startDate = $dateTime->format('Y-m-d');
        $dateTime->addDays($nextDays);
        $lastDate = $dateTime->format('Y-m-d');

        $areaDateRangeSlotPrices = array();

        $gymDateSlotsPriceData = GymSpecialDateSlot::getGymDateRangePrices($gym_id, $startDate, $lastDate);
        $slotPriceInfos = array();
        foreach ($gymDateSlotsPriceData as $gymDateSlotsPrice) {
            if ( $gymDateSlotsPrice->price > 0 ) {
                $key = $gymDateSlotsPrice->dat . '-' . $gymDateSlotsPrice->slot_id;
                $slotPriceInfos[$key] = $gymDateSlotsPrice->price;
            }
        }
        $gymData = Gym::where('id', $gym_id)->get()->first();
        $area_id = $gymData->area_id;
        $globalDatePriceData = GlobalAreaDateRange::getDateRangePrices($area_id, $startDate, $lastDate);
        foreach ($globalDatePriceData as $globalDatePrice) {
            foreach ($globalDatePrice->global_date_slots as $global_date_slots) {
                $key = $global_date_slots->dat . '-' . $global_date_slots->slot_id;
                if ( $global_date_slots->price > 0 && !array_key_exists($key, $slotPriceInfos) ) {
                    $slotPriceInfos[$key] = $global_date_slots->price;
                }
                if ( $global_date_slots->price > 0 ) {
                    $areaDateRangeSlotPrices[$key] = $global_date_slots->price;
                }
            }
        }
        $daysInfos = Day::getDaysInfos();

        $dateTime = Carbon::createFromFormat('Y-m-d', $dat);
        $gymDaySlotPrices = array();
        $areaDaySlotPrices = array();
        for ($d = 1; $d <= $nextDays; $d++) {
            $day_id = $daysInfos[$dateTime->format('l')];
            $dateVal = $dateTime->format('Y-m-d');
            $gymDayPricesData = GymSpecialDaySlot::getGymDayPrices($gym_id, $day_id);
            foreach ($gymDayPricesData as $gymDayPrice) {
                $key = $dateVal . '-' . $gymDayPrice->slot_id;
                if ( $gymDayPrice->price > 0 && !array_key_exists($key, $slotPriceInfos) ) {
                    $slotPriceInfos[$key] = $gymDayPrice->price;
                }
                if ( $gymDayPrice->price > 0 ) {
                    $gymDaySlotPrices[$key] = $gymDayPrice->price;
                }
            }
            $dateTime->addDays(1);
        }

        $dateTime = Carbon::createFromFormat('Y-m-d', $dat);
        $daysArr = array();
        for ($d = 1; $d <= $nextDays; $d++) {
            $day_id = $daysInfos[$dateTime->format('l')];
            $dateVal = $dateTime->format('Y-m-d');
            $daysArr[$dateVal] = $day_id;
            $globalPricesData = GlobalAreaDaySlots::getDayPrices($area_id, $day_id);
            foreach ($globalPricesData as $globalDayPrice) {
                $key = $dateVal . '-' . $globalDayPrice->slot_id;
                if ( $globalDayPrice->price > 0 && !array_key_exists($key, $slotPriceInfos) ) {
                    $slotPriceInfos[$key] = $globalDayPrice->price;
                }
                if ( $globalDayPrice->price > 0 ) {
                    $areaDaySlotPrices[$key] = $globalDayPrice->price;
                }
            }
            $dateTime->addDays(1);
        }
        $gymDetails['gym'] = $gymData;
        $gymDetails['slots'] = $this->slotTimePrice($gym_id, $slotPriceInfos, $areaDaySlotPrices, $gymDaySlotPrices, $areaDateRangeSlotPrices, $daysArr, $daysInfos, $user_id);
        return $gymDetails;
    }

    private function slotTimePrice($gym_id, $slotPriceInfos, $areaDaySlotPrices, $gymDaySlotPrices, $areaDateRangeSlotPrices, $daysArr, $daysInfos, $user_id)
    {
        $allInfos = array();

        foreach ($daysArr as $dat => $day_id) {
            $timeIntervalData = $this->getGymTimeSlots($dat, $gym_id, $daysInfos);
            $timeSlotsData = $this->slotAvailable($timeIntervalData, $gym_id, $user_id);
            $i = 0;
            foreach ($timeSlotsData as $timeSlots) {
                $key = $timeSlots['date'] . '-' . $timeSlots['slot_id'];
                $allInfos[$dat][$i] = $timeSlots;
                if ( array_key_exists($key, $slotPriceInfos) ) {
                    $allInfos[$dat][$i]['price'] = $slotPriceInfos[$key];
                    $allInfos[$dat][$i]['universal_price'] = $this->discountPrice($key, $slotPriceInfos[$key], $areaDaySlotPrices, $gymDaySlotPrices, $areaDateRangeSlotPrices);
                }
                $i++;
            }
        }
        return $allInfos;
    }

    private function discountPrice($key, $price, $areaDaySlotPrices, $gymDaySlotPrices, $areaDateRangeSlotPrices)
    {
        if ( array_key_exists($key, $areaDaySlotPrices) ) {
            return $areaDaySlotPrices[$key];
        }
        if ( array_key_exists($key, $areaDateRangeSlotPrices) ) {
            return $areaDateRangeSlotPrices[$key];
        }
        if ( array_key_exists($key, $gymDaySlotPrices) ) {
            return $gymDaySlotPrices[$key];
        }
        return $price;
    }

    private function slotAvailable($timeIntervalData, $gym_id, $user_id)
    {
        $finalTimeSlotsData = array();
        $i = 0;
        foreach ($timeIntervalData as $timeInterval) {
            $bookingStatus = Booking::getBookedSlots($timeInterval['start_time'], $timeInterval['date'], $gym_id);
            if ( $bookingStatus ) {
                $bookingStatus = Booking::getBookedSlots($timeInterval['end_time'], $timeInterval['date'], $gym_id);
            }
            if ( $bookingStatus ) {
                $bookingStatus = TempBooking::getBookedSlots($timeInterval['start_time'], $timeInterval['date'], $gym_id, $user_id);
            }
            if ( $bookingStatus ) {
                $bookingStatus = TempBooking::getBookedSlots($timeInterval['end_time'], $timeInterval['date'], $gym_id, $user_id);
            }
            $available = ($bookingStatus) ? 'true' : 'false';
            $finalTimeSlotsData[$i] = $timeInterval;
            $finalTimeSlotsData[$i]['available'] = $available;
            $i++;
        }
        return $finalTimeSlotsData;
    }

    /**
     * @param $dat
     * @param $gym_id
     * @return array
     */
    private
    function getGymTimeSlots($dat, $gym_id, $daysInfos)
    {
        $dateTime = Carbon::createFromFormat('Y-m-d', $dat);
        $day_id = $daysInfos[$dateTime->format('l')];
        $gymOpenTimeSlot = Gym::getGymOpeningTime($gym_id, $day_id);
        $closingDat = $dat;
        $gymOpenDateTime = $dat . ' ' . $gymOpenTimeSlot['open_time'];
        if ( $gymOpenTimeSlot['close_time'] < $gymOpenTimeSlot['open_time'] ) {
            $dateTime->addDays(1);
            $closingDat = $dateTime->format('Y-m-d');
        }
        $gymCloseDateTime = $closingDat . ' ' . $gymOpenTimeSlot['close_time'];
        $timeIntervalData = Slot::getTimeInterval($gymOpenDateTime, $gymCloseDateTime, $daysInfos);
        return $timeIntervalData;
    }
}
