<?php

namespace App\Http\Controllers\Reserve;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingCollection;
use App\Http\Requests\SampleUpdateBookingStatusRequest;
use App\Mail\BookingCancelMail;
use App\Mail\NotificationMail;
use App\Mail\BookingForceCancelMail;
use App\Models\SampleBooking;
use App\Models\SampleSchedule;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SampleBookingController extends Controller
{
    /**
     * Sample schedule.
     *
     * @OA\Get(
     *      path="/v1/auth/・・・/{sample_schedule_id}",
     *      description="Retrieve list of bookings for a specific schedule.",
     *      security={
     *          {"token": {}},
     *          {"hogehoge_id": {}},
     *      },
     *      tags={"Sample Booking"},
     *      @OA\Parameter(
     *          in="path",
     *          name="sample_schedule_id",
     *          description="SAMPLE Schedule ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              allOf={
     *                  @OA\Schema(
     *                      @OA\Items(ref="#/components/schemas/BookingList")
     *                  )
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not Found"
     *      )
     * )
     *
     * @param \App\Models\SampleSchedule $sample_schedule
     * @return \Illuminate\Http\Response
     */
    public function index(SampleSchedule $sample_schedule)
    {
        $this->authorize('view_sample_schedule', $sample_schedule);

        $sample_bookings = SampleBooking::where('sample_schedule_id', $sample_schedule->id)
            ->with('fugafuga')
            ->whereHas('sample', function ($q) {
                $q->where('sample_foo');
            })
            ->get();

        return new BookingCollection($sample_bookings, $sample_schedule);
    }

    /**
     * Sample update booking status.
     *
     * @OA\Put(
     *      path="/v1/auth/・・・/{booking_id}/hoge",
     *      description="SAMPLE Update the status.",
     *      security={
     *          {"token": {}},
     *          {"hogefuga_id": {}},
     *      },
     *      tags={"Sample Booking"},
     *      @OA\Parameter(
     *          in="path",
     *          name="sample_booking_id",
     *          description="SAMPLE Booking ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="status",
     *                  type="string",
     *                  enum={"cancel", "force_cancel"}
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Status updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="result",
     *                  type="boolean",
     *                  example=true
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unable to update status",
     *      ),
     * )
     * @param \App\Models\SampleBooking $booking
     * @return \Illuminate\Http\Response
     */
    public function updateBookingStatus(SampleUpdateBookingStatusRequest $request, SampleBooking $sample_booking)
    {
        $this->authorize('update_sample_booking', $sample_booking);

        try {
            $sample_booking->status = $request->input('status');
            $sample_booking->updated_at = now();
            //now()ではなくtouch()でもいいかも
            $sample_booking->save();
        } catch (Exception $e) {
            Log::error('Booking status update failed', ['error' => $e->getMessage()]);
            // エラーメッセージを統一するためにローカライズを利用して、多言語対応が将来的にしやすくなるようにする
            // return response()->json(['error' => 'Unable to update booking status.', 'message' => $e->getMessage()], 500);
            return response()->json(['error' => __('messages.update_failed')], 500);
        }

        // メール通知
        if ($sample_booking->user_email || $sample_booking->user_id) {
            switch ($sample_booking->status) {
                case 'cancel':
                    Mail::queue(new BookingCancelMail($sample_booking));
                    break;
                case 'force_cancel':
                    Mail::queue(new BookingForceCancelMail($sample_booking));
                    break;
            }
        }

    //条件分岐をシンプルにmatchを使うと、よりスッキリ書ける（PHP 8.0+)
    /*
        if ($sample_booking->user_email || $sample_booking->user_id) {
            $mailClass = match ($sample_booking->status) {
                'cancel' => BookingCancelMail::class,
                'force_cancel' => BookingForceCancelMail::class,
                default => null,
            };

            if ($mailClass) {
                Mail::queue(new $mailClass($sample_booking));
            }
        }
    */

        // 通知メールの送信（予約の状態変更に基づく）
        if ($sample_booking->fugahoge && $sample_booking->fugahoge->email) {
            $upcoming_bookings = SampleBooking::getUpcomingBookings($sample_booking->fugahoge_id)
                ->orderBy('start_date', 'asc')
                ->limit(10)
                ->get();

            switch ($sample_booking->status) {
                case 'cancel':
                case 'force_cancel':
                    Mail::queue(new NotificationMail($sample_booking, $upcoming_bookings));
                    break;
            }
        }

    //matchでリファクタ
    /*
    ?->（nullセーフ演算子）を使用し、fugahoge がnullでもエラーにならないようにする。
    in_array()を使ってcase分岐を削減する。
    */

    /*
        if ($sample_booking->fugahoge?->email) {
            $upcoming_bookings = SampleBooking::getUpcomingBookings($sample_booking->fugahoge_id)
                ->orderBy('start_date', 'asc')
                ->limit(10)
                ->get();

            if (in_array($sample_booking->status, ['cancel', 'force_cancel'])) {
                Mail::queue(new NotificationMail($sample_booking, $upcoming_bookings));
                }
        }
    */

        return response()->json(['result' => true]);
    }
}
