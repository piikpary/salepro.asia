<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\TransactionVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitPlanController extends ApiController
{
    /**
     * Daily target — same hardcoded value used in SalesOrderVisitController & SendDailySaleVisitSummary.
     */
    const DAILY_TARGET = 25;

    /**
     * Helper: calculate progress_percent and progress_status.
     */
    private function calcProgress(int $target_value, int $visited_value): array
    {
        if ($target_value <= 0) {
            return ['progress_percent' => 0, 'progress_status' => 'danger'];
        }

        $percent = ($visited_value / $target_value) * 100;
        $percent = min(round($percent, 2), 100);

        if ($percent >= 80) {
            $status = 'success';
        } elseif ($percent >= 50) {
            $status = 'warning';
        } else {
            $status = 'danger';
        }

        return ['progress_percent' => $percent, 'progress_status' => $status];
    }

    /**
     * GET /connector/api/mobile/visit-plan/summary?date=2026-04-10
     *
     * Returns the authenticated salesperson's daily visit target progress for the given date.
     * - daily_target  = fixed 25 per day (resets every day, NOT cumulative)
     * - target_value  = daily_target (same, no multiplication)
     * - visited_value = count of visits completed by this user on the given date
     * - remaining_value = max(0, target_value - visited_value)
     */
    public function summary(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;
        $user_id     = $user->id;
        $date        = $request->input('date');

        // Validate date param
        if (!$date) {
            return response()->json([
                'success' => false,
                'msg'     => 'date is required. Format: YYYY-MM-DD',
            ], 422);
        }

        $daily_target = self::DAILY_TARGET;

        // Count visits completed by this salesperson on the selected date
        $visited_value = TransactionVisit::where('business_id', $business_id)
            ->where('create_by', $user_id)
            ->whereDate('transaction_date', $date)
            ->count();

        $target_value    = $daily_target;
        $remaining_value = max(0, $target_value - $visited_value);
        $progress        = $this->calcProgress($target_value, $visited_value);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'    => $date,
                'summary' => [
                    'daily_target'     => $daily_target,
                    'target_value'     => $target_value,
                    'visited_value'    => $visited_value,
                    'remaining_value'  => $remaining_value,
                    'progress_percent' => $progress['progress_percent'],
                    'progress_status'  => $progress['progress_status'],
                ],
                'status' => 'active',
            ],
        ]);
    }
}
