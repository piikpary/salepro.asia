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
     * mode=assign — batch-assign invoices to a driver.
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
     * Returns driver-assignment history for a given date (defaults to today).
     * Optional: driver_id, assigned_by, start_date, end_date.
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

        $query = Activity::where('description', 'driver_assigned')
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
                'invoice_status'   => $tx->sub_status === 'proforma' ? 'proforma' : $tx->status,
                'shipping_status'  => $tx->shipping_status,
                'driver_id'        => $driver ? $driver->id : $props->get('driver_id'),
                'driver_name'      => $driver ? trim($driver->first_name) : $props->get('driver_name'),
                'assigned_by_id'   => $log->causer_id,
                'assigned_by_name' => $causer ? trim($causer->first_name) : null,
                'assigned_at'      => $log->created_at->format('Y-m-d H:i:s'),
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

        $tx = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where(function ($q) use ($invoice_val) {
                $q->where('invoice_no', $invoice_val)
                  ->orWhere('id', is_numeric($invoice_val) ? (int) $invoice_val : 0);
            })
            ->with(['contact', 'delivery_person_user'])
            ->first();

        if (! $tx) {
            return response()->json([
                'success' => false,
                'type'    => 'not_found',
                'message' => 'Invoice not found.',
            ]);
        }

        $invoice_status = $tx->sub_status === 'proforma' ? 'proforma' : $tx->status;
        $driver         = $tx->delivery_person_user;
        $already        = ! empty($tx->delivery_person);

        return response()->json([
            'success'      => true,
            'type'         => $already ? 'already_assigned' : 'ready',
            'need_confirm' => $already,
            'message'      => $already ? 'This invoice is already assigned.' : 'Invoice is ready to assign.',
            'data'         => [
                'id'                  => $tx->id,
                'invoice_no'          => $tx->invoice_no,
                'customer'            => optional($tx->contact)->name,
                'invoice_status'      => $invoice_status,
                'shipping_status'     => $tx->shipping_status,
                'current_driver_id'   => $driver ? $driver->id : null,
                'current_driver_name' => $driver ? trim($driver->first_name) : null,
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

        // Resolve each entry — accept both numeric transaction ID and invoice_no string
        $numeric_ids = array_filter($invoice_ids, fn($v) => is_numeric($v));
        $invoice_nos = array_filter($invoice_ids, fn($v) => ! is_numeric($v));

        $transactions = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where(function ($q) use ($numeric_ids, $invoice_nos) {
                $q->whereIn('id', $numeric_ids)
                  ->orWhereIn('invoice_no', $invoice_nos);
            })
            ->with(['contact', 'delivery_person_user'])
            ->get();

        $by_id  = $transactions->keyBy('id');
        $by_inv = $transactions->keyBy('invoice_no');

        // Load business details once for mapPurchaseSell
        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings     = empty($business_details->pos_settings)
            ? $this->businessUtil->defaultPosSettings()
            : json_decode($business_details->pos_settings, true);

        $accounting_method = DB::table('business')->where('id', $business_id)->value('accounting_method');

        $results      = [];
        $assigned     = 0;
        $failed       = 0;
        $need_confirm = 0;

        foreach ($invoice_ids as $tx_key) {
            $tx = is_numeric($tx_key) ? $by_id->get((int) $tx_key) : $by_inv->get($tx_key);

            if (! $tx) {
                $results[] = [
                    'id'         => $tx_key,
                    'invoice_no' => null,
                    'type'       => 'not_found',
                    'message'    => 'Invoice not found.',
                ];
                $failed++;
                continue;
            }

            // force_reassign: false → skip if invoice already has ANY driver assigned
            if (! $force_reassign && ! empty($tx->delivery_person)) {
                $current_driver = $tx->delivery_person_user;
                $results[] = [
                    'id'                  => $tx->id,
                    'invoice_no'          => $tx->invoice_no,
                    'type'                => 'already_assigned',
                    'need_confirm'        => true,
                    'current_driver_id'   => $current_driver ? $current_driver->id : null,
                    'current_driver_name' => $current_driver ? trim($current_driver->first_name) : null,
                    'new_driver_id'       => $driver->id,
                    'new_driver_name'     => trim($driver->first_name),
                    'message'             => 'Already assigned. Need confirm to reassign.',
                ];
                $need_confirm++;
                continue;
            }

            try {
                DB::beginTransaction();

                $was_proforma = ($tx->status === 'draft' && $tx->sub_status === 'proforma');

                $tx->delivery_person = $driver_id;
                $tx->delivery_date   = now();

                // Promote proforma → final
                if ($was_proforma) {
                    $tx->status     = 'final';
                    $tx->sub_status = null;
                    // Keep payment_status = 'due' since no payment collected yet
                }

                $tx->save();

                // Decrement stock only when promoting proforma → final
                if ($was_proforma) {
                    $this->decreaseStockForTransaction($tx, $business_id);

                    // Map purchase-sell lines for FIFO/LIFO/AVCO accounting
                    $tx->load('sell_lines');
                    $business_data = [
                        'id'                 => $business_id,
                        'accounting_method'  => $accounting_method,
                        'location_id'        => $tx->location_id,
                        'pos_settings'       => $pos_settings,
                    ];
                    $this->transactionUtil->mapPurchaseSell($business_data, $tx->sell_lines, 'purchase');
                }

                // Log the driver assignment for history
                activity()
                    ->causedBy($user)
                    ->performedOn($tx)
                    ->withProperties([
                        'driver_id'   => $driver->id,
                        'driver_name' => trim($driver->first_name),
                    ])
                    ->log('driver_assigned');

                DB::commit();

                $results[] = [
                    'id'         => $tx->id,
                    'invoice_no' => $tx->invoice_no,
                    'type'       => 'assigned',
                    'message'    => 'Assigned successfully.',
                ];
                $assigned++;

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('ScanAssign failed for tx ' . $tx->id . ': ' . $e->getMessage());
                $results[] = [
                    'id'         => $tx->id,
                    'invoice_no' => $tx->invoice_no,
                    'type'       => 'failed',
                    'message'    => 'Update failed for this invoice.',
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
     * Decrement stock for all sell lines of a transaction.
     * Handles single, combo, and combo_single product types.
     */
    private function decreaseStockForTransaction(Transaction $tx, int $business_id): void
    {
        // Load parent lines with product; child lines grouped by parent
        $all_lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $tx->id)
            ->get();

        $parent_lines   = $all_lines->whereNull('parent_sell_line_id');
        $children_by_parent = $all_lines
            ->whereNotNull('parent_sell_line_id')
            ->groupBy('parent_sell_line_id');

        foreach ($parent_lines as $line) {
            $product = \App\Product::find($line->product_id);
            if (! $product) {
                continue;
            }

            $qty = (float) $line->quantity;

            // Apply sub_unit multiplier if set
            if (! empty($line->sub_unit_id)) {
                $sub_unit = \App\Unit::find($line->sub_unit_id);
                if ($sub_unit && ! empty($sub_unit->base_unit_multiplier)) {
                    $qty = $qty * (float) $sub_unit->base_unit_multiplier;
                }
            }

            // Decrement stock for the parent line (single / combo parent)
            if ($product->enable_stock) {
                $this->productUtil->decreaseProductQuantity(
                    $line->product_id,
                    $line->variation_id,
                    $tx->location_id,
                    $qty
                );
            }

            // Decrement stock for combo child lines
            if ($product->type === 'combo') {
                $children  = $children_by_parent->get($line->id, collect());
                $combo_data = $children->map(fn($c) => [
                    'product_id'   => $c->product_id,
                    'variation_id' => $c->variation_id,
                    'quantity'     => (float) $c->quantity,
                ])->values()->toArray();

                if (! empty($combo_data)) {
                    $this->productUtil->decreaseProductQuantityCombo($combo_data, $tx->location_id);
                }
            }

            if ($product->type === 'combo_single') {
                $children        = $children_by_parent->get($line->id, collect());
                $combo_single_data = $children->map(fn($c) => [
                    'product_id'   => $c->product_id,
                    'variation_id' => $c->variation_id,
                    'quantity'     => (float) $c->quantity,
                ])->values()->toArray();

                if (! empty($combo_single_data)) {
                    $this->productUtil->decreaseProductQuantityComboSingle($combo_single_data, $tx->location_id);
                }
            }
        }
    }
}
