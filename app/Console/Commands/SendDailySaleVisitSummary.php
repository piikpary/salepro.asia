<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Business;
use App\TransactionVisit;
use Spatie\Browsershot\Browsershot;

class SendDailySaleVisitSummary extends Command
{
    protected $signature   = 'telegram:send-daily-visit-summary';
    protected $description = 'Auto-send daily sale visit summary to Telegram as an image (based on telegram_schedules)';

    public function handle()
    {
        $now           = Carbon::now();
        $currentMinute = $now->format('H:i');
        $currentDay    = $now->format('D');

        $schedules = DB::table('telegram_schedules')
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->where('type', 'schedule')
                  ->orWhereNull('type');
            })
            ->whereRaw('DATE_FORMAT(schedule_time, "%H:%i") = ?', [$currentMinute])
            ->get();

        $schedules = $schedules->filter(function ($s) use ($currentDay) {
            if (empty($s->send_days)) return true;
            $allowedDays = json_decode($s->send_days, true);
            return is_array($allowedDays) && in_array($currentDay, $allowedDays);
        });

        $schedules = $schedules->filter(function ($s) {
            if (empty($s->report_types)) return true;
            $types = json_decode($s->report_types, true);
            return is_array($types) && in_array('sales_visit', $types);
        });

        $groupedSchedules = $schedules->groupBy('business_id');

        foreach ($groupedSchedules as $business_id => $businessSchedules) {
            $chatIds = $businessSchedules->pluck('chat_id')->filter()->unique()->values()->toArray();
            if (!empty($chatIds)) {
                $this->sendSummaryForBusiness($business_id, $chatIds);
            }
        }

        $this->info(
            "Checked at {$currentMinute} ({$currentDay}). " .
            "Processed " . $groupedSchedules->count() . " business(es)."
        );
    }

    private function sendSummaryForBusiness(int $business_id, array $chat_ids): void
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $business      = Business::find($business_id);
        $business_name = $business ? $business->name : 'Unknown Business';

        $todays_visits = TransactionVisit::leftJoin('users', 'transactions_visit.create_by', '=', 'users.id')
            ->where('transactions_visit.business_id', $business_id)
            ->whereDate('transactions_visit.transaction_date', $today)
            ->select('transactions_visit.*', 'users.username')
            ->get();

        $todays_visits_count = $todays_visits->count();
        $target_per_day      = 25;
        $sales_report        = [];
        $total_own           = 0;
        $total_other         = 0;

        foreach ($todays_visits as $visit) {
            $rep_id   = $visit->create_by;
            $rep_name = $visit->sale_rep ?: ($visit->username ?: 'Unknown');

            if (!isset($sales_report[$rep_id])) {
                $sales_report[$rep_id] = [
                    'name'              => $rep_name,
                    'target'            => $target_per_day,
                    'qty_visit'         => 0,
                    'own_product_qty'   => 0,
                    'other_product_qty' => 0,
                ];
            }

            $sales_report[$rep_id]['qty_visit'] += 1;
            $own_qty   = intval($visit->own_product   ?? 0);
            $other_qty = intval($visit->other_product ?? 0);
            $sales_report[$rep_id]['own_product_qty']   += $own_qty;
            $sales_report[$rep_id]['other_product_qty'] += $other_qty;
            $total_own   += $own_qty;
            $total_other += $other_qty;
        }

        $total_target = count($sales_report) > 0
            ? count($sales_report) * $target_per_day
            : $target_per_day;

        foreach ($sales_report as &$report) {
            $report['remaining'] = $report['qty_visit'] - $report['target'];
            $report['variance']  = $report['target'] > 0
                ? round(($report['qty_visit'] / $report['target']) * 100) : 0;
            $total_rep = $report['own_product_qty'] + $report['other_product_qty'];
            $report['own_pct']   = $total_rep > 0 ? round(($report['own_product_qty']   / $total_rep) * 100) : 0;
            $report['other_pct'] = $total_rep > 0 ? round(($report['other_product_qty'] / $total_rep) * 100) : 0;
        }
        unset($report);

        $total_products_overall = $total_own + $total_other;
        $overall_own_pct   = $total_products_overall > 0 ? round(($total_own   / $total_products_overall) * 100) : 0;
        $overall_other_pct = $total_products_overall > 0 ? round(($total_other / $total_products_overall) * 100) : 0;
        $overall_variance  = $total_target > 0 ? round(($todays_visits_count / $total_target) * 100) : 0;
        $overall_remaining = $todays_visits_count - $total_target;

        $yesterdays_visits       = TransactionVisit::where('business_id', $business_id)
            ->whereDate('transaction_date', $yesterday)->select('create_by')->get();
        $yesterdays_visits_count = $yesterdays_visits->count();
        $yesterday_unique_reps   = $yesterdays_visits->groupBy('create_by')->count();
        $yesterday_target        = $yesterday_unique_reps > 0
            ? $yesterday_unique_reps * $target_per_day : $target_per_day;
        $yesterday_variance      = $yesterday_target > 0
            ? round(($yesterdays_visits_count / $yesterday_target) * 100) : 0;
        $dod_change              = $overall_variance - $yesterday_variance;

        $mapping_data = $this->buildMappingData($business_id, $today);

        // Image 1: summary only (pass empty mapping_data so that section is hidden)
        $html1 = view('sales_visit.snapshot', array_merge(compact(
            'today', 'business_name', 'todays_visits_count', 'total_target',
            'overall_variance', 'overall_remaining', 'overall_own_pct',
            'overall_other_pct', 'sales_report', 'yesterdays_visits_count',
            'yesterday_target', 'yesterday_variance', 'dod_change'
        ), ['mapping_data' => []]))->render();

        // Image 2: mapping compare only (only rendered when data exists)
        $html2 = ! empty($mapping_data)
            ? view('sales_visit.snapshot_mapping', compact('today', 'business_name', 'mapping_data'))->render()
            : null;

        $ts        = time();
        $tempPath1 = '/tmp/auto_summary_' . $business_id . '_' . $ts . '_1.png';
        $tempPath2 = '/tmp/auto_summary_' . $business_id . '_' . $ts . '_2.png';

        try {
            Browsershot::html($html1)
                ->windowSize(1000, 600)
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->noSandbox()
                ->save($tempPath1);

            if ($html2) {
                Browsershot::html($html2)
                    ->windowSize(1000, 600)
                    ->fullPage()
                    ->waitUntilNetworkIdle()
                    ->noSandbox()
                    ->save($tempPath2);
            }

            $botToken = config('services.telegram.bot_token', '8737726993:AAEd8C5uWwHu5cYc8YVH4zfpUwUxSWaplSc');
            $caption1 = "📊 *Daily Sale Visit Summary*\nDate: " . $today->format('Y-m-d');
            $caption2 = "📊 *Mapping Product Compare*\nDate: " . $today->format('Y-m-d');

            foreach ($chat_ids as $chat_id) {
                Http::attach('photo', file_get_contents($tempPath1), basename($tempPath1))
                    ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id'    => $chat_id,
                        'caption'    => $caption1,
                        'parse_mode' => 'Markdown',
                    ]);

                if ($html2 && file_exists($tempPath2)) {
                    Http::attach('photo', file_get_contents($tempPath2), basename($tempPath2))
                        ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                            'chat_id'    => $chat_id,
                            'caption'    => $caption2,
                            'parse_mode' => 'Markdown',
                        ]);
                }
            }

            if (file_exists($tempPath1)) unlink($tempPath1);
            if (file_exists($tempPath2)) unlink($tempPath2);

        } catch (\Exception $e) {
            $this->error("Telegram Error (business_id={$business_id}): " . $e->getMessage());
        }
    }

    private function buildMappingData(int $business_id, $date): array
    {
        $visitIds = DB::table('transactions_visit')
            ->where('business_id', $business_id)
            ->whereDate('transaction_date', $date)
            ->pluck('id')
            ->toArray();

        if (empty($visitIds)) return [];

        $lineData = DB::table('transaction_sell_lines_visit')
            ->whereIn('transaction_id', $visitIds)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, kind_product, SUM(quantity) as total_qty')
            ->groupBy('product_id', 'kind_product')
            ->get();

        $ownQtys   = [];
        $otherQtys = [];
        foreach ($lineData as $row) {
            if ((int) $row->kind_product === 0) {
                $ownQtys[$row->product_id]   = (float) $row->total_qty;
            } else {
                $otherQtys[$row->product_id] = (float) $row->total_qty;
            }
        }

        if (empty($ownQtys)) return [];

        $ownProductRows = DB::table('products')
            ->whereIn('id', array_keys($ownQtys))
            ->select(['id', 'name', 'assigned_competitors_product'])
            ->get()
            ->keyBy('id');

        $allCompetitorIds = [];
        foreach ($ownProductRows as $p) {
            $ids = json_decode($p->assigned_competitors_product ?? '[]', true) ?? [];
            $allCompetitorIds = array_merge($allCompetitorIds, $ids);
        }
        $allCompetitorIds = array_values(array_unique(array_filter($allCompetitorIds)));

        $competitorNames = [];
        if (!empty($allCompetitorIds)) {
            $competitorNames = DB::table('products')
                ->whereIn('id', $allCompetitorIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        $groups = [];
        foreach ($ownProductRows as $ownId => $ownProduct) {
            $ownQty        = $ownQtys[$ownId] ?? 0;
            $competitorIds = array_filter(json_decode($ownProduct->assigned_competitors_product ?? '[]', true) ?? []);

            if (empty($competitorIds)) continue;

            $competitors        = [];
            $totalCompetitorQty = 0;

            foreach ($competitorIds as $compId) {
                $compQty = $otherQtys[$compId] ?? 0;
                $diff    = $ownQty - $compQty;
                $win     = $diff > 0;
                $result  = ($win ? 'WIN' : ($diff < 0 ? 'LOSE' : 'DRAW'))
                         . ' ' . (int) $ownQty . ' vs ' . (int) $compQty;

                $competitors[] = [
                    'name'   => $competitorNames[$compId] ?? 'Unknown',
                    'qty'    => $compQty,
                    'result' => $result,
                    'win'    => $win,
                ];
                $totalCompetitorQty += $compQty;
            }

            $marketSize = $ownQty + $totalCompetitorQty;
            $ownPct     = $marketSize > 0 ? round(($ownQty / $marketSize) * 100) : 100;
            $otherPct   = 100 - $ownPct;

            $groups[] = [
                'own_name'             => $ownProduct->name,
                'own_qty'              => $ownQty,
                'competitors'          => $competitors,
                'total_competitor_qty' => $totalCompetitorQty,
                'market_size'          => $marketSize,
                'own_pct'              => $ownPct,
                'other_pct'            => $otherPct,
                'result'               => $ownQty > $totalCompetitorQty ? 'WIN' : 'LOSE',
            ];
        }

        return $groups;
    }
}