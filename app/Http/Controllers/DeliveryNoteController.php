<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Business;
use App\Product;
use App\User;
use App\Utils\ProductUtil;

class DeliveryNoteController extends Controller
{
    protected $productUtil;

    public function __construct(ProductUtil $productUtil)
    {
        $this->middleware('auth');
        $this->productUtil = $productUtil;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Calculate SO status based on total delivered qty vs ordered qty.
     * completed → all lines fully delivered
     * partial   → some lines partially delivered
     * ordered   → nothing delivered yet
     */
    private function syncSalesOrderStatus(int $so_id, int $business_id): void
    {
        $so_lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $so_id)
            ->whereNull('parent_sell_line_id')
            ->select('product_id', 'variation_id', 'quantity as ordered_qty')
            ->get();

        $all_full = true;
        $any_delivered = false;

        foreach ($so_lines as $so_line) {
            $delivered = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
                ->where('t.sales_order_id', $so_id)
                ->where('t.type', 'delivery_note')
                ->whereIn('t.status', ['delivered', 'completed'])
                ->whereNull('t.deleted_at')
                ->where('tsl.product_id', $so_line->product_id)
                ->where('tsl.variation_id', $so_line->variation_id)
                ->whereNull('tsl.parent_sell_line_id')
                ->sum('tsl.quantity');

            if ($delivered > 0) $any_delivered = true;
            if ($delivered < $so_line->ordered_qty) $all_full = false;
        }

        $new_status = $all_full ? 'completed' : ($any_delivered ? 'partial' : 'ordered');

        DB::table('transactions')
            ->where('id', $so_id)
            ->where('type', 'sales_order')
            ->update(['status' => $new_status, 'updated_at' => now()]);
    }

    private function generateDNNumber(int $business_id): string
    {
        $year  = Carbon::now()->year;
        $count = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->whereYear('created_at', $year)
            ->count();
        return 'DN' . $year . '/' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    private function logActivity(int $transaction_id, string $action, string $note = ''): void
    {
        DB::table('delivery_note_activities')->insert([
            'transaction_id' => $transaction_id,
            'action'         => $action,
            'by'             => auth()->user()->username ?? auth()->user()->name,
            'note'           => $note,
            'created_at'     => now(),
        ]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $draw   = intval($request->get('draw', 1));
            $start  = intval($request->get('start', 0));
            $length = intval($request->get('length', 25));
            $length = ($length <= 0) ? 25 : min($length, 500);
            $search = $request->get('search')['value'] ?? '';

            // ── Base query: minimal JOINs, no correlated subqueries ────────────
            $query = DB::table('transactions as t')
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('users as u', 't.delivery_person', '=', 'u.id')
                ->leftJoin('transactions as so_t', 't.sales_order_id', '=', 'so_t.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'delivery_note')
                ->whereNull('t.deleted_at')
                ->select([
                    't.id', 't.invoice_no', 't.transaction_date', 't.status',
                    't.final_total', 't.sales_order_id',
                    'c.name as customer_name',
                    'so_t.invoice_no as so_invoice_no',
                    DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as delivery_person_name"),
                ]);

            // view_own permission
            if (!auth()->user()->can('delivery_note.view_all') && auth()->user()->can('delivery_note.view_own')) {
                $query->where('t.created_by', auth()->id());
            }

            // ── Filters ────────────────────────────────────────────────────────
            if ($request->filled('location_id')) {
                $query->where('t.location_id', $request->location_id);
            }
            if ($request->filled('customer_id')) {
                $query->where('t.contact_id', $request->customer_id);
            }
            if ($request->filled('status')) {
                $query->where('t.status', $request->status);
            }
            if ($request->filled('driver_id')) {
                $query->where('t.delivery_person', $request->driver_id);
            }
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereDate('t.transaction_date', '>=', $request->start_date)
                      ->whereDate('t.transaction_date', '<=', $request->end_date);
            }

            // ── Search ─────────────────────────────────────────────────────────
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('t.invoice_no', 'like', "%{$search}%")
                      ->orWhere('c.name', 'like', "%{$search}%")
                      ->orWhere('so_t.invoice_no', 'like', "%{$search}%");
                });
            }

            // ── Fast count with 5-minute cache (skip cache when searching) ─────
            if (empty($search)) {
                $filterHash = md5(serialize($request->only([
                    'location_id', 'customer_id', 'status', 'driver_id', 'start_date', 'end_date'
                ])));
                $countKey   = "dn_count_{$business_id}_{$filterHash}";
                $totalCount = Cache::remember($countKey, 300, fn () => (clone $query)->count());
            } else {
                $totalCount = (clone $query)->count();
            }

