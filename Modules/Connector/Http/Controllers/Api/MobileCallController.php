<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Contact;
use App\Http\Controllers\Controller;
use App\PlanItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileCallController extends Controller
{
    private int $effectiveCallMinSeconds = 30;

    public function callPlans(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:pending,completed,skipped,callback,follow_up',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error.', $validator->errors(), 422);
        }

        $businessId = (int) $user->business_id;
        $perPage = (int) ($request->per_page ?: 20);
        $date = $request->date;

        $query = DB::connection('mysql')
            ->table('plan_items as pi')
            ->join('plans as p', 'p.id', '=', 'pi.plan_id')
            ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'pi.salesperson_id')
            ->leftJoin('customer_groups as cg', 'cg.id', '=', 'c.customer_group_id')
            ->whereDate('p.plan_date', $date)
            ->where('c.business_id', $businessId)
            ->where('pi.salesperson_id', (int) $user->id)
            ->where(function ($q) {
                $q->where('p.plan_type', 'call')
                    ->orWhereNull('p.plan_type');
            });

        // Mobile call plan list: show only not-yet-called tasks
            $query->where(function ($q) {
                $q->whereNull('pi.item_status')
                    ->orWhere('pi.item_status', 'pending');
            });
            // Hide customer if anyone already saved call result for this phone today in same business
$query->whereNotExists(function ($sub) use ($date, $businessId) {
    $sub->select(DB::raw(1))
        ->from('mobile_call_logs as mcl')
        ->where('mcl.business_id', $businessId)
        ->whereDate(DB::raw('COALESCE(mcl.call_started_at, mcl.created_at)'), $date)
        ->whereColumn('mcl.phone_number', 'c.mobile');
});

