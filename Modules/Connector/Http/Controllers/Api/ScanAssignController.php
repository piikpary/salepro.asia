<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Business;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ScanAssignController extends ApiController
{
    protected $productUtil;
    protected $transactionUtil;
    protected $businessUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, BusinessUtil $businessUtil)
    {
        $this->productUtil     = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil    = $businessUtil;
    }

    /**
     * GET /connector/api/mobile/drivers
     *
     * Returns active users assigned to the "Driver" role for the current business.
     */
    public function drivers(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;

        $driver_role_name = 'Driver#' . $business_id;

        $drivers = User::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereHas('roles', function ($q) use ($driver_role_name) {
                $q->where('name', $driver_role_name);
            })
            ->get(['id', 'first_name', 'contact_no', 'status']);

        $data = $drivers->map(function ($u) {
            return [
                'id'     => $u->id,
                'name'   => trim($u->first_name),
                'phone'  => $u->contact_no,
                'status' => $u->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /connector/api/mobile/scan-assign-invoice
     *
     * mode=check  — preview only, no DB update.
     * mode=assign — batch-assign delivery notes to a driver.
     *
     * Assign logic:
     *   status=ordered   → set to delivered + cut stock + update driver_id
     *   status=delivered → re-assign: update driver_id only (no stock effect)
     *   status=completed → re-assign: update driver_id only (no stock effect)
     */
    public function scanAssignInvoice(Request $request)
    {
        $mode = $request->input('mode');

        if ($mode === 'check') {
            return $this->handleCheck($request);
        }

        if ($mode === 'assign') {
            return $this->handleAssign($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid mode. Use "check" or "assign".',
        ], 422);
    }

    /**
     * GET /connector/api/mobile/scan-assign-invoice/history
     *
     * Returns driver-assignment history for delivery notes on a given date.
     * Optional query params: date, driver_id, assigned_by, start_date, end_date.
     */
    public function history(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;

        $date        = $request->input('date', now()->toDateString());
        $start_date  = $request->input('start_date', $date);
        $end_date    = $request->input('end_date', $date);
        $driver_id   = $request->input('driver_id');
        $assigned_by = $request->input('assigned_by');

        $query = Activity::where('description', 'dn_driver_assigned')
            ->where('subject_type', Transaction::class)
            ->whereBetween(DB::raw('DATE(created_at)'), [$start_date, $end_date])
            ->orderBy('created_at', 'desc');

        if ($assigned_by) {
            $query->where('causer_id', $assigned_by);
        }

        $logs            = $query->get();
        $transaction_ids = $logs->pluck('subject_id')->unique()->values();

        $transactions = Transaction::whereIn('id', $transaction_ids)
            ->where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->whereNull('deleted_at')
            ->when($driver_id, fn($q) => $q->where('delivery_person', $driver_id))
            ->with(['contact', 'delivery_person_user'])
            ->get()
            ->keyBy('id');

        $data = [];
        foreach ($logs as $log) {
            $tx = $transactions->get($log->subject_id);
            if (! $tx) {
                continue;
            }

            $props  = $log->properties ?? collect();
            $causer = User::find($log->causer_id);
            $driver = $tx->delivery_person_user;

            $data[] = [
                'id'               => $tx->id,
                'invoice_no'       => $tx->invoice_no,
                'customer'         => optional($tx->contact)->name,
                'dn_status'        => $tx->status,
                'driver_id'        => $driver ? $driver->id : $props->get('driver_id'),
                'driver_name'      => $driver ? trim($driver->first_name) : $props->get('driver_name'),
                'assigned_by_id'   => $log->causer_id,
                'assigned_by_name' => $causer ? trim($causer->first_name) : null,
                'assigned_at'      => $log->created_at->format('Y-m-d H:i:s'),
                'stock_cut'        => $props->get('stock_cut', false),
            ];
        }

        return response()->json([
            'success' => true,
            'date'    => $date,
            'total'   => count($data),
            'data'    => $data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function handleCheck(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;
        $invoice_val = trim($request->input('invoice', ''));

        if (empty($invoice_val)) {
            return response()->json([
                'success' => false,
                'type'    => 'not_found',
                'message' => 'Invoice value is required.',
            ], 422);
        }

        // ── Try Delivery Note first ──────────────────────────────────────────────
        $tx = Transaction::where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($invoice_val) {
                $q->where('invoice_no', $invoice_val)
                  ->orWhere('id', is_numeric($invoice_val) ? (int) $invoice_val : 0);
            })
            ->with(['contact', 'delivery_person_user'])
            ->first();

        if ($tx) {
            $driver         = $tx->delivery_person_user;
            $already        = ! empty($tx->delivery_person);
            $will_cut_stock = ($tx->status === 'ordered');

            return response()->json([
                'success'      => true,
                'scanned_type' => 'delivery_note',
                'type'         => $already ? 'already_assigned' : 'ready',
                'need_confirm' => $already,
                'message'      => $already
                    ? 'This delivery note is already assigned. Re-assign will update driver only.'
                    : 'Delivery note is ready to assign.',
                'data' => [
                    'id'                  => $tx->id,
                    'invoice_no'          => $tx->invoice_no,
                    'customer'            => optional($tx->contact)->name,
                    'dn_status'           => $tx->status,
                    'current_driver_id'   => $driver ? $driver->id : null,
                    'current_driver_name' => $driver ? trim($driver->first_name) : null,
                    'will_cut_stock'      => $will_cut_stock,
                    'can_assign'          => true,
                ],
            ]);
        }

        // ── Try Sell invoice ─────────────────────────────────────────────────────
        $sell = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereNull('deleted_at')
            ->where('invoice_no', $invoice_val)
            ->with(['contact', 'delivery_person_user'])
            ->first();

        if (! $sell) {
            return response()->json([
                'success' => false,
                'type'    => 'not_found',
                'message' => 'Invoice not found as delivery note or sell invoice.',
            ]);
        }

        $dn = $sell->delivery_note_id
            ? Transaction::where('business_id', $business_id)
                ->where('id', $sell->delivery_note_id)
                ->whereNull('deleted_at')
                ->first()
            : null;

        $driver         = $sell->delivery_person_user;
        $already        = ! empty($sell->delivery_person);
        $will_cut_stock = $dn && $dn->status === 'ordered';

        return response()->json([
            'success'      => true,
            'scanned_type' => 'sell',
            'type'         => $already ? 'already_assigned' : 'ready',
            'need_confirm' => $already,
            'message'      => $already
                ? 'This sell invoice is already assigned. Re-assign will update driver only.'
                : 'Sell invoice is ready to assign.',
            'data' => [
                'sell_id'             => $sell->id,
                'sell_invoice_no'     => $sell->invoice_no,
                'dn_id'               => $dn ? $dn->id : null,
                'dn_invoice_no'       => $dn ? $dn->invoice_no : null,
                'dn_status'           => $dn ? $dn->status : null,
                'customer'            => optional($sell->contact)->name,
                'current_driver_id'   => $driver ? $driver->id : null,
                'current_driver_name' => $driver ? trim($driver->first_name) : null,
                'will_cut_stock'      => $will_cut_stock,
                'can_assign'          => true,
            ],
        ]);
    }

    private function handleAssign(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;

        $invoice_ids    = $request->input('invoice_ids', []);
        $driver_id      = $request->input('driver_id');
        $force_reassign = (bool) $request->input('force_reassign', false);

        if (empty($invoice_ids) || ! $driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'invoice_ids and driver_id are required.',
            ], 422);
        }

        $driver = User::where('business_id', $business_id)
            ->where('id', $driver_id)
            ->where('status', 'active')
            ->first();

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found or inactive.',
            ], 422);
        }

        // Accept both numeric transaction ID and DN invoice_no string (e.g. DN2026/0019)
        $numeric_ids = array_values(array_filter($invoice_ids, fn($v) => is_numeric($v)));
        $invoice_nos = array_values(array_filter($invoice_ids, fn($v) => ! is_numeric($v)));

        // ── Batch fetch DNs (by numeric ID or invoice_no) ───────────────────────
        $dn_transactions = Transaction::where('business_id', $business_id)
            ->where('type', 'delivery_note')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($numeric_ids, $invoice_nos) {
                $q->whereIn('id', $numeric_ids)
                  ->orWhereIn('invoice_no', $invoice_nos);
            })
            ->with(['contact', 'delivery_person_user'])
            ->get();

        $by_dn_id  = $dn_transactions->keyBy('id');
        $by_dn_inv = $dn_transactions->keyBy('invoice_no');

        // ── Batch fetch Sell transactions by invoice_no (all raw values) ─────────
        // Numeric values like "0116" are checked as sell invoice_no string too.
        $sell_transactions = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereNull('deleted_at')
            ->whereIn('invoice_no', array_map('strval', $invoice_ids))
            ->with(['contact', 'delivery_person_user'])
            ->get();

        $by_sell_inv = $sell_transactions->keyBy('invoice_no');

        // ── Batch load DNs linked to found sells ─────────────────────────────────
        $linked_dn_ids = $sell_transactions->pluck('delivery_note_id')->filter()->unique()->values()->toArray();
        $linked_dns    = Transaction::whereIn('id', $linked_dn_ids)
            ->where('business_id', $business_id)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $accounting_method = DB::table('business')->where('id', $business_id)->value('accounting_method');
        $business_details  = $this->businessUtil->getDetails($business_id);
        $pos_settings      = empty($business_details->pos_settings)
            ? $this->businessUtil->defaultPosSettings()
            : json_decode($business_details->pos_settings, true);

        $results      = [];
        $assigned     = 0;
        $failed       = 0;
        $need_confirm = 0;

        foreach ($invoice_ids as $tx_key) {
            // ── Try as Delivery Note first ───────────────────────────────────────
            $tx = is_numeric($tx_key) ? $by_dn_id->get((int) $tx_key) : $by_dn_inv->get($tx_key);

            if ($tx) {
                // ── Process DN invoice ───────────────────────────────────────────
                if (! $force_reassign && ! empty($tx->delivery_person)) {
                    $current_driver = $tx->delivery_person_user;
                    $results[] = [
                        'id'                  => $tx->id,
                        'invoice_no'          => $tx->invoice_no,
                        'scanned_type'        => 'delivery_note',
                        'type'                => 'already_assigned',
                        'need_confirm'        => true,
                        'current_driver_id'   => $current_driver ? $current_driver->id : null,
                        'current_driver_name' => $current_driver ? trim($current_driver->first_name) : null,
                        'new_driver_id'       => $driver->id,
                        'new_driver_name'     => trim($driver->first_name),
                        'message'             => 'Already assigned. Send force_reassign=true to reassign.',
                    ];
                    $need_confirm++;
                    continue;
                }

                try {
                    DB::beginTransaction();

                    $stock_cut = false;

                    if ($tx->status === 'ordered') {
                        $tx->status          = 'delivered';
                        $tx->delivery_person = $driver_id;
                        $tx->delivery_date   = now();
                        $tx->save();

                        $this->decreaseStockForTransaction($tx, $business_id);

                        $tx->load('sell_lines');
                        $business_data = [
                            'id'                => $business_id,
                            'accounting_method' => $accounting_method,
                            'location_id'       => $tx->location_id,
                            'pos_settings'      => $pos_settings,
                        ];
                        $this->transactionUtil->mapPurchaseSell($business_data, $tx->sell_lines, 'purchase');

                        $stock_cut = true;

                        if (! empty($tx->sales_order_id)) {
                            $this->syncSalesOrderStatus((int) $tx->sales_order_id, $business_id);
                        }
                    } elseif (in_array($tx->status, ['delivered', 'completed'])) {
                        $tx->delivery_person = $driver_id;
                        $tx->delivery_date   = now();
                        $tx->save();
                    }

                    activity()
                        ->causedBy($user)
                        ->performedOn($tx)
                        ->withProperties([
                            'driver_id'   => $driver->id,
                            'driver_name' => trim($driver->first_name),
                            'stock_cut'   => $stock_cut,
                            'dn_status'   => $tx->status,
                        ])
                        ->log('dn_driver_assigned');

                    DB::commit();

                    $results[] = [
                        'id'          => $tx->id,
                        'invoice_no'  => $tx->invoice_no,
                        'scanned_type'=> 'delivery_note',
                        'dn_status'   => $tx->status,
                        'stock_cut'   => $stock_cut,
                        'type'        => 'assigned',
                        'message'     => $stock_cut
                            ? 'Assigned and status updated to delivered. Stock deducted.'
                            : 'Driver reassigned. No stock change.',
                    ];
                    $assigned++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('ScanAssign DN failed for tx ' . $tx->id . ': ' . $e->getMessage());
                    $results[] = [
                        'id'         => $tx->id,
                        'invoice_no' => $tx->invoice_no,
                        'type'       => 'failed',
                        'message'    => 'Update failed: ' . $e->getMessage(),
                    ];
                    $failed++;
                }

                continue;
            }

            // ── Try as Sell invoice ──────────────────────────────────────────────
            $sell = $by_sell_inv->get((string) $tx_key);

            if (! $sell) {
                $results[] = [
                    'id'         => $tx_key,
                    'invoice_no' => null,
                    'type'       => 'not_found',
                    'message'    => 'Invoice not found as delivery note or sell invoice.',
                ];
                $failed++;
                continue;
            }

            // Already assigned check on sell
            if (! $force_reassign && ! empty($sell->delivery_person)) {
                $current_driver = $sell->delivery_person_user;
                $results[] = [
                    'id'                  => $sell->id,
                    'invoice_no'          => $sell->invoice_no,
                    'scanned_type'        => 'sell',
                    'type'                => 'already_assigned',
                    'need_confirm'        => true,
                    'current_driver_id'   => $current_driver ? $current_driver->id : null,
                    'current_driver_name' => $current_driver ? trim($current_driver->first_name) : null,
                    'new_driver_id'       => $driver->id,
                    'new_driver_name'     => trim($driver->first_name),
                    'message'             => 'Already assigned. Send force_reassign=true to reassign.',
                ];
                $need_confirm++;
                continue;
            }

            // ── Process Sell invoice ─────────────────────────────────────────────
            $dn = $sell->delivery_note_id ? $linked_dns->get($sell->delivery_note_id) : null;

            try {
                DB::beginTransaction();

                $stock_cut = false;

                if ($dn) {
                    if ($dn->status === 'ordered') {
                        // DN not yet cut stock → cut stock, mark delivered, update driver on DN
                        $dn->status          = 'delivered';
                        $dn->delivery_person = $driver_id;
                        $dn->delivery_date   = now();
                        $dn->save();

                        $this->decreaseStockForTransaction($dn, $business_id);

                        $dn->load('sell_lines');
                        $business_data = [
                            'id'                => $business_id,
                            'accounting_method' => $accounting_method,
                            'location_id'       => $dn->location_id,
                            'pos_settings'      => $pos_settings,
                        ];
                        $this->transactionUtil->mapPurchaseSell($business_data, $dn->sell_lines, 'purchase');

                        $stock_cut = true;

                        if (! empty($dn->sales_order_id)) {
                            $this->syncSalesOrderStatus((int) $dn->sales_order_id, $business_id);
                        }
                    } else {
                        // DN already cut (delivered/completed) → update driver on DN only
                        $dn->delivery_person = $driver_id;
                        $dn->delivery_date   = now();
                        $dn->save();
                    }
                }

                // Always update driver on sell
                $sell->delivery_person = $driver_id;
                $sell->save();

                // Log on DN if available (consistent with history query), otherwise on sell
                $log_subject = $dn ?? $sell;
                activity()
                    ->causedBy($user)
                    ->performedOn($log_subject)
                    ->withProperties([
                        'driver_id'       => $driver->id,
                        'driver_name'     => trim($driver->first_name),
                        'stock_cut'       => $stock_cut,
                        'dn_status'       => $dn ? $dn->status : null,
                        'scanned_as'      => 'sell',
                        'sell_invoice_no' => $sell->invoice_no,
                    ])
                    ->log('dn_driver_assigned');

                DB::commit();

                $results[] = [
                    'id'            => $sell->id,
                    'invoice_no'    => $sell->invoice_no,
                    'scanned_type'  => 'sell',
                    'dn_id'         => $dn ? $dn->id : null,
                    'dn_invoice_no' => $dn ? $dn->invoice_no : null,
                    'dn_status'     => $dn ? $dn->status : null,
                    'stock_cut'     => $stock_cut,
                    'type'          => 'assigned',
                    'message'       => $stock_cut
                        ? 'Sell assigned. DN updated to delivered. Stock deducted.'
                        : 'Sell assigned. Driver updated. No stock change.',
                ];
                $assigned++;

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('ScanAssign Sell failed for tx ' . $sell->id . ': ' . $e->getMessage());
                $results[] = [
                    'id'         => $sell->id,
                    'invoice_no' => $sell->invoice_no,
                    'type'       => 'failed',
                    'message'    => 'Update failed: ' . $e->getMessage(),
                ];
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Assign process completed.',
            'summary' => [
                'assigned'     => $assigned,
                'failed'       => $failed,
                'need_confirm' => $need_confirm,
            ],
            'data' => $results,
        ]);
    }

    /**
     * Sync the linked Sales Order status based on total delivered qty across all its DNs.
     *   all lines fully delivered → completed
     *   some lines delivered      → partial
     *   nothing delivered         → ordered
     */
    private function syncSalesOrderStatus(int $so_id, int $business_id): void
    {
        $so_lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $so_id)
            ->whereNull('parent_sell_line_id')
            ->select('product_id', 'variation_id', 'quantity as ordered_qty')
            ->get();

        $all_full      = true;
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

            if ($delivered > 0) {
                $any_delivered = true;
            }
            if ($delivered < $so_line->ordered_qty) {
                $all_full = false;
            }
        }

        $new_status = $all_full ? 'completed' : ($any_delivered ? 'partial' : 'ordered');

        DB::table('transactions')
            ->where('id', $so_id)
            ->where('type', 'sales_order')
            ->update(['status' => $new_status, 'updated_at' => now()]);
    }

    /**
     * Decrement stock for all sell lines of a delivery note transaction.
     *
     * Stock rules (mirrors web DeliveryNoteController):
     *   single              → cut parent stock only
     *   combo               → cut children only (via decreaseProductQuantityCombo)
     *   combo_single        → cut children only (via decreaseProductQuantityComboSingle)
     *   reward_exchange     → cut children only; parent stock NOT cut
     *
     * reward_exchange is detected from transaction_sell_lines.children_type because
     * the parent product itself has type='single' in the products table.
     */
    private function decreaseStockForTransaction(Transaction $tx, int $business_id): void
    {
        $all_lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $tx->id)
            ->get();

        $parent_lines       = $all_lines->whereNull('parent_sell_line_id');
        $children_by_parent = $all_lines
            ->whereNotNull('parent_sell_line_id')
            ->groupBy('parent_sell_line_id');

        foreach ($parent_lines as $line) {
            $product = \App\Product::find($line->product_id);
            if (! $product) {
                continue;
            }

            $qty = (float) $line->quantity;

            if (! empty($line->sub_unit_id)) {
                $sub_unit = \App\Unit::find($line->sub_unit_id);
                if ($sub_unit && ! empty($sub_unit->base_unit_multiplier)) {
                    $qty = $qty * (float) $sub_unit->base_unit_multiplier;
                }
            }

            $children = $children_by_parent->get($line->id, collect());

            // Detect reward_exchange by children_type in sell lines (parent product type is 'single')
            $rx_children = $children->filter(fn($c) => ($c->children_type ?? '') === 'reward_exchange');

            if ($rx_children->isNotEmpty()) {
                // reward_exchange: parent stock NOT cut; each child cuts its own stock
                foreach ($rx_children as $child) {
                    $child_product = \App\Product::find($child->product_id);
                    if ($child_product && $child_product->enable_stock) {
                        $this->productUtil->decreaseProductQuantity(
                            $child->product_id,
                            $child->variation_id,
                            $tx->location_id,
                            (float) $child->quantity
                        );
                    }
                }
                continue;
            }

            // combo / combo_single: cut children only
            if ($product->type === 'combo') {
                $combo_data = $children->map(fn($c) => [
                    'product_id'   => $c->product_id,
                    'variation_id' => $c->variation_id,
                    'quantity'     => (float) $c->quantity,
                ])->values()->toArray();

                if (! empty($combo_data)) {
                    $this->productUtil->decreaseProductQuantityCombo($combo_data, $tx->location_id);
                }
                continue;
            }

            if ($product->type === 'combo_single') {
                $combo_single_data = $children->map(fn($c) => [
                    'product_id'   => $c->product_id,
                    'variation_id' => $c->variation_id,
                    'quantity'     => (float) $c->quantity,
                ])->values()->toArray();

                if (! empty($combo_single_data)) {
                    $this->productUtil->decreaseProductQuantityComboSingle($combo_single_data, $tx->location_id);
                }
                continue;
            }

            // single: cut parent stock
            if ($product->enable_stock) {
                $this->productUtil->decreaseProductQuantity(
                    $line->product_id,
                    $line->variation_id,
                    $tx->location_id,
                    $qty
                );
            }
        }
    }
}
