<?php

namespace App\Services;

use App\Plan;
use App\PlanItem;
use App\Product;
use App\ProductSaleTarget;
use App\SmartCallPlanTask;
use App\SmartCallPlanTaskLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SmartCallPlanService
{
    public function getCurrentTarget(?int $businessId, ?int $locationId, ?int $userId, ?Carbon $today = null): ?ProductSaleTarget
    {
        $today = $today ?: now();

        if (!$businessId) {
            return null;
        }

        return ProductSaleTarget::with(['details'])
            ->where('business_id', $businessId)
            ->when($locationId, function ($query) use ($locationId) {
                $query->where(function ($q) use ($locationId) {
                    $q->whereNull('location_id')
                        ->orWhere('location_id', $locationId);
                });
            })
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereHas('details', function ($q) {
                $q->whereNotNull('product_id')
                    ->where('target_qty', '>', 0);
            })
            ->latest('id')
            ->first();
    }

    public function getCurrentTargets(?int $businessId, ?int $locationId, ?int $userId, ?Carbon $today = null): Collection
    {
        $today = $today ?: now();

        if (!$businessId) {
            return collect();
        }

        return ProductSaleTarget::with(['details'])
            ->where('business_id', $businessId)
            ->when($locationId, function ($query) use ($locationId) {
                $query->where(function ($q) use ($locationId) {
                    $q->whereNull('location_id')
                        ->orWhere('location_id', $locationId);
                });
            })
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereHas('details', function ($q) {
                $q->whereNotNull('product_id')
                    ->where('target_qty', '>', 0);
            })
            ->latest('id')
            ->get();
    }

    public function buildDashboard(ProductSaleTarget $target, Carbon $today): array
    {
        $target->loadMissing('details');

        $productIds = $target->details->pluck('product_id')->filter()->unique()->values();
        $variationIds = $target->details->pluck('variation_id')->filter()->unique()->values();

        $productNames = $this->productDisplayNames($productIds, $variationIds);

        $totalTarget = 0;
        $expectedToday = 0;
        $actualSold = 0;
        $rows = [];

        $periodStart = Carbon::parse($target->start_date);
        $periodEnd = Carbon::parse($target->end_date);
        $actualEndDate = $today->greaterThan($periodEnd) ? $periodEnd : $today;

        foreach ($target->details as $item) {
            if (!$item->product_id || (float) $item->target_qty <= 0) {
                continue;
            }

            $targetQty = (float) $item->target_qty;

            $expected = $this->expectedUntilDate($targetQty, $periodStart, $periodEnd, $today);

            $actual = $this->getActualSold(
                $target->business_id,
                $target->location_id,
                null,
                (int) $item->product_id,
                $item->variation_id ? (int) $item->variation_id : null,
                $periodStart,
                $actualEndDate
            );

            $gap = max(0, round($expected - $actual, 2));

            $key = $this->productKey(
                (int) $item->product_id,
                $item->variation_id ? (int) $item->variation_id : null
            );

            $rows[] = [
                'product_id'    => (int) $item->product_id,
                'variation_id'  => $item->variation_id ? (int) $item->variation_id : null,
                'product_name'  => $productNames[$key] ?? 'Unknown Product',
                'target'        => round($targetQty, 2),
                'expected'      => round($expected, 2),
                'actual'        => round($actual, 2),
                'gap'           => round($gap, 2),
                'status'        => $this->determineStatus($expected, $actual),
                'status_label'  => $this->determineStatusLabel($expected, $actual),
                'status_class'  => $this->determineStatusClass($expected, $actual),
                'need_per_day'  => $this->needPerRemainingDay($targetQty, $actual, $today, $periodEnd),
            ];

            $totalTarget += $targetQty;
            $expectedToday += $expected;
            $actualSold += $actual;
        }

        $gapMissing = max(0, round($expectedToday - $actualSold, 2));

        $rowsCollection = collect($rows)
            ->sortByDesc(fn ($row) => ($row['gap'] ?? 0) > 0 ? $row['gap'] : 0)
            ->values();

        $aiRecommendation = '';

            return [
                'total_target'        => round($totalTarget, 2),
                'expected_today'      => round($expectedToday, 2),
                'actual_sold'         => round($actualSold, 2),
                'gap_missing'         => round($gapMissing, 2),
                'product_performance' => $rowsCollection,
                'ai_recommendation'   => $aiRecommendation,
                'hit_rate'            => $totalTarget > 0 ? round(($actualSold / $totalTarget) * 100, 2) : 0,
            ];
    }
   protected function aiRankedUrgentCustomersForAutoPlan(
    ProductSaleTarget $target,
    Carbon $today,
    array $criticalProduct,
    array $summary,
    int $candidateLimit = 100
): Collection {
    $businessId = $target->business_id;
    $locationId = $target->location_id;

    $productId = (int) ($criticalProduct['product_id'] ?? 0);
    $variationId = !empty($criticalProduct['variation_id'])
        ? (int) $criticalProduct['variation_id']
        : null;

    if ($productId <= 0) {
        return collect();
    }

    $candidates = $this->suggestCustomers(
        $businessId,
        $locationId,
        $productId,
        $variationId,
        max(10, min($candidateLimit, 30)),
        'combined_customers'
    );

    if ($candidates->isEmpty()) {
        return collect();
    }

    $aiPayload = [
    'date' => $today->toDateString(),
    'business_id' => $businessId,
    'location_id' => $locationId,

    'target_summary' => [
        'total_target'    => (float) ($summary['total_target'] ?? 0),
        'expected_today'  => (float) ($summary['expected_today'] ?? 0),
        'actual_sold'     => (float) ($summary['actual_sold'] ?? 0),
        'gap_missing'     => (float) ($summary['gap_missing'] ?? 0),
    ],

    'critical_product' => [
        'product_id'       => $productId,
        'variation_id'     => $variationId,
        'product_name'     => $criticalProduct['product_name'] ?? null,
        'target'           => (float) ($criticalProduct['target'] ?? 0),
        'expected_today'   => (float) ($criticalProduct['expected'] ?? 0),
        'actual_sold'      => (float) ($criticalProduct['actual'] ?? 0),
        'gap'              => (float) ($criticalProduct['gap'] ?? 0),
        'status'           => $criticalProduct['status_label'] ?? null,
    ],

    'customer_candidates' => $candidates->map(function ($customer) {
        $lastOrderDate = $customer->last_order_date ?? null;

        return [
            'contact_id'        => (int) $customer->id,
            'name'              => $customer->name,
            'phone'             => $customer->phone ?? $customer->mobile ?? null,
            'current_segment'   => $customer->customer_segment ?? null,
            'last_order_date'   => $lastOrderDate,
            'days_since_order'  => $lastOrderDate
                ? Carbon::parse($lastOrderDate)->diffInDays(now())
                : 9999,
            'buying_days'       => (int) ($customer->buy_days ?? 0),
            'total_qty'         => (float) ($customer->total_qty ?? 0),
        ];
    })->values()->toArray(),
];

$cacheKey = 'scp_auto_ai_' 
    . $businessId . '_' 
    . ($locationId ?? 'all') . '_' 
    . $target->id . '_' 
    . $productId . '_' 
    . $today->toDateString();

$aiSelected = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($aiPayload) {
    return app(SmartCallPlanAiRecommendationService::class)->rankUrgentCustomers($aiPayload);
});

    if (empty($aiSelected)) {
        return collect();
    }

    $candidateMap = $candidates->keyBy('id');

    return collect($aiSelected)
        ->filter(fn ($row) => !empty($row['contact_id']) && $candidateMap->has((int) $row['contact_id']))
        ->map(function ($row) use ($candidateMap) {
            $customer = clone $candidateMap->get((int) $row['contact_id']);

            $customer->ai_task_type = in_array(($row['task_type'] ?? 'call'), ['call', 'visit'], true)
                ? $row['task_type']
                : 'call';

            $customer->ai_customer_type = $row['customer_type'] ?? 'urgent_follow_up';
            $customer->ai_priority_level = $row['priority_level'] ?? 'high';
            $customer->ai_note = $row['ai_note']
                ?? 'AI បានជ្រើសអតិថិជននេះសម្រាប់ទាក់ទងបន្ទាន់ថ្ងៃនេះ។';

            return $customer;
        })
        ->values();
}
    public function buildDashboardFromTargets(Collection $targets, Carbon $today): array
    {
        $targets->loadMissing('details');

        $today = $today->copy()->startOfDay();

        $allItems = $targets->flatMap(function ($target) {
            return $target->details->map(function ($item) use ($target) {
                return [
                    'target_id'    => $target->id,
                    'business_id'  => $target->business_id,
                    'location_id'  => $target->location_id,
                    'assigned_to'  => $target->assigned_to,
                    'product_id'   => $item->product_id,
                    'variation_id' => $item->variation_id ?? null,
                    'target_qty'   => (float) $item->target_qty,
                    'start_date'   => $target->start_date,
                    'end_date'     => $target->end_date,
                ];
            });
        })->filter(function ($row) {
            return !empty($row['product_id'])
                && !empty($row['start_date'])
                && !empty($row['end_date'])
                && (float) $row['target_qty'] > 0;
        })->values();

        if ($allItems->isEmpty()) {
            return [
                'total_target'        => 0,
                'expected_today'      => 0,
                'actual_sold'         => 0,
                'gap_missing'         => 0,
                'product_performance' => collect(),
                'ai_recommendation'   => '',
                'hit_rate'            => 0,
            ];
        }

        $productIds = $allItems->pluck('product_id')->unique()->values();
        $businessId = $targets->first()->business_id ?? null;

        $productNames = DB::connection('mysql')
            ->table('products')
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $periodStartDate = $allItems
            ->pluck('start_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->min();

        $periodEndDate = $allItems
            ->pluck('end_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->max();

        $periodStartCarbon = $periodStartDate ? Carbon::parse($periodStartDate)->startOfDay() : $today->copy();
        $periodEndCarbon = $periodEndDate ? Carbon::parse($periodEndDate)->startOfDay() : $today->copy();
        $actualEndDate = $today->greaterThan($periodEndCarbon) ? $periodEndCarbon->copy() : $today->copy();

        $actualSoldByProduct = DB::connection('mysql')
            ->table('transactions as t')
            ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->when($businessId, function ($q) use ($businessId) {
                $q->where('t.business_id', $businessId);
            })
            ->whereIn('tsl.product_id', $productIds)
            ->whereDate('t.transaction_date', '>=', $periodStartCarbon->toDateString())
            ->whereDate('t.transaction_date', '<=', $actualEndDate->toDateString())
            ->selectRaw('tsl.product_id, COALESCE(SUM(tsl.quantity), 0) as total_qty')
            ->groupBy('tsl.product_id')
            ->pluck('total_qty', 'product_id');

        $productPerformance = $allItems
            ->groupBy('product_id')
            ->map(function ($rows, $productId) use ($productNames, $actualSoldByProduct, $today) {
                $targetQty = round((float) $rows->sum('target_qty'), 2);
                $expectedToday = 0.0;
                $totalDaysForDisplay = 0;
                $elapsedDaysForDisplay = 0;

                foreach ($rows as $row) {
                    $targetQtyRow = (float) ($row['target_qty'] ?? 0);
                    $start = Carbon::parse($row['start_date'])->startOfDay();
                    $end = Carbon::parse($row['end_date'])->startOfDay();
                    $currentDate = $today->copy()->startOfDay();

                    $totalDays = max(1, $start->diffInDays($end) + 1);

                    if ($currentDate->lessThan($start)) {
                        $elapsedDays = 0;
                    } elseif ($currentDate->greaterThan($end)) {
                        $elapsedDays = $totalDays;
                    } else {
                        $elapsedDays = max(1, $start->diffInDays($currentDate) + 1);
                    }

                    $elapsedDays = min($elapsedDays, $totalDays);

                    $expectedToday += ($targetQtyRow / $totalDays) * $elapsedDays;

                    $totalDaysForDisplay = max($totalDaysForDisplay, $totalDays);
                    $elapsedDaysForDisplay = max($elapsedDaysForDisplay, $elapsedDays);
                }

                $actual = round((float) ($actualSoldByProduct[$productId] ?? 0), 2);
                $expectedToday = round($expectedToday, 2);
                $gap = round(max(0, $expectedToday - $actual), 2);

                if ($expectedToday <= 0) {
                    $statusLabel = 'No Target Today';
                    $statusClass = 'info';
                } elseif ($gap <= 0) {
                    $statusLabel = 'On Track';
                    $statusClass = 'success';
                } elseif ($gap <= ($expectedToday * 0.3)) {
                    $statusLabel = 'Warning';
                    $statusClass = 'info';
                } else {
                    $statusLabel = 'Critical';
                    $statusClass = 'danger';
                }

                return [
                    'product_id'    => (int) $productId,
                    'product_name'  => $productNames[$productId] ?? 'Unknown Product',
                    'target'        => $targetQty,
                    'expected'      => $expectedToday,
                    'actual'        => $actual,
                    'gap'           => $gap,
                    'status_label'  => $statusLabel,
                    'status_class'  => $statusClass,
                    'total_days'    => $totalDaysForDisplay,
                    'elapsed_days'  => $elapsedDaysForDisplay,
                ];
            })
            ->sortByDesc('gap')
            ->values();

        $totalTarget = round($productPerformance->sum('target'), 2);
        $expectedToday = round($productPerformance->sum('expected'), 2);
        $actualSold = round($productPerformance->sum('actual'), 2);
        $gapMissing = round($productPerformance->sum('gap'), 2);

        $aiRecommendation = '';
        return [
            'total_target'        => round($totalTarget, 2),
            'expected_today'      => round($expectedToday, 2),
            'actual_sold'         => round($actualSold, 2),
            'gap_missing'         => round($gapMissing, 2),
            'product_performance' => $productPerformance,
            'ai_recommendation'   => $aiRecommendation,
            'hit_rate'            => $totalTarget > 0 ? round(($actualSold / $totalTarget) * 100, 2) : 0,
        ];
    }

    public function aiRecommendationHtmlFromSummary(ProductSaleTarget $target, array $summary, Carbon $today): string
{
    $target->loadMissing('details');

    $periodStart = Carbon::parse($target->start_date)->startOfDay();
    $periodEnd = Carbon::parse($target->end_date)->startOfDay();

    $products = collect($summary['product_performance'] ?? collect())
        ->map(function ($row) {
            return [
                'product_id' => $row['product_id'] ?? null,
                'variation_id' => $row['variation_id'] ?? null,
                'product_name' => $row['product_name'] ?? null,
                'target' => (float) ($row['target'] ?? 0),
                'expected_today' => (float) ($row['expected'] ?? 0),
                'actual_sold' => (float) ($row['actual'] ?? 0),
                'gap' => (float) ($row['gap'] ?? 0),
                'need_per_day' => (float) ($row['need_per_day'] ?? 0),
                'status' => $row['status_label'] ?? null,
            ];
        })
        ->values()
        ->toArray();

    return $this->aiRecommendationHtml([
        'mode' => 'single_sale_target',
        'date' => $today->toDateString(),
        'day' => max(1, $periodStart->diffInDays($today->copy()->startOfDay()) + 1),
        'total_days' => max(1, $periodStart->diffInDays($periodEnd) + 1),
        'remaining_days' => max(1, $today->copy()->startOfDay()->diffInDays($periodEnd, false) + 1),
        'total_target' => (float) ($summary['total_target'] ?? 0),
        'expected_today' => (float) ($summary['expected_today'] ?? 0),
        'actual_sold' => (float) ($summary['actual_sold'] ?? 0),
        'gap_missing' => (float) ($summary['gap_missing'] ?? 0),
        'products' => $products,
    ]);
}

    public function getBoardTasks(?int $businessId, ?int $locationId, ?int $assigneeId, Carbon $today): Collection
{
    $rows = DB::connection('mysql')
        ->table('plan_items as pi')
        ->join('plans as p', 'p.id', '=', 'pi.plan_id')
        ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
        ->leftJoin('users as u', 'u.id', '=', 'pi.salesperson_id')
        ->leftJoin('transactions_visit as tv', function ($join) {
            $join->on('tv.contact_id', '=', 'pi.contact_id')
                ->on('tv.create_by', '=', 'pi.salesperson_id')
                ->whereRaw('DATE(tv.transaction_date) = DATE(p.plan_date)');
        })

        // Today + tomorrow + future show, past hide
        ->whereDate('p.plan_date', '>=', $today->toDateString())

        ->when($businessId, function ($q) use ($businessId) {
            $q->where('c.business_id', $businessId);
        })
        ->select([
            'pi.id',
            'pi.plan_id',
            'pi.contact_id',
            'pi.salesperson_id',
            'pi.priority_level',
            'pi.item_status',
            'pi.result',
            'pi.notes',
            'pi.followup_date',
            'pi.ai_note',
            'pi.updated_at',

            'p.plan_type',
            'p.title as plan_title',
            'p.plan_date',

            'tv.id as visit_transaction_id',
            'tv.transaction_date as visit_transaction_date',

            'c.name as customer_name',
            'c.supplier_business_name',
            'c.mobile',
            'c.alternate_number',

            'u.first_name',
            'u.surname',
            'u.username',

            DB::raw("
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT COALESCE(
                            NULLIF(TRIM(CONCAT(COALESCE(u2.first_name, ''), ' ', COALESCE(u2.surname, ''))), ''),
                            u2.username,
                            CONCAT('User #', pi2.salesperson_id)
                        )
                        SEPARATOR ', '
                    )
                    FROM plan_items pi2
                    LEFT JOIN users u2 ON u2.id = pi2.salesperson_id
                    WHERE pi2.plan_id = pi.plan_id
                    AND pi2.contact_id = pi.contact_id
                ) as assigned_names_text
            "),

            DB::raw("
                (
                    SELECT GROUP_CONCAT(DISTINCT pi2.salesperson_id SEPARATOR ',')
                    FROM plan_items pi2
                    WHERE pi2.plan_id = pi.plan_id
                    AND pi2.contact_id = pi.contact_id
                ) as assigned_ids_text
            "),
        ])

        // Today first, then tomorrow, then future
        ->orderBy('p.plan_date', 'asc')
        ->orderByRaw("
            CASE pi.priority_level
                WHEN 'high' THEN 1
                WHEN 'med' THEN 2
                ELSE 3
            END
        ")
        ->latest('pi.id')
        ->get();

    $groupedRows = $rows
        ->groupBy(function ($row) {
            return $row->plan_id . '_' . $row->contact_id;
        })
        ->map(function ($group) {
            $row = $group->first();

            $hasVisitTransaction = $group->contains(function ($item) {
                return !empty($item->visit_transaction_id);
            });

            $boardStatus = match (true) {
                $row->plan_type === 'visit' && $hasVisitTransaction => 'completed',
                $group->contains(fn ($item) => $item->item_status === 'completed') => 'completed',
                $group->contains(fn ($item) => $item->item_status === 'followup') => 'follow_up',
                $group->contains(fn ($item) => $item->item_status === 'skipped') => 'skipped',
                default => 'todo',
            };

            $priority = match ($row->priority_level) {
                'high' => 'high',
                'med'  => 'medium',
                default => 'low',
            };

            $customerName = trim((string) $row->customer_name);

            if ($customerName === '') {
                $customerName = trim((string) $row->supplier_business_name);
            }

            if ($customerName === '') {
                $customerName = 'Customer #' . $row->contact_id;
            }

            $phone = trim((string) $row->mobile);

            if ($phone === '') {
                $phone = trim((string) $row->alternate_number);
            }

            if ($phone === '') {
                $phone = '-';
            }

            $assignedNames = collect(explode(',', $row->assigned_names_text ?? ''))
                ->map(fn ($name) => trim($name))
                ->filter()
                ->unique()
                ->values();

            $assignedIds = collect(explode(',', $row->assigned_ids_text ?? ''))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->unique()
                ->values();

            return (object) [
                'id'              => $row->id,
                'plan_item_ids'   => $group->pluck('id')->values()->all(),

                'plan_id'         => $row->plan_id,
                'contact_id'      => $row->contact_id,

                'assigned_to'     => $row->salesperson_id,
                'assigned_to_ids' => $assignedIds->all(),

                'plan_date'       => $row->plan_date,
                'task_type'       => $row->plan_type ?: 'call',
                'board_status'    => $boardStatus,
                'priority'        => $priority,
                'result'          => $row->result,
                'callback_at'     => $row->followup_date,
                'completed_at'    => $boardStatus === 'completed'
                    ? ($row->visit_transaction_date ?? $row->updated_at)
                    : null,

                'ai_reason'       => $row->plan_title,
                'ai_action'       => $row->plan_type === 'visit' ? 'Visit Plan' : 'Call Plan',
                'note'            => !empty($row->notes) ? $row->notes : $row->ai_note,
                'notes'           => $row->notes,
                'ai_note'         => $row->ai_note,

                'contact_name'    => $customerName,
                'name'            => $customerName,
                'phone'           => $phone,
                'mobile'          => $phone,
                'group_name'      => '-',

                'assigned_name'   => $assignedNames->join(', '),
                'assigned_names'  => $assignedNames->all(),

                'product_name'    => '-',
            ];
        })
        ->values();

    if ($assigneeId) {
        return $groupedRows
            ->filter(function ($task) use ($assigneeId) {
                return in_array((int) $assigneeId, $task->assigned_to_ids ?? [], true);
            })
            ->values();
    }

    return $groupedRows;
}
    public function generateAutoTasks(ProductSaleTarget $target, Carbon $today, ?int $count = null, string $customerSegment = 'combined_customers'): array
    {
        $dashboard = $this->buildDashboard($target, $today);

        $criticalProducts = collect($dashboard['product_performance'])
            ->filter(fn ($row) => ($row['gap'] ?? 0) > 0)
            ->sortByDesc('gap')
            ->take(3)
            ->values();

        if ($criticalProducts->isEmpty()) {
            $criticalProducts = collect($dashboard['product_performance'])->take(1);
        }

        SmartCallPlanTask::whereDate('plan_date', $today->toDateString())
            ->where('product_sale_target_id', $target->id)
            ->whereIn('board_status', ['todo', 'follow_up'])
            ->delete();

        $created = [];

        foreach ($criticalProducts as $productRow) {
            $productId = (int) $productRow['product_id'];
            $variationId = !empty($productRow['variation_id']) ? (int) $productRow['variation_id'] : null;
            $gap = (float) ($productRow['gap'] ?? 0);

            $aiCount = $count ?: $this->aiSuggestedCustomerCount(
                $target->business_id,
                $target->location_id,
                $productId,
                $variationId,
                $gap
            );

            $customers = $this->suggestCustomers(
                $target->business_id,
                $target->location_id,
                $productId,
                $variationId,
                $aiCount,
                $customerSegment
            );

            foreach ($customers as $customer) {
                $created[] = SmartCallPlanTask::create([
                    'product_sale_target_id' => $target->id,
                    'business_id'            => $target->business_id,
                    'location_id'            => $target->location_id,
                    'assigned_to'            => $target->assigned_to ?: Auth::id(),
                    'contact_id'             => $customer->id,
                    'product_id'             => $productId,
                    'plan_date'              => $today->toDateString(),
                    'task_type'              => $gap > 0 ? 'both' : 'call',
                    'board_status'           => 'todo',
                    'priority'               => $this->priorityFromGap($gap),
                    'ai_reason'              => "ផលិតផល {$productRow['product_name']} កំពុងខ្វះ {$gap} ឯកតា។",
                    'ai_action'              => "ផ្តោតលើអតិថិជន {$customer->name} ដើម្បីជំរុញការលក់ {$productRow['product_name']}",
                    'note'                   => "Focus product: {$productRow['product_name']}",
                ]);
            }
        }

        return [
            'created_count' => count($created),
            'tasks'         => $this->attachMainDbDataToTasks(collect($created)),
        ];
    }

    public function draftAutoPlan(ProductSaleTarget $target, Carbon $today, string $customerSegment = 'combined_customers'): array
    {
        $businessId = $target->business_id;
        $locationId = $target->location_id;
        $assignedTo = $target->assigned_to ?: Auth::id();

        $summary = $this->buildDashboard($target, $today);

        $criticalProduct = collect($summary['product_performance'] ?? [])
            ->filter(fn ($row) => ($row['gap'] ?? 0) > 0)
            ->sortByDesc('gap')
            ->first();

        if (!$criticalProduct) {
            $criticalProduct = collect($summary['product_performance'] ?? [])->first();
        }

        if (!$criticalProduct) {
            return [
                'filters' => [
                    'title'         => 'Targeted Plan: Close Gap',
                    'product_id'    => null,
                    'variation_id'  => null,
                    'product_name'  => 'Unknown Product',
                    'count'         => 0,
                    'ai_count'      => 0,
                    'assigned_to'   => $assignedTo,
                    'assigned_name' => $this->userDisplayName($assignedTo),
                    'task_type'     => 'both',
                    'ai_goal'       => 'No product target found.',
                ],
                'items' => [],
            ];
        }

        $productId = (int) $criticalProduct['product_id'];
        $variationId = !empty($criticalProduct['variation_id']) ? (int) $criticalProduct['variation_id'] : null;
        $productName = $criticalProduct['product_name'] ?? 'Unknown Product';
        $gap = (float) ($criticalProduct['gap'] ?? 0);

        $aiCount = 0;

$customers = $this->aiRankedUrgentCustomersForAutoPlan(
    $target,
    $today,
    $criticalProduct,
    $summary,
    30
);

if ($customers->isEmpty()) {
    return [
        'filters' => [
            'title'         => 'AI Urgent Plan',
            'product_id'    => $productId,
            'variation_id'  => $variationId,
            'product_name'  => $productName,
            'count'         => 0,
            'ai_count'      => 0,
            'assigned_to'   => $assignedTo,
            'assigned_name' => $this->userDisplayName($assignedTo),
            'task_type'     => 'ai',
            'ai_goal'       => 'AI did not return urgent customers.',
            'gap'           => $gap,
            'customer_segment' => 'ai_urgent_today',
            'customer_segment_label' => 'AI ជ្រើសប្រភេទអតិថិជនដោយស្វ័យប្រវត្តិ',
            'customer_segment_options' => $this->customerSegmentOptions(),
            
        ],
        'items' => [],
    ];
}

$aiCount = $customers->count();

        return [
            'filters' => [
                'title'         => 'AI Urgent Plan',
                'product_id'    => $productId,
                'variation_id'  => $variationId,
                'product_name'  => $productName,
                'count'         => $customers->count(),
                'ai_count'      => $aiCount,
                'assigned_to'   => $assignedTo,
                'assigned_name' => $this->userDisplayName($assignedTo),
                'task_type'     => 'AI decides per customer',
                'ai_goal'       => 'AI selected urgent customers for today based on target gap and customer history.',
                'gap'           => $gap,
                'customer_segment' => 'AI decides per customer',
                'customer_segment_label' => 'AI ជ្រើសប្រភេទអតិថិជនដោយស្វ័យប្រវត្តិ',
                'customer_segment_options' => $this->customerSegmentOptions(),
                
            ],
            'items' => collect($customers)->map(function ($c) use ($assignedTo) {
                return [
                    'contact_id'     => $c->id,
                    'name'           => $c->name,
                    'phone'          => $c->phone ?? $c->mobile ?? '-',
                    'group'          => $c->group_name ?? '-',
                    'assigned_to'    => $assignedTo,

                    // AI dynamic result per customer
                    'task_type'      => $c->ai_task_type ?? 'call',
                    'customer_type'  => $c->ai_customer_type ?? 'urgent_follow_up',
                    'priority'       => $c->ai_priority_level ?? 'high',
                    'ai_note'        => $c->ai_note ?? null,
                ];
            })->values()->all(),
        ];
    }

    public function draftManualPlan(
    ProductSaleTarget $target,
    Carbon $today,
    int $count,
    int $productId,
    ?int $assignedTo,
    string $taskType,
    string $customerSegment = 'combined_customers',
    array $areaFilter = ['is_all_area' => true]
): array {
    if (!in_array($taskType, ['call', 'visit'], true)) {
        $taskType = 'call';
    }

    $count = max(1, min($count, 100));
    $businessId = $target->business_id;
    $locationId = $target->location_id;
    $assignedTo = $assignedTo ?: auth()->id();

    $product = DB::connection('mysql')
        ->table('products')
        ->where('id', $productId)
        ->first(['id', 'name', 'sku']);

    $productName = $product
        ? trim($product->name . (!empty($product->sku) ? ' (' . $product->sku . ')' : ''))
        : 'Unknown Product';

    $this->buildDashboard($target, $today);

    $customers = $this->suggestCustomers(
        $businessId,
        $locationId,
        $productId,
        null,
        $count,
        $customerSegment,
        $areaFilter
    );

    if ($customers->count() < $count) {
        $existingIds = $customers->pluck('id')->all();

        $fallbackQuery = DB::connection('mysql')
            ->table('contacts as c')
            ->where(function ($q) {
                $q->where('c.type', 'customer')
                    ->orWhere('c.type', 'both');
            })
           ->where('c.business_id', $businessId)
            ->when(!empty($existingIds), fn ($q) => $q->whereNotIn('c.id', $existingIds));

        $fallbackQuery = $this->applyAreaFilter($fallbackQuery, $areaFilter);

        $fallback = $fallbackQuery
            ->select([
                'c.id',
                'c.name',
                DB::raw("COALESCE(c.mobile, c.alternate_number, c.landline, '') as phone"),
                DB::raw("'-' as group_name"),
                DB::raw('NULL as last_order_date'),
                DB::raw('0 as total_qty'),
                DB::raw("'manual_fallback' as customer_segment"),
            ])
            ->latest('c.id')
            ->limit($count - $customers->count())
            ->get();

        $customers = $customers
            ->concat($fallback)
            ->unique('id')
            ->take($count)
            ->values();
    }

    return [
        'filters' => [
            'title'         => 'Manual Plan',
            'product_id'    => $productId,
            'product_name'  => $productName,
            'count'         => $customers->count(),
            'assigned_to'   => $assignedTo,
            'assigned_name' => $this->userDisplayName($assignedTo),
            'task_type'     => $taskType,
            'customer_segment' => $customerSegment,
            'customer_segment_label' => $this->customerSegmentLabel($customerSegment),
            'customer_segment_options' => $this->customerSegmentOptions(),
            'target_id' => $target->id,
            'area_filter' => $areaFilter,
        ],

        'items' => collect($customers)->map(function ($c) use ($assignedTo, $taskType, $customerSegment) {
            return [
                'contact_id'     => $c->id,
                'name'           => $c->name,
                'phone'          => $c->phone ?? $c->mobile ?? '-',
                'group'          => $c->group_name ?? '-',
                'assigned_to'    => $assignedTo,
                'task_type'      => $taskType,
                'customer_type'  => $customerSegment,
                'priority'       => 'high',
                'ai_note'        => null,
                'ai_loading'     => true,
            ];
        })->values()->all(),
    ];
}
public function generateAiReasonsForDraftItems(
    ProductSaleTarget $target,
    Carbon $today,
    int $productId,
    string $productName,
    string $taskType,
    string $customerSegment,
    array $items
): array {
    if (empty($items)) {
        return [];
    }

    if (!in_array($taskType, ['call', 'visit'], true)) {
        $taskType = 'call';
    }

    $summary = $this->buildDashboard($target, $today);

    $productPerformance = collect($summary['product_performance'] ?? [])
        ->firstWhere('product_id', $productId);

    $contactIds = collect($items)
        ->pluck('contact_id')
        ->filter()
        ->unique()
        ->values();

    if ($contactIds->isEmpty()) {
        return [];
    }

    $customers = DB::connection('mysql')
        ->table('contacts as c')
        ->where('c.business_id', $target->business_id)
        ->whereIn('c.id', $contactIds)
        ->select([
            'c.id',
            'c.name',
            'c.mobile',
            'c.alternate_number',
            'c.supplier_business_name',
        ])
        ->get()
        ->keyBy('id');

    $history = DB::connection('mysql')
        ->table('transactions as t')
        ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
        ->where('t.type', 'sell')
        ->where('t.status', 'final')
        ->where('t.business_id', $target->business_id)
        ->where('tsl.product_id', $productId)
        ->whereIn('t.contact_id', $contactIds)
        ->groupBy('t.contact_id')
        ->select([
            't.contact_id',
            DB::raw('MAX(t.transaction_date) as last_order_date'),
            DB::raw('COUNT(DISTINCT DATE(t.transaction_date)) as buy_days'),
            DB::raw('COALESCE(SUM(tsl.quantity), 0) as total_qty'),
        ])
        ->get()
        ->keyBy('contact_id');

    $customerCandidates = collect($items)->map(function ($item) use ($customers, $history) {
        $contactId = (int) ($item['contact_id'] ?? 0);
        $customer = $customers->get($contactId);
        $h = $history->get($contactId);

        $lastOrderDate = $h->last_order_date ?? null;

        return [
            'contact_id'        => $contactId,
            'name'              => $customer->name ?? ($item['name'] ?? '-'),
            'phone'             => $customer->mobile
                ?? $customer->alternate_number
                ?? ($item['phone'] ?? null),
            'current_segment'   => $item['customer_type'] ?? null,
            'selected_task_type'=> $item['task_type'] ?? 'call',
            'last_order_date'   => $lastOrderDate,
            'days_since_order'  => $lastOrderDate
                ? Carbon::parse($lastOrderDate)->diffInDays(now())
                : 9999,
            'buying_days'       => (int) ($h->buy_days ?? 0),
            'total_qty'         => (float) ($h->total_qty ?? 0),
        ];
    })->values();

    $payload = [
        'mode' => 'manual_plan_reason',
        'instruction' => 'User manually selected these customers. Return one JSON row for every customer_candidates item. Do not remove customers. Give ai_note, customer_type, priority_level, and task_type for each customer.',
        'date' => $today->toDateString(),
        'business_id' => $target->business_id,
        'location_id' => $target->location_id,

        'manual_setting' => [
            'selected_task_type' => $taskType,
            'selected_customer_segment' => $customerSegment,
            'selected_product_id' => $productId,
            'selected_product_name' => $productName,
        ],

        'target_summary' => [
            'total_target'   => (float) ($summary['total_target'] ?? 0),
            'expected_today' => (float) ($summary['expected_today'] ?? 0),
            'actual_sold'    => (float) ($summary['actual_sold'] ?? 0),
            'gap_missing'    => (float) ($summary['gap_missing'] ?? 0),
        ],

        // keep this name because your AI logic for auto likely understands critical_product
        'critical_product' => [
            'product_id'      => $productId,
            'product_name'    => $productName,
            'target'          => (float) ($productPerformance['target'] ?? 0),
            'expected_today'  => (float) ($productPerformance['expected'] ?? 0),
            'actual_sold'     => (float) ($productPerformance['actual'] ?? 0),
            'gap'             => (float) ($productPerformance['gap'] ?? 0),
            'status'          => $productPerformance['status_label'] ?? null,
        ],

        'customer_candidates' => $customerCandidates->toArray(),
    ];

    try {
        $aiRows = app(SmartCallPlanAiRecommendationService::class)
            ->rankUrgentCustomers($payload);
    } catch (\Throwable $e) {
        report($e);
        $aiRows = [];
    }

    $aiMap = collect($aiRows ?? [])->keyBy(function ($row) {
        return (int) ($row['contact_id'] ?? 0);
    });

    return $customerCandidates->map(function ($customer) use (
        $aiMap,
        $taskType,
        $customerSegment,
        $productName,
        $summary
    ) {
        $contactId = (int) $customer['contact_id'];
        $ai = $aiMap->get($contactId);

        return [
            'contact_id' => $contactId,

            'task_type' => in_array(($ai['task_type'] ?? $taskType), ['call', 'visit'], true)
                ? ($ai['task_type'] ?? $taskType)
                : $taskType,

            'customer_type' => $ai['customer_type'] ?? $customerSegment,

            'priority_level' => $ai['priority_level']
                ?? $ai['priority']
                ?? 'high',

            'ai_note' => $ai['ai_note'] ?? (
                'ត្រូវតាមដានអតិថិជននេះសម្រាប់ផលិតផល ' . $productName .
                ' ព្រោះ Target នៅមាន Gap ខ្វះ ' . number_format((float) ($summary['gap_missing'] ?? 0), 2) .
                ' និងត្រូវជំរុញការទិញថ្ងៃនេះ។'
            ),
        ];
    })->values()->all();
}
    protected function attachAiReasonToManualCustomers(
    Collection $customers,
    ProductSaleTarget $target,
    Carbon $today,
    int $productId,
    string $productName,
    string $taskType,
    string $customerSegment,
    array $summary,
    array $productPerformance
): Collection {
    if ($customers->isEmpty()) {
        return $customers;
    }

    try {
        $aiRows = app(SmartCallPlanAiRecommendationService::class)->rankUrgentCustomers([
            'mode' => 'manual_plan_reason',
            'instruction' => 'User manually selected these customers. Do not ignore them. Return ai_note, customer_type, priority_level, and task_type for each selected customer based on product target gap and customer history.',
            'date' => $today->toDateString(),
            'business_id' => $target->business_id,
            'location_id' => $target->location_id,

            'manual_setting' => [
                'selected_task_type' => $taskType,
                'selected_customer_segment' => $customerSegment,
                'selected_product_id' => $productId,
                'selected_product_name' => $productName,
            ],

            'target_summary' => [
                'total_target'   => (float) ($summary['total_target'] ?? 0),
                'expected_today' => (float) ($summary['expected_today'] ?? 0),
                'actual_sold'    => (float) ($summary['actual_sold'] ?? 0),
                'gap_missing'    => (float) ($summary['gap_missing'] ?? 0),
            ],

            'product' => [
                'product_id'      => $productId,
                'product_name'    => $productName,
                'target'          => (float) ($productPerformance['target'] ?? 0),
                'expected_today'  => (float) ($productPerformance['expected'] ?? 0),
                'actual_sold'     => (float) ($productPerformance['actual'] ?? 0),
                'gap'             => (float) ($productPerformance['gap'] ?? 0),
                'status'          => $productPerformance['status_label'] ?? null,
            ],

            'customer_candidates' => $customers->map(function ($customer) {
                $lastOrderDate = $customer->last_order_date ?? null;

                return [
                    'contact_id'       => (int) $customer->id,
                    'name'             => $customer->name,
                    'phone'            => $customer->phone ?? $customer->mobile ?? null,
                    'current_segment'  => $customer->customer_segment ?? null,
                    'last_order_date'  => $lastOrderDate,
                    'days_since_order' => $lastOrderDate
                        ? Carbon::parse($lastOrderDate)->diffInDays(now())
                        : 9999,
                    'buying_days'      => (int) ($customer->buy_days ?? 0),
                    'total_qty'        => (float) ($customer->total_qty ?? 0),
                ];
            })->values()->toArray(),
        ]);

        if (empty($aiRows)) {
            return $customers;
        }

        $aiMap = collect($aiRows)->keyBy('contact_id');

        return $customers->map(function ($customer) use ($aiMap, $taskType, $customerSegment) {
            $ai = $aiMap->get((int) $customer->id);

            if (!$ai) {
                return $customer;
            }

            $customer->ai_task_type = in_array(($ai['task_type'] ?? $taskType), ['call', 'visit'], true)
                ? ($ai['task_type'] ?? $taskType)
                : $taskType;

            $customer->ai_customer_type = $ai['customer_type'] ?? $customerSegment;
            $customer->ai_priority_level = $ai['priority_level'] ?? 'high';
            $customer->ai_note = $ai['ai_note'] ?? null;

            return $customer;
        });

    } catch (\Throwable $e) {
        report($e);

        return $customers;
    }
}
    public function applyManualPlan(
    ProductSaleTarget $target,
    array $contactIds,
    array $productIds,
    array $assignedToIds,
    string $taskType,
    ?string $taskTitle,
    string $planDate,
    ?int $businessId = null,
    ?int $locationId = null
): int {
    if (!in_array($taskType, ['call', 'visit'], true)) {
        $taskType = 'call';
    }

    $businessId = $businessId ?: $target->business_id;
    $locationId = $locationId ?: $target->location_id;

    $assignedToIds = collect($assignedToIds)
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    if (empty($assignedToIds)) {
        $assignedToIds = [auth()->id()];
    }

    $productIds = collect($productIds)
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $products = DB::connection('mysql')
        ->table('products')
        ->whereIn('id', $productIds)
        ->get(['id', 'name', 'sku']);

    $productName = $products
        ->map(function ($product) {
            return trim($product->name . (!empty($product->sku) ? ' (' . $product->sku . ')' : ''));
        })
        ->filter()
        ->values()
        ->join(', ');

    if ($productName === '') {
        $productName = 'Product';
    }

    $finalTitle = trim((string) $taskTitle);

    if ($finalTitle === '') {
        $finalTitle = ucfirst($taskType) . ' Plan - ' . $productName;
    }

    $contacts = DB::connection('mysql')
        ->table('contacts')
        ->whereIn('id', $contactIds)
        ->where('business_id', $businessId)
        ->get(['id', 'name', 'mobile', 'alternate_number']);

    if ($contacts->isEmpty()) {
        return 0;
    }

    $created = 0;

    DB::connection('mysql')->transaction(function () use (
        $contacts,
        $productName,
        $taskType,
        $finalTitle,
        $planDate,
        $assignedToIds,
        $businessId,
        $locationId,
        &$created
    ) {
        $plan = Plan::create([
            'plan_type'       => $taskType,
            'title'           => $finalTitle,
            'plan_date'       => $planDate,
            'completed_count' => 0,
            'skipped_count'   => 0,
            'strategy_input'  => 'Manual plan for ' . $productName,
            'created_by'      => auth()->id(),
        ]);

        foreach ($contacts as $contact) {
            $lastOrderDate = DB::connection('mysql')
                ->table('transactions as t')
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.business_id', $businessId)
                ->when($locationId, function ($query) use ($locationId) {
                    $query->where('t.location_id', $locationId);
                })
                ->where('t.contact_id', $contact->id)
                ->max('t.transaction_date');

            $lastCallDate = DB::connection('mysql')
                ->table('plan_items as pi')
                ->join('plans as p', 'p.id', '=', 'pi.plan_id')
                ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
                ->where('c.business_id', $businessId)
                ->when($locationId, function ($query) use ($locationId) {
                    $query->where('c.location_id', $locationId);
                })
                ->where('pi.contact_id', $contact->id)
                ->where('p.plan_type', 'call')
                ->where('pi.item_status', 'completed')
                ->max('pi.updated_at');

            $lastVisitDate = DB::connection('mysql')
                ->table('plan_items as pi')
                ->join('plans as p', 'p.id', '=', 'pi.plan_id')
                ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
                ->where('c.business_id', $businessId)
                ->when($locationId, function ($query) use ($locationId) {
                    $query->where('c.location_id', $locationId);
                })
                ->where('pi.contact_id', $contact->id)
                ->where('p.plan_type', 'visit')
                ->where('pi.item_status', 'completed')
                ->max('pi.updated_at');

            foreach ($assignedToIds as $assignedTo) {
                PlanItem::create([
                    'plan_id'         => $plan->id,
                    'contact_id'      => $contact->id,
                    'salesperson_id'  => $assignedTo,
                    'priority_level'  => 'high',
                    'last_order_date' => $lastOrderDate,
                    'last_call_date'  => $lastCallDate,
                    'last_visit_date' => $lastVisitDate,
                    'ai_note'         => 'Manual plan for ' . $productName . '. Please follow up with this customer.',
                    'item_status'     => 'pending',
                    'result'          => null,
                    'notes'           => null,
                    'followup_date'   => null,
                ]);

                $created++;
            }
        }
    });

    return $created;
}
    public function saveTaskLog(SmartCallPlanTask $task, array $data): SmartCallPlanTask
    {
        SmartCallPlanTaskLog::create([
            'task_id'     => $task->id,
            'created_by'  => Auth::id(),
            'result'      => $data['result'],
            'callback_at' => $data['callback_at'] ?? null,
            'note'        => $data['note'] ?? null,
        ]);

        $status = $this->mapResultToBoardStatus($data['result']);

        $task->update([
            'result'       => $data['result'],
            'callback_at'  => $data['callback_at'] ?? null,
            'note'         => $data['note'] ?? null,
            'board_status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        return $task->fresh();
    }

    public function skipTask(SmartCallPlanTask $task): SmartCallPlanTask
    {
        $task->update([
            'result'       => 'skipped',
            'board_status' => 'skipped',
        ]);

        return $task->fresh();
    }

    public function searchCustomers(?int $businessId, string $keyword): array
    {
        return DB::connection('mysql')
            ->table('contacts')
            ->where('type', 'customer')
            ->when($businessId, function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            })
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhere('alternate_number', 'like', '%' . $keyword . '%');
            })
            ->limit(20)
            ->get()
            ->map(function ($c) {
                return [
                    'id'    => $c->id,
                    'name'  => $c->name ?? 'Unknown Customer',
                    'phone' => $c->mobile ?? $c->phone ?? $c->alternate_number ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    protected function expectedUntilDate(float $targetQty, Carbon $start, Carbon $end, Carbon $today): float
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $today = $today->copy()->startOfDay();

        if ($today->lt($start)) {
            return 0;
        }

        if ($today->gt($end)) {
            return round($targetQty, 2);
        }

        $totalDays = max(1, $start->diffInDays($end) + 1);
        $elapsedDays = max(1, $start->diffInDays($today) + 1);

        return round(min(($targetQty / $totalDays) * $elapsedDays, $targetQty), 2);
    }

    protected function needPerRemainingDay(float $totalTarget, float $actualSold, Carbon $today, Carbon $periodEnd): float
    {
        $remainingQty = max(0, $totalTarget - $actualSold);
        $remainingDays = max(1, $today->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay(), false) + 1);

        return round($remainingQty / $remainingDays, 2);
    }

    protected function getActualSold(
        ?int $businessId,
        ?int $locationId,
        ?int $assignedTo,
        int $productId,
        ?int $variationId,
        Carbon $startDate,
        Carbon $endDate
    ): float {
        $query = DB::connection('mysql')
            ->table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where('tsl.product_id', $productId);

        if ($variationId) {
            $query->where('tsl.variation_id', $variationId);
        }

        if ($businessId) {
            $query->where('t.business_id', $businessId);
        }

        if ($locationId) {
            $query->where('t.location_id', $locationId);
        }

        if ($assignedTo) {
            $query->where(function ($q) use ($assignedTo) {
                $q->where('t.created_by', $assignedTo)
                    ->orWhere('t.commission_agent', $assignedTo);
            });
        }

        return round((float) $query->sum('tsl.quantity'), 2);
    }

    protected function suggestCustomers(
    ?int $businessId,
    ?int $locationId,
    int $productId,
    ?int $variationId = null,
    int $limit = 5,
    string $customerSegment = 'combined_customers',
    array $areaFilter = ['is_all_area' => true]
): Collection {
    $limit = max(1, min($limit, 100));
    $customerSegment = $this->normalizeCustomerSegment($customerSegment);

    return match ($customerSegment) {
        'inactive_7_days' => $this->inactiveCustomersForProduct(
            $businessId,
            $locationId,
            $productId,
            $variationId,
            $limit,
            7,
            $areaFilter
        ),

        'daily_buyers' => $this->dailyBuyerCustomersForProduct(
            $businessId,
            $locationId,
            $productId,
            $variationId,
            $limit,
            7,
            $areaFilter
        ),

        'combined_customers' => $this->combinedSegmentCustomers(
            $businessId,
            $locationId,
            $productId,
            $variationId,
            $limit,
            7,
            $areaFilter
        ),

        default => $this->combinedSegmentCustomers(
            $businessId,
            $locationId,
            $productId,
            $variationId,
            $limit,
            7,
            $areaFilter
        ),
    };
}

    protected function inactiveCustomersForProduct(
    ?int $businessId,
    ?int $locationId,
    int $productId,
    ?int $variationId = null,
    int $limit = 5,
    int $days = 7,
    array $areaFilter = ['is_all_area' => true]
): Collection {
    $cutoffDate = Carbon::today()->subDays($days)->toDateString();

    $history = DB::connection('mysql')
        ->table('transactions as t')
        ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
        ->where('t.type', 'sell')
        ->where('t.status', 'final')
        ->where('tsl.product_id', $productId)
        ->when($variationId, fn ($q) => $q->where('tsl.variation_id', $variationId))
        ->when($businessId, fn ($q) => $q->where('t.business_id', $businessId))
        ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
        ->groupBy('t.contact_id')
        ->select([
            't.contact_id',
            DB::raw('MAX(DATE(t.transaction_date)) as last_order_date'),
            DB::raw('SUM(tsl.quantity) as total_qty'),
        ]);

    $query = DB::connection('mysql')
        ->table('contacts as c')
        ->leftJoinSub($history, 'h', function ($join) {
            $join->on('h.contact_id', '=', 'c.id');
        })
        ->where(function ($q) {
            $q->where('c.type', 'customer')
                ->orWhere('c.type', 'both');
        })
        ->when($businessId, fn ($q) => $q->where('c.business_id', $businessId))
        ->where(function ($q) use ($cutoffDate) {
            $q->whereNull('h.last_order_date')
                ->orWhereDate('h.last_order_date', '<=', $cutoffDate);
        });

    $query = $this->applyAreaFilter($query, $areaFilter);

    return $query
        ->select([
            'c.id',
            'c.name',
            DB::raw("COALESCE(c.mobile, c.alternate_number, c.landline, '') as phone"),
            DB::raw("'-' as group_name"),
            'h.last_order_date',
            DB::raw('COALESCE(h.total_qty, 0) as total_qty'),
            DB::raw("'inactive_7_days' as customer_segment"),
        ])
        ->orderByRaw('CASE WHEN h.last_order_date IS NULL THEN 0 ELSE 1 END')
        ->orderBy('h.last_order_date')
        ->limit($limit)
        ->get();
}

protected function normalizeAreaNames(Collection $names): Collection
{
    return $names
        ->flatMap(function ($value) {
            $value = trim((string) $value);

            preg_match('/\((.*?)\)/', $value, $matches);

            return array_filter([
                $value,
                $matches[1] ?? null,
                trim(preg_replace('/\(.*?\)/', '', $value)),
            ]);
        })
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->unique()
        ->values();
}
    protected function dailyBuyerCustomersForProduct(
    ?int $businessId,
    ?int $locationId,
    int $productId,
    ?int $variationId = null,
    int $limit = 5,
    int $days = 7,
    array $areaFilter = ['is_all_area' => true]
): Collection {
    $startDate = Carbon::today()->subDays($days - 1)->toDateString();

    $dailyBuyers = DB::connection('mysql')
        ->table('transactions as t')
        ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
        ->where('t.type', 'sell')
        ->where('t.status', 'final')
        ->where('tsl.product_id', $productId)
        ->whereDate('t.transaction_date', '>=', $startDate)
        ->when($variationId, fn ($q) => $q->where('tsl.variation_id', $variationId))
        ->when($businessId, fn ($q) => $q->where('t.business_id', $businessId))
        ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
        ->groupBy('t.contact_id')
        ->select([
            't.contact_id',
            DB::raw('COUNT(DISTINCT DATE(t.transaction_date)) as buy_days'),
            DB::raw('MAX(DATE(t.transaction_date)) as last_order_date'),
            DB::raw('SUM(tsl.quantity) as total_qty'),
        ])
        ->havingRaw('COUNT(DISTINCT DATE(t.transaction_date)) >= ?', [$days]);

    $query = DB::connection('mysql')
        ->table('contacts as c')
        ->joinSub($dailyBuyers, 'db', function ($join) {
            $join->on('db.contact_id', '=', 'c.id');
        })
        ->where(function ($q) {
            $q->where('c.type', 'customer')
                ->orWhere('c.type', 'both');
        })
        ->when($businessId, fn ($q) => $q->where('c.business_id', $businessId));

    $query = $this->applyAreaFilter($query, $areaFilter);

    return $query
        ->select([
            'c.id',
            'c.name',
            DB::raw("COALESCE(c.mobile, c.alternate_number, c.landline, '') as phone"),
            DB::raw("'-' as group_name"),
            'db.last_order_date',
            'db.buy_days',
            DB::raw('COALESCE(db.total_qty, 0) as total_qty'),
            DB::raw("'daily_buyers' as customer_segment"),
        ])
        ->orderByDesc('db.buy_days')
        ->orderByDesc('db.last_order_date')
        ->limit($limit)
        ->get();
}

    protected function combinedSegmentCustomers(
    ?int $businessId,
    ?int $locationId,
    int $productId,
    ?int $variationId = null,
    int $limit = 5,
    int $days = 7,
    array $areaFilter = ['is_all_area' => true]
): Collection {
    $inactiveLimit = max(1, (int) ceil($limit / 2));
    $dailyLimit = max(1, $limit - $inactiveLimit);

    $inactive = $this->inactiveCustomersForProduct(
        $businessId,
        $locationId,
        $productId,
        $variationId,
        $inactiveLimit,
        $days,
        $areaFilter
    );

    $daily = $this->dailyBuyerCustomersForProduct(
        $businessId,
        $locationId,
        $productId,
        $variationId,
        $dailyLimit,
        $days,
        $areaFilter
    );

    $combined = $inactive
        ->concat($daily)
        ->unique('id')
        ->values();

    if ($combined->count() < $limit) {
        $extra = $this->inactiveCustomersForProduct(
            $businessId,
            $locationId,
            $productId,
            $variationId,
            $limit,
            $days,
            $areaFilter
        )->whereNotIn('id', $combined->pluck('id')->all());

        $combined = $combined
            ->concat($extra)
            ->unique('id')
            ->take($limit)
            ->values();
    }

    return $combined->take($limit)->values();
}
  protected function applyAreaFilter($query, array $areaFilter)
{
    $isAllArea = (bool) ($areaFilter['is_all_area'] ?? true);

    if ($isAllArea) {
        return $query;
    }

    $provinceIds = collect($areaFilter['province_ids'] ?? [])
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $districtIds = collect($areaFilter['district_ids'] ?? [])
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $communeIds = collect($areaFilter['commune_ids'] ?? [])
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    return $query->whereExists(function ($sub) use ($provinceIds, $districtIds, $communeIds) {
        $sub->select(DB::raw(1))
            ->from('contacts_map as cm')
            ->whereColumn('cm.contact_id', 'c.id')
            ->whereNull('cm.deleted_at');

        if (!empty($provinceIds)) {
            $sub->whereIn('cm.province_id', $provinceIds);
        }

        if (!empty($districtIds)) {
            $sub->whereIn('cm.district_id', $districtIds);
        }

        if (!empty($communeIds)) {
            $sub->whereIn('cm.commune_id', $communeIds);
        }
    });
}

    protected function aiSuggestedCustomerCount(?int $businessId, ?int $locationId, int $productId, ?int $variationId, float $gap): int
    {
        if ($gap <= 0) {
            return 0;
        }

        $history = DB::connection('mysql')
            ->table('transactions as t')
            ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('tsl.product_id', $productId)
            ->when($variationId, fn ($q) => $q->where('tsl.variation_id', $variationId))
            ->when($businessId, fn ($q) => $q->where('t.business_id', $businessId))
            ->when($locationId, fn ($q) => $q->where('t.location_id', $locationId))
            ->selectRaw('
                COUNT(DISTINCT t.contact_id) as buyer_count,
                COALESCE(SUM(tsl.quantity), 0) as total_qty
            ')
            ->first();

        $buyerCount = (int) ($history->buyer_count ?? 0);
        $totalQty = (float) ($history->total_qty ?? 0);

        $availableCustomers = DB::connection('mysql')
            ->table('contacts')
            ->where('type', 'customer')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->count();

        if ($availableCustomers <= 0) {
            return 0;
        }

        if ($buyerCount > 0 && $totalQty > 0) {
            $avgQtyPerCustomer = max(1, $totalQty / $buyerCount);
            $suggestedCount = (int) ceil($gap / $avgQtyPerCustomer);

            return max(1, min($suggestedCount, $availableCustomers, 100));
        }

        $fallbackCount = (int) ceil($availableCustomers * 0.02);

        return max(1, min($fallbackCount, $availableCustomers, 100));
    }

    protected function attachMainDbDataToTasks(Collection $tasks): Collection
    {
        if ($tasks->isEmpty()) {
            return $tasks;
        }

        $contactIds = $tasks->pluck('contact_id')->filter()->unique()->values();
        $productIds = $tasks->pluck('product_id')->filter()->unique()->values();
        $userIds = $tasks->pluck('assigned_to')->filter()->unique()->values();

        $contacts = DB::connection('mysql')
            ->table('contacts')
            ->whereIn('id', $contactIds)
            ->get([
                'id',
                'name',
                DB::raw("COALESCE(mobile, alternate_number, landline, '') as phone"),
            ])
            ->keyBy('id');

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $users = DB::connection('mysql')
            ->table('users')
            ->whereIn('id', $userIds)
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'surname', 'username'])
            ->keyBy('id');

        return $tasks->map(function ($task) use ($contacts, $products, $users) {
            $contact = $contacts[$task->contact_id] ?? null;
            $user = $users[$task->assigned_to] ?? null;

            $task->customer_name = $contact->name ?? 'Unknown Customer';
            $task->customer_phone = $contact->phone ?? '-';
            $task->product_name = $products[$task->product_id] ?? 'Unknown Product';
            $task->assigned_name = $this->formatUserName($user, $task->assigned_to);

            return $task;
        });
    }

    protected function productDisplayNames(Collection $productIds, Collection $variationIds): array
    {
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $variations = DB::connection('mysql')
            ->table('variations')
            ->whereIn('id', $variationIds)
            ->get(['id', 'product_id', 'name', 'sub_sku'])
            ->keyBy('id');

        $names = [];

        foreach ($productIds as $productId) {
            $names[$this->productKey((int) $productId, null)] = $products[$productId] ?? 'Unknown Product';
        }

        foreach ($variations as $variation) {
            $baseName = $products[$variation->product_id] ?? 'Unknown Product';
            $sku = $variation->sub_sku ? ' (' . $variation->sub_sku . ')' : '';
            $names[$this->productKey((int) $variation->product_id, (int) $variation->id)] = $baseName . $sku;
        }

        return $names;
    }

    protected function singleProductDisplayName(int $productId, ?int $variationId = null): string
    {
        $product = Product::query()->where('id', $productId)->first(['id', 'name']);

        if (!$product) {
            return 'Unknown Product';
        }

        if (!$variationId) {
            return $product->name;
        }

        $variation = DB::connection('mysql')
            ->table('variations')
            ->where('id', $variationId)
            ->first(['id', 'sub_sku']);

        if (!$variation || empty($variation->sub_sku)) {
            return $product->name;
        }

        return $product->name . ' (' . $variation->sub_sku . ')';
    }

    protected function productKey(int $productId, ?int $variationId = null): string
    {
        return $productId . ':' . ($variationId ?: 0);
    }

    protected function userDisplayName(?int $userId): string
    {
        if (!$userId) {
            return 'General / All Team';
        }

        $user = DB::connection('mysql')
            ->table('users')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first(['id', 'first_name', 'surname', 'username']);

        return $this->formatUserName($user, $userId);
    }

    protected function formatUserName($user, ?int $fallbackId = null): string
    {
        if (!$user) {
            return $fallbackId ? 'User #' . $fallbackId : '-';
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->surname ?? ''));

        return $name ?: ($user->username ?? ($fallbackId ? 'User #' . $fallbackId : '-'));
    }

    protected function priorityFromGap(float $gap): string
    {
        if ($gap >= 50) {
            return 'high';
        }

        if ($gap >= 15) {
            return 'medium';
        }

        return 'low';
    }

    protected function determineStatus(float $expected, float $actual): string
    {
        if ($expected <= 0) {
            return 'on_track';
        }

        if ($actual >= $expected) {
            return $actual > ($expected * 1.05) ? 'ahead' : 'on_track';
        }

        $gap = $expected - $actual;

        if ($gap <= max(1, ($expected * 0.1))) {
            return 'on_track';
        }

        return 'critical';
    }

    protected function determineStatusLabel(float $expected, float $actual): string
    {
        return match ($this->determineStatus($expected, $actual)) {
            'ahead'    => 'Ahead',
            'on_track' => 'On Track',
            default    => 'Critical',
        };
    }

    protected function determineStatusClass(float $expected, float $actual): string
    {
        return match ($this->determineStatus($expected, $actual)) {
            'ahead'    => 'success',
            'on_track' => 'info',
            default    => 'danger',
        };
    }

    protected function aiRecommendationHtml(array $data): string
    {
        try {
            return app(SmartCallPlanAiRecommendationService::class)->analyze($data);
        } catch (\Throwable $e) {
            report($e);
            return '';
        }
    }

   

    protected function normalizeCustomerSegment(?string $segment): string
    {
        $segment = trim((string) $segment);

        return in_array($segment, ['inactive_7_days', 'daily_buyers', 'combined_customers'], true)
            ? $segment
            : 'combined_customers';
    }

    protected function customerSegmentLabel(?string $segment): string
    {
        return match ($this->normalizeCustomerSegment($segment)) {
            'inactive_7_days' => 'អតិថិជនមិនបានទិញក្នុងរយៈពេល 7 ថ្ងៃចុងក្រោយ',
            'daily_buyers' => 'អតិថិជនដែលទិញផលិតផលរាល់ថ្ងៃ',
            default => 'បង្ហាញអតិថិជនទាំងអស់',
        };
    }

    protected function customerSegmentOptions(): array
    {
        return [
            [
                'value' => 'inactive_7_days',
                'label' => 'អតិថិជនមិនបានទិញក្នុងរយៈពេល 7 ថ្ងៃចុងក្រោយ',
            ],
            [
                'value' => 'daily_buyers',
                'label' => 'អតិថិជនដែលទិញផលិតផលរាល់ថ្ងៃ',
            ],
            [
                'value' => 'combined_customers',
                'label' => 'បង្ហាញអតិថិជនទាំងអស់ (Option 1 + Option 2)',
            ],
        ];
    }

    protected function mapResultToBoardStatus(string $result): string
{
    return match ($result) {
        'order_placed_success' => 'completed',
        'interested_positive'  => 'follow_up',
        'request_callback'     => 'follow_up',
        'no_answer_busy'       => 'skipped',
        'not_interested'       => 'skipped',

        // old data support
        'sale_closed',
        'visit_completed'      => 'completed',

        'revisit',
        'visit_scheduled',
        'followup'             => 'follow_up',

        'no_answer',
        'skipped',
        'declined'             => 'skipped',

        default                => 'todo',
    };
}

    
}