<?php

namespace App\Http\Controllers;


use App\Enumeration\BookingStatusTypesInterface;
use App\Enumeration\BookingTypesInterface;
use App\Model\Booking;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use App\Mail\RemoteLockNotificaton;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    private $bookingRepository;
    const MODEL = "App\Model\Booking";
    use RESTActions {
        getRelationalData as allUserSessions;
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->bookingRepository = $bookingRepository;
    }

    public function all(Request $request)
    {
        $booking_type = $request->query('booking_type');
        $gym_id = $request->query('gym_id');
        $defaultCond = array();
        if ( !empty($booking_type) ) {
            $defaultCond = array_merge($defaultCond, [['booking_type', $booking_type]]);
        }
        if(!empty($request->query('search')))
        {
            $defaultCond = array_merge($defaultCond, [['description', 'like', '%' . $request->query('search') . '%']]);
        }
        if(!empty($request->query('maintenance_status')))
        {
            $defaultCond = array_merge($defaultCond, [['maintenance_status', $request->query('maintenance_status')]]);
        }
        if(!empty($request->query('maintenance_type')))
        {
            $defaultCond = array_merge($defaultCond, [['maintenance_type', $request->query('maintenance_type')]]);
        }
        $defaultCond = array_merge($defaultCond, [['gym_id', $gym_id]]);
        $pagination = array();
        if ( !empty($request->query('page')) ) {
            $pagination = ['limit' => env('PAGE_LIMIT')];
        }
        $orderBy['sort_criteria'] = $this->sort_criteria('created_at', 'desc');
        return $this->find($defaultCond, $orderBy, [], [], [], [], $pagination);
    }

    public function edit($id)
    {
        return $this->get($id);
    }

    public function update(Request $request, $id)
    {
        return $this->put($request, $id);
    }

    public function delete($id)
    {
        return $this->bookingRepository->delete($id);
    }

    public function create(Request $request)
    {
        return $this->bookingRepository->create($request);
    }

    /**
     * @OA\Get(
     *     path="/find_a_time",
     *     summary="find available day and time to book a session in a gym",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="auth token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="gym_id",
     *         in="query",
     *         description="gym",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="dat",
     *         in="query",
     *         description="date",
     *         required=false,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     )
     * )
     */
    public function find_a_time(Request $request)
    {

        return $this->bookingRepository->find_a_time($request);
    }

    /**
     * @OA\POST(
     *     path="/reserve_booking",
     *     summary="Temporary booking",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"gym_id","slot_id","day_id","start_time","end_time","start_time_label","end_time_label","booking_date","price"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="gym_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="slot_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="day_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="start_time",
     *                     type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="end_time",
     *                     type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="start_time_label",
     *                     type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="end_time_label",
     *                     type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="price",
     *                     type="number",
     *                     format="float",
     *                  ),
     *                  @OA\Property(
     *                      property="booking_date",
     *                     type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="invite",
     *                     type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="first_friend_mail",
     *                     type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="second_friend_mail",
     *                     type="string",
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function reserve_booking(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        return $this->bookingRepository->reserve_booking($request);
    }

    /**
     * @OA\POST(
     *     path="/confirm_invite_unpaid_booking_payg",
     *     summary="Confirm invite unpaid booking by payg",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"invite_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="invite_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="card_no",
     *                     type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="name_on_card",
     *                     type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="ccExpiryMonth",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="ccExpiryYear",
     *                     type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="cvvNumber",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="booking_type",
     *                     type="string",
     *                     enum={"session","maintenance"},
     *                     default="session",
     *                  ),
     *                  @OA\Property(
     *                      property="save_card",
     *                     type="integer",
     *                     enum={0,1},
     *                     default="0",
     *                     description="* 0 - No, 1 - Yes",
     *                  ),
     *                  @OA\Property(
     *                      property="card_option",
     *                     type="string",
     *                     enum={"new","old"},
     *                     default="new",
     *                     description="OR use Save Card",
     *                  ),
     *                  @OA\Property(
     *                      property="card_id",
     *                     type="integer",
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function confirm_invite_unpaid_booking_payg(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        return $this->bookingRepository->confirm_invite_unpaid_booking_payg($request);
    }

    /**
     * @OA\POST(
     *     path="/confirm_invite_paid_booking",
     *     summary="Confirm invite paid booking",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"invite_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="invite_id",
     *                     type="integer"
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function confirm_invite_paid_booking(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        return $this->bookingRepository->confirm_invite_paid_booking($request);
    }

    /**
     * @OA\POST(
     *     path="/confirm_booking_payg",
     *     summary="Confirm booking by payg",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"temp_booking_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="temp_booking_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="card_no",
     *                     type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="name_on_card",
     *                     type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="ccExpiryMonth",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="ccExpiryYear",
     *                     type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="cvvNumber",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="booking_type",
     *                     type="string",
     *                     enum={"session","maintenance"},
     *                     default="session",
     *                  ),
     *                  @OA\Property(
     *                      property="save_card",
     *                     type="integer",
     *                     enum={0,1},
     *                     default="0",
     *                     description="* 0 - No, 1 - Yes",
     *                  ),
     *                  @OA\Property(
     *                      property="card_option",
     *                     type="string",
     *                     enum={"new","old"},
     *                     default="new",
     *                     description="OR use Save Card",
     *                  ),
     *                  @OA\Property(
     *                      property="card_id",
     *                     type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="coupon",
     *                     type="string"
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function confirm_booking_payg(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        if ( empty($request->get('booking_type')) ) {
            $request->merge(['booking_type' => BookingTypesInterface::SESSION]);
        }
        return $this->bookingRepository->confirm_booking_payg($request);
    }

    /**
     * @OA\POST(
     *     path="/confirm_booking_credit_pack",
     *     summary="Confirm booking by credit pack",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"temp_booking_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="temp_booking_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="booking_type",
     *                     type="string",
     *                     enum={"session","maintenance"},
     *                     default="session",
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function confirm_booking_credit_pack(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        if ( empty($request->get('booking_type')) ) {
            $request->merge(['booking_type' => BookingTypesInterface::SESSION]);
        }
        return $this->bookingRepository->confirm_booking_credit_pack($request);
    }


    /**
     * @OA\POST(
     *     path="/confirm_invite_unpaid_booking_credit_pack",
     *     summary="Confirm invite unpaid booking by credit pack",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"temp_booking_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="temp_booking_id",
     *                     type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="booking_type",
     *                     type="string",
     *                     enum={"session","maintenance"},
     *                     default="session",
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function confirm_invite_unpaid_booking_credit_pack(Request $request)
    {
        if ( empty($request->get('user_id')) ) {
            $request->merge(['user_id' => $request->auth->id]);
        }
        return $this->bookingRepository->confirm_invite_unpaid_booking_credit_pack($request);
    }

    /**
     * @OA\POST(
     *     path="/cancel_booking",
     *     summary="cancel booking",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\RequestBody(
     *         description="",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *           @OA\Schema(
     *                  required={"booking_id"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="booking_id",
     *                     type="integer"
     *                  )
     *             )
     *         )
     *
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found",
     *     )
     * )
     */
    public function cancel_booking(Request $request)
    {
        return $this->bookingRepository->cancel_booking($request);
    }

    /**
     * @OA\Get(
     *     path="/getUserSessions",
     *     summary="show user sessions/bookings",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="auth token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="user",
     *         required=false,
     *         @OA\Schema(
     *           type="integer",
     *           @OA\Items(type="integer"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="booking_status",
     *         in="query",
     *         description="(success, failed, cancel)",
     *         required=false,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="future",
     *         in="query",
     *         description="(no -> show only past sessions, yes -> show only future sessions)",
     *         required=false,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     )
     * )
     */
    public function showUserSessions(Request $request)
    {
        $user_id = $request->query('user_id');
        $status = $request->query('status');
        $future = $request->query('future');
        if ( empty($user_id) ) {
            $user_id = $request->auth->id;
        }

        $parentCond = array();
        $parentCond[] = ['user_id', $user_id];
        if ( !empty($status) ) {
            $parentCond[] = ['booking_status', $status];
        }
        if ( !empty($future) && $future == 'yes' ) {
            $parentCond[] = ['booking_date', '>=', date('Y-m-d')];
        } else {
            $parentCond[] = ['booking_date', '<', date('Y-m-d')];
        }

        return $this->allUserSessions(['gym', 'purchase'], $parentCond);
    }


    /**
     * @OA\Get(
     *     path="/getUserSessionTimer",
     *     summary="show user active session timer",
     *     tags={"Booking"},
     *     description="",
     *     @OA\Parameter(
     *         name="authkey",
     *         in="header",
     *         description="auth Key",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="auth token",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *           @OA\Items(type="string"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="user",
     *         required=false,
     *         @OA\Schema(
     *           type="integer",
     *           @OA\Items(type="integer"),
     *         ),
     *         style="form"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful get result",
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *     )
     * )
     */
    public function showUserSessionTimer(Request $request)
    {
        $user_id = $request->query('user_id');
        if ( empty($user_id) ) {
            $user_id = $request->auth->id;
        }

        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $timer = Booking::getBookedSessionTimer($user_id, $current_date, $current_time, BookingTypesInterface::SESSION, BookingStatusTypesInterface::SUCCESS);

        return $this->respond(Response::HTTP_OK, $timer);
    }
}
