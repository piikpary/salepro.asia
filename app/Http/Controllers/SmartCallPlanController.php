<?php

namespace App\Http\Controllers;

use App\PlanItem;
use App\User;
use App\Services\SmartCallPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\ProductSaleTarget;

class SmartCallPlanController extends Controller
{
    public function __construct(
        protected SmartCallPlanService $service
    ) {}

    protected function businessId(): ?int
    {
        return session('user.business_id')
            ?? optional(auth()->user())->business_id
            ?? null;
    }

    protected function locationId(): ?int
    {
        return session('user.business_location_id')
            ?? session('business_location_id')
            ?? null;
    }

    /**
     * Because plans table has no business_id,
     * we protect by checking the contact business_id.
     */
    protected function taskBelongsToCurrentBusiness(PlanItem $task): bool
    {
        $businessId = $this->businessId();

        if (!$businessId) {
            return true;
        }

        return DB::connection('mysql')
            ->table('contacts')
            ->where('id', $task->contact_id)
            ->where('business_id', $businessId)
            ->exists();
    }

    protected function updatePlanCounters(int $planId): void
    {
        $completed = PlanItem::where('plan_id', $planId)
            ->where('item_status', 'completed')
            ->count();

        $skipped = PlanItem::where('plan_id', $planId)
            ->where('item_status', 'skipped')
            ->count();

        DB::connection('mysql')
            ->table('plans')
            ->where('id', $planId)
            ->update([
                'completed_count' => $completed,
                'skipped_count'   => $skipped,
                'updated_at'      => now(),
            ]);
    }

