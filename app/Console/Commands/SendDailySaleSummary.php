<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Business;
use Spatie\Browsershot\Browsershot;

class SendDailySaleSummary extends Command
{
    protected $signature   = 'telegram:send-daily-sale-summary';
    protected $description = 'Auto-send daily sale summary to Telegram as an image (based on telegram_schedules)';

    public function handle()
    {
        $now           = Carbon::now();
        $currentMinute = $now->format('H:i');
        $currentDay    = $now->format('D');   // e.g. "Mon", "Sat"

        // 1. Get all active scheduled (non-immediate) telegram rows for this minute
        $schedules = DB::table('telegram_schedules')
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->where('type', 'schedule')
                  ->orWhereNull('type');
            })
            ->whereRaw('DATE_FORMAT(schedule_time, "%H:%i") = ?', [$currentMinute])
            ->get();

        // 2. Filter by day-of-week
        $schedules = $schedules->filter(function ($s) use ($currentDay) {
            if (empty($s->send_days)) return true;
            $days = json_decode($s->send_days, true);
            return is_array($days) && in_array($currentDay, $days);
        });

        // 3. Filter by report type "daily_sale_summary"
        $schedules = $schedules->filter(function ($s) {
            if (empty($s->report_types)) return true;
            $types = json_decode($s->report_types, true);
            return is_array($types) && in_array('daily_sale_summary', $types);
        });

        // 4. Deduplicate by (business_id, chat_id): prefer the row that has back_days set.
        $byKey = [];
        foreach ($schedules as $s) {
            if (empty($s->chat_id)) continue;
            $key = $s->business_id . '_' . $s->chat_id;
            if (!isset($byKey[$key]) || (!empty($s->back_days) && empty($byKey[$key]->back_days))) {
                $byKey[$key] = $s;
            }
        }

        // 5. Per unique (business_id, chat_id): resolve back_days and send one report per date
        $processedCount = 0;
        foreach ($byKey as $schedule) {
            // How many days back to report for this weekday
            $backDaysMap = json_decode($schedule->back_days ?? '{}', true) ?? [];
            $backDays    = max(1, (int) ($backDaysMap[$currentDay] ?? 1));

            // Build date list: oldest first (today-backDays … today-1)
            for ($i = $backDays; $i >= 1; $i--) {
                $date = $now->copy()->subDays($i)->toDateString();
                $this->sendSummaryForBusiness((int) $schedule->business_id, [$schedule->chat_id], $date);
            }

            $processedCount++;
        }

        $this->info(
            "Checked at {$currentMinute} ({$currentDay}). Processed {$processedCount} schedule(s)."
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build report data + render snapshot + send to Telegram
    // ─────────────────────────────────────────────────────────────────────────
    private function sendSummaryForBusiness(int $business_id, array $chat_ids, string $date): void
    {
        $business = Business::find($business_id);

        // ── Section 1: Daily Sale Overview ──────────────────────────────────
        $sale_totals = DB::table('transactions as t')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', $date)
            ->selectRaw('COUNT(*) as total_invoices, COALESCE(SUM(t.final_total),0) as total_amount')
            ->first();

        $paid_result = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN tp.is_return=0 THEN tp.amount ELSE -tp.amount END),0) as paid_amount')
            ->first();

        $total_amount = $sale_totals->total_amount ?? 0;
        $paid_amount  = $paid_result->paid_amount  ?? 0;
        $due_amount   = max(0, $total_amount - $paid_amount);

        // ── Section 2: Payment & Collections ────────────────────────────────
        $payment_stats = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('tp.paid_on', $date)
            ->selectRaw("
                COUNT(DISTINCT tp.transaction_id) as collected_invoices,
                COALESCE(SUM(CASE WHEN tp.is_return=0 THEN tp.amount ELSE -tp.amount END),0) as total_received,
                COALESCE(SUM(CASE WHEN tp.is_return=0 AND DATE(t.transaction_date)=? THEN tp.amount
                                   WHEN tp.is_return=1 AND DATE(t.transaction_date)=? THEN -tp.amount
                                   ELSE 0 END),0) as today_sale_coll,
                COALESCE(SUM(CASE WHEN tp.is_return=0 AND DATE(t.transaction_date)!=? THEN tp.amount
                                   WHEN tp.is_return=1 AND DATE(t.transaction_date)!=? THEN -tp.amount
                                   ELSE 0 END),0) as ar_collected
            ", [$date, $date, $date, $date])
            ->first();

        // Collection by payment method
        $payment_by_method = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('tp.paid_on', $date)
            ->selectRaw('tp.method, COALESCE(SUM(CASE WHEN tp.is_return=0 THEN tp.amount ELSE -tp.amount END),0) as total')
            ->groupBy('tp.method')
            ->orderBy('total', 'desc')
            ->get();

        // Human-readable payment method labels (reads custom_labels from business)
        $custom_labels         = json_decode($business->custom_labels ?? '{}', true) ?? [];
        $payment_method_labels = [
            'cash'                 => 'Cash',
            'card'                 => 'Card',
            'cheque'               => 'Cheque',
            'bank_transfer'        => 'Bank Transfer',
            'other'                => 'Other',
            'cash_ring'            => 'Cash Ring',
            'cash_ring_percentage' => 'Cash Ring(%)',
            'custom_pay_1'         => $custom_labels['payments']['custom_pay_1'] ?? 'Custom Pay 1',
            'custom_pay_2'         => $custom_labels['payments']['custom_pay_2'] ?? 'Custom Pay 2',
            'custom_pay_3'         => $custom_labels['payments']['custom_pay_3'] ?? 'Custom Pay 3',
            'custom_pay_4'         => $custom_labels['payments']['custom_pay_4'] ?? 'Custom Pay 4',
            'custom_pay_5'         => $custom_labels['payments']['custom_pay_5'] ?? 'Custom Pay 5',
            'custom_pay_6'         => $custom_labels['payments']['custom_pay_6'] ?? 'Custom Pay 6',
            'custom_pay_7'         => $custom_labels['payments']['custom_pay_7'] ?? 'Custom Pay 7',
        ];

        // AR Collected Breakdown (payments today on old invoices, grouped by origin date)
        $ar_breakdown = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('tp.paid_on', $date)
            ->whereRaw('DATE(t.transaction_date) != ?', [$date])
            ->where('tp.is_return', 0)
            ->selectRaw('DATE(t.transaction_date) as origin_date, COUNT(DISTINCT t.id) as inv_count, SUM(tp.amount) as amount')
            ->groupBy(DB::raw('DATE(t.transaction_date)'))
            ->orderBy('origin_date', 'desc')
            ->get();

        // ── Outstanding AR Aging ─────────────────────────────────────────────
        $outstanding_raw = DB::table('transactions as t')
            ->leftJoin(DB::raw('(SELECT transaction_id,
                SUM(CASE WHEN is_return=0 THEN amount ELSE -amount END) as total_paid
                FROM transaction_payments GROUP BY transaction_id) as tp_agg'),
                't.id', '=', 'tp_agg.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->selectRaw('DATE(t.transaction_date) as t_date, t.final_total, COALESCE(tp_agg.total_paid,0) as total_paid_calc')
            ->get();

        $today_obj = Carbon::parse($date);
        $aging = [
            'current' => ['label' => 'Current (Today Due)', 'count' => 0, 'amount' => 0.0, 'color' => ''],
            '1_15'    => ['label' => '1 - 15 Days',         'count' => 0, 'amount' => 0.0, 'color' => 'warning'],
            '16_30'   => ['label' => '16 - 30 Days',        'count' => 0, 'amount' => 0.0, 'color' => 'orange'],
            'over_30' => ['label' => 'Over 30 Days',         'count' => 0, 'amount' => 0.0, 'color' => 'danger'],
        ];
        foreach ($outstanding_raw as $row) {
            $days      = Carbon::parse($row->t_date)->diffInDays($today_obj);
            $remaining = max(0, $row->final_total - $row->total_paid_calc);
            if ($days == 0)      { $aging['current']['count']++; $aging['current']['amount'] += $remaining; }
            elseif ($days <= 15) { $aging['1_15']['count']++;    $aging['1_15']['amount']    += $remaining; }
            elseif ($days <= 30) { $aging['16_30']['count']++;   $aging['16_30']['amount']   += $remaining; }
            else                 { $aging['over_30']['count']++; $aging['over_30']['amount'] += $remaining; }
        }
        $aging_total_count  = array_sum(array_column($aging, 'count'));
        $aging_total_amount = array_sum(array_column($aging, 'amount'));

        // ── Section 3: Daily Reward Exchange ────────────────────────────────
        $reward_summary = $this->buildRewardSummary($business_id, $date);

        // ── Render HTML snapshot & send via Browsershot ──────────────────────
        $html = view('report.daily_sale_snapshot', compact(
            'date', 'business',
            'sale_totals', 'total_amount', 'paid_amount', 'due_amount',
            'payment_stats', 'payment_by_method', 'payment_method_labels', 'ar_breakdown',
            'aging', 'aging_total_count', 'aging_total_amount',
            'reward_summary'
        ))->render();

        $fileName = 'dss_auto_' . $business_id . '_' . time() . '.png';
        $tempPath = '/tmp/' . $fileName;

        try {
            Browsershot::html($html)
                ->windowSize(480, 600)
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->noSandbox()
                ->save($tempPath);

            $botToken = config('services.telegram.bot_token', '8737726993:AAEd8C5uWwHu5cYc8YVH4zfpUwUxSWaplSc');
            $caption  = "📊 *Daily Sale Summary*\nDate: " . Carbon::parse($date)->format('d-M-Y')
                      . "\nBusiness: " . ($business->name ?? '');

            foreach ($chat_ids as $chat_id) {
                Http::attach('photo', file_get_contents($tempPath), $fileName)
                    ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id'    => $chat_id,
                        'caption'    => $caption,
                        'parse_mode' => 'Markdown',
                    ]);
            }

            if (file_exists($tempPath)) unlink($tempPath);

        } catch (\Exception $e) {
            $this->error("Telegram Error (business_id={$business_id}): " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build reward exchange summary (grouped by prize/receive_product)
    // ─────────────────────────────────────────────────────────────────────────
    private function buildRewardSummary(int $business_id, string $date): array
    {
        $reward_pfs_ids = DB::table('rewards_exchange')
            ->where('business_id', $business_id)
            ->where('type', 'customers')
            ->whereNull('deleted_at')
            ->pluck('product_for_sale')
            ->unique()->values()->toArray();

        if (empty($reward_pfs_ids)) return [];

        // Stock out: qty sold today per product_for_sale
        $stock_out_today = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', $date)
            ->whereIn('tsl.product_id', $reward_pfs_ids)
            ->selectRaw('tsl.product_id, SUM(tsl.quantity) as total_qty')
            ->groupBy('tsl.product_id')
            ->get()->keyBy('product_id');

        // Ring received: use Eloquent + transactionSellRingBalances (same as SalesOrderRewardController)
        $today_invoice_nos = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', $date)
            ->pluck('invoice_no');

        $ring_received_map = []; // [exchange_product_id => total_rings]
        if ($today_invoice_nos->isNotEmpty()) {
            $topUps = \App\TransactionRingBalance::whereIn('sell_ref_invoice', $today_invoice_nos)
                ->where('type', 'top_up_ring_balance')
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->with(['transactionSellRingBalances'])
                ->get();

            foreach ($topUps as $topUp) {
                foreach ($topUp->transactionSellRingBalances as $line) {
                    if (empty($line->cash_ring)) {
                        $pid = $line->product_id;
                        $ring_received_map[$pid] = ($ring_received_map[$pid] ?? 0) + $line->quantity;
                    }
                }
            }
        }

        // Group rewards_exchange by receive_product (prize) with prize name
        $prize_rows = DB::table('rewards_exchange as re')
            ->join('products as rp', 're.receive_product', '=', 'rp.id')
            ->where('re.business_id', $business_id)
            ->where('re.type', 'customers')
            ->whereNull('re.deleted_at')
            ->whereIn('re.product_for_sale', $reward_pfs_ids)
            ->select('re.receive_product', 'rp.name as prize_name', 're.product_for_sale', 're.exchange_product', 're.exchange_quantity')
            ->get();

        $prizes = [];
        foreach ($prize_rows as $row) {
            $pid = $row->receive_product;
            if (!isset($prizes[$pid])) {
                $prizes[$pid] = [
                    'prize_name'       => $row->prize_name,
                    'exchange_product' => $row->exchange_product,
                    'exchange_quantity' => (float) $row->exchange_quantity,
                    'pfs_ids'          => [],
                ];
            }
            $prizes[$pid]['pfs_ids'][] = $row->product_for_sale;
        }

        $reward_summary = [];
        foreach ($prizes as $prize) {
            $sold = 0;
            foreach ($prize['pfs_ids'] as $pfs_id) {
                $sold += $stock_out_today->get($pfs_id)->total_qty ?? 0;
            }
            if ($sold == 0) continue;

            $eq                = $prize['exchange_quantity'];
            $ep_id             = $prize['exchange_product'];
            $received_rings    = $ring_received_map[$ep_id] ?? 0;
            $outstanding_rings = max(0, ($sold * $eq) - $received_rings);

            // Format: X Case Y rings  (same logic as Daily Ring Top Up "X CTN Y rings")
            $r_cases    = $eq > 0 ? (int) floor($received_rings / $eq)    : 0;
            $r_leftover = $eq > 0 ? (int) round(fmod($received_rings, $eq))    : (int) $received_rings;
            $o_cases    = $eq > 0 ? (int) floor($outstanding_rings / $eq) : 0;
            $o_leftover = $eq > 0 ? (int) round(fmod($outstanding_rings, $eq)) : (int) $outstanding_rings;

            $reward_summary[] = [
                'product_name'              => $prize['prize_name'],
                'stock_out_qty'             => $sold,
                'ring_received'             => $received_rings,
                'received_cases_display'    => ($r_cases > 0 && $r_leftover > 0)
                                                ? "{$r_cases} Case {$r_leftover} rings"
                                                : ($r_cases > 0 ? "{$r_cases} Case" : "{$r_leftover} rings"),
                'outstanding_rings'         => $outstanding_rings,
                'outstanding_cases_display' => ($o_cases > 0 && $o_leftover > 0)
                                                ? "{$o_cases} Case {$o_leftover} rings"
                                                : ($o_cases > 0 ? "{$o_cases} Case" : "{$o_leftover} rings"),
            ];
        }

        return $reward_summary;
    }
}
