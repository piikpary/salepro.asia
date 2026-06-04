<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitMissionController extends ApiController
{
    /**
     * GET /connector/api/mobile/visit-mission/list?date=2026-04-24
     *
     * Returns all visit/both plans for the authenticated salesperson on the given date.
     * Only plan_type = 'visit' or 'both' are returned (mobile Visit Mission only).
     * Status on customers is driven by transactions_visit: if a visit record exists for
     * the contact on the plan date → completed; otherwise → pending.
     */
    public function list(Request $request)
    {
        $user        = Auth::user();
        $user_id     = $user->id;
        $business_id = $user->business_id;
        $date        = $request->input('date', now()->toDateString());

        $plans = DB::table('plans')
            ->where('plan_type', 'visit')
            ->whereDate('plan_date', $date)
            ->whereExists(function ($q) use ($user_id) {
                $q->select(DB::raw(1))
                    ->from('plan_items')
                    ->whereColumn('plan_items.plan_id', 'plans.id')
                    ->where('plan_items.salesperson_id', $user_id);
            })
            ->get();

        if ($plans->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'date'    => $date,
                    'summary' => [
                        'total_plans'      => 0,
                        'total_customers'  => 0,
                        'completed_count'  => 0,
                        'pending_count'    => 0,
                        'progress_percent' => 0,
                        'status'           => 'none',
                    ],
                    'plans' => [],
                ],
            ]);
        }

        $plansData        = [];
        $summaryTotal     = 0;
        $summaryCompleted = 0;

        foreach ($plans as $plan) {
            $customers = $this->buildCustomerList($plan->id, $user_id, $plan->plan_date, $business_id);
            $total     = count($customers);
            $completed = collect($customers)->where('status', 'completed')->count();

            $summaryTotal     += $total;
            $summaryCompleted += $completed;

            $plansData[] = $this->formatPlan($plan, $customers, $total, $completed);
        }

        $summaryPending  = $summaryTotal - $summaryCompleted;
        $summaryProgress = $this->calcProgress($summaryTotal, $summaryCompleted);
        $overallStatus   = ($summaryTotal > 0 && $summaryCompleted === $summaryTotal) ? 'completed' : 'active';

        return response()->json([
            'success' => true,
            'data'    => [
                'date'    => $date,
                'summary' => [
                    'total_plans'      => count($plansData),
                    'total_customers'  => $summaryTotal,
                    'completed_count'  => $summaryCompleted,
                    'pending_count'    => $summaryPending,
                    'progress_percent' => $summaryProgress,
                    'status'           => $overallStatus,
                ],
                'plans' => $plansData,
            ],
        ]);
    }

    /**
     * GET /connector/api/mobile/visit-mission/detail/{id}
     *
     * Returns full detail of a single visit/both plan with all assigned customers.
     */
    public function detail(Request $request, $id)
    {
        $user        = Auth::user();
        $user_id     = $user->id;
        $business_id = $user->business_id;

        $plan = DB::table('plans')
            ->where('plan_type', 'visit')
            ->where('id', $id)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'msg'     => 'Mission not found.',
            ], 404);
        }

        $customers  = $this->buildCustomerList($plan->id, $user_id, $plan->plan_date, $business_id);
        $total      = count($customers);
        $completed  = collect($customers)->where('status', 'completed')->count();
        $pending    = $total - $completed;
        $progress   = $this->calcProgress($total, $completed);
        $planStatus = ($total > 0 && $completed === $total) ? 'completed' : 'active';

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $plan->id,
                'title'     => $plan->title,
                'plan_date' => $plan->plan_date,
                'type'      => 'Visit',
                'status'    => $planStatus,
                'note'      => $plan->strategy_input,
                'summary'   => [
                    'total_customers'  => $total,
                    'completed_count'  => $completed,
                    'pending_count'    => $pending,
                    'progress_percent' => $progress,
                ],
                'customers' => $customers,
            ],
        ]);
    }

    /**
     * Query plan_items for a plan/user, then resolve visit completion status from
     * transactions_visit: a customer is "completed" if a transactions_visit record
     * exists for that contact_id + business_id on the plan_date.
     *
     * @param  int    $planId
     * @param  int    $userId
     * @param  string $planDate   e.g. "2026-04-27"
     * @param  int    $businessId
     * @return array
     */
    private function buildCustomerList(int $planId, int $userId, string $planDate, int $businessId): array
    {
        $items = DB::table('plan_items as pi')
            ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
            ->leftJoin('customer_groups as cg', 'cg.id', '=', 'c.customer_group_id')
            ->leftJoin('contacts_map as cm', 'cm.contact_id', '=', 'c.id')
            ->leftJoin('cambodia_provinces as p', 'p.id', '=', 'cm.province_id')
            ->leftJoin('cambodia_districts as d', 'd.id', '=', 'cm.district_id')
            ->leftJoin('cambodia_communes as co', 'co.id', '=', 'cm.commune_id')
            ->where('pi.plan_id', $planId)
            ->where('pi.salesperson_id', $userId)
            ->select([
                'pi.id as mission_customer_id',
                'pi.plan_id as mission_id',
                'pi.contact_id',
                'c.name',
                'c.mobile as phone',
                'cg.name as group_name',
                DB::raw("TRIM(CONCAT(IFNULL(p.name_en, ''), ' ', IFNULL(d.name_en, ''), ' ', IFNULL(co.name_en, ''))) AS address"),
                'cm.points',
                'pi.priority_level as priority',
                'pi.ai_note as note',
            ])
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        // Batch-load one transactions_visit record per contact for this business on plan_date.
        // Ordered ASC so keyBy() retains the earliest (first) visit of the day per contact.
        $contactIds = $items->pluck('contact_id')->unique()->values()->toArray();

        $visitMap = DB::table('transactions_visit')
            ->where('business_id', $businessId)
            ->whereIn('contact_id', $contactIds)
            ->whereDate('transaction_date', $planDate)
            ->select('id', 'contact_id', 'transaction_date')
            ->orderBy('id', 'asc')
            ->get()
            ->keyBy('contact_id'); // one visit per contact; earliest wins

        $result = [];
        foreach ($items as $item) {
            [$latitude, $longitude] = $this->parsePoints($item->points ?? null);

            $visit = $visitMap[$item->contact_id] ?? null;

            $status             = $visit ? 'completed' : 'pending';
            $completedVisitId   = $visit ? $visit->id : null;
            $completedAt        = $visit ? $visit->transaction_date : null;

            $result[] = [
                'mission_customer_id' => $item->mission_customer_id,
                'mission_id'          => $item->mission_id,
                'contact_id'          => $item->contact_id,
                'name'                => $item->name,
                'phone'               => $item->phone,
                'group'               => $item->group_name,
                'address'             => $item->address ?: null,
                'latitude'            => $latitude,
                'longitude'           => $longitude,
                'priority'            => $this->mapPriority($item->priority),
                'status'              => $status,
                'note'                => $item->note,
                'completed_visit_id'  => $completedVisitId,
                'completed_at'        => $completedAt,
            ];
        }

        return $result;
    }

    /**
     * Split "lat,lng" points string into [float|null, float|null].
     */
    private function parsePoints(?string $points): array
    {
        if (empty($points)) {
            return [null, null];
        }

        $coords = explode(',', $points, 2);
        if (count($coords) === 2) {
            return [(float) trim($coords[0]), (float) trim($coords[1])];
        }

        return [null, null];
    }

    /**
     * Map DB priority_level (low/med/high) to API label (low/medium/high).
     */
    private function mapPriority(?string $priority): ?string
    {
        return match ($priority) {
            'med'  => 'medium',
            'high' => 'high',
            'low'  => 'low',
            default => $priority,
        };
    }

    /**
     * Calculate progress_percent capped at 100.
     */
    private function calcProgress(int $total, int $completed): float
    {
        if ($total <= 0) {
            return 0;
        }

        return min(round(($completed / $total) * 100, 2), 100);
    }

    /**
     * Format a single plan row into the response shape.
     */
    private function formatPlan(object $plan, array $customers, int $total, int $completed): array
    {
        $pending    = $total - $completed;
        $progress   = $this->calcProgress($total, $completed);
        $planStatus = ($total > 0 && $completed === $total) ? 'completed' : 'active';

        return [
            'id'        => $plan->id,
            'title'     => $plan->title,
            'plan_date' => $plan->plan_date,
            'type'      => 'Visit',
            'status'    => $planStatus,
            'note'      => $plan->strategy_input,
            'customers' => $customers,
        ];
    }
}
