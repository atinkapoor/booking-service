<?php

namespace App\Rules;

use App\Enumeration\PaymentTypesInterface;
use App\Model\Booking;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use App\Model\Purchase;

class CancelBookingValidate implements Rule
{

    private $data;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $bookingModel = Booking::where('id', $this->data['booking_id'])->first();
        $purchase_id = $bookingModel->purchase_id;
        if ( $purchase_id == 0 ) {
            return true;
        }
        $purchaseData = Purchase::where('id', $purchase_id)
            ->with('booking')
            ->get()->first();

        switch ($purchaseData->product) {
            case PaymentTypesInterface::SESSION_PAYG:
                $time_threshold = env('PAG_CANCEL_TIME_IN_HOUR');
                break;
            case PaymentTypesInterface::SESSION_CREDIT_PACK:
                $time_threshold = env('CREDIT_CANCEL_TIME_IN_HOUR');
                break;
        }
        $sessionDateTime = $purchaseData->booking->booking_date . ' ' . $purchaseData->booking->start_time;
        $sessionDateTimeObj = Carbon::createFromFormat('Y-m-d H:i:s', $sessionDateTime);
        $currentDateTimeObj = Carbon::now();
        return ($currentDateTimeObj->diffInHours($sessionDateTimeObj, false) > $time_threshold);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'You can not cancel this session, please check cancellation policy.';
    }
}
