<?php

namespace App\Enumeration;


interface BookingStatusTypesInterface
{
    const SUCCESS = 'success';
    const FAILED = 'failed';
    const CANCEL = 'cancel';
    const BOOKING_STATUS_TYPES = [
        self::SUCCESS,
        self::FAILED,
        self::CANCEL,
    ];
}