            // ── Paginate ───────────────────────────────────────────────────────
            $rows = (clone $query)
                ->orderBy('t.transaction_date', 'desc')
                ->orderBy('t.id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            // ── Batch-fetch invoice & return status for this page only ─────────
            //    2 queries for any page size — replaces N×2 per-row queries
            $dnIds = $rows->pluck('id')->toArray();

            $sellMap = DB::table('transactions')
                ->whereIn('delivery_note_id', $dnIds)
                ->where('type', 'sell')
                ->whereNull('deleted_at')
                ->select('delivery_note_id', 'id as sell_id', 'invoice_no as sell_invoice_no')
                ->get()
                ->keyBy('delivery_note_id');

            $returnsMap = DB::table('transactions')
                ->whereIn('delivery_note_id', $dnIds)
                ->where('type', 'delivery_return')
                ->whereNull('deleted_at')
                ->select('delivery_note_id', 'id as dr_id', 'invoice_no as dr_invoice_no')
                ->get()
                ->keyBy('delivery_note_id');

            // ── Build response rows ────────────────────────────────────────────
            $user = auth()->user();
            $data = [];

            foreach ($rows as $row) {
                $sell        = $sellMap->get($row->id);
                $has_invoice = $sell !== null;
                $dr          = $returnsMap->get($row->id);
                $has_return  = $dr !== null;

                // Action buttons
                $items = '';

                if ($user->can('delivery_note.update')) {
                    $can_edit = !in_array($row->status, ['delivered', 'completed']);
                    $items .= $can_edit
                        ? '<li><a href="' . route('delivery-note.edit', $row->id) . '"><i class="fa fa-edit"></i> Edit Delivery Note</a></li>'
                        : '<li class="disabled"><a style="color:#aaa;cursor:not-allowed;"><i class="fa fa-edit"></i> Edit Delivery Note</a></li>';
                }

                if ($user->can('delivery_note.view_all') || $user->can('delivery_note.view_own')) {
                    $items .= '<li><a href="#" class="btn-view-dn" data-id="' . $row->id . '"><i class="fa fa-eye"></i> View</a></li>';
                }

                if ($user->can('delivery_note.print')) {
                    $items .= '<li><a href="#" class="print-invoice" data-href="' . route('delivery-note.getReceipt', $row->id) . '"><i class="fa fa-print"></i> Print</a></li>';
                }

                if ($user->can('sell.create') || $user->can('direct_sell.access')) {
                    $can_invoice = !$has_invoice && in_array($row->status, ['delivered', 'completed']);
                    $items .= $can_invoice
                        ? '<li><a href="' . url('sell/create-from-delivery-note/' . $row->id) . '" class="text-success"><i class="fa fa-plus-circle"></i> Add Sale Invoice</a></li>'
                        : '<li class="disabled"><a style="color:#aaa;cursor:not-allowed;"><i class="fa fa-plus-circle"></i> Add Sale Invoice</a></li>';
                }

                if ($user->can('delivery_return.create')) {
                    $can_return = in_array($row->status, ['delivered', 'completed']) && !$has_invoice;
                    $dr_title   = $has_invoice ? 'Remove the sale invoice first to create a return' : '';
                    $items .= $can_return
                        ? '<li><a href="' . route('delivery-return.createFromDN', $row->id) . '" class="text-warning"><i class="fa fa-undo"></i> Delivery Return</a></li>'
                        : '<li class="disabled"><a style="color:#aaa;cursor:not-allowed;" title="' . $dr_title . '"><i class="fa fa-undo"></i> Delivery Return</a></li>';
                }

                if ($user->can('delivery_note.delete')) {
                    $can_delete   = !$has_invoice && !$has_return;
                    $delete_title = $has_invoice ? 'Delete sale invoice first' : ($has_return ? 'Delete delivery return first' : '');
                    $items .= '<li class="divider"></li>';
                    $items .= $can_delete
                        ? '<li><a href="#" class="delete-dn text-danger" data-id="' . $row->id . '"><i class="fa fa-trash"></i> Delete</a></li>'
                        : '<li class="disabled"><a style="color:#aaa;cursor:not-allowed;" title="' . $delete_title . '"><i class="fa fa-trash"></i> Delete</a></li>';
                }

                $action_html = '<div class="btn-group">
                    <button type="button" class="btn btn-info btn-xs dropdown-toggle" data-toggle="dropdown">
                        Action <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">' . $items . '</ul>
                </div>';

                $status_label = match ($row->status) {
                    'ordered'   => '<span class="label label-warning">Ordered</span>',
                    'delivered' => '<span class="label label-success">Delivered</span>',
                    'completed' => '<span class="label label-primary">Completed</span>',
                    default     => '<span class="label label-default">' . ucfirst($row->status) . '</span>',
                };

                $dp_name = trim($row->delivery_person_name ?? '');

                $data[] = [
                    'action'                => $action_html,
                    'transaction_date'      => Carbon::parse($row->transaction_date)->format('m/d/Y H:i'),
                    'invoice_no'            => $row->invoice_no,
                    'delivery_return_no'    => $dr
                        ? '<a href="' . route('delivery-return.index') . '?auto_view=' . $dr->dr_id . '" class="label label-warning">' . $dr->dr_invoice_no . '</a>'
                        : '—',
                    'so_no'                 => $row->so_invoice_no ?? '—',
                    'sell_invoice_no'       => $sell ? $sell->sell_invoice_no : '—',
                    'customer_name'         => $row->customer_name,
                    'status_label'          => $status_label,
                    'stock_status_label'    => in_array($row->status, ['delivered', 'completed'])
                        ? '<span class="label label-success">Deducted</span>'
                        : '<span class="label label-danger">Not Deducted</span>',
                    'invoice_status_label'  => $has_invoice
                        ? '<span class="label label-success">Invoiced</span>'
                        : '<span class="label label-default">Not Invoiced</span>',
                    'delivery_person_label' => $dp_name !== '' ? $dp_name : '<span class="text-muted">—</span>',
                    'final_total'           => '$' . number_format($row->final_total, 2),
                ];
            }

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $totalCount,
                'recordsFiltered' => $totalCount,
                'data'            => $data,
            ]);
        }

        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)->pluck('name', 'id')->prepend('All', '');
        $customers = DB::table('contacts')
            ->where('business_id', $business_id)
            ->where('type', 'customer')
            ->pluck('name', 'id')->prepend('All', '');

        $drivers = User::where('business_id', $business_id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->pluck('first_name', 'id')
            ->prepend('All', '');

        $pos_settings = !empty(session('business.pos_settings')) ? json_decode(session('business.pos_settings'), true) : [];

        return view('delivery_note.index', compact('business_locations', 'customers', 'drivers', 'pos_settings'));
    }

    /**
     * Invalidate DN count cache — call after store/delete so next load re-counts.
     */
    public function clearDnCountCache(int $business_id): void
    {
        $pattern = "dn_count_{$business_id}_";
        // Flush by tag if using Redis/Memcached; for file/array driver, forget known keys.
        // Simple approach: store tracked keys like SellController does.
        $trackKey = "dn_count_keys_{$business_id}";
        $keys = Cache::get($trackKey, []);
        foreach ($keys as $k) {
            Cache::forget($k);
        }
        Cache::forget($trackKey);
    }

    // ─── Create ──────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        if (!auth()->user()->can('delivery_note.create')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)->pluck('name', 'id');

        // Auto-select first location as default (same logic as sell/sales_order)
        $default_location_id = $business_locations->keys()->first();

        $customers = DB::table('contacts')
            ->where('business_id', $business_id)
            ->where('type', 'customer')
            ->orderBy('name')
            ->pluck('name', 'id');

        $delivery_persons = User::forDropdown($business_id, false)->prepend(__('messages.please_select'), '');

        $dn_no = $this->generateDNNumber($business_id);

        return view('delivery_note.create', compact(
            'business_locations', 'customers', 'delivery_persons', 'dn_no', 'default_location_id'
        ));
    }

    // ─── Create from Sales Order ─────────────────────────────────────────────────

    public function createFromSalesOrder(Request $request, int $so_id)
    {
        if (!auth()->user()->can('delivery_note.create')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $so = DB::table('transactions')
            ->where('id', $so_id)
            ->where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->first();

        if (!$so) {
            return redirect()->route('delivery-note.create');
        }

        // Block if total DN qty already covers total SO qty
        $soTotalQty = DB::table('transaction_sell_lines')
            ->where('transaction_id', $so_id)
            ->whereNull('parent_sell_line_id')
            ->sum('quantity');
        $dnTotalQty = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
            ->where('t.sales_order_id', $so_id)
            ->where('t.type', 'delivery_note')
            ->whereNull('t.deleted_at')
            ->whereNull('tsl.parent_sell_line_id')
            ->sum('tsl.quantity');
        if ($soTotalQty > 0 && $dnTotalQty >= $soTotalQty) {
            return redirect()->route('delivery-note.index')
                ->with('error', 'All quantity for this Sales Order has already been covered by existing Delivery Notes.');
        }

        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)->pluck('name', 'id');

        // SO's location is the natural default; fallback to first location
        $default_location_id = $so->location_id ?? $business_locations->keys()->first();

        $customers = DB::table('contacts')
            ->where('business_id', $business_id)
            ->where('type', 'customer')
            ->orderBy('name')
            ->pluck('name', 'id');

        $delivery_persons = User::forDropdown($business_id, false)->prepend(__('messages.please_select'), '');

        $dn_no = $this->generateDNNumber($business_id);

        // Load SO lines
        $so_lines = $this->getSalesOrderLinesData($so_id, $business_id);

        return view('delivery_note.create', compact(
            'business_locations', 'customers', 'delivery_persons', 'dn_no', 'default_location_id', 'so', 'so_lines'
        ));
    }

    // ─── Get SO Lines (AJAX) ─────────────────────────────────────────────────────

    public function getSalesOrderLines(Request $request, int $id)
    {
        $business_id = $request->session()->get('user.business_id');
        $lines = $this->getSalesOrderLinesData($id, $business_id);
        return response()->json(['success' => true, 'lines' => $lines]);
    }

    private function getSalesOrderLinesData(int $so_id, int $business_id): array
    {
        // Parent lines only (exclude combo children)
        $lines = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')          // product default unit
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')  // sell-line sub-unit override
            ->where('t.id', $so_id)
            ->where('t.business_id', $business_id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select([
                'tsl.id as so_line_id',
                'tsl.product_id',
                'tsl.variation_id',
                'tsl.quantity as ordered_qty',
                'tsl.unit_price',
                'tsl.sub_unit_id',
                'p.name as product_name',
                'p.type as product_type',
                'p.enable_stock',
                'v.name as variation_name',
                'v.sub_sku',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"),
            ])
            ->get();

        $result = [];
        foreach ($lines as $line) {
            // Already delivered qty for this SO line (parent lines only)
            $delivered = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
                ->where('t.sales_order_id', $so_id)
                ->where('t.type', 'delivery_note')
                ->whereNull('t.deleted_at')
                ->where('tsl.product_id', $line->product_id)
                ->where('tsl.variation_id', $line->variation_id)
                ->whereNull('tsl.parent_sell_line_id')
                ->sum('tsl.quantity');

            $remaining = max(0, $line->ordered_qty - $delivered);

            // Fetch combo children from SO so DN can replicate same structure
            $children = [];
            if (in_array($line->product_type, ['combo', 'combo_single'])) {
                $child_rows = DB::table('transaction_sell_lines as tsl')
                    ->join('products as p', 'tsl.product_id', '=', 'p.id')
                    ->where('tsl.parent_sell_line_id', $line->so_line_id)
                    ->select([
                        'tsl.product_id', 'tsl.variation_id',
                        'tsl.unit_price', 'p.enable_stock',
                        // unit_qty = qty per 1 parent unit
                        DB::raw('tsl.quantity / ' . (float) $line->ordered_qty . ' as unit_qty'),
                    ])
                    ->get();
                $children = $child_rows->toArray();
            }

            $result[] = [
                'so_line_id'    => $line->so_line_id,
                'product_id'    => $line->product_id,
                'variation_id'  => $line->variation_id,
                'product_name'  => $line->product_name . ($line->variation_name !== 'DUMMY' ? ' - ' . $line->variation_name : ''),
                'sub_sku'       => $line->sub_sku ?? '',
                'product_type'  => $line->product_type,
                'enable_stock'  => $line->enable_stock,
                'ordered_qty'   => (float) $line->ordered_qty,
                'delivered_qty' => (float) $delivered,
                'remaining_qty' => (float) $remaining,
                'deliver_qty'   => (float) $remaining,
                'unit_price'    => (float) $line->unit_price,
                'sub_unit_id'   => $line->sub_unit_id,
                'unit_name'     => $line->unit_name ?? '',
                'subtotal'      => round($remaining * $line->unit_price, 4),
                'children'      => $children,
            ];
        }
        return $result;
    }

    // ─── Check DN Invoice No ─────────────────────────────────────────────────────

    public function checkInvoiceNo(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $dn_no = trim($request->input('dn_no', ''));
        if (empty($dn_no)) {
            return response()->json(['exists' => false]);
        }
        $exists = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->where('invoice_no', $dn_no)
            ->whereNull('deleted_at')
            ->exists();
        return response()->json(['exists' => $exists]);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        if (!auth()->user()->can('delivery_note.create')) abort(403, 'Unauthorized action.');
        $request->validate([
            'customer_id' => 'required|integer',
            'location_id' => 'required|integer',
            'status'      => 'required|in:ordered,delivered',
            'products'    => 'required|array|min:1',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id     = auth()->id();

        // REQ 1: Validate qty doesn't exceed remaining SO qty
        if (!empty($request->sales_order_id)) {
            $so_lines_data = $this->getSalesOrderLinesData((int) $request->sales_order_id, $business_id);
            // Key by so_line_id so duplicate products on the same SO are validated independently
            $remaining_map = collect($so_lines_data)->keyBy('so_line_id');

            foreach ($request->products as $product) {
                $so_line_id = (int) ($product['so_line_id'] ?? 0);
                $remaining  = $so_line_id ? ($remaining_map->get($so_line_id)['remaining_qty'] ?? null) : null;
                $deliver    = (float) ($product['deliver_qty'] ?? 0);

                if (!is_null($remaining) && $deliver > $remaining) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Deliver qty (' . $deliver . ') exceeds remaining SO qty (' . $remaining . ') for product ID ' . ($product['product_id'] ?? ''),
                    ], 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Use user-supplied DN number if provided, else auto-generate
            $invoice_no  = !empty($request->dn_no) ? trim($request->dn_no) : $this->generateDNNumber($business_id);

            // Block duplicate invoice_no within same business
            if (DB::table('transactions')->where('business_id', $business_id)->where('type', 'delivery_note')->where('invoice_no', $invoice_no)->whereNull('deleted_at')->exists()) {
                return response()->json(['success' => false, 'message' => 'Delivery Note No. "' . $invoice_no . '" already exists. Please use a different number.'], 422);
            }

            $final_total = collect($request->products)->sum(fn($p) => ($p['deliver_qty'] ?? 0) * ($p['unit_price'] ?? 0));

            $dn_id = DB::table('transactions')->insertGetId([
                'business_id'      => $business_id,
                'location_id'      => $request->location_id,
                'type'             => 'delivery_note',
                'status'           => $request->status,
                'contact_id'       => $request->customer_id,
                'invoice_no'       => $invoice_no,
                'transaction_date' => $request->transaction_date ? \Carbon\Carbon::parse($request->transaction_date) : now(),
                'sales_order_id'   => $request->sales_order_id ?: null,
                'delivery_person'  => $request->delivery_person ?: null,
                'additional_notes' => $request->delivery_note,
                'final_total'      => $final_total,
                'total_before_tax' => $final_total,
                'created_by'       => $user_id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($request->products as $product) {
                $qty = (float) ($product['deliver_qty'] ?? 0);
                if ($qty <= 0) continue;

                $unit_price   = (float) ($product['unit_price'] ?? 0);
                $product_type = $product['product_type'] ?? 'single';
                $enable_stock = !empty($product['enable_stock']);

                // Insert parent sell line (same field structure as sell/SO)
                $sell_line_id = DB::table('transaction_sell_lines')->insertGetId([
                    'transaction_id'             => $dn_id,
                    'product_id'                 => $product['product_id'],
                    'variation_id'               => $product['variation_id'],
                    'quantity'                   => $qty,
                    'unit_price'                 => $unit_price,
                    'unit_price_before_discount' => $unit_price,
                    'unit_price_inc_tax'          => $unit_price,
                    'sub_unit_id'                => $product['sub_unit_id'] ?: null,
                    'so_line_id'                 => $product['so_line_id'] ?: null,
                    'so_quantity_invoiced'        => $qty,
                    'item_tax'                   => 0,
                    'line_discount_type'         => null,
                    'line_discount_amount'       => 0,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);

                // Build combo children list:
                // 1. From form (combo/combo_single products send children explicitly)
                $children = [];
                if (!empty($product['children'])) {
                    $children = is_string($product['children'])
                        ? json_decode($product['children'], true)
                        : $product['children'];
                }

                // 2. If no children from form, auto-inject from rewards_exchange table
                $children_type = in_array($product_type, ['combo', 'combo_single']) ? $product_type : 'combo';
                if (empty($children)) {
                    $rewardRules = DB::table('rewards_exchange')
                        ->where('product_for_sale', $product['product_id'])
                        ->where('business_id', $business_id)
                        ->whereNull('deleted_at')
                        ->get();

                    foreach ($rewardRules as $rule) {
                        $receiveVariation = DB::table('variations')
                            ->where('product_id', $rule->receive_product)
                            ->first();
                        if (!$receiveVariation) continue;

                        $receiveProduct = DB::table('products')
                            ->where('id', $rule->receive_product)
                            ->first();

                        $children[] = [
                            'product_id'   => $rule->receive_product,
                            'variation_id' => $receiveVariation->id,
                            'unit_qty'     => (float) $rule->receive_quantity,
                            'unit_price'   => 0,
                            'enable_stock' => $receiveProduct ? $receiveProduct->enable_stock : 1,
                        ];
                    }
                    // Reward exchange children: parent stock NOT cut, children_type = reward_exchange
                    if (!empty($children)) {
                        $product_type  = 'reward_exchange';
                        $children_type = 'reward_exchange';
                        $enable_stock  = false;
                    }
                }

                // Insert child lines into DN transaction_sell_lines
                foreach ((array) $children as $child) {
                    $child_qty = (float) ($child['unit_qty'] ?? 0) * $qty;
                    if ($child_qty <= 0) continue;
                    $child_price = (float) ($child['unit_price'] ?? 0);
                    DB::table('transaction_sell_lines')->insert([
                        'transaction_id'             => $dn_id,
                        'product_id'                 => $child['product_id'],
                        'variation_id'               => $child['variation_id'],
                        'quantity'                   => $child_qty,
                        'unit_price'                 => $child_price,
                        'unit_price_before_discount' => $child_price,
                        'unit_price_inc_tax'          => 0,
                        'sub_unit_id'                => null,
                        'parent_sell_line_id'        => $sell_line_id,
                        'children_type'              => $children_type,
                        'item_tax'                   => 0,
                        'line_discount_type'         => null,
                        'line_discount_amount'       => 0,
                        'so_quantity_invoiced'        => 0,
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ]);
                }

                // Cut stock when status = delivered
                // - Single product: cut parent stock directly
                // - Combo (incl. rewards_exchange children): ONLY children cut stock, NOT parent
                if ($request->status === 'delivered') {
                    if ($enable_stock && $product_type === 'single') {
                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $request->location_id,
                            $qty
                        );
                    }
                    // Children (combo or rewards_exchange receive_product) cut their stock
                    foreach ((array) $children as $child) {
                        if (!empty($child['enable_stock'])) {
                            $child_qty = (float) ($child['unit_qty'] ?? 0) * $qty;
                            if ($child_qty > 0) {
                                $this->productUtil->decreaseProductQuantity(
                                    $child['product_id'],
                                    $child['variation_id'],
                                    $request->location_id,
                                    $child_qty
                                );
                            }
                        }
                    }
                }
            }

            // When DN is delivered → update SO status (partial or completed)
            if ($request->status === 'delivered' && !empty($request->sales_order_id)) {
                $this->syncSalesOrderStatus((int) $request->sales_order_id, $business_id);
            }

            // Always touch the SO updated_at so the Sales Order cache knows to refresh
            // (syncSalesOrderStatus only runs for 'delivered'; 'ordered' status also needs this)
            if (!empty($request->sales_order_id)) {
                DB::table('transactions')
                    ->where('id', (int) $request->sales_order_id)
                    ->where('type', 'sales_order')
                    ->update(['updated_at' => now()]);
            }

            $this->logActivity($dn_id, 'Added');

            DB::commit();
            return response()->json(['success' => true, 'invoice_no' => $invoice_no, 'id' => $dn_id]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Edit ────────────────────────────────────────────────────────────────────

    public function edit(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_note.update')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_note')
            ->select('t.*', 'c.name as customer_name', 'bl.name as location_name')
            ->first();

        if (!$dn) abort(404);

        if (in_array($dn->status, ['delivered', 'completed'])) {
            return redirect()->route('delivery-note.index')
                ->with('error', 'Delivery Note "' . $dn->invoice_no . '" cannot be edited — stock has already been cut (status: ' . ucfirst($dn->status) . ').');
        }

        $dn_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select(
                'tsl.*',
                'p.name as product_name',
                'p.type as product_type',
                'p.enable_stock',
                'v.name as variation_name',
                'v.sub_sku',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name")
            )
            ->get();

        $so = $dn->sales_order_id
            ? DB::table('transactions')->where('id', $dn->sales_order_id)->first()
            : null;

        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)->pluck('name', 'id');

        $delivery_persons = User::forDropdown($business_id, false)->prepend(__('messages.please_select'), '');

        return view('delivery_note.edit', compact('dn', 'dn_lines', 'so', 'business_locations', 'delivery_persons'));
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_note.update')) abort(403, 'Unauthorized action.');
        $request->validate([
            'status'   => 'required|in:ordered,delivered,completed', // completed allowed only when already set (auto via invoice)
            'products' => 'required|array|min:1',
        ]);

        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->first();

        if (!$dn) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        // Block editing when stock has already been cut
        if (in_array($dn->status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery Note "' . $dn->invoice_no . '" cannot be edited — stock has already been cut (status: ' . ucfirst($dn->status) . ').',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $old_status  = $dn->status;
            $new_status  = $request->status;
            $final_total = collect($request->products)->sum(fn($p) => ($p['deliver_qty'] ?? 0) * ($p['unit_price'] ?? 0));

            DB::table('transactions')->where('id', $id)->update([
                'status'           => $new_status,
                'location_id'      => $request->location_id ?? $dn->location_id,
                'delivery_person'  => $request->delivery_person ?: null,
                'additional_notes' => $request->delivery_note,
                'final_total'      => $final_total,
                'total_before_tax' => $final_total,
                'updated_at'       => now(),
            ]);

            // Handle stock changes:
            // - 'delivered' → cuts stock
            // - 'completed' → no stock cut (DN is done, sell was created from it)
            // - back to 'ordered' from 'delivered' → restore stock
            $was_stock_cut   = in_array($old_status, ['delivered', 'completed']);
            $was_delivered   = $old_status === 'delivered';
            $is_delivered    = $new_status === 'delivered';    // only 'delivered' cuts stock
            $restoring_stock = $was_stock_cut && $new_status === 'ordered';

            // Get current lines for stock reversal if needed
            $old_lines = DB::table('transaction_sell_lines')
                ->where('transaction_id', $id)->get();

            if ($restoring_stock) {
                // Revert stock: add back (transitioning back to ordered)
                foreach ($old_lines as $line) {
                    DB::table('variation_location_details')
                        ->where('variation_id', $line->variation_id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $dn->location_id)
                        ->increment('qty_available', $line->quantity);
                }
            }

            // Delete old lines
            DB::table('transaction_sell_lines')->where('transaction_id', $id)->delete();

            // Re-insert lines (same structure as sell/SO)
            foreach ($request->products as $product) {
                $qty = (float) ($product['deliver_qty'] ?? 0);
                if ($qty <= 0) continue;

                $unit_price   = (float) ($product['unit_price'] ?? 0);
                $product_type = $product['product_type'] ?? 'single';
                $enable_stock = !empty($product['enable_stock']);

                $sell_line_id = DB::table('transaction_sell_lines')->insertGetId([
                    'transaction_id'             => $id,
                    'product_id'                 => $product['product_id'],
                    'variation_id'               => $product['variation_id'],
                    'quantity'                   => $qty,
                    'unit_price'                 => $unit_price,
                    'unit_price_before_discount' => $unit_price,
                    'unit_price_inc_tax'          => $unit_price,
                    'sub_unit_id'                => $product['sub_unit_id'] ?: null,
                    'so_line_id'                 => $product['so_line_id'] ?: null,
                    'so_quantity_invoiced'        => $qty,
                    'item_tax'                   => 0,
                    'line_discount_type'         => null,
                    'line_discount_amount'       => 0,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);

                // Build children: from form first, then auto-inject from rewards_exchange
                $children = [];
                $children_type = in_array($product_type, ['combo', 'combo_single']) ? $product_type : 'combo';
                if (!empty($product['children'])) {
                    $children = is_string($product['children'])
                        ? json_decode($product['children'], true)
                        : $product['children'];
                }
                if (empty($children)) {
                    $rewardRules = DB::table('rewards_exchange')
                        ->where('product_for_sale', $product['product_id'])
                        ->where('business_id', $business_id)
                        ->whereNull('deleted_at')
                        ->get();
                    foreach ($rewardRules as $rule) {
                        $receiveVariation = DB::table('variations')->where('product_id', $rule->receive_product)->first();
                        if (!$receiveVariation) continue;
                        $receiveProduct = DB::table('products')->where('id', $rule->receive_product)->first();
                        $children[] = [
                            'product_id'   => $rule->receive_product,
                            'variation_id' => $receiveVariation->id,
                            'unit_qty'     => (float) $rule->receive_quantity,
                            'unit_price'   => 0,
                            'enable_stock' => $receiveProduct ? $receiveProduct->enable_stock : 1,
                        ];
                    }
                    if (!empty($children)) {
                        $product_type  = 'reward_exchange';
                        $children_type = 'reward_exchange';
                        $enable_stock  = false;
                    }
                }

                foreach ((array) $children as $child) {
                    $child_qty = (float) ($child['unit_qty'] ?? 0) * $qty;
                    if ($child_qty <= 0) continue;
                    $child_price = (float) ($child['unit_price'] ?? 0);
                    DB::table('transaction_sell_lines')->insert([
                        'transaction_id'             => $id,
                        'product_id'                 => $child['product_id'],
                        'variation_id'               => $child['variation_id'],
                        'quantity'                   => $child_qty,
                        'unit_price'                 => $child_price,
                        'unit_price_before_discount' => $child_price,
                        'unit_price_inc_tax'          => 0,
                        'sub_unit_id'                => null,
                        'parent_sell_line_id'        => $sell_line_id,
                        'children_type'              => $children_type,
                        'item_tax'                   => 0,
                        'line_discount_type'         => null,
                        'line_discount_amount'       => 0,
                        'so_quantity_invoiced'        => 0,
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ]);
                }

                // Cut stock only when transitioning to 'delivered' (not to 'completed')
                // Combo / rewards_exchange: ONLY children cut stock, NOT parent
                if (!$was_stock_cut && $is_delivered) {
                    if ($enable_stock && $product_type === 'single') {
                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $dn->location_id,
                            $qty
                        );
                    }
                    foreach ((array) $children as $child) {
                        if (!empty($child['enable_stock'])) {
                            $child_qty = (float) ($child['unit_qty'] ?? 0) * $qty;
                            if ($child_qty > 0) {
                                $this->productUtil->decreaseProductQuantity(
                                    $child['product_id'],
                                    $child['variation_id'],
                                    $dn->location_id,
                                    $child_qty
                                );
                            }
                        }
                    }
                }
            }

            // Sync Sales Order status based on DN status transition
            if (!empty($dn->sales_order_id)) {
                if ($is_delivered) {
                    $this->syncSalesOrderStatus((int) $dn->sales_order_id, $business_id);
                } elseif ($restoring_stock) {
                    $otherDeliveredDN = DB::table('transactions')
                        ->where('sales_order_id', $dn->sales_order_id)
                        ->where('type', 'delivery_note')
                        ->where('id', '!=', $id)
                        ->whereIn('status', ['delivered', 'completed'])
                        ->whereNull('deleted_at')
                        ->exists();
                    if (!$otherDeliveredDN) {
                        DB::table('transactions')
                            ->where('id', $dn->sales_order_id)
                            ->where('type', 'sales_order')
                            ->update(['status' => 'ordered', 'updated_at' => now()]);
                    } else {
                        $this->syncSalesOrderStatus((int) $dn->sales_order_id, $business_id);
                    }
                } else {
                    // No status change (e.g. staying 'ordered') — still touch SO updated_at
                    // so the Sales Order cache knows to refresh the DN badge
                    DB::table('transactions')
                        ->where('id', (int) $dn->sales_order_id)
                        ->where('type', 'sales_order')
                        ->update(['updated_at' => now()]);
                }
            }

            $this->logActivity($id, 'Updated');

            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── View (modal) ────────────────────────────────────────────────────────────

    public function show(Request $request, int $id)
    {
        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoin('users as dp', 't.delivery_person', '=', 'dp.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_note')
            ->select([
                't.*', 'c.name as customer_name', 'c.mobile as customer_mobile',
                'c.address_line_1 as customer_address', 'c.city as customer_city',
                'bl.name as location_name',
                DB::raw("TRIM(CONCAT(COALESCE(dp.first_name,''), ' ', COALESCE(dp.last_name,''))) as delivery_person_name"),
            ])
            ->first();

        if (!$dn) abort(404);

        $dn_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select(
                'tsl.*',
                'p.name as product_name',
                'p.type as product_type',
                'p.enable_stock',
                'v.name as variation_name',
                'v.sub_sku',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name")
            )
            ->get();

        $so = $dn->sales_order_id
            ? DB::table('transactions')->where('id', $dn->sales_order_id)->first()
            : null;

        // Source 1: delivery_note_activities (Created, Updated, etc.)
        $dnActivities = DB::table('delivery_note_activities')
            ->where('transaction_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($r) => (object)[
                'created_at' => $r->created_at,
                'action'     => $r->action,
                'by'         => $r->by,
                'note'       => $r->note ?? '',
            ]);

        // Source 2: activity_log (dn_driver_assigned from scan-assign API)
        $logActivities = DB::table('activity_log as al')
            ->leftJoin('users as u', 'al.causer_id', '=', 'u.id')
            ->where('al.subject_id', $id)
            ->where('al.subject_type', \App\Transaction::class)
            ->where('al.description', 'dn_driver_assigned')
            ->orderBy('al.created_at', 'asc')
            ->select('al.created_at', 'al.properties', 'u.first_name')
            ->get()
            ->map(function ($r) {
                $props      = json_decode($r->properties ?? '{}', true);
                $driverName = $props['driver_name'] ?? '—';
                $stockCut   = isset($props['stock_cut']) ? ($props['stock_cut'] ? 'Stock deducted' : 'No stock change') : '';
                return (object)[
                    'created_at' => $r->created_at,
                    'action'     => 'Driver Assigned',
                    'by'         => $r->first_name ?? '—',
                    'note'       => 'Driver: ' . $driverName . ($stockCut ? ' · ' . $stockCut : ''),
                ];
            });

        // Merge and sort by date ascending
        $activities = $dnActivities->concat($logActivities)
            ->sortBy('created_at')
            ->values();

        $total_ordered  = $dn_lines->sum('quantity');
        $total_delivered = $dn_lines->sum('quantity');

        return view('delivery_note.view_modal', compact('dn', 'dn_lines', 'so', 'activities', 'total_ordered', 'total_delivered'));
    }

    // ─── Receipt JSON (for print-invoice AJAX mechanism) ─────────────────────────

    public function getReceipt(Request $request, int $id)
    {
        $business_id = $request->session()->get('user.business_id');

        // Verify DN belongs to this business
        $dn = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->whereNull('deleted_at')
            ->first();

        if (!$dn) return response()->json(['success' => 0, 'msg' => 'Not found']);

        // Use sale_invoice_layout_id — same layout used by Sell/Invoice direct sales.
        // This ensures DN print has identical Khmer labels, footer text, and signature section.
        $location = DB::table('business_locations')->where('id', $dn->location_id)->first();
        $invoice_layout_id = $location->sale_invoice_layout_id ?? $location->invoice_layout_id;

        $dn_heading = DB::table('invoice_layouts')->where('id', $invoice_layout_id)->value('dn_heading') ?: __('lang_v1.delivery_note');

        // Always use the DN transaction itself (not the linked sell):
        // - shows DN invoice number, DN lines, no payment section
        // - footer text + signature section come from sale invoice layout (same as Sell/Invoice)
        // - heading is overridden with dn_heading from the invoice layout
        $sellPosController = app(\App\Http\Controllers\SellPosController::class);
        $receipt = $sellPosController->receiptContent(
            $business_id,
            $dn->location_id,
            $id,
            'browser',
            false,
            false,
            $invoice_layout_id,  // explicitly use sale_invoice_layout_id — same as Sell/Invoice
            false,               // $is_delivery_note = false → standard classic/elegant template
            $dn_heading          // override heading with dn_heading from the invoice layout
        );

        if (empty($receipt) || empty($receipt['html_content'])) {
            return response()->json(['success' => 0, 'msg' => 'Could not generate receipt']);
        }

        return response()->json([
            'success' => 1,
            'receipt' => [
                'html_content' => $receipt['html_content'],
                'print_title'  => 'DN-' . $dn->invoice_no,
                'rotate_90'    => !empty($receipt['rotate_90']) ? 1 : 0,
            ],
        ]);
    }

    // ─── Print (direct new-tab, kept for fallback) ────────────────────────────────

    public function printNote(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_note.print')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoin('users as dp', 't.delivery_person', '=', 'dp.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->select([
                't.*', 'c.name as customer_name', 'c.mobile as customer_mobile',
                'c.address_line_1 as customer_address',
                'bl.name as location_name',
                DB::raw("TRIM(CONCAT(COALESCE(dp.first_name,''), ' ', COALESCE(dp.last_name,''))) as delivery_person_name"),
            ])
            ->first();

        if (!$dn) abort(404);

        $dn_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select(
                'tsl.*',
                'p.name as product_name',
                'p.type as product_type',
                'p.enable_stock',
                'v.name as variation_name',
                'v.sub_sku',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name")
            )
            ->get();

        $business = Business::find($business_id);

        return view('delivery_note.print', compact('dn', 'dn_lines', 'business'));
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function destroy(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_note.delete')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->first();

        if (!$dn) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        // Block delete if any active sell invoice references this DN
        $linked_sell = DB::table('transactions')
            ->where('delivery_note_id', $id)
            ->where('type', 'sell')
            ->whereNull('deleted_at')
            ->first();

        if ($linked_sell) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete Delivery Note "' . $dn->invoice_no . '" — it is linked to Sale Invoice "' . $linked_sell->invoice_no . '". Delete the sale invoice first.',
            ], 422);
        }

        // Block delete if any delivery return references this DN
        $linked_return = DB::table('transactions')
            ->where('delivery_note_id', $id)
            ->where('type', 'delivery_return')
            ->whereNull('deleted_at')
            ->first();

        if ($linked_return) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete Delivery Note "' . $dn->invoice_no . '" — it is linked to Delivery Return "' . $linked_return->invoice_no . '". Delete the delivery return first.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Revert stock if was delivered
            if (in_array($dn->status, ['delivered', 'completed'])) {
                $lines = DB::table('transaction_sell_lines')->where('transaction_id', $id)->get();
                foreach ($lines as $line) {
                    DB::table('variation_location_details')
                        ->where('variation_id', $line->variation_id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $dn->location_id)
                        ->increment('qty_available', $line->quantity);
                }
            }

            DB::table('transaction_sell_lines')->where('transaction_id', $id)->delete();
            DB::table('transactions')->where('id', $id)->update(['deleted_at' => now()]);

            // Reset SO status back to 'ordered' so user can create a new DN
            if (!empty($dn->sales_order_id)) {
                $this->syncSalesOrderStatus((int) $dn->sales_order_id, $business_id);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
