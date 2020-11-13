<?php

namespace App\Http\Controllers;

use App\Enumeration\PurchaseStatusTypesInterface;
use App\Model\Purchase;
use App\Model\Setting;
use App\Repositories\Interfaces\PurchaseRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade as PDF;

class PurchasesController extends Controller
{

    private $purchaseRepository;
    const MODEL = "App\Model\Purchase";
    use RESTActions {
        getRelationalData as allUserPurchases;
        getRelationalDataModel as allUserPurchasesData;
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(PurchaseRepositoryInterface $purchaseRepository)
    {
        $this->purchaseRepository = $purchaseRepository;
    }

    /**
     * @OA\Post(
     *     path="/new_subscription",
     *     summary="user want to subscribe credits to use in return for gym sessions",
     *     tags={"Purchases"},
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
     *     ),@OA\Parameter(
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
     *                  required={"credit_id","card_no","ccExpiryMonth","ccExpiryYear","cvvNumber"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="credit_id",
     *                     type="string"
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
    public function new_subscription(Request $request)
    {
        return $this->purchaseRepository->new_subscription($request);
    }

    /**
     * @OA\Post(
     *     path="/change_subscription",
     *     summary="user want to change subscription credits to use in return for gym sessions",
     *     tags={"Purchases"},
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
     *     ),@OA\Parameter(
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
     *                  required={"credit_id","card_no","ccExpiryMonth","ccExpiryYear","cvvNumber"},
     *                  type="object",
     *                  @OA\Property(
     *                      property="credit_id",
     *                     type="string"
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
    public function change_subscription(Request $request)
    {
        return $this->purchaseRepository->switch_subscription($request);
    }

    public function bulk_change_subscription(Request $request)
    {
        return $this->purchaseRepository->bulk_switch_subscription($request);
    }

}