// Hide pending duplicate if same phone already has completed/skipped/followup call task today in same business
$query->whereNotExists(function ($sub) use ($date, $businessId) {
    $sub->select(DB::raw(1))
        ->from('plan_items as pi_done')
        ->join('plans as p_done', 'p_done.id', '=', 'pi_done.plan_id')
        ->join('contacts as c_done', 'c_done.id', '=', 'pi_done.contact_id')
        ->where('c_done.business_id', $businessId)
        ->whereDate('p_done.plan_date', $date)
        ->where(function ($q) {
            $q->where('p_done.plan_type', 'call')
                ->orWhereNull('p_done.plan_type');
        })
        ->whereColumn('c_done.mobile', 'c.mobile')
        ->whereNotNull('pi_done.item_status')
        ->where('pi_done.item_status', '<>', 'pending');
});

        $plans = $query
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
                'pi.last_call_date',
                'pi.updated_at',

                'p.plan_type',
                'p.title as plan_title',
                'p.plan_date',

                'c.name as customer_name',
                'c.supplier_business_name',
                'c.mobile',
                'c.alternate_number',
                'cg.name as customer_group',

                'u.first_name',
                'u.surname',
                'u.username',
            ])
            ->orderByRaw("
                CASE pi.priority_level
                    WHEN 'high' THEN 1
                    WHEN 'med' THEN 2
                    ELSE 3
                END
            ")
            ->latest('pi.id')
            ->paginate($perPage);

        $data = collect($plans->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'customer_id' => (int) $row->contact_id,
                'customer_name' => $this->customerName($row),
                'phone' => $this->phone($row),
                'customer_group' => $row->customer_group,
                'priority' => $this->priority($row->priority_level),
                'status' => $this->apiStatus($row->item_status),
                'board_status' => $this->boardStatus($row->item_status),
                'plan_date' => Carbon::parse($row->plan_date)->format('Y-m-d'),
                'note' => $row->ai_note ?: $row->notes,
                'plan_title' => $row->plan_title,
                'assigned_to' => $row->salesperson_id ? (int) $row->salesperson_id : null,
                'assignee_name' => $this->assigneeName($row),
                'last_call_at' => $row->last_call_date ? Carbon::parse($row->last_call_date)->format('Y-m-d H:i:s') : null,
                'last_outcome' => $row->result,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => (int) $plans->currentPage(),
                'last_page' => (int) $plans->lastPage(),
                'per_page' => (int) $plans->perPage(),
                'total' => (int) $plans->total(),
            ],
            'links' => [
                'next' => $plans->nextPageUrl(),
            ],
        ]);
    }

    public function saveCallLog(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), $this->logRules(false));

        if ($validator->fails()) {
            return $this->errorResponse('Validation error.', $validator->errors(), 422);
        }

        $result = $this->storeCallLog($request->all(), $user);

        if (!$result['success']) {
            return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['duplicate'] ? 'Call log already exists.' : 'Call log saved successfully.',
            'data' => $this->callLogResponse($result['log'], $result['task'] ?? null),
        ]);
    }

    public function callLogs(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'source' => 'nullable|in:plan,manual',
            'outcome' => 'nullable|string|max:50',
            'customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error.', $validator->errors(), 422);
        }

        $businessId = (int) $user->business_id;
        $perPage = (int) ($request->per_page ?: 20);
        

        $query = DB::table('mobile_call_logs as mcl')
            ->leftJoin('contacts as c', 'c.id', '=', 'mcl.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'mcl.created_by')
            ->where('mcl.business_id', $businessId)
            ->where('mcl.created_by', $user->id);

        if ($request->filled('date_from')) {
            $query->where('mcl.call_started_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('mcl.call_started_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('source')) {
            $query->where('mcl.source', $request->source);
        }

        if ($request->filled('outcome')) {
            $query->where('mcl.outcome', $request->outcome);
        }

        if ($request->filled('customer_id')) {
            $query->where('mcl.contact_id', (int) $request->customer_id);
        }

        $logs = $query
            ->select([
                'mcl.*',
                'c.name as customer_name',
                'c.supplier_business_name',
                'u.first_name',
                'u.surname',
                'u.username',
            ])
            ->orderBy('mcl.call_started_at', 'desc')
            ->orderBy('mcl.id', 'desc')
            ->paginate($perPage);

        $data = collect($logs->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'call_plan_id' => $row->mobile_call_plan_id ? (int) $row->mobile_call_plan_id : null,
                'customer_id' => $row->contact_id ? (int) $row->contact_id : null,
                'customer_name' => trim($row->customer_name ?: $row->supplier_business_name),
                'phone_number' => $row->phone_number,
                'source' => $row->source,
                'duration_seconds' => (int) $row->duration_seconds,
                'outcome' => $row->outcome,
                'note' => $row->note,
                'call_started_at' => $row->call_started_at,
                'call_ended_at' => $row->call_ended_at,
                'created_by' => (int) $row->created_by,
                'created_by_name' => trim(($row->first_name ?? '') . ' ' . ($row->surname ?? '')) ?: ($row->username ?? null),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => (int) $logs->currentPage(),
                'last_page' => (int) $logs->lastPage(),
                'per_page' => (int) $logs->perPage(),
                'total' => (int) $logs->total(),
            ],
            'links' => [
                'next' => $logs->nextPageUrl(),
            ],
        ]);
    }

    public function dashboard(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error.', $validator->errors(), 422);
        }

        $businessId = (int) $user->business_id;
        $date = $request->date;

        $taskQuery = DB::connection('mysql')
        ->table('plan_items as pi')
        ->join('plans as p', 'p.id', '=', 'pi.plan_id')
        ->join('contacts as c', 'c.id', '=', 'pi.contact_id')
        ->whereDate('p.plan_date', $date)
        ->where('c.business_id', $businessId)
        ->where('pi.salesperson_id', (int) $user->id)
        ->where(function ($q) {
            $q->where('p.plan_type', 'call')
                ->orWhereNull('p.plan_type');
        });

        

        $totalTasks = (clone $taskQuery)->count();
        $completed = (clone $taskQuery)->where('pi.item_status', 'completed')->count();
        $followUp = (clone $taskQuery)->where('pi.item_status', 'followup')->count();
        $skipped = (clone $taskQuery)->where('pi.item_status', 'skipped')->count();
        $pending = max(0, $totalTasks - $completed - $followUp - $skipped);

        $logQuery = DB::table('mobile_call_logs')
            ->where('business_id', $businessId)
            ->where('created_by', (int) $user->id)
            ->whereDate('call_started_at', $date);

        

        $totalDuration = (int) (clone $logQuery)->sum('duration_seconds');
        $totalCalls = (clone $logQuery)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_calls' => $totalCalls,
                'effective_calls' => (clone $logQuery)->where('duration_seconds', '>=', $this->effectiveCallMinSeconds)->count(),
                'orders' => (clone $logQuery)->where('outcome', 'order_placed')->count(),
                'manual_calls' => (clone $logQuery)->where('source', 'manual')->count(),
                'plan_calls' => (clone $logQuery)->where('source', 'plan')->count(),
                'no_answer' => (clone $logQuery)->where('outcome', 'no_answer')->count(),
                'callback' => (clone $logQuery)->where('outcome', 'callback')->count(),
                'total_duration_seconds' => $totalDuration,
                'average_duration_seconds' => $totalCalls > 0 ? (int) round($totalDuration / $totalCalls) : 0,
                'effective_call_min_seconds' => $this->effectiveCallMinSeconds,

                'total_tasks' => $totalTasks,
                'completed_tasks' => $completed,
                'follow_up_tasks' => $followUp,
                'skipped_tasks' => $skipped,
                'pending_tasks' => $pending,
            ],
        ]);
    }

    public function syncCallLogs(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'logs' => 'required|array|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error.', $validator->errors(), 422);
        }

        $totalReceived = count($request->logs);
        $inserted = 0;
        $duplicates = 0;
        $failed = 0;
        $results = [];

        foreach ($request->logs as $item) {
            $itemValidator = Validator::make($item, $this->logRules(true));

            if ($itemValidator->fails()) {
                $failed++;

                $results[] = [
                    'local_id' => $item['local_id'] ?? null,
                    'status' => 'failed',
                    'server_id' => null,
                    'message' => 'Validation error.',
                    'errors' => $itemValidator->errors(),
                ];

                continue;
            }

            $result = $this->storeCallLog($item, $user);

            if (!$result['success']) {
                $failed++;

                $results[] = [
                    'local_id' => $item['local_id'] ?? null,
                    'status' => 'failed',
                    'server_id' => null,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? [],
                ];

                continue;
            }

            if ($result['duplicate']) {
                $duplicates++;
                $status = 'duplicate';
            } else {
                $inserted++;
                $status = 'inserted';
            }

            $results[] = [
                'local_id' => $item['local_id'] ?? null,
                'status' => $status,
                'server_id' => (int) $result['log']->id,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Offline logs synced.',
            'data' => [
                'total_received' => $totalReceived,
                'inserted' => $inserted,
                'duplicates' => $duplicates,
                'failed' => $failed,
                'results' => $results,
            ],
        ]);
    }

    public function settings()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'effective_call_min_seconds' => $this->effectiveCallMinSeconds,
                'allow_manual_call' => true,
                'require_note_for_no_answer' => false,
                'require_next_callback_for_callback' => true,
            ],
        ]);
    }

    public function outcomes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['key' => 'order_placed', 'label' => 'Order Placed', 'requires_note' => false, 'requires_callback' => false],
                ['key' => 'interested', 'label' => 'Interested', 'requires_note' => false, 'requires_callback' => false],
                ['key' => 'callback', 'label' => 'Callback', 'requires_note' => false, 'requires_callback' => true],
                ['key' => 'no_answer', 'label' => 'No Answer', 'requires_note' => false, 'requires_callback' => false],
                ['key' => 'not_interested', 'label' => 'Not Interested', 'requires_note' => true, 'requires_callback' => false],
            ],
        ]);
    }

    private function storeCallLog(array $payload, $user): array
    {
        $businessId = (int) $user->business_id;
        $localId = !empty($payload['local_id']) ? trim($payload['local_id']) : null;

        if ($localId) {
            $existing = DB::table('mobile_call_logs')
                ->where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('local_id', $localId)
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'duplicate' => true,
                    'log' => $existing,
                    'task' => !empty($existing->mobile_call_plan_id) ? PlanItem::find($existing->mobile_call_plan_id) : null,
                ];
            }
        }

        $task = null;

        if (!empty($payload['call_plan_id'])) {
            $task = PlanItem::with(['contact', 'plan'])->find((int) $payload['call_plan_id']);

            if (!$task) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Call plan not found.',
                ];
            }

            if (!$task->contact || (int) $task->contact->business_id !== $businessId) {
                return [
                    'success' => false,
                    'status' => 403,
                    'message' => 'This task does not belong to your business.',
                ];
            }
            if ((int) $task->salesperson_id !== (int) $user->id) {
                return [
                    'success' => false,
                    'status' => 403,
                    'message' => 'This call plan is not assigned to you.',
                ];
            }
        }

        $customerId = !empty($payload['customer_id'])
            ? (int) $payload['customer_id']
            : ($task ? (int) $task->contact_id : null);

        if (!$customerId) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Validation error.',
                'errors' => [
                    'customer_id' => ['The customer_id field is required.'],
                ],
            ];
        }

        $customer = Contact::where('business_id', $businessId)
            ->where('id', $customerId)
            ->first();

        if (!$customer) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found.',
            ];
        }

        $startedAt = Carbon::parse($payload['call_started_at'])->format('Y-m-d H:i:s');
        $endedAt = !empty($payload['call_ended_at'])
            ? Carbon::parse($payload['call_ended_at'])->format('Y-m-d H:i:s')
            : null;

        $duration = isset($payload['duration_seconds'])
            ? (int) $payload['duration_seconds']
            : $this->calculateDuration($startedAt, $endedAt);

        $map = $this->outcomeMap($payload['outcome']);
        $source = $task ? 'plan' : ($payload['source'] ?? 'manual');

        DB::beginTransaction();

        try {
            $logId = DB::table('mobile_call_logs')->insertGetId([
                'business_id' => $businessId,
                'mobile_call_plan_id' => $task ? $task->id : null,
                'contact_id' => $customerId,
                'created_by' => $user->id,
                'local_id' => $localId,
                'source' => $source,
                'phone_number' => $payload['phone_number'] ?? ($customer->mobile ?: $customer->alternate_number),
                'call_started_at' => $startedAt,
                'call_ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'outcome' => $payload['outcome'],
                'note' => $payload['note'] ?? null,
                'next_callback_at' => !empty($payload['next_callback_at'])
                    ? Carbon::parse($payload['next_callback_at'])->format('Y-m-d H:i:s')
                    : null,
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($task) {
                $task->result = $map['result'];
                $task->item_status = $map['item_status'];
                $task->notes = $payload['note'] ?? null;
                $task->last_call_date = $startedAt;

                if ($map['result'] === 'request_callback') {
                    $task->followup_date = !empty($payload['next_callback_at'])
                        ? Carbon::parse($payload['next_callback_at'])->format('Y-m-d H:i:s')
                        : null;
                } else {
                    $task->followup_date = null;
                }

                $task->save();

                $this->updatePlanCounters((int) $task->plan_id);
            }

            DB::commit();

            return [
                'success' => true,
                'duplicate' => false,
                'log' => DB::table('mobile_call_logs')->where('id', $logId)->first(),
                'task' => $task,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'status' => 500,
                'message' => 'Server error while saving call log.',
                'errors' => [
                    'exception' => [$e->getMessage()],
                ],
            ];
        }
    }

    private function logRules(bool $sync = false): array
    {
        return [
            'local_id' => $sync ? 'required|string|max:191' : 'nullable|string|max:191',
            'call_plan_id' => 'nullable|integer',
            'customer_id' => 'required_without:call_plan_id|nullable|integer',
            'source' => 'nullable|in:plan,manual',
            'phone_number' => 'nullable|string|max:50',
            'call_started_at' => 'required|date',
            'call_ended_at' => 'nullable|date',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
            'outcome' => 'required|in:order_placed,interested,callback,no_answer,not_interested,skipped',
            'note' => 'nullable|string|max:5000',
            'next_callback_at' => 'nullable|date',
        ];
    }

    private function callLogResponse($log, ?PlanItem $task): array
    {
        $customer = !empty($log->contact_id) ? Contact::find($log->contact_id) : null;

        return [
            'id' => (int) $log->id,
            'local_id' => $log->local_id,
            'call_plan_id' => $log->mobile_call_plan_id ? (int) $log->mobile_call_plan_id : null,
            'customer_id' => $log->contact_id ? (int) $log->contact_id : null,
            'customer_name' => $customer ? trim($customer->name ?: $customer->supplier_business_name) : null,
            'source' => $log->source,
            'phone_number' => $log->phone_number,
            'duration_seconds' => (int) $log->duration_seconds,
            'outcome' => $log->outcome,
            'plan_status' => $task ? $this->apiStatus($task->item_status) : null,
            'board_status' => $task ? $this->boardStatus($task->item_status) : null,
            'synced_at' => $log->synced_at,
        ];
    }

    private function updatePlanCounters(int $planId): void
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
                'skipped_count' => $skipped,
                'updated_at' => now(),
            ]);
    }

    private function outcomeMap(string $outcome): array
    {
        return match ($outcome) {
            'order_placed' => [
                'result' => 'order_placed_success',
                'item_status' => 'completed',
            ],
            'interested' => [
                'result' => 'interested_positive',
                'item_status' => 'followup',
            ],
            'callback' => [
                'result' => 'request_callback',
                'item_status' => 'followup',
            ],
            'no_answer' => [
                'result' => 'no_answer_busy',
                'item_status' => 'skipped',
            ],
            'not_interested' => [
                'result' => 'not_interested',
                'item_status' => 'skipped',
            ],
            'skipped' => [
                'result' => 'no_answer_busy',
                'item_status' => 'skipped',
            ],
            default => [
                'result' => 'interested_positive',
                'item_status' => 'followup',
            ],
        };
    }

    private function apiStatus(?string $itemStatus): string
    {
        return match ($itemStatus) {
            'completed' => 'completed',
            'followup' => 'callback',
            'skipped' => 'skipped',
            default => 'pending',
        };
    }

    private function boardStatus(?string $itemStatus): string
    {
        return match ($itemStatus) {
            'completed' => 'completed',
            'followup' => 'follow_up',
            'skipped' => 'skipped',
            default => 'todo',
        };
    }

    private function priority(?string $priority): string
    {
        return match ($priority) {
            'high' => 'high',
            'med' => 'medium',
            default => 'low',
        };
    }

    private function customerName($row): string
    {
        $name = trim((string) $row->customer_name);

        if ($name === '') {
            $name = trim((string) $row->supplier_business_name);
        }

        return $name !== '' ? $name : 'Customer #' . $row->contact_id;
    }

    private function phone($row): string
    {
        $phone = trim((string) $row->mobile);

        if ($phone === '') {
            $phone = trim((string) $row->alternate_number);
        }

        return $phone !== '' ? $phone : '-';
    }

    private function assigneeName($row): string
    {
        $name = trim(($row->first_name ?? '') . ' ' . ($row->surname ?? ''));

        if ($name === '') {
            $name = $row->username ?? '-';
        }

        return $name;
    }

    private function calculateDuration(?string $startedAt, ?string $endedAt): int
    {
        if (!$startedAt || !$endedAt) {
            return 0;
        }

        $start = Carbon::parse($startedAt);
        $end = Carbon::parse($endedAt);

        if ($end->lessThan($start)) {
            return 0;
        }

        return $start->diffInSeconds($end);
    }

    private function errorResponse(string $message, $errors = [], int $status = 422)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}