<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Business;
use Yajra\DataTables\Facades\DataTables;

class DeliveryReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function generateDRNumber(int $business_id): string
    {
        $year  = Carbon::now()->year;
        $count = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'delivery_return')
            ->whereYear('created_at', $year)
            ->count();
        return 'DR' . $year . '/' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    private function logActivity(int $transaction_id, string $action, string $note = ''): void
    {
        DB::table('delivery_return_activities')->insert([
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
            $query = DB::table('transactions as t')
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'delivery_return')
                ->whereNull('t.deleted_at')
                ->select([
                    't.id', 't.invoice_no', 't.transaction_date', 't.status',
                    't.delivery_note_id', 't.contact_id',
                    'c.name as customer_name', 'bl.name as location_name',
                ]);

            // view_own: only show records created by this user
            if (!auth()->user()->can('delivery_return.view_all') && auth()->user()->can('delivery_return.view_own')) {
                $query->where('t.created_by', auth()->id());
            }

            if ($request->filled('location_id')) {
                $query->where('t.location_id', $request->location_id);
            }
            if ($request->filled('customer_id')) {
                $query->where('t.contact_id', $request->customer_id);
            }
            if ($request->filled('date_range')) {
                [$start, $end] = explode(' - ', $request->date_range);
                $query->whereBetween(DB::raw('DATE(t.transaction_date)'), [
                    Carbon::createFromFormat('m/d/Y', trim($start))->toDateString(),
                    Carbon::createFromFormat('m/d/Y', trim($end))->toDateString(),
                ]);
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $dn_no = '';
                    if ($row->delivery_note_id) {
                        $dn_no = DB::table('transactions')->where('id', $row->delivery_note_id)->value('invoice_no') ?? '';
                    }

                    $stock_status = $row->status === 'completed'
                        ? '<span class="label label-success">Added Back</span>'
                        : '<span class="label label-warning">Not Added Back</span>';

                    $status_label = match ($row->status) {
                        'pending'   => '<span class="label label-warning">Pending</span>',
                        'completed' => '<span class="label label-success">Completed</span>',
                        default     => '<span class="label label-default">' . ucfirst($row->status) . '</span>',
                    };

                    $user  = auth()->user();
                    $items = '';

                    // Edit
                    if ($user->can('delivery_return.update')) {
                        $items .= '<li><a href="' . route('delivery-return.edit', $row->id) . '"><i class="fa fa-edit"></i> Edit Return</a></li>';
                    }

                    // View
                    if ($user->can('delivery_return.view_all') || $user->can('delivery_return.view_own')) {
                        $items .= '<li><a href="#" class="btn-view-dr" data-id="' . $row->id . '"><i class="fa fa-eye"></i> View Return</a></li>';
                    }

                    // Print
                    if ($user->can('delivery_return.print')) {
                        $items .= '<li><a href="#" class="print-invoice" data-href="' . route('delivery-return.getReceipt', $row->id) . '"><i class="fa fa-print"></i> Print Return</a></li>';
                    }

                    // Delete
                    if ($user->can('delivery_return.delete')) {
                        $items .= '<li class="divider"></li>';
                        $items .= '<li><a href="#" class="delete-dr text-danger" data-id="' . $row->id . '"><i class="fa fa-trash"></i> Delete</a></li>';
                    }

                    $btn = '<div class="btn-group">
                        <button type="button" class="btn btn-info btn-xs dropdown-toggle" data-toggle="dropdown">
                            Action <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">
                            ' . $items . '
                        </ul>
                    </div>
                    <span class="hidden dn_no">' . $dn_no . '</span>
                    <span class="hidden stock_status_raw">' . $stock_status . '</span>
                    <span class="hidden status_label_raw">' . $status_label . '</span>';

                    return $btn;
                })
                ->editColumn('transaction_date', fn($r) => Carbon::parse($r->transaction_date)->format('m/d/Y H:i'))
                ->rawColumns(['action'])
                ->make(true);
        }

        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)->pluck('name', 'id')->prepend('All', '');
        $customers = DB::table('contacts')
            ->where('business_id', $business_id)
            ->where('type', 'customer')
            ->pluck('name', 'id')->prepend('All', '');

        return view('delivery_return.index', compact('business_locations', 'customers'));
    }

    // ─── Check DN number (AJAX) ───────────────────────────────────────────────────

    public function checkDeliveryNote(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $dn_no       = $request->input('dn_no');

        $dn = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_note')
            ->where('t.invoice_no', $dn_no)
            ->whereNull('t.deleted_at')
            ->select('t.*', 'c.name as customer_name')
            ->first();

        if (!$dn) {
            return response()->json(['success' => false, 'message' => 'Delivery Note not found.']);
        }

        // SO invoice_no
        $so_no = $dn->sales_order_id
            ? DB::table('transactions')->where('id', $dn->sales_order_id)->value('invoice_no')
            : null;

        // Lines with delivered qty
        $lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $dn->id)
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get()
            ->map(function ($line) {
                return [
                    'product_id'    => $line->product_id,
                    'variation_id'  => $line->variation_id,
                    'product_name'  => $line->product_name . ($line->variation_name !== 'DUMMY' ? ' - ' . $line->variation_name : ''),
                    'delivered_qty' => (float) $line->quantity,
                    'unit_name'     => $line->unit_name ?? '',
                    'sub_unit_id'   => $line->sub_unit_id,
                ];
            });

        return response()->json([
            'success'       => true,
            'dn_id'         => $dn->id,
            'dn_no'         => $dn->invoice_no,
            'customer_name' => $dn->customer_name,
            'customer_id'   => $dn->contact_id,
            'so_no'         => $so_no,
            'location_id'   => $dn->location_id,
            'lines'         => $lines,
        ]);
    }

    // ─── Create ──────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        if (!auth()->user()->can('delivery_return.create')) abort(403, 'Unauthorized action.');
        $business_id  = $request->session()->get('user.business_id');
        $dr_no_prefix = 'DR' . Carbon::now()->year . '/';
        return view('delivery_return.create', compact('dr_no_prefix'));
    }

    public function createFromDN(Request $request, int $dn_id)
    {
        if (!auth()->user()->can('delivery_return.create')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dn = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.id', $dn_id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_note')
            ->select('t.*', 'c.name as customer_name')
            ->first();

        if (!$dn) return redirect()->route('delivery-return.create');

        $so_no = $dn->sales_order_id
            ? DB::table('transactions')->where('id', $dn->sales_order_id)->value('invoice_no')
            : null;

        // Only parent lines — reward_exchange children are auto-handled on DR store
        $lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $dn_id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get();

        $dr_no_prefix = 'DR' . Carbon::now()->year . '/';

        return view('delivery_return.create', compact('dn', 'so_no', 'lines', 'dr_no_prefix'));
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        if (!auth()->user()->can('delivery_return.create')) abort(403, 'Unauthorized action.');
        $request->validate([
            'dn_id'      => 'required|integer',
            'customer_id'=> 'required|integer',
            'location_id'=> 'required|integer',
            'status'     => 'required|in:pending,completed',
            'products'   => 'required|array|min:1',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id     = auth()->id();

        DB::beginTransaction();
        try {
            $dr_no = !empty($request->return_no) ? trim($request->return_no) : $this->generateDRNumber($business_id);

            $dr_id = DB::table('transactions')->insertGetId([
                'business_id'      => $business_id,
                'location_id'      => $request->location_id,
                'type'             => 'delivery_return',
                'status'           => $request->status,
                'contact_id'       => $request->customer_id,
                'invoice_no'       => $dr_no,
                'transaction_date' => now(),
                'delivery_note_id' => $request->dn_id,
                'additional_notes' => $request->return_note,
                'final_total'      => 0,
                'total_before_tax' => 0,
                'created_by'       => $user_id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($request->products as $product) {
                $return_qty  = (float) ($product['return_qty'] ?? 0);
                $good_stock  = (float) ($product['good_stock'] ?? 0);
                $damaged     = (float) ($product['damaged'] ?? 0);

                // Skip only when ALL three columns are zero
                if ($return_qty <= 0 && $good_stock <= 0 && $damaged <= 0) continue;

                // Only return_qty increases stock; good_stock and damaged are notes only
                $total_restore = $return_qty;

                // Parent: quantity = return_qty (stock amount); good_stock_qty/damaged_qty saved as notes
                $dr_line_id = DB::table('transaction_sell_lines')->insertGetId([
                    'transaction_id'             => $dr_id,
                    'product_id'                 => $product['product_id'],
                    'variation_id'               => $product['variation_id'],
                    'quantity'                   => $total_restore,
                    'unit_price'                 => 0,
                    'unit_price_before_discount' => 0,
                    'unit_price_inc_tax'         => 0,
                    'sub_unit_id'                => $product['sub_unit_id'] ?: null,
                    'quantity_returned'          => $return_qty,
                    'good_stock_qty'             => $good_stock,
                    'damaged_qty'                => $damaged,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);

                // Restore parent stock (upsert in case VLD row doesn't exist)
                if ($request->status === 'completed' && $total_restore > 0) {
                    $parentVLD = DB::table('variation_location_details')
                        ->where('variation_id', $product['variation_id'])
                        ->where('product_id', $product['product_id'])
                        ->where('location_id', $request->location_id)
                        ->first();
                    if ($parentVLD) {
                        DB::table('variation_location_details')
                            ->where('variation_id', $product['variation_id'])
                            ->where('product_id', $product['product_id'])
                            ->where('location_id', $request->location_id)
                            ->increment('qty_available', $total_restore);
                    } else {
                        $pv = DB::table('variations')->where('id', $product['variation_id'])->first();
                        DB::table('variation_location_details')->insert([
                            'variation_id'         => $product['variation_id'],
                            'product_id'           => $product['product_id'],
                            'location_id'          => $request->location_id,
                            'product_variation_id' => $pv->product_variation_id ?? null,
                            'qty_available'        => $total_restore,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);
                    }
                }

                // For combo/combo_single/reward_exchange parents:
                // Restore child stock using total_restore ratio (not just return_qty)
                if (!empty($request->dn_id)) {
                    $dnChildren = DB::table('transaction_sell_lines')
                        ->where('transaction_id', $request->dn_id)
                        ->whereIn('children_type', ['combo', 'combo_single', 'reward_exchange'])
                        ->whereIn('parent_sell_line_id', function ($q) use ($request, $product) {
                            $q->select('id')->from('transaction_sell_lines')
                                ->where('transaction_id', $request->dn_id)
                                ->where('product_id', $product['product_id'])
                                ->where('variation_id', $product['variation_id'])
                                ->whereNull('parent_sell_line_id');
                        })
                        ->get();

                    foreach ($dnChildren as $child) {
                        $dnParentQty  = DB::table('transaction_sell_lines')
                            ->where('id', $child->parent_sell_line_id)
                            ->value('quantity') ?: 1;
                        $ratio = $child->quantity / $dnParentQty;

                        // Each column split proportionally: qty=total, returned=return_qty, good=good_stock, damaged=damaged
                        $childQty       = round($total_restore * $ratio, 4);
                        $childReturned  = round($return_qty    * $ratio, 4);
                        $childGood      = round($good_stock    * $ratio, 4);
                        $childDamaged   = round($damaged       * $ratio, 4);

                        if ($childQty <= 0) continue;

                        // Save child line into DR transaction_sell_lines for record
                        DB::table('transaction_sell_lines')->insert([
                            'transaction_id'             => $dr_id,
                            'product_id'                 => $child->product_id,
                            'variation_id'               => $child->variation_id,
                            'quantity'                   => $childQty,
                            'unit_price'                 => 0,
                            'unit_price_before_discount' => 0,
                            'unit_price_inc_tax'         => 0,
                            'parent_sell_line_id'        => $dr_line_id,
                            'children_type'              => $child->children_type,
                            'quantity_returned'          => $childReturned,
                            'good_stock_qty'             => $childGood,
                            'damaged_qty'                => $childDamaged,
                            'item_tax'                   => 0,
                            'line_discount_type'         => null,
                            'line_discount_amount'       => 0,
                            'so_quantity_invoiced'       => 0,
                            'created_at'                 => now(),
                            'updated_at'                 => now(),
                        ]);

                        // Restore child stock (upsert: create VLD row if missing)
                        if ($request->status === 'completed') {
                            $childVLD = DB::table('variation_location_details')
                                ->where('variation_id', $child->variation_id)
                                ->where('product_id', $child->product_id)
                                ->where('location_id', $request->location_id)
                                ->first();
                            if ($childVLD) {
                                DB::table('variation_location_details')
                                    ->where('variation_id', $child->variation_id)
                                    ->where('product_id', $child->product_id)
                                    ->where('location_id', $request->location_id)
                                    ->increment('qty_available', $childQty);
                            } else {
                                $childVariation = DB::table('variations')->where('id', $child->variation_id)->first();
                                DB::table('variation_location_details')->insert([
                                    'variation_id'         => $child->variation_id,
                                    'product_id'           => $child->product_id,
                                    'location_id'          => $request->location_id,
                                    'product_variation_id' => $childVariation->product_variation_id ?? null,
                                    'qty_available'        => $childQty,
                                    'created_at'           => now(),
                                    'updated_at'           => now(),
                                ]);
                            }
                        }
                    }
                }
            }

            $this->logActivity($dr_id, 'Added');

            DB::commit();
            return response()->json(['success' => true, 'invoice_no' => $dr_no, 'id' => $dr_id]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Edit ────────────────────────────────────────────────────────────────────

    public function edit(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_return.update')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dr = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_return')
            ->select('t.*', 'c.name as customer_name', 'bl.name as location_name')
            ->first();

        if (!$dr) abort(404);

        // Show only parent lines — child combo/reward_exchange lines are hidden
        // (same rule as view modal: client finds child breakdown confusing)
        $dr_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get();

        $dn = $dr->delivery_note_id
            ? DB::table('transactions')->where('id', $dr->delivery_note_id)->first()
            : null;

        return view('delivery_return.edit', compact('dr', 'dr_lines', 'dn'));
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_return.update')) abort(403, 'Unauthorized action.');
        $request->validate([
            'status'   => 'required|in:pending,completed',
            'products' => 'required|array|min:1',
        ]);

        $business_id = $request->session()->get('user.business_id');

        $dr = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_return')
            ->first();

        if (!$dr) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        DB::beginTransaction();
        try {
            $old_status = $dr->status;
            $new_status = $request->status;

            // Revert stock whenever old status was completed (covers completed→pending AND completed→completed)
            if ($old_status === 'completed') {
                $old_lines = DB::table('transaction_sell_lines')
                    ->where('transaction_id', $id)
                    ->get();
                foreach ($old_lines as $line) {
                    $old_total = (float)$line->quantity;
                    if ($old_total <= 0) continue;
                    DB::table('variation_location_details')
                        ->where('variation_id', $line->variation_id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $dr->location_id)
                        ->decrement('qty_available', $old_total);
                }
            }

            DB::table('transactions')->where('id', $id)->update([
                'status'           => $new_status,
                'additional_notes' => $request->return_note,
                'updated_at'       => now(),
            ]);

            DB::table('transaction_sell_lines')->where('transaction_id', $id)->delete();

            foreach ($request->products as $product) {
                $return_qty = (float) ($product['return_qty'] ?? 0);
                $good_stock = (float) ($product['good_stock'] ?? 0);
                $damaged    = (float) ($product['damaged'] ?? 0);

                // Skip only when ALL three columns are zero
                if ($return_qty <= 0 && $good_stock <= 0 && $damaged <= 0) continue;

                // Only return_qty increases stock; good_stock and damaged are notes only
                $total_restore = $return_qty;

                // Parent: quantity = return_qty (stock amount); good_stock_qty/damaged_qty saved as notes
                $dr_line_id = DB::table('transaction_sell_lines')->insertGetId([
                    'transaction_id'             => $id,
                    'product_id'                 => $product['product_id'],
                    'variation_id'               => $product['variation_id'],
                    'quantity'                   => $total_restore,
                    'unit_price'                 => 0,
                    'unit_price_before_discount' => 0,
                    'unit_price_inc_tax'         => 0,
                    'sub_unit_id'                => $product['sub_unit_id'] ?: null,
                    'quantity_returned'          => $return_qty,
                    'good_stock_qty'             => $good_stock,
                    'damaged_qty'                => $damaged,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);

                // Restore parent stock (upsert in case VLD row doesn't exist)
                if ($new_status === 'completed' && $total_restore > 0) {
                    $parentVLD = DB::table('variation_location_details')
                        ->where('variation_id', $product['variation_id'])
                        ->where('product_id', $product['product_id'])
                        ->where('location_id', $dr->location_id)
                        ->first();
                    if ($parentVLD) {
                        DB::table('variation_location_details')
                            ->where('variation_id', $product['variation_id'])
                            ->where('product_id', $product['product_id'])
                            ->where('location_id', $dr->location_id)
                            ->increment('qty_available', $total_restore);
                    } else {
                        $pv = DB::table('variations')->where('id', $product['variation_id'])->first();
                        DB::table('variation_location_details')->insert([
                            'variation_id'         => $product['variation_id'],
                            'product_id'           => $product['product_id'],
                            'location_id'          => $dr->location_id,
                            'product_variation_id' => $pv->product_variation_id ?? null,
                            'qty_available'        => $total_restore,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);
                    }
                }

                // Insert child lines + restore child stock for combo/combo_single/reward_exchange
                if (!empty($dr->delivery_note_id)) {
                    $dnChildren = DB::table('transaction_sell_lines')
                        ->where('transaction_id', $dr->delivery_note_id)
                        ->whereIn('children_type', ['combo', 'combo_single', 'reward_exchange'])
                        ->whereIn('parent_sell_line_id', function ($q) use ($product, $dr) {
                            $q->select('id')->from('transaction_sell_lines')
                                ->where('transaction_id', $dr->delivery_note_id)
                                ->where('product_id', $product['product_id'])
                                ->where('variation_id', $product['variation_id'])
                                ->whereNull('parent_sell_line_id');
                        })
                        ->get();

                    foreach ($dnChildren as $child) {
                        $dnParentQty  = DB::table('transaction_sell_lines')
                            ->where('id', $child->parent_sell_line_id)->value('quantity') ?: 1;
                        $ratio = $child->quantity / $dnParentQty;

                        // Each column split proportionally
                        $childQty     = round($total_restore * $ratio, 4);
                        $childReturned = round($return_qty   * $ratio, 4);
                        $childGood    = round($good_stock    * $ratio, 4);
                        $childDamaged = round($damaged       * $ratio, 4);

                        if ($childQty <= 0) continue;

                        // Save child line into DR transaction_sell_lines for record
                        DB::table('transaction_sell_lines')->insert([
                            'transaction_id'             => $id,
                            'product_id'                 => $child->product_id,
                            'variation_id'               => $child->variation_id,
                            'quantity'                   => $childQty,
                            'unit_price'                 => 0,
                            'unit_price_before_discount' => 0,
                            'unit_price_inc_tax'         => 0,
                            'parent_sell_line_id'        => $dr_line_id,
                            'children_type'              => $child->children_type,
                            'quantity_returned'          => $childReturned,
                            'good_stock_qty'             => $childGood,
                            'damaged_qty'                => $childDamaged,
                            'item_tax'                   => 0,
                            'line_discount_type'         => null,
                            'line_discount_amount'       => 0,
                            'so_quantity_invoiced'       => 0,
                            'created_at'                 => now(),
                            'updated_at'                 => now(),
                        ]);

                        // Restore child stock when completing (upsert: create VLD row if missing)
                        if ($new_status === 'completed') {
                            $childVLD = DB::table('variation_location_details')
                                ->where('variation_id', $child->variation_id)
                                ->where('product_id', $child->product_id)
                                ->where('location_id', $dr->location_id)
                                ->first();
                            if ($childVLD) {
                                DB::table('variation_location_details')
                                    ->where('variation_id', $child->variation_id)
                                    ->where('product_id', $child->product_id)
                                    ->where('location_id', $dr->location_id)
                                    ->increment('qty_available', $childQty);
                            } else {
                                $childVariation = DB::table('variations')->where('id', $child->variation_id)->first();
                                DB::table('variation_location_details')->insert([
                                    'variation_id'         => $child->variation_id,
                                    'product_id'           => $child->product_id,
                                    'location_id'          => $dr->location_id,
                                    'product_variation_id' => $childVariation->product_variation_id ?? null,
                                    'qty_available'        => $childQty,
                                    'created_at'           => now(),
                                    'updated_at'           => now(),
                                ]);
                            }
                        }
                    }
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

        $dr = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_return')
            ->select([
                't.*', 'c.name as customer_name', 'c.mobile as customer_mobile',
                'c.address_line_1 as customer_address',
                'bl.name as location_name',
            ])
            ->first();

        if (!$dr) abort(404);

        // Show only parent lines (parent_sell_line_id IS NULL = GBS Sale, GBS Prize etc.)
        // Child breakdown lines (GBS Product cans) are hidden to avoid confusion
        $dr_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get();

        $dn = $dr->delivery_note_id
            ? DB::table('transactions')->where('id', $dr->delivery_note_id)->first()
            : null;

        // Pre-load DN parent-line quantities to match parent-only DR lines shown in view
        $dn_qty_map = [];
        if ($dr->delivery_note_id) {
            DB::table('transaction_sell_lines')
                ->where('transaction_id', $dr->delivery_note_id)
                ->whereNull('parent_sell_line_id')
                ->select('product_id', 'variation_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_id', 'variation_id')
                ->get()
                ->each(function ($row) use (&$dn_qty_map) {
                    $dn_qty_map[$row->product_id . '_' . $row->variation_id] = $row->total_qty;
                });
        }

        $activities = DB::table('delivery_return_activities')
            ->where('transaction_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $total_return_qty   = $dr_lines->sum('quantity_returned');
        $total_good_stock   = $dr_lines->sum('good_stock_qty');

        return view('delivery_return.view_modal', compact('dr', 'dr_lines', 'dn', 'dn_qty_map', 'activities', 'total_return_qty', 'total_good_stock'));
    }

    // ─── Print ───────────────────────────────────────────────────────────────────

    public function printReturn(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_return.print')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dr = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_return')
            ->select('t.*', 'c.name as customer_name', 'c.mobile as customer_mobile', 'bl.name as location_name')
            ->first();

        if (!$dr) abort(404);

        // Parent lines only — same rule as view modal (hide child breakdown)
        $dr_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get();

        $dn       = $dr->delivery_note_id ? DB::table('transactions')->where('id', $dr->delivery_note_id)->first() : null;
        $business = Business::find($business_id);

        // Pre-load DN parent-line quantities
        $dn_qty_map = [];
        if ($dr->delivery_note_id) {
            DB::table('transaction_sell_lines')
                ->where('transaction_id', $dr->delivery_note_id)
                ->whereNull('parent_sell_line_id')
                ->select('product_id', 'variation_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_id', 'variation_id')
                ->get()
                ->each(function ($row) use (&$dn_qty_map) {
                    $dn_qty_map[$row->product_id . '_' . $row->variation_id] = $row->total_qty;
                });
        }

        return view('delivery_return.print', compact('dr', 'dr_lines', 'dn', 'dn_qty_map', 'business'));
    }

    // ─── Receipt JSON (for print-invoice AJAX mechanism) ─────────────────────────

    public function getReceipt(Request $request, int $id)
    {
        $business_id = $request->session()->get('user.business_id');

        $dr = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.id', $id)
            ->where('t.business_id', $business_id)
            ->where('t.type', 'delivery_return')
            ->select('t.*', 'c.name as customer_name', 'c.mobile as customer_mobile', 'bl.name as location_name')
            ->first();

        if (!$dr) return response()->json(['success' => 0, 'msg' => 'Not found']);

        // Parent lines only — same rule as view modal (hide child combo/reward_exchange breakdown)
        $dr_lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('units as u2', 'tsl.sub_unit_id', '=', 'u2.id')
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.*', 'p.name as product_name', 'v.name as variation_name',
                'p.weight', 'v.default_sell_price',
                DB::raw("COALESCE(u2.short_name, u.short_name) as unit_name"))
            ->get();

        $dn       = $dr->delivery_note_id ? DB::table('transactions')->where('id', $dr->delivery_note_id)->first() : null;
        $business = \App\Business::find($business_id);

        // Pre-load DN parent-line quantities
        $dn_qty_map = [];
        if ($dr->delivery_note_id) {
            DB::table('transaction_sell_lines')
                ->where('transaction_id', $dr->delivery_note_id)
                ->whereNull('parent_sell_line_id')
                ->select('product_id', 'variation_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_id', 'variation_id')
                ->get()
                ->each(function ($row) use (&$dn_qty_map) {
                    $dn_qty_map[$row->product_id . '_' . $row->variation_id] = $row->total_qty;
                });
        }

        // Business logo URL
        $logo = null;
        if (!empty($business->logo)) {
            $logo = asset('storage/' . $business->logo);
        }

        // Location address
        $location = DB::table('business_locations')->where('id', $dr->location_id)->first();
        $addr_parts = array_filter([
            $location->landmark ?? null,
            $location->city ?? null,
            $location->state ?? null,
            $location->zip_code ?? null,
        ]);
        $address = implode(', ', $addr_parts);
        if (!empty($location->country)) {
            $address .= '<br>' . $location->country;
        }

        // Customer info
        $customer_info = $dr->customer_name;
        if (!empty($dr->customer_mobile)) {
            $customer_info .= '<br>Mobile: ' . $dr->customer_mobile;
        }
        $customer_info .= '<br>Location: ' . $dr->location_name;

        $qty_precision = (int) session('business.quantity_precision', 2);
        // Smart format: preserve actual decimal digits even when precision=0 (e.g. 0.5 shows as 0.5 not 1)
        $fmt_qty = function ($n) use ($qty_precision) {
            $n = (float) $n;
            $p = $qty_precision;
            if ($n != floor($n)) {
                $str = rtrim(rtrim(sprintf('%.10f', $n), '0'), '.');
                $dot = strpos($str, '.');
                $actual = ($dot !== false) ? (strlen($str) - $dot - 1) : 0;
                $p = max($p, $actual);
            }
            return number_format($n, $p);
        };

        // Build product lines
        $lines = $dr_lines->map(function ($line) use ($dn_qty_map, $fmt_qty) {
            $dn_qty = $dn_qty_map[$line->product_id . '_' . $line->variation_id] ?? 0;
            $name   = $line->product_name . ($line->variation_name !== 'DUMMY' ? ' - ' . $line->variation_name : '');
            return [
                'name'               => $name,
                'unit'               => $line->unit_name ?? '',
                'dn_qty'             => $fmt_qty($dn_qty),
                'return_qty'         => $fmt_qty($line->quantity_returned ?? 0),
                'good_stock'         => $fmt_qty($line->good_stock_qty ?? 0),
                'damaged'            => $fmt_qty($line->damaged_qty ?? 0),
                'weight'             => !empty($line->weight) ? (float) $line->weight : null,
                'default_sell_price' => $line->default_sell_price,
            ];
        })->values()->all();

        // Load invoice layout for marketing_price_label
        $invoice_layout_id = DB::table('business_locations')->where('id', $dr->location_id)->value('invoice_layout_id');
        $invoice_layout    = $invoice_layout_id ? \App\InvoiceLayout::find($invoice_layout_id) : null;
        $marketing_price_label = !empty($invoice_layout->marketing_price_label) ? $invoice_layout->marketing_price_label : null;

        // Build receipt details object for the template
        $rd = (object) [
            'logo'                  => $logo,
            'display_name'          => $business->name ?? '',
            'address'               => $address,
            'contact'               => $business->mobile ?? ($business->landline ?? null),
            'invoice_no'            => $dr->invoice_no,
            'dn_invoice_no'         => $dn->invoice_no ?? '—',
            'invoice_date'          => Carbon::parse($dr->transaction_date)->format('d-M-Y'),
            'status'                => ucfirst($dr->status),
            'customer_info'         => $customer_info,
            'lines'                 => $lines,
            'total_return_qty'      => $fmt_qty($dr_lines->sum('quantity_returned')),
            'total_good_stock'      => $fmt_qty($dr_lines->sum('good_stock_qty')),
            'total_damaged'         => $fmt_qty($dr_lines->sum('damaged_qty')),
            'notes'                 => $dr->additional_notes ?? null,
            'footer_text'           => null,
            'marketing_price_label' => $marketing_price_label,
        ];

        $html_content = view('sale_pos.receipts.delivery_return', compact('rd'))->render();

        $rotate_90 = !empty($invoice_layout->rotate_90) ? 1 : 0;
        if (!$rotate_90) {
            $sale_layout_id = DB::table('business_locations')->where('id', $dr->location_id)->value('sale_invoice_layout_id');
            if ($sale_layout_id && $sale_layout_id != $invoice_layout_id) {
                $sale_layout = \App\InvoiceLayout::find($sale_layout_id);
                if (!empty($sale_layout->rotate_90)) {
                    $rotate_90 = 1;
                }
            }
        }

        return response()->json([
            'success' => 1,
            'receipt' => [
                'html_content' => $html_content,
                'print_title'  => 'DR-' . $dr->invoice_no,
                'rotate_90'    => $rotate_90,
            ],
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function destroy(Request $request, int $id)
    {
        if (!auth()->user()->can('delivery_return.delete')) abort(403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');

        $dr = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_return')
            ->first();

        if (!$dr) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        DB::beginTransaction();
        try {
            // Revert stock if was completed
            if ($dr->status === 'completed') {
                $all_lines = DB::table('transaction_sell_lines')
                    ->where('transaction_id', $id)
                    ->get();

                foreach ($all_lines as $line) {
                    $revert = (float)$line->quantity;
                    if ($revert <= 0) continue;
                    DB::table('variation_location_details')
                        ->where('variation_id', $line->variation_id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $dr->location_id)
                        ->decrement('qty_available', $revert);
                }
            }

            DB::table('transaction_sell_lines')->where('transaction_id', $id)->delete();
            DB::table('transactions')->where('id', $id)->update(['deleted_at' => now()]);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
