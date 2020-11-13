<?php

namespace App\Validators;

use App\Rules\BookingValidate;
use App\Rules\CancelBookingValidate;
use App\Rules\CouponValidate;
use App\Rules\CreditPacksAvailabilityValidate;
use App\Rules\InviteCreditPacksAvailabilityValidate;
use App\Rules\InvitePaidBookingValidate;
use App\Rules\InviteUnPaidBookingValidate;
use App\Rules\TempBookingValidate;
use App\Utilities\BookingSlot;
use App\Utilities\Stripe\StripeCreditPayment;
use Laravel\Lumen\Routing\ProvidesConvenienceMethods;

class BookingValidator
{
    use ProvidesConvenienceMethods;

    public function reserve_booking($request)
    {
        $request->merge(['user_id' => $request->auth->id]);
        $request->merge(['alreadyBooked' => 'check']);
        $request->merge(['alreadyTempBooked' => 'check']);
        $this->validate($request, [
            'alreadyTempBooked' => ['sometimes', new TempBookingValidate($request->all())],
        ]);

        return (new BookingSlot())->reserve_booking($request);
    }

    public function confirm_invite_paid_booking($request)
    {
        $this->validate($request, [
            'invite_id' => 'required',
            'validInvite' => [new InvitePaidBookingValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->confirm_invite_paid_booking($request);
    }

    public function confirm_invite_unpaid_booking_payg($request)
    {
        $this->validate($request, [
            'invite_id' => 'required',
            'card_option' => 'required|in:new,old',
            'card_no' => 'required_if:card_option,new',
            'ccExpiryMonth' => 'required_if:card_option,new',
            'ccExpiryYear' => 'required_if:card_option,new',
            'cvvNumber' => 'required_if:card_option,new',
            'card_id' => 'required_if:card_option,old',
            'validInvite' => [new InviteUnPaidBookingValidate($request->all())],
            'coupon' => ['sometimes', new CouponValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->confirm_invite_unpaid_booking_payg($request);
    }

    public function confirm_booking_payg($request)
    {
        $request->merge(['customer_id' => $request->auth->id]);
        $this->validate($request, [
            'temp_booking_id' => 'required',
            'card_option' => 'required|in:new,old',
            'card_no' => 'required_if:card_option,new',
            'ccExpiryMonth' => 'required_if:card_option,new',
            'ccExpiryYear' => 'required_if:card_option,new',
            'cvvNumber' => 'required_if:card_option,new',
            'card_id' => 'required_if:card_option,old',
            'alreadyBooked' => [new BookingValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->booking_by_payg($request);
    }

    public function confirm_booking_credit_pack($request)
    {
        $request->merge(['alreadyBooked' => 'check']);
        $request->merge(['creditPackAviability' => 'check']);
        $this->validate($request, [
            'creditPackAviability' => ['sometimes', new CreditPacksAvailabilityValidate($request->all())],
            'alreadyBooked' => ['sometimes', new BookingValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->booking_by_credit_pack($request);
    }

    public function confirm_invite_paid_booking_credit_pack($request)
    {
        $request->merge(['creditPackAviability' => 'check']);
        $this->validate($request, [
            'creditPackAviability' => ['sometimes', new InviteCreditPacksAvailabilityValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->confirm_invite_paid_booking_credit_pack($request);
    }

    public function confirm_invite_unpaid_booking_credit_pack($request)
    {
        $request->merge(['creditPackAviability' => 'check']);
        $this->validate($request, [
            'creditPackAviability' => ['sometimes', new InviteCreditPacksAvailabilityValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->confirm_invite_unpaid_booking_credit_pack($request);
    }

    public function cancel_booking($request)
    {
        $request->merge(['cancelCheck' => 'check']);
        $this->validate($request, [
            'cancelCheck' => [new CancelBookingValidate($request->all())],
        ]);
        return (new StripeCreditPayment())->cancel_booking($request);
    }
}