    public function index(Request $request)
    {
        $today = now();
        $businessId = $this->businessId();
        $locationId = $this->locationId();
        $userId = auth()->id();

        $targets = $this->service->getCurrentTargets($businessId, $locationId, $userId, $today);
        $target = $targets->first();

        $summary = null;

        $tasksByStatus = collect([
            'todo'      => collect(),
            'follow_up' => collect(),
            'completed' => collect(),
            'skipped'   => collect(),
        ]);

        $team = User::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'surname', 'username', 'business_id']);

        $productIds = $targets
                ->flatMap(function ($target) {
                    return $target->details->pluck('product_id');
                })
                ->filter()
                ->unique()
                ->values();

        $productNames = collect();
        $productSkus = collect();

        if ($productIds->count() > 0) {
            $productNames = DB::connection('mysql')
                ->table('products')
                ->whereIn('id', $productIds)
                ->pluck('name', 'id');

            $productSkus = DB::connection('mysql')
                ->table('products')
                ->whereIn('id', $productIds)
                ->pluck('sku', 'id');
        }

        if ($targets->count() > 0) {
            $summary = $this->service->buildDashboardFromTargets($targets, $today);

            /**
             * IMPORTANT:
             * getBoardTasks() must read from main DB:
             * plans + plan_items
             */
            $tasks = $this->service->getBoardTasks($businessId, $locationId, null, $today);

            $tasksByStatus = collect([
                'todo'      => $tasks->where('board_status', 'todo')->values(),
                'follow_up' => $tasks->where('board_status', 'follow_up')->values(),
                'completed' => $tasks->where('board_status', 'completed')->values(),
                'skipped'   => $tasks->where('board_status', 'skipped')->values(),
            ]);
        }
        $areaOptions = $this->getAreaOptions($businessId);

        return view('call-plans.dashboard', [
            'today'         => $today,
            'target'        => $target,
            'targets'       => $targets,
            'summary'       => $summary,
            'tasksByStatus' => $tasksByStatus,
            'team'          => $team,
            'productNames'  => $productNames,
            'productSkus'   => $productSkus,

            // area options
            'provinces'     => $areaOptions['provinces'],
            'districts'     => $areaOptions['districts'],
            'communes'      => $areaOptions['communes'],
        ]);
    }

    public function generateBoard(Request $request)
    {
        try {
            $today = now();
            $businessId = $this->businessId();
            $locationId = $this->locationId();
            $userId = auth()->id();

            $targets = $this->service->getCurrentTargets($businessId, $locationId, $userId, $today);
            $target = $targets->first();

            if (!$target) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No active target found.',
                ], 422);
            }

            $draft = $this->service->draftAutoPlan($target, $today);

            return response()->json([
                'ok'      => true,
                'message' => 'Auto draft generated successfully.',
                'data'    => $draft,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function manualDraft(Request $request)
    {
        try {
            $validated = $request->validate([
                'count'       => ['required', 'integer', 'min:1', 'max:500'],
                'product_id'  => ['required', 'integer'],
                'assigned_to' => ['nullable', 'integer'],
                'task_type'   => ['required', 'in:call,visit'],

                'is_all_area'    => ['nullable', 'boolean'],

                'province_ids'   => ['nullable', 'array'],
                'province_ids.*' => ['nullable', 'integer'],

                'district_ids'   => ['nullable', 'array'],
                'district_ids.*' => ['nullable', 'integer'],

                'commune_ids'    => ['nullable', 'array'],
                'commune_ids.*'  => ['nullable', 'integer'],

                'province_names'   => ['nullable', 'array'],
                'province_names.*' => ['nullable', 'string', 'max:255'],

                'district_names'   => ['nullable', 'array'],
                'district_names.*' => ['nullable', 'string', 'max:255'],

                'commune_names'   => ['nullable', 'array'],
                'commune_names.*' => ['nullable', 'string', 'max:255'],
            ]);

            $today = now();
            $businessId = $this->businessId();
            $locationId = $this->locationId();
            $userId = auth()->id();

            $targets = $this->service->getCurrentTargets($businessId, $locationId, $userId, $today);
            $target = $targets->first();

            if (!$target) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No active target found.',
                ], 422);
            }
            $areaFilter = [
                'is_all_area' => (bool) ($validated['is_all_area'] ?? true),

                'province_ids' => $validated['province_ids'] ?? [],
                'district_ids' => $validated['district_ids'] ?? [],
                'commune_ids'  => $validated['commune_ids'] ?? [],

                'province_names' => $validated['province_names'] ?? [],
                'district_names' => $validated['district_names'] ?? [],
                'commune_names'  => $validated['commune_names'] ?? [],
            ];

            $draft = $this->service->draftManualPlan(
                $target,
                $today,
                (int) $validated['count'],
                (int) $validated['product_id'],
                !empty($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
                $validated['task_type'],
                $request->input('customer_segment', 'combined_customers'),
                $areaFilter
            );

            return response()->json([
                'ok'      => true,
                'message' => 'Manual draft generated successfully.',
                'data'    => $draft,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function manualApply(Request $request)
{
    try {
        $validated = $request->validate([
            'product_id'      => ['nullable', 'integer'],
            'product_ids'     => ['nullable', 'array'],
            'product_ids.*'   => ['nullable', 'integer'],

            'assigned_to'       => ['nullable', 'integer'],
            'assigned_to_ids'   => ['nullable', 'array'],
            'assigned_to_ids.*' => ['nullable', 'integer'],

            'task_type'       => ['required', 'in:call,visit'],
            'task_title'      => ['nullable', 'string', 'max:255'],
            'plan_date'       => ['required', 'date'],
            'contact_ids'     => ['required', 'array', 'min:1'],
            'contact_ids.*'   => ['required', 'integer'],
        ]);

        $today = now();
        $businessId = $this->businessId();
        $locationId = $this->locationId();
        $userId = auth()->id();

        $targets = $this->service->getCurrentTargets($businessId, $locationId, $userId, $today);
        $target = $targets->first();

        if (!$target) {
            return response()->json([
                'ok' => false,
                'message' => 'No active target found.',
            ], 422);
        }

        $productIds = collect($validated['product_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($productIds) && !empty($validated['product_id'])) {
            $productIds = [(int) $validated['product_id']];
        }

        if (empty($productIds)) {
            return response()->json([
                'ok' => false,
                'message' => 'Please select at least one product.',
            ], 422);
        }

        $assignedToIds = collect($validated['assigned_to_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($assignedToIds) && !empty($validated['assigned_to'])) {
            $assignedToIds = [(int) $validated['assigned_to']];
        }

        if (empty($assignedToIds)) {
            $assignedToIds = [$userId];
        }

        $count = $this->service->applyManualPlan(
            $target,
            $validated['contact_ids'],
            $productIds,
            $assignedToIds,
            $validated['task_type'],
            $validated['task_title'] ?? null,
            $validated['plan_date'],
            $businessId,
            $locationId
        );

        return response()->json([
            'ok' => true,
            'message' => 'Plan applied to board successfully.',
            'count' => $count,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function logTask(Request $request, PlanItem $task)
{
    try {
        if (!$this->taskBelongsToCurrentBusiness($task)) {
            return response()->json([
                'ok' => false,
                'message' => 'This task does not belong to your business.',
            ], 403);
        }

        $validated = $request->validate([
            'result' => [
                'required',
                'in:order_placed_success,interested_positive,request_callback,no_answer_busy,not_interested',
            ],
            'callback_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $validated['result'];
        $note = $validated['note'] ?? null;

        $itemStatus = match ($result) {
            'order_placed_success' => 'completed',
            'interested_positive'  => 'followup',
            'request_callback'     => 'followup',
            'no_answer_busy'       => 'skipped',
            'not_interested'       => 'skipped',
            default                => 'pending',
        };

        $task->result = $result;
        $task->item_status = $itemStatus;
        $task->notes = $note;

        if ($result === 'request_callback') {
            $task->followup_date = $validated['callback_at'] ?? null;
        } else {
            $task->followup_date = null;
        }

        $task->save();

        $this->updatePlanCounters((int) $task->plan_id);

        return response()->json([
            'ok' => true,
            'message' => 'Task log saved successfully.',
            'task' => [
                'id' => $task->id,
                'result' => $task->result,
                'item_status' => $task->item_status,
                'notes' => $task->notes,
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
    public function skipTask(PlanItem $task)
{
    try {
        if (!$this->taskBelongsToCurrentBusiness($task)) {
            return response()->json([
                'ok' => false,
                'message' => 'This task does not belong to your business.',
            ], 403);
        }

        $task->result = 'no_answer_busy';
        $task->item_status = 'skipped';
        $task->followup_date = null;
        $task->save();

        $this->updatePlanCounters((int) $task->plan_id);

        return response()->json([
            'ok' => true,
            'message' => 'Task skipped successfully.',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
public function aiRecommendation(Request $request)
{
    $today = now();
    $businessId = $this->businessId();
    $locationId = $this->locationId();
    $userId = auth()->id();

    $targets = $this->service->getCurrentTargets($businessId, $locationId, $userId, $today);
    $target = $targets->first();

    if (!$target) {
        return response()->json([
            'html' => '',
        ]);
    }

    $summary = $this->service->buildDashboardFromTargets($targets, $today);

    $html = $this->service->aiRecommendationHtmlFromSummary($target, $summary, $today);

    return response()->json([
        'html' => $html,
    ]);
}

public function draftAiReasons(Request $request)
{
    try {
        $validated = $request->validate([
            'target_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'product_name' => ['required', 'string'],
            'task_type' => ['required', 'in:call,visit'],
            'customer_segment' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.contact_id' => ['required', 'integer'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.phone' => ['nullable', 'string'],
            'items.*.task_type' => ['nullable', 'string'],
            'items.*.customer_type' => ['nullable', 'string'],
        ]);

        $businessId = $this->businessId();

        $target = ProductSaleTarget::query()
            ->where('business_id', $businessId)
            ->where('id', $validated['target_id'])
            ->firstOrFail();

        $rows = $this->service->generateAiReasonsForDraftItems(
            target: $target,
            today: now(),
            productId: (int) $validated['product_id'],
            productName: $validated['product_name'],
            taskType: $validated['task_type'],
            customerSegment: $validated['customer_segment'] ?? 'combined_customers',
            items: $validated['items']
        );

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);

    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
            'data' => [],
        ], 500);
    }
}

    public function moveTask(Request $request, PlanItem $task)
{
    try {
        if (!$this->taskBelongsToCurrentBusiness($task)) {
            return response()->json([
                'ok'      => false,
                'message' => 'This task does not belong to your business.',
            ], 403);
        }

        $validated = $request->validate([
            'board_status' => ['required', 'in:todo,follow_up,completed,skipped'],
        ]);

        $itemStatus = match ($validated['board_status']) {
            'completed' => 'completed',
            'follow_up' => 'followup',
            'skipped'   => 'skipped',
            default     => 'pending',
        };

        $updateData = [
            'item_status' => $itemStatus,
        ];

        if ($itemStatus === 'completed') {
            $updateData['result'] = $task->result ?: 'order_placed_success';
            $updateData['followup_date'] = null;
        } elseif ($itemStatus === 'skipped') {
            $updateData['result'] = $task->result ?: 'no_answer_busy';
            $updateData['followup_date'] = null;
        } elseif ($itemStatus === 'followup') {
            $updateData['result'] = $task->result ?: 'request_callback';
        } else {
            $updateData['result'] = null;
            $updateData['followup_date'] = null;
        }

        $task->update($updateData);

        $this->updatePlanCounters((int) $task->plan_id);

        return response()->json([
            'ok'      => true,
            'message' => 'Task moved successfully.',
            'task'    => [
                'id'           => $task->id,
                'board_status' => $validated['board_status'],
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'      => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function searchCustomers(Request $request)
    {
        try {
            $keyword = trim($request->input('q', ''));

            if (strlen($keyword) < 2) {
                return response()->json([
                    'ok'   => true,
                    'data' => [],
                ]);
            }

            $businessId = $this->businessId();

            $customers = DB::connection('mysql')
                ->table('contacts')
                ->where('type', 'customer')
                ->where('business_id', $businessId)
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('mobile', 'like', '%' . $keyword . '%')
                        ->orWhere('alternate_number', 'like', '%' . $keyword . '%');
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'mobile', 'alternate_number']);
            $data = $customers->map(function ($customer) {
                return [
                    'id'    => $customer->id,
                    'name'  => $customer->name ?? 'Unknown Customer',
                    'phone' => $customer->mobile
                        ?? $customer->alternate_number
                        ?? '-',
                ];
            })->values();

            return response()->json([
                'ok'   => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

   protected function getAreaOptions(?int $businessId): array
{
    /*
     * Area options are not business-specific.
     * Show all province/district/commune from Cambodia master tables.
     * Customer filtering will happen later by business_id + selected area.
     */

    $provinces = DB::connection('mysql')
        ->table('cambodia_provinces')
        ->select([
            'id',
            DB::raw('COALESCE(name_kh, name_en) as name'),
            'name_kh',
            'name_en',
        ])
        ->orderBy('name_kh')
        ->get();

    $districts = DB::connection('mysql')
        ->table('cambodia_districts')
        ->select([
            'id',
            'province_id',
            DB::raw('COALESCE(name_kh, name_en) as name'),
            'name_kh',
            'name_en',
        ])
        ->orderBy('name_kh')
        ->get();

    $communes = DB::connection('mysql')
    ->table('cambodia_communes')
    ->select([
        'id',
        'province_id',
        'district_id',
        DB::raw('COALESCE(name_kh, name_en) as name'),
        'name_kh',
        'name_en',
    ])
    ->orderBy('name_kh')
    ->get();

    return [
        'provinces' => $provinces,
        'districts' => $districts,
        'communes'  => $communes,
    ];
}
}