<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\ProductSaleTarget;
use App\TransactionSellLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductSaleTargetController extends ApiController
{
    /**
     * Helper: calculate progress_percent and progress_status.
     */
    private function calcProgress($target_value, $achieved_value): array
    {
        if ($target_value <= 0) {
            return ['progress_percent' => 0, 'progress_status' => 'danger'];
        }

        $percent = ($achieved_value / $target_value) * 100;
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
     * Helper: get achieved qty for a variation within a period for a user.
     * Counts qty_sold from final sell transactions created by the user.
     */
    private function getAchievedQty($business_id, $user_id, $variation_id, $start_date, $end_date): float
    {
        $baseQuery = function ($query) use ($business_id, $user_id, $start_date, $end_date) {
            $query->join('transactions', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->where('transactions.commission_agent', $user_id)
                ->whereBetween(DB::raw('DATE(transactions.transaction_date)'), [$start_date, $end_date]);
        };

        // 1. Normal single product lines — use their own qty
        $normal_qty = TransactionSellLine::where('transaction_sell_lines.variation_id', $variation_id)
            ->whereNull('transaction_sell_lines.children_type')
            ->tap($baseQuery)
            ->join('products', 'products.id', '=', 'transaction_sell_lines.product_id')
            ->where('products.type', '!=', 'combo')
            ->sum('transaction_sell_lines.quantity');

        // 2. Combo child lines — use the PARENT line qty (e.g. ABC Sale qty=2, not child stock-cut qty=48)
        $combo_qty = TransactionSellLine::where('transaction_sell_lines.variation_id', $variation_id)
            ->whereIn('transaction_sell_lines.children_type', ['combo', 'combo_single'])
            ->tap($baseQuery)
            ->join('transaction_sell_lines as parent_tsl', 'parent_tsl.id', '=', 'transaction_sell_lines.parent_sell_line_id')
            ->sum('parent_tsl.quantity');

        return (float) ($normal_qty + $combo_qty);
    }

    /**
     * Helper: get empty response when no target found.
     */
    private function emptyResponse(): array
    {
        return [
            'success' => true,
            'data' => [
                'target_type' => 'quantity',
                'unit_label'  => 'SKU',
                'summary' => [
                    'target_value'    => 0,
                    'achieved_value'  => 0,
                    'remaining_value' => 0,
                    'progress_percent' => 0,
                    'progress_status'  => 'danger',
                ],
                'items'  => [],
                'status' => 'none',
            ],
        ];
    }

    /**
     * GET /connector/api/mobile/sale-target/summary
     */
    public function summary(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;
        $user_id     = $user->id;
        $start_date  = $request->input('start_date');
        $end_date    = $request->input('end_date');

        if (! $start_date || ! $end_date) {
            return response()->json(['success' => false, 'msg' => 'start_date and end_date are required.'], 422);
        }

        $target = ProductSaleTarget::where('business_id', $business_id)
            ->where('user_id', $user_id)
            ->where('start_date', '<=', $start_date)
            ->where('end_date', '>=', $end_date)
            ->whereNull('deleted_at')
            ->with('details.variation')
            ->first();

        if (! $target) {
            return response()->json($this->emptyResponse());
        }

        $target_value   = 0;
        $achieved_value = 0;

        foreach ($target->details as $detail) {
            $target_value   += (float) $detail->target_qty;
            $achieved_value += $this->getAchievedQty($business_id, $user_id, $detail->variation_id, $target->start_date, $end_date);
        }

        $remaining_value = max(0, $target_value - $achieved_value);
        $progress        = $this->calcProgress($target_value, $achieved_value);

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => 'quantity',
                'unit_label'  => 'SKU',
                'period' => [
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                ],
                'summary' => [
                    'target_value'     => $target_value,
                    'achieved_value'   => $achieved_value,
                    'remaining_value'  => $remaining_value,
                    'progress_percent' => $progress['progress_percent'],
                    'progress_status'  => $progress['progress_status'],
                ],
                'status' => $target->status,
            ],
        ]);
    }

    /**
     * GET /connector/api/mobile/sale-target/detail
     */
    public function detail(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;
        $user_id     = $user->id;
        $start_date  = $request->input('start_date');
        $end_date    = $request->input('end_date');

        if (! $start_date || ! $end_date) {
            return response()->json(['success' => false, 'msg' => 'start_date and end_date are required.'], 422);
        }

        $target = ProductSaleTarget::where('business_id', $business_id)
            ->where('user_id', $user_id)
            ->where('start_date', '<=', $start_date)
            ->where('end_date', '>=', $end_date)
            ->whereNull('deleted_at')
            ->with(['details.product', 'details.variation'])
            ->first();

        if (! $target) {
            return response()->json($this->emptyResponse());
        }

        $total_target   = 0;
        $total_achieved = 0;
        $items          = [];

        foreach ($target->details as $detail) {
            $target_qty   = (float) $detail->target_qty;
            $achieved_qty = $this->getAchievedQty($business_id, $user_id, $detail->variation_id, $target->start_date, $end_date);
            $remaining    = max(0, $target_qty - $achieved_qty);
            $progress     = $this->calcProgress($target_qty, $achieved_qty);

            $total_target   += $target_qty;
            $total_achieved += $achieved_qty;

            $items[] = [
                'sku'              => optional($detail->variation)->sub_sku ?? '',
                'product_name'     => optional($detail->product)->name ?? '',
                'target_value'     => $target_qty,
                'achieved_value'   => $achieved_qty,
                'remaining_value'  => $remaining,
                'progress_percent' => $progress['progress_percent'],
                'progress_status'  => $progress['progress_status'],
            ];
        }

        $total_remaining = max(0, $total_target - $total_achieved);
        $total_progress  = $this->calcProgress($total_target, $total_achieved);

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => 'quantity',
                'unit_label'  => 'SKU',
                'period' => [
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                ],
                'summary' => [
                    'target_value'     => $total_target,
                    'achieved_value'   => $total_achieved,
                    'remaining_value'  => $total_remaining,
                    'progress_percent' => $total_progress['progress_percent'],
                    'progress_status'  => $total_progress['progress_status'],
                ],
                'items'  => $items,
                'status' => $target->status,
            ],
        ]);
    }
}
