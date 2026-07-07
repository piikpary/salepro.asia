<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Business;
use App\Contact;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerCreditCheckController extends ApiController
{
    /**
     * GET /connector/api/mobile/customer-credit-check?contact_id={contact_id}
     *
     * Check if a customer has overdue unpaid invoices based on the
     * business Credit Term (Days) setting.
     */
    public function check(Request $request)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;

        $contact_id = $request->input('contact_id');
        if (empty($contact_id)) {
            return response()->json(['success' => false, 'message' => 'contact_id is required.'], 422);
        }

        // Load contact
        $contact = Contact::where('id', $contact_id)
            ->where('business_id', $business_id)
            ->first(['id', 'name', 'supplier_business_name']);

        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $customer_name = !empty($contact->supplier_business_name)
            ? $contact->supplier_business_name
            : $contact->name;

        // Read credit_term_days from business common_settings
        $business        = Business::find($business_id);
        $common_settings = is_array($business->common_settings)
            ? $business->common_settings
            : (json_decode($business->common_settings, true) ?? []);
        $raw_credit_term = $common_settings['credit_term_days'] ?? null;

        // If credit term is not configured, no overdue check applies
        if ($raw_credit_term === null || $raw_credit_term === '' || (int) $raw_credit_term <= 0) {
            return response()->json([
                'success'              => true,
                'customer_id'          => (int) $contact->id,
                'customer_name'        => $customer_name,
                'credit_term_days'     => null,
                'has_overdue'          => false,
                'overdue_count'        => 0,
                'total_overdue_amount' => 0,
                'oldest_overdue_days'  => 0,
                'message'              => null,
                'invoices'             => [],
            ]);
        }

        $credit_term_days = (int) $raw_credit_term;

        // Query unpaid/partial sell invoices for this customer
        $transactions = DB::table('transactions as t')
            ->where('t.business_id', $business_id)
            ->where('t.contact_id', $contact_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->whereNull('t.deleted_at')
            ->select([
                't.id',
                't.invoice_no',
                't.transaction_date',
                't.final_total',
                't.payment_status',
                DB::raw('COALESCE((SELECT SUM(IF(tp.is_return=1, -tp.amount, tp.amount)) FROM transaction_payments tp WHERE tp.transaction_id = t.id), 0) as total_paid'),
            ])
            ->orderBy('t.transaction_date', 'asc')
            ->get();

        $today       = Carbon::today();
        $overdue     = [];
        $overdue_count        = 0;
        $total_overdue_amount = 0.0;
        $oldest_overdue_days  = 0;

        foreach ($transactions as $txn) {
            $due_amount = max(0, (float) $txn->final_total - (float) $txn->total_paid);

            if ($due_amount <= 0) {
                continue;
            }

            $txn_date    = Carbon::parse($txn->transaction_date);
            $days_passed = $txn_date->diffInDays($today);

            if ($credit_term_days > 0 && $days_passed <= $credit_term_days) {
                continue; // not yet overdue
            }

            $overdue_days = $credit_term_days > 0
                ? (int) ($days_passed - $credit_term_days)
                : (int) $days_passed;

            $overdue_count++;
            $total_overdue_amount += $due_amount;

            if ($overdue_days > $oldest_overdue_days) {
                $oldest_overdue_days = $overdue_days;
            }

            $overdue[] = [
                'invoice_no'       => $txn->invoice_no,
                'transaction_date' => Carbon::parse($txn->transaction_date)->format('Y-m-d'),
                'due_amount'       => round($due_amount, 2),
                'overdue_days'     => $overdue_days,
                'payment_status'   => $txn->payment_status,
            ];

            if (count($overdue) >= 10) {
                break;
            }
        }

        $has_overdue = $overdue_count > 0;
        $message     = null;

        if ($has_overdue) {
            $message = "This customer has {$overdue_count} overdue invoice"
                . ($overdue_count > 1 ? 's' : '')
                . '. Total overdue amount is $' . number_format($total_overdue_amount, 2) . '.';
        }

        return response()->json([
            'success'              => true,
            'customer_id'          => (int) $contact->id,
            'customer_name'        => $customer_name,
            'credit_term_days'     => $credit_term_days,
            'has_overdue'          => $has_overdue,
            'overdue_count'        => $overdue_count,
            'total_overdue_amount' => round($total_overdue_amount, 2),
            'oldest_overdue_days'  => $oldest_overdue_days,
            'message'              => $message,
            'invoices'             => $overdue,
        ]);
    }
}
