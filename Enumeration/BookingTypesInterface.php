<?php

namespace App\Enumeration;


interface BookingTypesInterface
{
    const SESSION = 'session';
    const MAINTENANCE = 'maintenance';
    const BOOKING_TYPES = [
        self::SESSION,
        self::MAINTENANCE,
    ];
}