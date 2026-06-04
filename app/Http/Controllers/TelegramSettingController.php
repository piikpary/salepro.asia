<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class TelegramSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $schedules = DB::table('telegram_schedules')
                ->where('business_id', $business_id)
                ->select(['id', 'chat_id', 'type', 'schedule_time', 'send_days', 'report_types', 'is_active', 'created_at'])
                ->orderBy('created_at', 'desc');

            return DataTables::of($schedules)
                ->addColumn('type_formatted', function ($row) {
                    if (($row->type ?? 'schedule') === 'immediate') {
                        return '<span class="label label-warning"><i class="fa fa-bolt"></i> Immediate</span>';
                    }
                    return '<span class="label label-info"><i class="fa fa-clock-o"></i> Schedule</span>';
                })
                ->addColumn('schedule_time_formatted', function ($row) {
                    if (empty($row->schedule_time) || ($row->type ?? 'schedule') === 'immediate') {
                        return '<span class="text-muted">—</span>';
                    }
                    return Carbon::createFromFormat('H:i:s', $row->schedule_time)->format('h:i A');
                })
                ->addColumn('send_days_formatted', function ($row) {
                    if (($row->type ?? 'schedule') === 'immediate') {
                        return '<span class="text-muted">—</span>';
                    }
                    $days = json_decode($row->send_days, true);
                    if (empty($days)) {
                        return '<span class="label label-default">Every Day</span>';
                    }
                    $html = '';
                    foreach ($days as $day) {
                        $html .= '<span class="label label-info" style="margin:2px 1px;display:inline-block;">' . e($day) . '</span>';
                    }
                    return $html;
                })
                ->addColumn('report_types_formatted', function ($row) {
                    $types = json_decode($row->report_types, true);
                    if (empty($types)) return '<span class="text-muted">-</span>';

                    $labels = [
                        'sales_visit'          => ['Sales Visit Report',   'success'],
                        'sales_visit_alert'    => ['Sales Visit Alert',    'warning'],
                        'sales_order'          => ['Sales Order Report',   'primary'],
                        'prize_ring'           => ['Prize Ring Report',    'danger'],
                        'daily_sale_summary'   => ['Daily Sale Summary',   'info'],
                        'sales_order_invoice'  => ['Sales Order Invoice',  'success'],
                    ];
                    $html = '';
                    foreach ($types as $type) {
                        if (isset($labels[$type])) {
                            [$label, $color] = $labels[$type];
                            $html .= "<span class=\"label label-{$color}\" style=\"margin:2px 1px;display:inline-block;\">{$label}</span>";
                        }
                    }
                    return $html ?: '<span class="text-muted">-</span>';
                })
                ->addColumn('status', function ($row) {
                    $checked = $row->is_active ? 'checked' : '';
                    return '<input type="checkbox" class="toggle-status" data-id="' . $row->id . '" ' . $checked . '>';
                })
                ->addColumn('action', function ($row) {
                    return '
                        <button class="btn btn-xs btn-primary btn-edit" data-id="' . $row->id . '" style="margin-right:4px;">
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-xs btn-danger btn-delete" data-id="' . $row->id . '">
                            <i class="fa fa-trash"></i> Delete
                        </button>';
                })
                ->rawColumns(['type_formatted', 'schedule_time_formatted', 'send_days_formatted', 'report_types_formatted', 'status', 'action'])
                ->make(true);
        }

        return view('telegram_setting.index');
    }

    public function store(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $request->validate([
            'chat_id'       => 'required|string',
            'type'          => 'required|in:immediate,schedule',
            'schedule_time' => 'required_if:type,schedule',
            'report_types'  => 'required|array|min:1',
        ]);

        $type = $request->input('type', 'schedule');

        // Only keep back_days for days that are actually in send_days
        $sendDaysArray = $request->input('send_days', []);
        $backDaysRaw   = array_intersect_key(
            $request->input('back_days', []),
            array_flip($sendDaysArray)
        );
        $backDaysJson = !empty($backDaysRaw) ? json_encode(array_map('intval', $backDaysRaw)) : null;

        DB::table('telegram_schedules')->insert([
            'business_id'   => $business_id,
            'chat_id'       => $request->input('chat_id'),
            'type'          => $type,
            'schedule_time' => $type === 'immediate' ? '00:00:00' : $this->parseTime($request->input('schedule_time')),
            'send_days'     => $type === 'immediate' ? null : (!empty($request->input('send_days')) ? json_encode($request->input('send_days')) : null),
            'back_days'     => $type === 'immediate' ? null : $backDaysJson,
            'report_types'  => json_encode($request->input('report_types')),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Telegram schedule created successfully.']);
    }

    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $schedule = DB::table('telegram_schedules')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $schedule->type               = $schedule->type ?? 'schedule';
        $schedule->send_days_array    = $schedule->send_days    ? json_decode($schedule->send_days, true)    : [];
        $schedule->back_days_array    = $schedule->back_days    ? json_decode($schedule->back_days, true)    : [];
        $schedule->report_types_array = $schedule->report_types ? json_decode($schedule->report_types, true) : [];

        return response()->json(['success' => true, 'data' => $schedule]);
    }

    public function update(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');

        $request->validate([
            'chat_id'       => 'required|string',
            'type'          => 'required|in:immediate,schedule',
            'schedule_time' => 'required_if:type,schedule',
            'report_types'  => 'required|array|min:1',
        ]);

        $type = $request->input('type', 'schedule');

        // Only keep back_days for days that are actually in send_days
        $sendDaysArray = $request->input('send_days', []);
        $backDaysRaw   = array_intersect_key(
            $request->input('back_days', []),
            array_flip($sendDaysArray)
        );
        $backDaysJson = !empty($backDaysRaw) ? json_encode(array_map('intval', $backDaysRaw)) : null;

        DB::table('telegram_schedules')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'chat_id'       => $request->input('chat_id'),
                'type'          => $type,
                'schedule_time' => $type === 'immediate' ? '00:00:00' : $this->parseTime($request->input('schedule_time')),
                'send_days'     => $type === 'immediate' ? null : (!empty($request->input('send_days')) ? json_encode($request->input('send_days')) : null),
                'back_days'     => $type === 'immediate' ? null : $backDaysJson,
                'report_types'  => json_encode($request->input('report_types')),
                'updated_at'    => now(),
            ]);

        return response()->json(['success' => true, 'message' => 'Telegram schedule updated successfully.']);
    }

    public function toggleStatus(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');

        $schedule = DB::table('telegram_schedules')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $newStatus = $schedule->is_active ? 0 : 1;

        DB::table('telegram_schedules')
            ->where('id', $id)
            ->update(['is_active' => $newStatus, 'updated_at' => now()]);

        return response()->json(['success' => true, 'is_active' => $newStatus]);
    }

    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');

        DB::table('telegram_schedules')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    private function parseTime(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $input)) {
            $fmt = (strlen($input) > 5) ? 'H:i:s' : 'H:i';
            return Carbon::createFromFormat($fmt, $input)->format('H:i:s');
        }
        return Carbon::createFromFormat('h:i A', strtoupper($input))->format('H:i:s');
    }
}