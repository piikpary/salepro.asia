<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\Unit;
use App\Media;
use App\Utils\BusinessUtil;
use App\Utils\CashRegisterUtil;
use App\Utils\ContactUtil;
use App\Utils\NotificationUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Connector\Transformers\SellResource;

/**
 * @group Sales management
 * @authenticated
 *
 * APIs for managing sales
 */
class SellController extends ApiController
{
    /**
     * All Utils instance.
     */
    protected $contactUtil;

    protected $productUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $cashRegisterUtil;

    protected $moduleUtil;

    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(
        ContactUtil $contactUtil,
        ProductUtil $productUtil,
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        CashRegisterUtil $cashRegisterUtil,
        NotificationUtil $notificationUtil
    ) {
        $this->contactUtil = $contactUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->cashRegisterUtil = $cashRegisterUtil;
        $this->notificationUtil = $notificationUtil;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];
        parent::__construct();
    }

    /**
     * List sells
     *
     * @queryParam location_id id of the location Example: 1
     * @queryParam contact_id id of the customer
     * @queryParam status Sell status. Available values final, draft, quotation, proforma Example: final
     * @queryParam payment_status Comma separated values of payment statuses. Available values due, partial, paid, overdue Example: due,partial
     * @queryParam start_date format:Y-m-d Example: 2018-06-25
     * @queryParam end_date format:Y-m-d Example: 2018-06-25
     * @queryParam user_id id of the user who created the sale
     * @queryParam service_staff_id id of the service staff assigned with the sale
     * @queryParam shipping_status Shipping Status of the sale ('ordered', 'packed', 'shipped', 'delivered', 'cancelled') Example: ordered
     * @queryParam source Source of the sale
     * @queryParam only_subscriptions Filter only subcription invoices (1, 0)
     * @queryParam send_purchase_details Get purchase details of each sell line (1, 0)
     * @queryParam order_by_date Sort sell list by date ('asc', 'desc') Example: desc
     * @queryParam per_page Total records per page. default: 10, Set -1 for no pagination Example:10
     *
     * @response {
        "data": [
            {
                "id": 6,
                "business_id": 1,
                "location_id": 1,
                "res_table_id": null,
                "res_waiter_id": null,
                "res_order_status": null,
                "type": "sell",
                "sub_type": null,
                "status": "final",
                "is_quotation": 0,
                "payment_status": "paid",
                "adjustment_type": null,
                "contact_id": 4,
                "customer_group_id": null,
                "invoice_no": "AS0001",
                "ref_no": "",
                "source": null,
                "subscription_no": null,
                "subscription_repeat_on": null,
                "transaction_date": "2018-04-10 13:23:21",
                "total_before_tax": "770.0000",
                "tax_id": null,
                "tax_amount": "0.0000",
                "discount_type": "percentage",
                "discount_amount": "0.0000",
                "rp_redeemed": 0,
                "rp_redeemed_amount": "0.0000",
                "shipping_details": null,
                "shipping_address": null,
                "shipping_status": null,
                "delivered_to": null,
                "shipping_charges": "0.0000",
                "additional_notes": null,
                "staff_note": null,
                "round_off_amount": "0.0000",
                "final_total": "770.0000",
                "expense_category_id": null,
                "expense_for": null,
                "commission_agent": null,
                "document": null,
                "is_direct_sale": 0,
                "is_suspend": 0,
                "exchange_rate": "1.000",
                "total_amount_recovered": null,
                "transfer_parent_id": null,
                "return_parent_id": null,
                "opening_stock_product_id": null,
                "created_by": 1,
                "import_batch": null,
                "import_time": null,
                "types_of_service_id": null,
                "packing_charge": null,
                "packing_charge_type": null,
                "service_custom_field_1": null,
                "service_custom_field_2": null,
                "service_custom_field_3": null,
                "service_custom_field_4": null,
                "mfg_parent_production_purchase_id": null,
                "mfg_wasted_units": null,
                "mfg_production_cost": "0.0000",
                "mfg_is_final": 0,
                "is_created_from_api": 0,
                "essentials_duration": "0.00",
                "essentials_duration_unit": null,
                "essentials_amount_per_unit_duration": "0.0000",
                "essentials_allowances": null,
                "essentials_deductions": null,
                "rp_earned": 0,
                "repair_completed_on": null,
                "repair_warranty_id": null,
                "repair_brand_id": null,
                "repair_status_id": null,
                "repair_model_id": null,
                "repair_defects": null,
                "repair_serial_no": null,
                "repair_updates_email": 0,
                "repair_updates_sms": 0,
                "repair_checklist": null,
                "repair_security_pwd": null,
                "repair_security_pattern": null,
                "repair_due_date": null,
                "repair_device_id": null,
                "order_addresses": null,
                "is_recurring": 0,
                "recur_interval": null,
                "recur_interval_type": null,
                "recur_repetitions": null,
                "recur_stopped_on": null,
                "recur_parent_id": null,
                "invoice_token": null,
                "pay_term_number": null,
                "pay_term_type": null,
                "pjt_project_id": null,
                "pjt_title": null,
                "woocommerce_order_id": null,
                "selling_price_group_id": null,
                "created_at": "2018-01-06 07:06:11",
                "updated_at": "2018-01-06 07:06:11",
                "sell_lines": [
                    {
                        "id": 1,
                        "transaction_id": 6,
                        "product_id": 2,
                        "variation_id": 3,
                        "quantity": 10,
                        "mfg_waste_percent": "0.0000",
                        "quantity_returned": "0.0000",
                        "unit_price_before_discount": "70.0000",
                        "unit_price": "70.0000",
                        "line_discount_type": null,
                        "line_discount_amount": "0.0000",
                        "unit_price_inc_tax": "77.0000",
                        "item_tax": "7.0000",
                        "tax_id": 1,
                        "discount_id": null,
                        "lot_no_line_id": null,
                        "sell_line_note": null,
                        "res_service_staff_id": null,
                        "res_line_order_status": null,
                        "woocommerce_line_items_id": null,
                        "parent_sell_line_id": null,
                        "children_type": "",
                        "sub_unit_id": null,
                        "created_at": "2018-01-06 07:06:11",
                        "updated_at": "2018-01-06 07:06:11"
                    }
                ],
                "payment_lines": [
                    {
                        "id": 1,
                        "transaction_id": 6,
                        "business_id": null,
                        "is_return": 0,
                        "amount": "770.0000",
                        "method": "cash",
                        "transaction_no": null,
                        "card_transaction_number": null,
                        "card_number": null,
                        "card_type": "visa",
                        "card_holder_name": null,
                        "card_month": null,
                        "card_year": null,
                        "card_security": null,
                        "cheque_number": null,
                        "bank_account_number": null,
                        "paid_on": "2018-01-09 17:30:35",
                        "created_by": 1,
                        "payment_for": null,
                        "parent_id": null,
                        "note": null,
                        "document": null,
                        "payment_ref_no": null,
                        "account_id": null,
                        "created_at": "2018-01-06 01:36:11",
                        "updated_at": "2018-01-06 01:36:11"
                    }
                ],
                "invoice_url": "http://local.pos.com/invoice/6dfd77eb80f4976b456128e7f1311c9f",
                "payment_link": "http://local.pos.com/pay/6dfd77eb80f4976b456128e7f1311c9f"
            }
        ],
        "links": {
            "first": "http://local.pos.com/connector/api/sell?page=1",
            "last": "http://local.pos.com/connector/api/sell?page=6",
            "prev": null,
            "next": "http://local.pos.com/connector/api/sell?page=2"
        },
        "meta": {
            "current_page": 1,
            "from": 1,
            "path": "http://local.pos.com/connector/api/sell",
            "per_page": 10,
            "to": 10
        }
    }
     */
    public function index()
    {
        //TODO::order by
        $user = Auth::user(); 
        $business_id = $user->business_id;
        $is_admin = $this->businessUtil->is_admin($user, $business_id);
        // return "ok";

        
        if (! $is_admin && (!$user->hasPermissionTo('direct_sell.view','web') && !$user->hasPermissionTo('view_own_sell_only','web')) && !auth()->user()->hasAnyPermission(['sell.view', 'direct_sell.access', 'direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        // $filters = request()->only(['location_id', 'contact_id', 'contact_name', 'invoice_no', 'payment_status', 'start_date', 'end_date', 'user_id', 'service_staff_id', 'only_subscriptions', 'per_page', 'shipping_status', 'order_by_date', 'source', 'status','delivery_person','driver_app']);
        $filters = request()->only(['location_id', 'contact_id', 'contact_name', 'invoice_no', 'payment_status', 'start_date', 'end_date', 'user_id', 'service_staff_id', 'only_subscriptions', 'per_page', 'shipping_status', 'order_by_date', 'source', 'status','delivery_person']);
        $with = ['sell_lines', 'payment_lines', 'contact' , 'Currency'];
        
        // ======================================================
        // (!!!) THIS IS THE MAIN FIX (!!!)
        // Changed from where('type', 'sell') to whereIn()
        // to find BOTH sell and sales_order
        // ======================================================
        $query = Transaction::where('business_id', $business_id)
                        ->whereIn('type', ['sell', 'sales_order'])
                        ->whereNull('transactions.deleted_at');
        // =================== END OF FIX ===================
                        
        $with[] = 'contact.contactMap';
        if (! empty(request()->input('send_purchase_details')) && request()->input('send_purchase_details') == 1) {
            $with[] = 'sell_lines.sell_line_purchase_lines';
            $with[] = 'sell_lines.sell_line_purchase_lines.purchase_line';
            
            
        }
        $query->with('ShippingAddress');
        $query->with($with);
        
        $permitted_locations = $user->permitted_locations($business_id);
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        if (! $user->can('direct_sell.view')) {
            $query->where(function ($q) use ($user) {
                // if ($user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                if($user->hasPermissionTo('view_own_sell_only','web')){
                    $q->where('transactions.created_by', $user->id);
                }

                //if user is commission agent display only assigned sells
                if ($user->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $q->orWhere('transactions.commission_agent', $user->id);
                }
            });
        }

        if (! empty($filters['status'])) {
            // ======================================================
            // (!!!) THIS IS THE SECOND FIX (!!!)
            // Added 'ordered' to the list of statuses
            // so you can filter for sales_order status
            // ======================================================
            if ($filters['status'] == 'final' || $filters['status'] == 'draft' || $filters['status'] == 'ordered') {
                $query->where('transactions.status', $filters['status']);
            } elseif ($filters['status'] == 'quotation') {
                $query->where('transactions.sub_status', 'quotation');
            } elseif ($filters['status'] == 'proforma') {
                $query->where('transactions.sub_status', 'proforma');
            }
            // =================== END OF FIX ===================
        }

        if (! empty($filters['location_id'])) {
            $query->where('transactions.location_id', $filters['location_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('transactions.contact_id', $filters['contact_id']);
        }

        if (! empty($filters['contact_name'])) {
            $query->whereHas('contact', function($query) use ($filters){
                $query->where('name', 'LIKE', '%'.$filters['contact_name'].'%');
            });
        }
        

        if (! empty($filters['invoice_no'])) {
            // This filter will now work for sales_order
            // because the query is fetching them correctly.
            $query->where('transactions.invoice_no', $filters['invoice_no']);
        }

        $payment_status = [];
        if (! empty($filters['payment_status'])) {
            $payment_status = explode(',', $filters['payment_status']);
        }

        if (! $is_admin) {
            $payment_status_arr = [];
            if (auth()->user()->can('view_paid_sells_only')) {
                $payment_status_arr[] = 'paid';
            }

            if (auth()->user()->can('view_due_sells_only')) {
                $payment_status_arr[] = 'due';
            }

            if (auth()->user()->can('view_partial_sells_only')) {
                $payment_status_arr[] = 'partial';
            }

            if (empty($payment_status_arr)) {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $query->OverDue();
                }
            } else {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $query->where(function ($q) use ($payment_status_arr) {
                        $q->whereIn('transactions.payment_status', $payment_status_arr)
                        ->orWhere(function ($qr) {
                            $qr->OverDue();
                        });
                    });
                } else {
                    $query->whereIn('transactions.payment_status', $payment_status_arr);
                }
            }
        }

        if (! empty($payment_status)) {
            $query->where(function ($q) use ($payment_status) {
                $is_overdue = false;
                if (in_array('overdue', $payment_status)) {
                    $is_overdue = true;
                    $key = array_search('overdue', $payment_status);
                    unset($payment_status[$key]);
                }

                if (! empty($payment_status)) {
                    $q->whereIn('transactions.payment_status', $payment_status);
                }

                if ($is_overdue) {
                    $q->orWhere(function ($qr) {
                        $qr->whereIn('transactions.payment_status', ['due', 'partial'])
                            ->whereNotNull('transactions.pay_term_number')
                            ->whereNotNull('transactions.pay_term_type')
                            ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
                    });
                }
            });
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('transactions.transaction_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('transactions.transaction_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['order_by_date'])) {
            $order_by_date = in_array($filters['order_by_date'], ['asc', 'desc']) ? $filters['order_by_date'] : 'desc';
            $query->orderBy('transactions.transaction_date', $order_by_date);
        }

        if (! empty($filters['user_id'])) {
            $query->where('transactions.created_by', $filters['user_id']);
        }

        if (! empty($filters['service_staff_id'])) {
            $query->where('transactions.res_waiter_id', $filters['service_staff_id']);
        }

        if (! empty($filters['delivery_person'])) {
            $query->where('transactions.delivery_person', $filters['delivery_person']);
        }

        //Edited by Sokha
        if (!empty($filters['shipping_status'])) {
            // Split the shipping_status into an array if it's a comma-separated string
            $shipping_status = array_map('trim', explode(',', $filters['shipping_status'])); // Trim spaces from each status value
        
            // Filter using whereIn to allow for multiple shipping statuses
            $query->whereIn('transactions.shipping_status', $shipping_status);
        }
        //End of Edited by Sokha
        

        if (! empty($filters['only_subscriptions']) && $filters['only_subscriptions'] == 1) {
            $query->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                    ->orWhere('transactions.is_recurring', 1);
            });
        }

        if (! empty($filters['source'])) {
            //only exception for woocommerce
            if ($filters['source'] == 'woocommerce') {
                $query->whereNotNull('transactions.woocommerce_order_id');
            } else {
                $query->where('transactions.source', $filters['source']);
            }
        }

        $perPage = ! empty($filters['per_page']) ? $filters['per_page'] : $this->perPage;
        if ($perPage == -1) {
            $sells = $query->get();
        } else {
            // if($filters['driver_app'] == 1){

            // $sells = $query->get()->all();

            // }else{

            //     $sells = $query->paginate($perPage);
            //     $sells->appends(request()->query());
            // }
            $sells = $query->paginate($perPage);
$sells->appends(request()->query());
        }
        
        return SellResource::collection($sells);

    }

    /**
     * Get the specified sell
     *
     * @urlParam sell required comma separated ids of the sells Example: 55
     * @queryParam send_purchase_details Get purchase details of each sell line (1, 0)
     *
     * @response {
        "data": [
            {
                "id": 55,
                "business_id": 1,
                "location_id": 1,
                "res_table_id": 5,
                "res_waiter_id": null,
                "res_order_status": null,
                "type": "sell",
                "sub_type": null,
                "status": "final",
                "is_quotation": 0,
                "payment_status": "paid",
                "adjustment_type": null,
                "contact_id": 1,
                "customer_group_id": null,
                "invoice_no": "AS0007",
                "ref_no": "",
                "source": null,
                "subscription_no": null,
                "subscription_repeat_on": null,
                "transaction_date": "2020-06-04 23:29:36",
                "total_before_tax": "437.5000",
                "tax_id": 1,
                "tax_amount": "39.3750",
                "discount_type": "percentage",
                "discount_amount": "10.0000",
                "rp_redeemed": 0,
                "rp_redeemed_amount": "0.0000",
                "shipping_details": "Express Delivery",
                "shipping_address": null,
                "shipping_status": "ordered",
                "delivered_to": "Mr Robin",
                "shipping_charges": "10.0000",
                "additional_notes": null,
                "staff_note": null,
                "round_off_amount": "0.0000",
                "final_total": "453.1300",
                "expense_category_id": null,
                "expense_for": null,
                "commission_agent": null,
                "document": null,
                "is_direct_sale": 0,
                "is_suspend": 0,
                "exchange_rate": "1.000",
                "total_amount_recovered": null,
                "transfer_parent_id": null,
                "return_parent_id": null,
                "opening_stock_product_id": null,
                "created_by": 9,
                "import_batch": null,
                "import_time": null,
                "types_of_service_id": 1,
                "packing_charge": "10.0000",
                "packing_charge_type": "fixed",
                "service_custom_field_1": null,
                "service_custom_field_2": null,
                "service_custom_field_3": null,
                "service_custom_field_4": null,
                "mfg_parent_production_purchase_id": null,
                "mfg_wasted_units": null,
                "mfg_production_cost": "0.0000",
                "mfg_is_final": 0,
                "is_created_from_api": 0,
                "essentials_duration": "0.00",
                "essentials_duration_unit": null,
                "essentials_amount_per_unit_duration": "0.0000",
                "essentials_allowances": null,
                "essentials_deductions": null,
                "rp_earned": 0,
                "repair_completed_on": null,
                "repair_warranty_id": null,
                "repair_brand_id": null,
                "repair_status_id": null,
                "repair_model_id": null,
                "repair_defects": null,
                "repair_serial_no": null,
                "repair_updates_email": 0,
                "repair_updates_sms": 0,
                "repair_checklist": null,
                "repair_security_pwd": null,
                "repair_security_pattern": null,
                "repair_due_date": null,
                "repair_device_id": null,
                "order_addresses": null,
                "is_recurring": 0,
                "recur_interval": null,
                "recur_interval_type": "days",
                "recur_repetitions": 0,
                "recur_stopped_on": null,
                "recur_parent_id": null,
                "invoice_token": null,
                "pay_term_number": null,
                "pay_term_type": null,
                "pjt_project_id": null,
                "pjt_title": null,
                "woocommerce_order_id": null,
                "selling_price_group_id": 0,
                "created_at": "2020-06-04 23:29:36",
                "updated_at": "2020-06-04 23:29:36",
                "sell_lines": [
                    {
                        "id": 38,
                        "transaction_id": 55,
                        "product_id": 17,
                        "variation_id": 58,
                        "quantity": 1,
                        "mfg_waste_percent": "0.0000",
                        "quantity_returned": "0.0000",
                        "unit_price_before_discount": "437.5000",
                        "unit_price": "437.5000",
                        "line_discount_type": "fixed",
                        "line_discount_amount": "0.0000",
                        "unit_price_inc_tax": "437.5000",
                        "item_tax": "0.0000",
                        "tax_id": null,
                        "discount_id": null,
                        "lot_no_line_id": null,
                        "sell_line_note": "",
                        "res_service_staff_id": null,
                        "res_line_order_status": null,
                        "woocommerce_line_items_id": null,
                        "parent_sell_line_id": null,
                        "children_type": "",
                        "sub_unit_id": null,
                        "created_at": "2020-06-04 23:29:36",
                        "updated_at": "2020-06-04 23:29:36"
                    }
                ],
                "payment_lines": [
                    {
                        "id": 37,
                        "transaction_id": 55,
                        "business_id": 1,
                        "is_return": 0,
                        "amount": "453.1300",
                        "method": "cash",
                        "transaction_no": null,
                        "card_transaction_number": null,
                        "card_number": null,
                        "card_type": "credit",
                        "card_holder_name": null,
                        "card_month": null,
                        "card_year": null,
                        "card_security": null,
                        "cheque_number": null,
                        "bank_account_number": null,
                        "paid_on": "2020-06-04 23:29:36",
                        "created_by": 9,
                        "payment_for": 1,
                        "parent_id": null,
                        "note": null,
                        "document": null,
                        "payment_ref_no": "SP2020/0002",
                        "account_id": null,
                        "created_at": "2020-06-04 23:29:36",
                        "updated_at": "2020-06-04 23:29:36"
                    }
                ],

                "invoice_url": "http://local.pos.com/invoice/6dfd77eb80f4976b456128e7f1311c9f",
                "payment_link": "http://local.pos.com/pay/6dfd77eb80f4976b456128e7f1311c9f"
            }
        ]
    }
     */
    public function show($sell_ids)
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        $sell_ids = explode(',', $sell_ids);

        $query = Transaction::where('business_id', $business_id)
                        ->whereIn('id', $sell_ids);

        $with = ['sell_lines', 'payment_lines'];

        if (! empty(request()->input('send_purchase_details')) && request()->input('send_purchase_details') == 1) {
            $with[] = 'sell_lines.sell_line_purchase_lines';
            $with[] = 'sell_lines.sell_line_purchase_lines.purchase_line';
        }

        $sells = $query->with($with)
                    ->get();

        return SellResource::collection($sells);
    }

    /**
     * Create sell
     *
     * @bodyParam sells.*.location_id int required id of the business location Example: 1
     * @bodyParam sells.*.contact_id int required id of the customer
     * @bodyParam sells.*.transaction_date string transaction date format:Y-m-d H:i:s, Example: 2020-07-22 15:48:29
     * @bodyParam sells.*.invoice_no string Invoice number
     * @bodyParam sells.*.source string Source of the invoice Example: api, phone, woocommerce
     * @bodyParam sells.*.status string sale status (final, draft) Example: final
     * @bodyParam sells.*.sub_status string sale sub status ("quotation" for quotation and "proforma" for proforma invoice) Example:null
     * @bodyParam sells.*.is_quotation boolean Is sell quotation (0, 1), If 1 status should be draft Example: 1
     * @bodyParam sells.*.tax_rate_id int id of the tax rate applicable to the sale
     * @bodyParam sells.*.discount_amount float discount amount applicable to the sale Example:10.00
     * @bodyParam sells.*.discount_type string  type of the discount amount (fixed, percentage) Example: fixed
     * @bodyParam sells.*.sale_note string
     * @bodyParam sells.*.staff_note string
     * @bodyParam sells.*.commission_agent int commission agent id
     * @bodyParam sells.*.shipping_details string shipping details Example: Express Delivery
     * @bodyParam sells.*.shipping_address string shipping address
     * @bodyParam sells.*.shipping_status string ('ordered', 'packed', 'shipped', 'delivered', 'cancelled') Example: ordered
     * @bodyParam sells.*.delivered_to string Name of the person recieved the consignment Example:'Mr robin'
     * @bodyParam sells.*.shipping_charges float shipping amount Example:10.0000
     * @bodyParam sells.*.packing_charge float packing charge Example:10
     * @bodyParam sells.*.exchange_rate float exchange rate for the currency used Example: 1
     * @bodyParam sells.*.selling_price_group_id int id of the selling price group
     * @bodyParam sells.*.pay_term_number int pay term value Example:3
     * @bodyParam sells.*.pay_term_type string type of the pay term value ('days', 'months') Example: months
     * @bodyParam sells.*.is_suspend boolean Is suspended sale (0, 1) Example: 0
     * @bodyParam sells.*.is_recurring int whether the invoice is recurring (0, 1) Example: 0
     * @bodyParam sells.*.recur_interval int value of the interval invoice will be regenerated
     * @bodyParam sells.*.recur_interval_type string type of the recur interval ('days', 'months', 'years') Example: months
     * @bodyParam sells.*.subscription_repeat_on int day of the month on which invoice will be generated if recur interval type is months (1-30) Example: 15
     * @bodyParam sells.*.subscription_no string subscription number
     * @bodyParam sells.*.recur_repetitions int total number of invoices to be generated
     * @bodyParam sells.*.rp_redeemed int reward points redeemed
     * @bodyParam sells.*.rp_redeemed_amount float reward point redeemed amount after conversion Example: 13.5000
     * @bodyParam sells.*.types_of_service_id int types of service id
     * @bodyParam sells.*.service_custom_field_1 string types of service custom field 1
     * @bodyParam sells.*.service_custom_field_2 string types of service custom field 2
     * @bodyParam sells.*.service_custom_field_3 string types of service custom field 3
     * @bodyParam sells.*.service_custom_field_4 string types of service custom field 4
     * @bodyParam sells.*.service_custom_field_5 string types of service custom field 5
     * @bodyParam sells.*.service_custom_field_6 string types of service custom field 6
     * @bodyParam sells.*.round_off_amount float round off amount on total payable
     * @bodyParam sells.*.table_id int id of the table
     * @bodyParam sells.*.service_staff_id int id of the service staff assigned to the sale
     * @bodyParam sells.*.change_return float Excess paid amount Example:0.0000
     * @bodyParam sells.*.products array required array of the products for the sale
     * @bodyParam sells.*.payments array payment lines for the sale
     *
     *
     * @bodyParam sells.*.products.*.product_id int required product id Example:17
     * @bodyParam sells.*.products.*.variation_id int required variation id Example:58
     * @bodyParam sells.*.products.*.quantity float required quantity Example: 1
     * @bodyParam sells.*.products.*.unit_price float unit selling price Example:437.5000
     * @bodyParam sells.*.products.*.tax_rate_id int tax rate id applicable on the product Example:null
     * @bodyParam sells.*.products.*.discount_amount float discount amount applicable on the product Example:0.0000
     * @bodyParam sells.*.products.*.discount_type string type of discount amount ('fixed', 'percentage') Example: percentage
     * @bodyParam sells.*.products.*.sub_unit_id int sub unit id
     * @bodyParam sells.*.products.*.note string note for the product
     *
     *
     * @bodyParam sells.*.payments.*.amount float required amount of the payment Example: 453.1300
     * @bodyParam sells.*.payments.*.method string payment methods ('cash', 'card', 'cheque', 'bank_transfer', 'other', 'custom_pay_1', 'custom_pay_2', 'custom_pay_3') Example: cash
     * @bodyParam sells.*.payments.*.account_id int account id
     * @bodyParam sells.*.payments.*.card_number string
     * @bodyParam sells.*.payments.*.card_holder_name string
     * @bodyParam sells.*.payments.*.card_transaction_number string
     * @bodyParam sells.*.payments.*.card_type string
     * @bodyParam sells.*.payments.*.card_month string
     * @bodyParam sells.*.payments.*.card_year string
     * @bodyParam sells.*.payments.*.card_security string
     * @bodyParam sells.*.payments.*.transaction_no_1 string
     * @bodyParam sells.*.payments.*.transaction_no_2 string
     * @bodyParam sells.*.payments.*.transaction_no_3 string
     * @bodyParam sells.*.payments.*.bank_account_number string
     * @bodyParam sells.*.payments.*.note string payment note
     * @bodyParam sells.*.payments.*.cheque_number string
     *
     * @response {
        "data": [
            {
                "id": 6,
                "business_id": 1,
                "location_id": 1,
                "res_table_id": null,
                "res_waiter_id": null,
                "res_order_status": null,
                "type": "sell",
                "sub_type": null,
                "status": "final",
                "is_quotation": 0,
                "payment_status": "paid",
                "adjustment_type": null,
                "contact_id": 4,
                "customer_group_id": null,
                "invoice_no": "AS0001",
                "ref_no": "",
                "source": null,
                "subscription_no": null,
                "subscription_repeat_on": null,
                "transaction_date": "2018-04-10 13:23:21",
                "total_before_tax": "770.0000",
                "tax_id": null,
                "tax_amount": "0.0000",
                "discount_type": "percentage",
                "discount_amount": "0.0000",
                "rp_redeemed": 0,
                "rp_redeemed_amount": "0.0000",
                "shipping_details": null,
                "shipping_address": null,
                "shipping_status": null,
                "delivered_to": null,
                "shipping_charges": "0.0000",
                "additional_notes": null,
                "staff_note": null,
                "round_off_amount": "0.0000",
                "final_total": "770.0000",
                "expense_category_id": null,
                "expense_for": null,
                "commission_agent": null,
                "document": null,
                "is_direct_sale": 0,
                "is_suspend": 0,
                "exchange_rate": "1.000",
                "total_amount_recovered": null,
                "transfer_parent_id": null,
                "return_parent_id": null,
                "opening_stock_product_id": null,
                "created_by": 1,
                "import_batch": null,
                "import_time": null,
                "types_of_service_id": null,
                "packing_charge": null,
                "packing_charge_type": null,
                "service_custom_field_1": null,
                "service_custom_field_2": null,
                "service_custom_field_3": null,
                "service_custom_field_4": null,
                "mfg_parent_production_purchase_id": null,
                "mfg_wasted_units": null,
                "mfg_production_cost": "0.0000",
                "mfg_is_final": 0,
                "is_created_from_api": 0,
                "essentials_duration": "0.00",
                "essentials_duration_unit": null,
                "essentials_amount_per_unit_duration": "0.0000",
                "essentials_allowances": null,
                "essentials_deductions": null,
                "rp_earned": 0,
                "repair_completed_on": null,
                "repair_warranty_id": null,
                "repair_brand_id": null,
                "repair_status_id": null,
                "repair_model_id": null,
                "repair_defects": null,
                "repair_serial_no": null,
                "repair_updates_email": 0,
                "repair_updates_sms": 0,
                "repair_checklist": null,
                "repair_security_pwd": null,
                "repair_security_pattern": null,
                "repair_due_date": null,
                "repair_device_id": null,
                "order_addresses": null,
                "is_recurring": 0,
                "recur_interval": null,
                "recur_interval_type": null,
                "recur_repetitions": null,
                "recur_stopped_on": null,
                "recur_parent_id": null,
                "invoice_token": null,
                "pay_term_number": null,
                "pay_term_type": null,
                "pjt_project_id": null,
                "pjt_title": null,
                "woocommerce_order_id": null,
                "selling_price_group_id": null,
                "created_at": "2018-01-06 07:06:11",
                "updated_at": "2018-01-06 07:06:11",
                "invoice_url": "http://local.pos.com/invoice/6dfd77eb80f4976b456128e7f1311c9f",
                "payment_link": "http://local.pos.com/pay/6dfd77eb80f4976b456128e7f1311c9f",
                "sell_lines": [
                    {
                        "id": 1,
                        "transaction_id": 6,
                        "product_id": 2,
                        "variation_id": 3,
                        "quantity": 10,
                        "mfg_waste_percent": "0.0000",
                        "quantity_returned": "0.0000",
                        "unit_price_before_discount": "70.0000",
                        "unit_price": "70.0000",
                        "line_discount_type": null,
                        "line_discount_amount": "0.0000",
                        "unit_price_inc_tax": "77.0000",
                        "item_tax": "7.0000",
                        "tax_id": 1,
                        "discount_id": null,
                        "lot_no_line_id": null,
                        "sell_line_note": null,
                        "res_service_staff_id": null,
                        "res_line_order_status": null,
                        "woocommerce_line_items_id": null,
                        "parent_sell_line_id": null,
                        "children_type": "",
                        "sub_unit_id": null,
                        "created_at": "2018-01-06 07:06:11",
                        "updated_at": "2018-01-06 07:06:11"
                    }
                ],
                "payment_lines": [
                    {
                        "id": 1,
                        "transaction_id": 6,
                        "business_id": null,
                        "is_return": 0,
                        "amount": "770.0000",
                        "method": "cash",
                        "transaction_no": null,
                        "card_transaction_number": null,
                        "card_number": null,
                        "card_type": "visa",
                        "card_holder_name": null,
                        "card_month": null,
                        "card_year": null,
                        "card_security": null,
                        "cheque_number": null,
                        "bank_account_number": null,
                        "paid_on": "2018-01-09 17:30:35",
                        "created_by": 1,
                        "payment_for": null,
                        "parent_id": null,
                        "note": null,
                        "document": null,
                        "payment_ref_no": null,
                        "account_id": null,
                        "created_at": "2018-01-06 01:36:11",
                        "updated_at": "2018-01-06 01:36:11"
                    }
                ]
            }
        ]
    }
     */
    public function store(Request $request)
    {

        

        //TODO::Check customer credit limit
        try {
            $sells[] = $request->sells;
            $user = Auth::user();

        $statusCode = 200;
            $business_id = $user->business_id;
            $business = Business::find($business_id);
            $commsn_agnt_setting = $business->sales_cmsn_agnt;
            $output = [];

            if (empty($sells) || ! is_array($sells)) {
                throw new \Exception('Invalid form data');
            }

            foreach ($sells as $sell_data) {
                // return $sell_data;
                try {
                    // return $sell_data;
                    DB::beginTransaction();
                    $sell_data['business_id'] = $user->business_id;
                    $input = $this->__formatSellData($sell_data);

                    // __formatSellData() does not carry 'type', inject it here so
                    // createSellTransaction() saves the correct type ('sales_order', 'sell', etc.)
                    $input['type'] = $sell_data['type'] ?? 'sell';

                    //TODO: temporarily used false to bypass the check, bcz of session issue in can_access_this_location function
                    //Check if location allowed
                    if (false && ! $user->can_access_this_location($input['location_id'])) {
                        throw new \Exception('User not allowed to access location with id '.$input['location_id']);
                    }

                    if (empty($input['products'])) {
                        throw new \Exception('No products added');
                    }

                    $discount = ['discount_type' => $input['discount_type'],
                        'discount_amount' => $input['discount_amount'],
                    ];
                    $invoice_total = $this->productUtil->calculateInvoiceTotal($input['products'], $input['tax_rate_id'], $discount, false);

                    if ($commsn_agnt_setting == 'logged_in_user') {
                        $input['commission_agent'] = $user->id;
                    }

                    $transaction = $this->transactionUtil->createSellTransaction($user->business_id, $input, $invoice_total, $user->id, false);

                    $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $input['location_id'], false, null, [], false);
                    //Add change return
                    $change_return = $this->dummyPaymentLine;
                    $change_return['amount'] = $input['change_return'];
                    $change_return['is_return'] = 1;
                    $input['payment'][] = $change_return;

                    if (! empty($input['payment']) && $transaction->is_suspend == 0) {
                        $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment'], $business_id, $user->id, false);
                    }

                    
                    // ======================================================
                    // (!!!) THIS IS THE FIX (!!!)
                    //
                    // We only decrease stock and update payment status IF
                    // Type is 'sell' AND Status is 'final'.
                    // This will skip stock deduction for 'sales_order'
                    // ======================================================
                    if (isset($input['type']) && $input['type'] == 'sell' && $input['status'] == 'final') {
                    
                        //Check for final and do some processing.
                        //update product stock
                        foreach ($input['products'] as $product) {
                            $decrease_qty = $product['quantity'];
                            if (! empty($product['base_unit_multiplier'])) {
                                $decrease_qty = $decrease_qty * $product['base_unit_multiplier'];
                            }

                            if ($product['enable_stock']) {
                                $this->productUtil->decreaseProductQuantity(
                                    $product['product_id'],
                                    $product['variation_id'],
                                    $input['location_id'],
                                    $decrease_qty
                                );
                            }

                            if ($product['product_type'] == 'combo_single') {

                                //Decrease quantity of combo as well.
                                $this->productUtil
                                    ->decreaseProductQuantityComboSingle(
                                        $product['combo_single'],
                                        $input['location_id']
                                    );
                            }

                            if ($product['product_type'] == 'combo') {
                                //Decrease quantity of combo as well.
                                $this->productUtil
                                    ->decreaseProductQuantityCombo(
                                        $product['combo'],
                                        $input['location_id']
                                    );
                            }
                        }

                        //Update payment status
                        $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

                        if ($business->enable_rp == 1) {
                            $redeemed = ! empty($input['rp_redeemed']) ? $input['rp_redeemed'] : 0;
                            $this->transactionUtil->updateCustomerRewardPoints($transaction->contact_id, $transaction->rp_earned, 0, $redeemed);
                        }

                        //Allocate the quantity from purchase and add mapping of
                        //purchase & sell lines in
                        //transaction_sell_lines_purchase_lines table
                        $business_details = $this->businessUtil->getDetails($business_id);
                        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

                        $business_info = ['id' => $business_id,
                            'accounting_method' => $business->accounting_method,
                            'location_id' => $input['location_id'],
                            'pos_settings' => $pos_settings,
                        ];
                        $this->transactionUtil->mapPurchaseSell($business_info, $transaction->sell_lines, 'purchase');

                        //Auto send notification
                        $this->notificationUtil->autoSendNotification($business_id, 'new_sale', $transaction, $transaction->contact);

                        $client = $this->getClient();

                        $this->transactionUtil->activityLog($transaction, 'added', null, ['from_api' => $client]);
                    
                    } // =================== END OF IF BLOCK ===================


                    $transaction->invoice_url = $this->transactionUtil->getInvoiceUrl($transaction->id, $business_id);
                    $transaction->payment_link = $this->transactionUtil->getInvoicePaymentLink($transaction->id, $business_id);

                    DB::commit();
                    $transaction->load([
                        'sell_lines',
                        'sell_lines.product',
                        'sell_lines.variations',
                        'contact'
                    ]);
                    $output[] = $transaction;

                    // Dispatch to Redis queue — mobile gets instant response,
                    // invoice rendering + Telegram send happen in background worker.
                    if (($input['type'] ?? '') === 'sales_order') {
                        \App\Jobs\SendInvoiceTelegramJob::dispatch(
                            $transaction->id,
                            $business_id
                        );
                    }

                } catch (ModelNotFoundException $e) {
                    DB::rollback();

                    \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                    $output[] = $this->modelNotFoundExceptionResult($e);

                    $statusCode = 404;


                } catch (\Exception $e) {
                    DB::rollback();

                    \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                    $output[] = $this->otherExceptions($e);
                    $statusCode = 404;
                    
                }
            }
        } catch (\Exception $e) {
            DB::rollback();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output[] = $this->otherExceptions($e);
            $statusCode = 404;
        }

        
        return response()->json($output, $statusCode);

    }

    /**
     * Update sell
     *
     * @urlParam sell required id of sell to update Example: 6
     * @bodyParam contact_id int id of the customer
     * @bodyParam transaction_date string transaction date format:Y-m-d H:i:s, Example: 2020-5-7 15:20:22
     * @bodyParam status string sale status (final, draft) Example:final
     * @bodyParam sub_status string sale sub status ("quotation" for quotation and "proforma" for proforma invoice) Example:null
     * @bodyParam is_quotation boolean Is sell quotation (0, 1), If 1 status should be draft Example: 1
     * @bodyParam tax_rate_id int id of the tax rate applicable to the sale
     * @bodyParam discount_amount float discount amount applicable to the sale Example: 10.0000
     * @bodyParam discount_type string type of the discount amount (fixed, percentage) Example: fixed
     * @bodyParam sale_note string
     * @bodyParam source string Source of the invoice
     * @bodyParam staff_note string
     * @bodyParam is_suspend boolean Is suspended sale (0, 1) Example: 0
     * @bodyParam commission_agent int commission agent id
     * @bodyParam shipping_details string shipping details Example: Express Delivery
     * @bodyParam shipping_address string shipping address
     * @bodyParam shipping_status string ('ordered', 'packed', 'shipped', 'delivered', 'cancelled') Example:ordered
     * @bodyParam delivered_to string Name of the person recieved the consignment Example: Mr Robin
     * @bodyParam shipping_charges float shipping amount Example: 10.0000
     * @bodyParam packing_charge float packing charge Example: 10.0000
     * @bodyParam exchange_rate float exchange rate for the currency used Example:1
     * @bodyParam selling_price_group_id int id of the selling price group
     * @bodyParam pay_term_number int pay term value
     * @bodyParam pay_term_type string type of the pay term value ('days', 'months') Example: months
     * @bodyParam is_recurring int whether the invoice is recurring (0, 1) Example:0
     * @bodyParam recur_interval int value of the interval invoice will be regenerated
     * @bodyParam recur_interval_type string type of the recur interval ('days', 'months', 'years') Example:days
     * @bodyParam subscription_repeat_on int day of the month on which invoice will be generated if recur interval type is months (1-30) Example:7
     * @bodyParam subscription_no string subscription number
     * @bodyParam recur_repetitions int total number of invoices to be generated
     * @bodyParam rp_redeemed int reward points redeemed
     * @bodyParam rp_redeemed_amount float reward point redeemed amount after conversion Example: 13.5000
     * @bodyParam types_of_service_id int types of service id
     * @bodyParam service_custom_field_1 string types of service custom field 1
     * @bodyParam service_custom_field_2 string types of service custom field 2
     * @bodyParam service_custom_field_3 string types of service custom field 3
     * @bodyParam service_custom_field_4 string types of service custom field 4
     * @bodyParam service_custom_field_5 string types of service custom field 5
     * @bodyParam service_custom_field_6 string types of service custom field 6
     * @bodyParam round_off_amount float round off amount on total payable
     * @bodyParam table_id int id of the table
     * @bodyParam service_staff_id int id of the service staff assigned to the sale
     * @bodyParam change_return float Excess paid amount Example:0.0000
     * @bodyParam change_return_id int id of the change return payment if exists
     * @bodyParam products array required array of the products for the sale
     * @bodyParam payments array payment lines for the sale
     *
     *
     * @bodyParam products.*.sell_line_id int sell line id for existing item only
     * @bodyParam products.*.product_id int product id Example: 17
     * @bodyParam products.*.variation_id int variation id Example: 58
     * @bodyParam products.*.quantity float quantity Example: 1
     * @bodyParam products.*.unit_price float unit selling price Example: 437.5000
     * @bodyParam products.*.tax_rate_id int tax rate id applicable on the product
     * @bodyParam products.*.discount_amount float discount amount applicable on the product  Example:0.0000
     * @bodyParam products.*.discount_type string type of discount amount ('fixed', 'percentage') Example: percentage
     * @bodyParam products.*.sub_unit_id int sub unit id
     * @bodyParam products.*.note string note for the product
     *
     *
     * @bodyParam payments.*.payment_id int payment id for existing payment line
     * @bodyParam payments.*.amount float amount of the payment Example:453.1300
     * @bodyParam payments.*.method string payment methods ('cash', 'card', 'cheque', 'bank_transfer', 'other', 'custom_pay_1', 'custom_pay_2', 'custom_pay_3') Example:cash
     * @bodyParam payments.*.account_id int account id
     * @bodyParam payments.*.card_number string
     * @bodyParam payments.*.card_holder_name string
     * @bodyParam payments.*.card_transaction_number string
     * @bodyParam payments.*.card_type string
     * @bodyParam payments.*.card_month string
     * @bodyParam payments.*.card_year string
     * @bodyParam payments.*.card_security string
     * @bodyParam payments.*.transaction_no_1 string
     * @bodyParam payments.*.transaction_no_2 string
     * @bodyParam payments.*.transaction_no_3 string
     * @bodyParam payments.*.note string payment note
     * @bodyParam payments.*.cheque_number string
     * @bodyParam payments.*.bank_account_number string
     *
     * @response {
        "id": 91,
        "business_id": 1,
        "location_id": 1,
        "res_table_id": null,
        "res_waiter_id": null,
        "res_order_status": null,
        "type": "sell",
        "sub_type": null,
        "status": "final",
        "is_quotation": 0,
        "payment_status": "paid",
        "adjustment_type": null,
        "contact_id": 1,
        "customer_group_id": 1,
        "invoice_no": "AS0020",
        "ref_no": "",
        "source": null,
        "subscription_no": null,
        "subscription_repeat_on": null,
        "transaction_date": "25-09-2020 15:22",
        "total_before_tax": 962.5,
        "tax_id": null,
        "tax_amount": 0,
        "discount_type": "fixed",
        "discount_amount": "19.5000",
        "rp_redeemed": 0,
        "rp_redeemed_amount": "0.0000",
        "shipping_details": null,
        "shipping_address": null,
        "shipping_status": null,
        "delivered_to": null,
        "shipping_charges": "0.0000",
        "additional_notes": null,
        "staff_note": null,
        "round_off_amount": "0.0000",
        "final_total": 943,
        "expense_category_id": null,
        "expense_for": null,
        "commission_agent": null,
        "document": null,
        "is_direct_sale": 0,
        "is_suspend": 0,
        "exchange_rate": "1.000",
        "total_amount_recovered": null,
        "transfer_parent_id": null,
        "return_parent_id": null,
        "opening_stock_product_id": null,
        "created_by": 9,
        "import_batch": null,
        "import_time": null,
        "types_of_service_id": null,
        "packing_charge": "0.0000",
        "packing_charge_type": null,
        "service_custom_field_1": null,
        "service_custom_field_2": null,
        "service_custom_field_3": null,
        "service_custom_field_4": null,
        "mfg_parent_production_purchase_id": null,
        "mfg_wasted_units": null,
        "mfg_production_cost": "0.0000",
        "mfg_production_cost_type": "percentage",
        "mfg_is_final": 0,
        "is_created_from_api": 0,
        "essentials_duration": "0.00",
        "essentials_duration_unit": null,
        "essentials_amount_per_unit_duration": "0.0000",
        "essentials_allowances": null,
        "essentials_deductions": null,
        "rp_earned": 0,
        "repair_completed_on": null,
        "repair_warranty_id": null,
        "repair_brand_id": null,
        "repair_status_id": null,
        "repair_model_id": null,
        "repair_job_sheet_id": null,
        "repair_defects": null,
        "repair_serial_no": null,
        "repair_checklist": null,
        "repair_security_pwd": null,
        "repair_security_pattern": null,
        "repair_due_date": null,
        "repair_device_id": null,
        "repair_updates_notif": 0,
        "order_addresses": null,
        "is_recurring": 0,
        "recur_interval": 1,
        "recur_interval_type": "days",
        "recur_repetitions": 0,
        "recur_stopped_on": null,
        "recur_parent_id": null,
        "invoice_token": null,
        "pay_term_number": null,
        "pay_term_type": null,
        "pjt_project_id": null,
        "pjt_title": null,
        "woocommerce_order_id": null,
        "selling_price_group_id": 0,
        "created_at": "2020-09-23 20:16:19",
        "updated_at": "2020-09-25 17:57:08",
        "payment_lines": [
            {
                "id": 55,
                "transaction_id": 91,
                "business_id": 1,
                "is_return": 0,
                "amount": "461.7500",
                "method": "cash",
                "transaction_no": null,
                "card_transaction_number": null,
                "card_number": null,
                "card_type": "credit",
                "card_holder_name": null,
                "card_month": null,
                "card_year": null,
                "card_security": null,
                "cheque_number": null,
                "bank_account_number": null,
                "paid_on": "2020-09-23 20:16:19",
                "created_by": 9,
                "is_advance": 0,
                "payment_for": 1,
                "parent_id": null,
                "note": null,
                "document": null,
                "payment_ref_no": "SP2020/0018",
                "account_id": null,
                "created_at": "2020-09-23 20:16:19",
                "updated_at": "2020-09-23 20:16:19"
            }
        ],
        "invoice_url": "http://local.pos.com/invoice/6dfd77eb80f4976b456128e7f1311c9f",
        "payment_link": "http://local.pos.com/pay/6dfd77eb80f4976b456128e7f1311c9f"
    }
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $business_id = $user->business_id;
            $business = Business::find($business_id);

            $sell_data = $request->input();
            $sell_data['business_id'] = $user->business_id; 

            $transaction_before = Transaction::where('business_id', $user->business_id)->with(['payment_lines'])
                                    ->findOrFail($id);

            //Check if location allowed
            if (! $user->can_access_this_location($transaction_before->location_id)) {
                throw new \Exception('User not allowed to access location with id '.$input['location_id']);
            }

            $status_before = $transaction_before->status;
            $rp_earned_before = $transaction_before->rp_earned;
            $rp_redeemed_before = $transaction_before->rp_redeemed;

            $sell_data['location_id'] = $transaction_before->location_id; 
            $input = $this->__formatSellData($sell_data, $transaction_before);
            $discount = ['discount_type' => $input['discount_type'],
                'discount_amount' => $input['discount_amount'],
            ]; 
            $invoice_total = $this->productUtil->calculateInvoiceTotal($input['products'], $input['tax_rate_id'], $discount);

            //Begin transaction
            DB::beginTransaction();

            $transaction = $this->transactionUtil->updateSellTransaction($transaction_before, $business_id, $input, $invoice_total, $user->id, false); //return $input;

            //Update Sell lines
            $deleted_lines = $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $input['location_id'], true, $status_before, [], false);
            if (! empty($input['payment']) && $transaction->is_suspend == 0) {
                $change_return = $this->dummyPaymentLine;
                $change_return['amount'] = $input['change_return'];
                $change_return['is_return'] = 1;
                if (! empty($input['change_return_id'])) {
                    $change_return['id'] = $input['change_return_id'];
                }
                $input['payment'][] = $change_return;

                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment'], $business_id, $user->id, false);
            }

            if ($business->enable_rp == 1) {
                $this->transactionUtil->updateCustomerRewardPoints($transaction->contact_id, $transaction->rp_earned, $rp_earned_before, $transaction->rp_redeemed, $rp_redeemed_before);
            }

            //Update payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            //Update product stock
            $this->productUtil->adjustProductStockForInvoice($status_before, $transaction, $input, false);

            //Allocate the quantity from purchase and add mapping of
            //purchase & sell lines in
            //transaction_sell_lines_purchase_lines table
            $business_details = $this->businessUtil->getDetails($business_id);
            $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

            $business = ['id' => $business_id,
                'accounting_method' => $business->accounting_method,
                'location_id' => $input['location_id'],
                'pos_settings' => $pos_settings,
            ];
            $this->transactionUtil->adjustMappingPurchaseSell($status_before, $transaction, $business, $deleted_lines);

            $updated_transaction = Transaction::where('business_id', $user->business_id)->with(['payment_lines'])
                                    ->findOrFail($id);

            $updated_transaction->invoice_url = $this->transactionUtil->getInvoiceUrl($updated_transaction->id, $business_id);
            $updated_transaction->payment_link = $this->transactionUtil->getInvoicePaymentLink($updated_transaction->id, $business_id);

            $output = $updated_transaction;

            $client = $this->getClient();

            $this->transactionUtil->activityLog($updated_transaction, 'edited', $transaction_before, ['from_api' => $client]);
            DB::commit();
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            $output = $this->modelNotFoundExceptionResult($e);
        } catch (\Exception $e) {
            DB::rollback();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = $this->otherExceptions($e);
        }

        return $output;
    }

    private function __getValue($key, $data, $obj, $default = null, $db_key = null)
    {
        $value = $default;

        if (isset($data[$key])) {
            $value = $data[$key];
        } elseif (! empty($obj)) {
            $key = ! empty($db_key) ? $db_key : $key;
            $value = $obj->$key;
        }

        return $value;
    }

    /**
     * Formats input form data to sell data
     *
     * @param  array  $data
     * @return array
     */
    private function __formatSellData($data, $transaction = null)
    {
        
        $business_id = $data['business_id'];
        $location = BusinessLocation::where('business_id', $business_id)
                                        ->findOrFail($data['location_id']);

        $customer_id = $this->__getValue('contact_id', $data, $transaction, null);
        $contact = Contact::where('business_id', $data['business_id'])
                                ->whereIn('type', ['customer', 'both'])
                                ->findOrFail($customer_id);

        $cg = $this->contactUtil->getCustomerGroup($business_id, $contact->id);
        $customer_group_id = (empty($cg) || empty($cg->id)) ? null : $cg->id;
        $formated_data = [
            'business_id' => $business_id,
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'customer_group_id' => $customer_group_id,
            'transaction_date' => $this->__getValue('transaction_date', $data,
                                    $transaction, \Carbon::now()->toDateTimeString()),
            
            // ======================================================
            // (!!!) THIS IS THE NEW LINE (!!!)
            // It reads 'type' from your API request, or defaults to 'sell'
            // ======================================================
            'type' => $this->__getValue('type', $data, $transaction, 'sell'),
            // ======================================================

            'invoice_no' => $this->__getValue('invoice_no', $data, $transaction, null, 'invoice_no'),
            'source' => $this->__getValue('source', $data, $transaction, null, 'source'),
            'status' => $this->__getValue('status', $data, $transaction, 'final'),
            'sub_status' => $this->__getValue('sub_status', $data, $transaction, null),
            'sale_note' => $this->__getValue('sale_note', $data, $transaction),
            'staff_note' => $this->__getValue('staff_note', $data, $transaction),
            'commission_agent' => $this->__getValue('commission_agent',
                                    $data, $transaction),
            'shipping_details' => $this->__getValue('shipping_details',
                                    $data, $transaction),
            'shipping_address' => $this->__getValue('shipping_address',
                                    $data, $transaction),
            'shipping_status' => $this->__getValue('shipping_status', $data, $transaction),
            'delivered_to' => $this->__getValue('delivered_to', $data, $transaction),
            'shipping_charges' => $this->__getValue('shipping_charges', $data,
                    $transaction, 0),
            'exchange_rate' => $this->__getValue('exchange_rate', $data, $transaction, 1),
            'selling_price_group_id' => $this->__getValue('selling_price_group_id', $data, $transaction),
            'pay_term_number' => $this->__getValue('pay_term_number', $data, $transaction),
            'pay_term_type' => $this->__getValue('pay_term_type', $data, $transaction),
            'is_recurring' => $this->__getValue('is_recurring', $data, $transaction, 0),
            'recur_interval' => $this->__getValue('recur_interval', $data, $transaction),
            'recur_interval_type' => $this->__getValue('recur_interval_type', $data, $transaction),
            'subscription_repeat_on' => $this->__getValue('subscription_repeat_on', $data, $transaction),
            'subscription_no' => $this->__getValue('subscription_no', $data, $transaction),
            'recur_repetitions' => $this->__getValue('recur_repetitions', $data, $transaction, 0),
            'order_addresses' => $this->__getValue('order_addresses', $data, $transaction),
            'rp_redeemed' => $this->__getValue('rp_redeemed', $data, $transaction, 0),
            'rp_redeemed_amount' => $this->__getValue('rp_redeemed_amount', $data, $transaction, 0),
            'is_created_from_api' => 1,
            'types_of_service_id' => $this->__getValue('types_of_service_id', $data, $transaction),
            'packing_charge' => $this->__getValue('packing_charge', $data, $transaction, 0),
            'packing_charge_type' => $this->__getValue('packing_charge_type', $data, $transaction),
            'custom_field_1' => $this->__getValue('custom_field_1', $data, $transaction),
            'service_custom_field_1' => $this->__getValue('service_custom_field_1', $data, $transaction),
            'service_custom_field_2' => $this->__getValue('service_custom_field_2', $data, $transaction),
            'service_custom_field_3' => $this->__getValue('service_custom_field_3', $data, $transaction),
            'service_custom_field_4' => $this->__getValue('service_custom_field_4', $data, $transaction),
            'service_custom_field_5' => $this->__getValue('service_custom_field_5', $data, $transaction),
            'service_custom_field_6' => $this->__getValue('service_custom_field_6', $data, $transaction),
            'round_off_amount' => $this->__getValue('round_off_amount', $data, $transaction),
            'res_table_id' => $this->__getValue('table_id', $data, $transaction, null, 'res_table_id'),
            'res_waiter_id' => $this->__getValue('service_staff_id', $data, $transaction, null, 'res_waiter_id'),
            'change_return' => $this->__getValue('change_return', $data, $transaction, 0),
            'change_return_id' => $this->__getValue('change_return_id', $data, $transaction, null),
            'is_quotation' => $this->__getValue('is_quotation', $data, $transaction, 0),
            'is_suspend' => $this->__getValue('is_suspend', $data, $transaction, 0),
            'currency_id' => $this->__getValue('currency_id', $data, $transaction, 0),
        ];

        //Generate reference number
        if (! empty($formated_data['is_recurring'])) {
            //Update reference count
            $ref_count = $this->transactionUtil->setAndGetReferenceCount('subscription', $business_id);
            $formated_data['subscription_no'] = $this->transactionUtil->generateReferenceNumber('subscription', $ref_count, $business_id);
        }

        $sell_lines = [];
        $subtotal = 0;

        if (! empty($data['products'])) {
            foreach ($data['products'] as $product_data) {
                $sell_line = null;
                if (! empty($product_data['sell_line_id'])) {
                    $sell_line = TransactionSellLine::findOrFail($product_data['sell_line_id']);
                }

                $product_id = $this->__getValue('product_id', $product_data, $sell_line);
                $variation_id = $this->__getValue('variation_id', $product_data, $sell_line);
                $product = Product::where('business_id', $business_id)
                                ->with(['variations'])
                                ->findOrFail($product_id);

                $variation = $product->variations->where('id', $variation_id)->first();

                //Calculate line discount
                $unit_price = $this->__getValue('unit_price', $product_data, $sell_line, $variation->sell_price_inc_tax, 'unit_price_before_discount');

                $discount_amount = $this->__getValue('discount_amount', $product_data, $sell_line, 0, 'line_discount_amount');

                $line_discount = $discount_amount;
                $line_discount_type = $this->__getValue('discount_type', $product_data, $sell_line, 'fixed', 'line_discount_type');

                if ($line_discount_type == 'percentage') {
                    $line_discount = $this->transactionUtil->calc_percentage($unit_price, $discount_amount);
                }
                $discounted_price = $unit_price - $line_discount;

                //calculate line tax
                $item_tax = 0;
                $unit_price_inc_tax = $discounted_price;
                $tax_id = $this->__getValue('tax_rate_id', $product_data, $sell_line, null, 'tax_id');
                if (! empty($tax_id)) {
                    $tax = TaxRate::where('business_id', $business_id)
                                ->findOrFail($tax_id);

                    $item_tax = $this->transactionUtil->calc_percentage($discounted_price, $tax->amount);
                    $unit_price_inc_tax += $item_tax;
                }

                $formated_sell_line = [
                    'product_id' => $product->id,
                    'variation_id' => $variation->id,
                    'product_type' => $product->type,
                    'unit_price' => $unit_price,
                    'line_discount_type' => $line_discount_type,
                    'line_discount_amount' => $discount_amount,
                    'tax_id' => $tax_id,
                    'item_tax' => $item_tax,
                    'sell_line_note' => $this->__getValue('note', $product_data, $sell_line, null, 'sell_line_note'),
                    'enable_stock' => $product->enable_stock,
                    'quantity' => $this->__getValue('quantity', $product_data,
                                        $sell_line, 0),
                    'product_unit_id' => $product->unit_id,
                    'sub_unit_id' => $this->__getValue('sub_unit_id', $product_data,
                                        $sell_line),
                    'unit_price_inc_tax' => $unit_price_inc_tax,
                ];
                if (! empty($sell_line)) {
                    $formated_sell_line['transaction_sell_lines_id'] = $sell_line->id;
                }

                if (($formated_sell_line['product_unit_id'] != $formated_sell_line['sub_unit_id']) && ! empty($formated_sell_line['sub_unit_id'])) {
                    $sub_unit = Unit::where('business_id', $business_id)
                                    ->findOrFail($formated_sell_line['sub_unit_id']);
                    $formated_sell_line['base_unit_multiplier'] = $sub_unit->base_unit_multiplier;
                } else {
                    $formated_sell_line['base_unit_multiplier'] = 1;
                }

                //Combo product
                if ($product->type == 'combo') {
                    $combo_variations = $this->productUtil->calculateComboDetails($location->id, $variation->combo_variations);
                    foreach ($combo_variations as $key => $value) {
                        $combo_variations[$key]['quantity'] = $combo_variations[$key]['qty_required'] * $formated_sell_line['quantity'] * $formated_sell_line['base_unit_multiplier'];
                    }

                    $formated_sell_line['combo'] = $combo_variations;
                }

                if ($product->type == 'combo_single') {
                    //Assuming combo_single uses the same relationship and calculation as combo
                    $combo_variations = $this->productUtil->calculateComboDetails($location->id, $variation->combo_variations); 
                    foreach ($combo_variations as $key => $value) {
                        $combo_variations[$key]['quantity'] = $combo_variations[$key]['qty_required'] * $formated_sell_line['quantity'] * $formated_sell_line['base_unit_multiplier'];
                    }

                    $formated_sell_line['combo_single'] = $combo_variations;
                }

                $line_total = $unit_price_inc_tax * $formated_sell_line['quantity'];

                $sell_lines[] = $formated_sell_line;

                $subtotal += $line_total;
            }
        }

        $formated_data['products'] = $sell_lines;

        //calculate sell discount and tax
        $order_discount_amount = $this->__getValue('discount_amount', $data, $transaction, 0);
        $order_discount_type = $this->__getValue('discount_type', $data, $transaction, 'fixed');
        $order_discount = $order_discount_amount;
        if ($order_discount_type == 'percentage') {
            $order_discount = $this->transactionUtil->calc_percentage($subtotal, $order_discount_amount);
        }
        $discounted_total = $subtotal - $order_discount;

        //calculate line tax
        $order_tax = 0;
        $final_total = $discounted_total;
        $order_tax_id = $this->__getValue('tax_rate_id', $data, $transaction);
        if (! empty($order_tax_id)) {
            $tax = TaxRate::where('business_id', $business_id)
                        ->findOrFail($order_tax_id);

            $order_tax = $this->transactionUtil->calc_percentage($discounted_total, $tax->amount);
            $final_total += $order_tax;
        }

        $formated_data['discount_amount'] = $order_discount_amount;
        $formated_data['discount_type'] = $order_discount_type;
        $formated_data['tax_rate_id'] = $order_tax_id;
        $formated_data['tax_calculation_amount'] = $order_tax;

        $final_total += $formated_data['shipping_charges'];

        if (! empty($formated_data['packing_charge']) && ! empty($formated_data['types_of_service_id'])) {
            $final_total += $formated_data['packing_charge'];
        }

        $formated_data['final_total'] = $final_total;

        $payments = [];
        if (! empty($data['payments'])) {
            foreach ($data['payments'] as $payment_data) {
                $transaction_payment = null;
                if (! empty($payment_data['payment_id'])) {
                    $transaction_payment = TransactionPayment::findOrFail($payment_data['payment_id']);
                }
                $payment = [
                    'amount' => $this->__getValue('amount', $payment_data, $transaction_payment),
                    'method' => $this->__getValue('method', $payment_data, $transaction_payment),
                    'account_id' => $this->__getValue('account_id', $payment_data, $transaction_payment),
                    'card_number' => $this->__getValue('card_number', $payment_data, $transaction_payment),
                    'card_holder_name' => $this->__getValue('card_holder_name', $payment_data, $transaction_payment),
                    'card_transaction_number' => $this->__getValue('card_transaction_number', $payment_data, $transaction_payment),
                    'card_type' => $this->__getValue('card_type', $payment_data, $transaction_payment),
                    'card_month' => $this->__getValue('card_month', $payment_data, $transaction_payment),
                    'card_year' => $this->__getValue('card_year', $payment_data, $transaction_payment),
                    'card_security' => $this->__getValue('card_security', $payment_data, $transaction_payment),
                    'cheque_number' => $this->__getValue('cheque_number', $payment_data, $transaction_payment),
                    'bank_account_number' => $this->__getValue('bank_account_number', $payment_data, $transaction_payment),
                    'transaction_no_1' => $this->__getValue('transaction_no_1', $payment_data, $transaction_payment),
                    'transaction_no_2' => $this->__getValue('transaction_no_2', $payment_data, $transaction_payment),
                    'transaction_no_3' => $this->__getValue('transaction_no_3', $payment_data, $transaction_payment),
                    'note' => $this->__getValue('note', $payment_data, $transaction_payment),
                ];
                if (! empty($transaction_payment)) {
                    $payment['payment_id'] = $transaction_payment->id;
                }

                $payments[] = $payment;
            }

            $formated_data['payment'] = $payments;
        }

        return $formated_data;
    }

    /**
     * Delete Sell
     *
     * @urlParam sell required id of the sell to be deleted
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $business_id = $user->business_id;
            //Begin transaction
            DB::beginTransaction();

            $output = $this->transactionUtil->deleteSale($business_id, $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output['success'] = false;
            $output['msg'] = trans('messages.something_went_wrong');
        }

        return $output;
    }

    /**
     * Update shipping status
     *
     * @bodyParam id int required id of the sale
     * @bodyParam shipping_status string ('ordered', 'packed', 'shipped', 'delivered', 'cancelled') Example:ordered
     * @bodyParam delivered_to string Name of the consignee
     */
    public function updateSellShippingStatus(Request $request)
{
    try {
        $user = Auth::user();
        $business_id = $user->business_id;

        $sell_id = $request->input('id');
        $shipping_status = $request->input('shipping_status');
        $delivered_to = $request->input('delivered_to');
        $shipping_note = $request->input('shipping_note', '');
        
        // 1. Get the delivery person ID from request
        $delivery_person = $request->input('delivery_person'); 

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        if (array_key_exists($shipping_status, $shipping_statuses)) {

            // 2. Modified query to support 'sales_order' as well as 'sell'
            $transaction = Transaction::where('business_id', $business_id)
                ->where('id', $sell_id)
                ->whereIn('type', ['sell', 'sales_order']) // Changed from where('type', 'sell')
                ->first();

            if (!$transaction) {
                return $this->otherExceptions('Transaction not found', 404);
            }

            $transaction_before = $transaction->replicate();

            // Prepare data for update
            $update_data = [
                'shipping_status' => $shipping_status,
                'delivered_to' => $delivered_to,
                'shipping_note' => $shipping_note,
            ];

            // 3. Only update delivery_person if a value is provided (Optional)
            if (!empty($delivery_person)) {
                $update_data['delivery_person'] = $delivery_person;
            }

            // Update transaction
            $transaction->update($update_data);

            // Handle media uploads
            if ($request->hasFile('media') || $request->filled('media')) {
                Media::uploadMedia($business_id, $transaction, request(), 'media', false, 'shipping_document');
            }

            $activity_property = ['update_note' => $shipping_note];
            $this->transactionUtil->activityLog($transaction, 'shipping_edited', $transaction_before, $activity_property);
        
        } else {
            return $this->otherExceptions('Invalid shipping status');
        }

        return $this->respond([
            'success' => 1,
            'msg' => trans('lang_v1.updated_success'),
        ]);

    } catch (\Exception $e) {
        \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

        return $this->otherExceptions($e);
    }
}

    /**
     * Add Sell Return
     *
     * @bodyParam transaction_id integer required Id of the sell
     * @bodyParam transaction_date string transaction date format:Y-m-d H:i:s, Example: 2020-5-7 15:20:22
     * @bodyParam invoice_no string Invoice number of the return
     * @bodyParam discount_amount float discount amount applicable to the sale Example: 10.0000
     * @bodyParam discount_type string type of the discount amount (fixed, percentage) Example: fixed

     * @bodyParam products array required array of the products for the sale
     *
     * @bodyParam products.*.sell_line_id int required sell line id
     * @bodyParam products.*.quantity float required quantity to be returned from the sell line Example: 1
     * @bodyParam products.*.unit_price_inc_tax float required unit selling price of the returning item Example: 437.5000
     *
     * @response {
        "id": 159,
        "business_id": 1,
        "location_id": 1,
        "res_table_id": null,
        "res_waiter_id": null,
        "res_order_status": null,
        "type": "sell_return",
        "sub_type": null,
        "status": "final",
        "is_quotation": 0,
        "payment_status": "paid",
        "adjustment_type": null,
        "contact_id": 1,
        "customer_group_id": null,
        "invoice_no": "CN2020/0005",
        "ref_no": null,
        "subscription_no": null,
        "subscription_repeat_on": null,
        "transaction_date": "2020-11-17 00:00:00",
        "total_before_tax": 3,
        "tax_id": null,
        "tax_amount": 0,
        "discount_type": "percentage",
        "discount_amount": 12,
        "rp_redeemed": 0,
        "rp_redeemed_amount": "0.0000",
        "shipping_details": null,
        "shipping_address": null,
        "shipping_status": null,
        "delivered_to": null,
        "shipping_charges": "0.0000",
        "additional_notes": null,
        "staff_note": null,
        "round_off_amount": "0.0000",
        "final_total": 2.64,
        "expense_category_id": null,
        "expense_for": null,
        "commission_agent": null,
        "document": null,
        "is_direct_sale": 0,
        "is_suspend": 0,
        "exchange_rate": "1.000",
        "total_amount_recovered": null,
        "transfer_parent_id": null,
        "return_parent_id": 157,
        "opening_stock_product_id": null,
        "created_by": 9,
        "import_batch": null,
        "import_time": null,
        "types_of_service_id": null,
        "packing_charge": null,
        "packing_charge_type": null,
        "service_custom_field_1": null,
        "service_custom_field_2": null,
        "service_custom_field_3": null,
        "service_custom_field_4": null,
        "mfg_parent_production_purchase_id": null,
        "mfg_wasted_units": null,
        "mfg_production_cost": "0.0000",
        "mfg_production_cost_type": "percentage",
        "mfg_is_final": 0,
        "is_created_from_api": 0,
        "essentials_duration": "0.00",
        "essentials_duration_unit": null,
        "essentials_amount_per_unit_duration": "0.0000",
        "essentials_allowances": null,
        "essentials_deductions": null,
        "rp_earned": 0,
        "repair_completed_on": null,
        "repair_warranty_id": null,
        "repair_brand_id": null,
        "repair_status_id": null,
        "repair_model_id": null,
        "repair_job_sheet_id": null,
        "repair_defects": null,
        "repair_serial_no": null,
        "repair_checklist": null,
        "repair_security_pwd": null,
        "repair_security_pattern": null,
        "repair_due_date": null,
        "repair_device_id": null,
        "repair_updates_notif": 0,
        "order_addresses": null,
        "is_recurring": 0,
        "recur_interval": null,
        "recur_interval_type": null,
        "recur_repetitions": null,
        "recur_stopped_on": null,
        "recur_parent_id": null,
        "invoice_token": null,
        "pay_term_number": null,
        "pay_term_type": null,
        "pjt_project_id": null,
        "pjt_title": null,
        "woocommerce_order_id": null,
        "selling_price_group_id": null,
        "created_at": "2020-11-17 12:05:11",
        "updated_at": "2020-11-17 13:22:09"
    }
     */
    public function addSellReturn(Request $request)
    {
        try {
            $input = $request->except('_token');

            if (! empty($input['products'])) {
                $user = Auth::user();

                $business_id = $user->business_id;

                DB::beginTransaction();

                $output = $this->transactionUtil->addSellReturn($input, $business_id, $user->id);

                DB::commit();
            }
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            $output = $this->modelNotFoundExceptionResult($e);
        } catch (\Exception $e) {
            DB::rollback();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = $this->otherExceptions($e);
        }

        return $output;
    }

    /**
     * List Sell Return
     *
     * @urlParam sell_id Id of the sell for which return is added
     *
     * @response {
        "data": [
            {
                "id": 159,
                "business_id": 1,
                "location_id": 1,
                "res_table_id": null,
                "res_waiter_id": null,
                "res_order_status": null,
                "type": "sell_return",
                "sub_type": null,
                "status": "final",
                "is_quotation": 0,
                "payment_status": "partial",
                "adjustment_type": null,
                "contact_id": 1,
                "customer_group_id": null,
                "invoice_no": "CN2020/0005",
                "ref_no": null,
                "subscription_no": null,
                "subscription_repeat_on": null,
                "transaction_date": "2020-11-17 00:00:00",
                "total_before_tax": "3.0000",
                "tax_id": null,
                "tax_amount": "0.0000",
                "discount_type": "percentage",
                "discount_amount": "12.0000",
                "rp_redeemed": 0,
                "rp_redeemed_amount": "0.0000",
                "shipping_details": null,
                "shipping_address": null,
                "shipping_status": null,
                "delivered_to": null,
                "shipping_charges": "0.0000",
                "additional_notes": null,
                "staff_note": null,
                "round_off_amount": "0.0000",
                "final_total": "2.6400",
                "expense_category_id": null,
                "expense_for": null,
                "commission_agent": null,
                "document": null,
                "is_direct_sale": 0,
                "is_suspend": 0,
                "exchange_rate": "1.000",
                "total_amount_recovered": null,
                "transfer_parent_id": null,
                "return_parent_id": 157,
                "opening_stock_product_id": null,
                "created_by": 9,
                "import_batch": null,
                "import_time": null,
                "types_of_service_id": null,
                "packing_charge": null,
                "packing_charge_type": null,
                "service_custom_field_1": null,
                "service_custom_field_2": null,
                "service_custom_field_3": null,
                "service_custom_field_4": null,
                "mfg_parent_production_purchase_id": null,
                "mfg_wasted_units": null,
                "mfg_production_cost": "0.0000",
                "mfg_production_cost_type": "percentage",
                "mfg_is_final": 0,
                "is_created_from_api": 0,
                "essentials_duration": "0.00",
                "essentials_duration_unit": null,
                "essentials_amount_per_unit_duration": "0.0000",
                "essentials_allowances": null,
                "essentials_deductions": null,
                "rp_earned": 0,
                "repair_completed_on": null,
                "repair_warranty_id": null,
                "repair_brand_id": null,
                "repair_status_id": null,
                "repair_model_id": null,
                "repair_job_sheet_id": null,
                "repair_defects": null,
                "repair_serial_no": null,
                "repair_checklist": null,
                "repair_security_pwd": null,
                "repair_security_pattern": null,
                "repair_due_date": null,
                "repair_device_id": null,
                "repair_updates_notif": 0,
                "order_addresses": null,
                "is_recurring": 0,
                "recur_interval": null,
                "recur_interval_type": null,
                "recur_repetitions": null,
                "recur_stopped_on": null,
                "recur_parent_id": null,
                "invoice_token": null,
                "pay_term_number": null,
                "pay_term_type": null,
                "pjt_project_id": null,
                "pjt_title": null,
                "woocommerce_order_id": null,
                "selling_price_group_id": null,
                "created_at": "2020-11-17 12:05:11",
                "updated_at": "2020-11-17 13:22:09",
                "payment_lines": [
                    {
                        "id": 126,
                        "transaction_id": 159,
                        "business_id": 1,
                        "is_return": 0,
                        "amount": "1.8000",
                        "method": "cash",
                        "transaction_no": null,
                        "card_transaction_number": null,
                        "card_number": null,
                        "card_type": "credit",
                        "card_holder_name": null,
                        "card_month": null,
                        "card_year": null,
                        "card_security": null,
                        "cheque_number": null,
                        "bank_account_number": null,
                        "paid_on": "2020-11-17 12:05:00",
                        "created_by": 9,
                        "is_advance": 0,
                        "payment_for": 1,
                        "parent_id": null,
                        "note": null,
                        "document": null,
                        "payment_ref_no": "SP2020/0078",
                        "account_id": null,
                        "created_at": "2020-11-17 12:05:58",
                        "updated_at": "2020-11-17 12:05:58"
                    }
                ],
                "return_parent_sell": {
                    "id": 157,
                    "business_id": 1,
                    "location_id": 1,
                    "res_table_id": null,
                    "res_waiter_id": null,
                    "res_order_status": null,
                    "type": "sell",
                    "sub_type": null,
                    "status": "final",
                    "is_quotation": 0,
                    "payment_status": "paid",
                    "adjustment_type": null,
                    "contact_id": 1,
                    "customer_group_id": null,
                    "invoice_no": "AS0073",
                    "ref_no": "",
                    "subscription_no": null,
                    "subscription_repeat_on": null,
                    "transaction_date": "2020-11-13 12:42:17",
                    "total_before_tax": "6.2500",
                    "tax_id": null,
                    "tax_amount": "0.0000",
                    "discount_type": "percentage",
                    "discount_amount": "10.0000",
                    "rp_redeemed": 0,
                    "rp_redeemed_amount": "0.0000",
                    "shipping_details": null,
                    "shipping_address": null,
                    "shipping_status": null,
                    "delivered_to": null,
                    "shipping_charges": "0.0000",
                    "additional_notes": null,
                    "staff_note": null,
                    "round_off_amount": "0.0000",
                    "final_total": "5.6300",
                    "expense_category_id": null,
                    "expense_for": null,
                    "commission_agent": null,
                    "document": null,
                    "is_direct_sale": 0,
                    "is_suspend": 0,
                    "exchange_rate": "1.000",
                    "total_amount_recovered": null,
                    "transfer_parent_id": null,
                    "return_parent_id": null,
                    "opening_stock_product_id": null,
                    "created_by": 9,
                    "import_batch": null,
                    "import_time": null,
                    "types_of_service_id": null,
                    "packing_charge": "0.0000",
                    "packing_charge_type": null,
                    "service_custom_field_1": null,
                    "service_custom_field_2": null,
                    "service_custom_field_3": null,
                    "service_custom_field_4": null,
                    "mfg_parent_production_purchase_id": null,
                    "mfg_wasted_units": null,
                    "mfg_production_cost": "0.0000",
                    "mfg_production_cost_type": "percentage",
                    "mfg_is_final": 0,
                    "is_created_from_api": 0,
                    "essentials_duration": "0.00",
                    "essentials_duration_unit": null,
                    "essentials_amount_per_unit_duration": "0.0000",
                    "essentials_allowances": null,
                    "essentials_deductions": null,
                    "rp_earned": 0,
                    "repair_completed_on": null,
                    "repair_warranty_id": null,
                    "repair_brand_id": null,
                    "repair_status_id": null,
                    "repair_model_id": null,
                    "repair_job_sheet_id": null,
                    "repair_defects": null,
                    "repair_serial_no": null,
                    "repair_checklist": null,
                    "repair_security_pwd": null,
                    "repair_security_pattern": null,
                    "repair_due_date": null,
                    "repair_device_id": null,
                    "repair_updates_notif": 0,
                    "order_addresses": null,
                    "is_recurring": 0,
                    "recur_interval": 1,
                    "recur_interval_type": "days",
                    "recur_repetitions": 0,
                    "recur_stopped_on": null,
                    "recur_parent_id": null,
                    "invoice_token": null,
                    "pay_term_number": null,
                    "pay_term_type": null,
                    "pjt_project_id": null,
                    "pjt_title": null,
                    "woocommerce_order_id": null,
                    "selling_price_group_id": 0,
                    "created_at": "2020-11-13 12:42:17",
                    "updated_at": "2020-11-13 12:42:18",
                    "sell_lines": [
                        {
                            "id": 139,
                            "transaction_id": 157,
                            "product_id": 157,
                            "variation_id": 205,
                            "quantity": 5,
                            "mfg_waste_percent": "0.0000",
                            "quantity_returned": "3.0000",
                            "unit_price_before_discount": "1.2500",
                            "unit_price": "1.2500",
                            "line_discount_type": "fixed",
                            "line_discount_amount": "0.0000",
                            "unit_price_inc_tax": "1.2500",
                            "item_tax": "0.0000",
                            "tax_id": null,
                            "discount_id": null,
                            "lot_no_line_id": null,
                            "sell_line_note": "",
                            "res_service_staff_id": null,
                            "res_line_order_status": null,
                            "woocommerce_line_items_id": null,
                            "parent_sell_line_id": null,
                            "children_type": "",
                            "sub_unit_id": null,
                            "created_at": "2020-11-13 12:42:17",
                            "updated_at": "2020-11-17 13:22:09"
                        }
                    ]
                }
            }
        ],
        "links": {
            "first": "http://local.pos.com/connector/api/list-sell-return?sell_id=157&page=1",
            "last": null,
            "prev": null,
            "next": null
        },
        "meta": {
            "current_page": 1,
            "from": 1,
            "path": "http://local.pos.com/connector/api/list-sell-return",
            "per_page": 10,
            "to": 1
        }
    }
     */
    public function listSellReturn()
    {
        $filters = request()->input();
        $user = Auth::user();
        $business_id = $user->business_id;

        $sell_id = request()->input('sell_id');
        $query = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell_return')
                            ->where('status', 'final')
                            ->with(['payment_lines', 'return_parent_sell', 'return_parent_sell.sell_lines'])
                            ->select('transactions.*');

        $permitted_locations = $user->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        if (! empty($sell_id)) {
            $query->where('return_parent_id', $sell_id);
        }

        $perPage = ! empty($filters['per_page']) ? $filters['per_page'] : $this->perPage;
        if ($perPage == -1) {
            $sell_returns = $query->get();
        } else {
            $sell_returns = $query->paginate($perPage);
            $sell_returns->appends(request()->query());
        }

        return SellResource::collection($sell_returns);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Render invoice → PDF bytes (mPDF) → PNG bytes → Telegram sendPhoto.
     * ZERO files written to disk at any point.
     *
     * PDF→PNG conversion tried in order (all via stdin/stdout pipes or PHP memory):
     *   1. Imagick PHP extension  readImageBlob / getImageBlob
     *   2. Ghostscript via proc_open pipes  (gs reads stdin, writes PNG to stdout)
     *   3. ImageMagick via proc_open pipes  (convert pdf:- png:-)
     */
    public function sendInvoiceToTelegram(Transaction $transaction, int $business_id): void
    {
        try {
            // ── 1. Render invoice Blade → HTML string ─────────────────────────────────
            $business_details = $this->businessUtil->getDetails($business_id);
            $location_details = BusinessLocation::find($transaction->location_id);
            $invoice_layout   = $this->businessUtil->invoiceLayout(
                $business_id,
                $location_details->invoice_layout_id ?? null
            );

            $receipt_details = $this->transactionUtil->getReceiptDetails(
                $transaction->id,
                $transaction->location_id,
                $invoice_layout,
                $business_details,
                $location_details,
                'browser',
                $business_id
            );
            $receipt_details->currency = [
                'symbol'             => $business_details->currency_symbol,
                'thousand_separator' => $business_details->thousand_separator,
                'decimal_separator'  => $business_details->decimal_separator,
            ];

            $layout = ! empty($receipt_details->design)
                ? 'sale_pos.receipts.' . $receipt_details->design
                : 'sale_pos.receipts.classic';

            $html = view($layout, compact('receipt_details'))->render();

            // ── 2. Build caption & invoice URL (needed by Method A and the send step) ──
            $invoice_url = $this->transactionUtil->getInvoiceUrl($transaction->id, $business_id);
            $botToken    = config('services.telegram.bot_token', '8737726993:AAEd8C5uWwHu5cYc8YVH4zfpUwUxSWaplSc');
            $caption     = "🧾 *New Sales Order Invoice*"
                         . "\nOrder No: " . $transaction->invoice_no
                         . "\nDate: " . \Carbon\Carbon::parse($transaction->transaction_date)->format('Y-m-d H:i')
                         . "\n\n🔗 " . $invoice_url;

            // ── 3. Render invoice HTML → PNG ──────────────────────────────────────────
            // Try best-quality methods first (correct Khmer shaping), fall back to
            // dompdf GD (renders Khmer but without OpenType shaping — characters show
            // but syllable clusters may not compose correctly).
            $pngBytes      = null;
            $method_used   = 'none';
            $dompdfFontDir = base_path('vendor/dompdf/dompdf/lib/fonts');
            $standaloneHtml = $this->buildStandaloneInvoiceHtml($html);
            $lazyPdfBytes  = null; // computed once on first PDF-based CLI tool attempt

            $phpExtDir       = ini_get('extension_dir');
            $imagickFileExists = file_exists($phpExtDir . '/imagick.so')
                || file_exists($phpExtDir . '/php_imagick.dll')
                || file_exists($phpExtDir . '/imagick.dll');
            \Log::info('SalesOrder Telegram: extensions=' . implode(',', array_filter(['gd', 'imagick'], 'extension_loaded'))
                . ' imagick_file=' . ($imagickFileExists ? 'yes(disabled)' : 'no')
                . ' ext_dir=' . $phpExtDir
                . ' chromium=' . (trim((string) shell_exec('which chromium-browser 2>/dev/null')) ?: 'none')
                . ' wkhtmltoimage=' . (trim((string) shell_exec('which wkhtmltoimage 2>/dev/null')) ?: 'none'));

            // Method A — mPDF + Imagick (best Khmer: mPDF applies OTL shaping, Imagick converts PDF→PNG)
            if (! $pngBytes && extension_loaded('imagick')) {
                $pdfBytes = $this->renderPdfBytes($standaloneHtml, $dompdfFontDir);
                if ($pdfBytes) {
                    try {
                        $im = new \Imagick();
                        $im->setResolution(150, 150);
                        $im->readImageBlob($pdfBytes);
                        $im->setIteratorIndex(0);
                        $im->setImageFormat('png');
                        $im->setImageBackgroundColor('white');
                        $im->flattenImages();
                        $candidate = $im->getImageBlob();
                        $im->clear();
                        $im->destroy();
                        if (strlen($candidate) > 500) {
                            $pngBytes    = $candidate;
                            $method_used = 'mpdf_imagick';
                        }
                    } catch (\Exception $ex) {
                        \Log::warning('SalesOrder Telegram: Imagick render failed — ' . $ex->getMessage());
                    }
                }
            }

            // Method B — CLI tools (wkhtmltoimage / chromium have full browser rendering with correct Khmer)
            if (! $pngBytes) {
                // stdin→stdout pipe tools
                $cli_methods = [
                    'wkhtmltoimage' => function($p) use ($standaloneHtml) {
                        $tmpDir  = public_path('uploads/temp');
                        @mkdir($tmpDir, 0755, true);
                        $tmpHtml = $tmpDir . '/wk_' . uniqid() . '.html';
                        $tmpPng  = $tmpDir . '/wk_' . uniqid() . '.png';
                        try {
                            file_put_contents($tmpHtml, $standaloneHtml);
                            $xvfb   = trim((string) shell_exec('which xvfb-run 2>/dev/null'));
                            $prefix = $xvfb ? $xvfb . ' --auto-servernum ' : '';
                            $cmd    = $prefix . $p
                                . ' --format png --quality 90 --width 800'
                                . ' --enable-local-file-access'
                                . ' --no-stop-slow-scripts --javascript-delay 0'
                                . ' ' . escapeshellarg($tmpHtml)
                                . ' ' . escapeshellarg($tmpPng)
                                . ' 2>&1';
                            $output = shell_exec($cmd);
                            $size   = file_exists($tmpPng) ? filesize($tmpPng) : 0;
                            \Log::info('SalesOrder Telegram: wkhtmltoimage size=' . $size . ' output=' . substr((string)$output, 0, 200));
                            return ($size > 500) ? file_get_contents($tmpPng) : null;
                        } finally {
                            @unlink($tmpHtml);
                            @unlink($tmpPng);
                        }
                    },
                    'gs'            => fn($p) => $this->runPipeCommand(
                        "$p -q -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -dFirstPage=1 -dLastPage=1 -sOutputFile=- -",
                        $lazyPdfBytes ?? ($lazyPdfBytes = $this->renderPdfBytes($standaloneHtml, $dompdfFontDir) ?? '')),
                    'convert'       => fn($p) => $this->runPipeCommand(
                        "$p -density 150 -background white -flatten pdf:- png:-",
                        $lazyPdfBytes ?? ($lazyPdfBytes = $this->renderPdfBytes($standaloneHtml, $dompdfFontDir) ?? '')),
                    'mutool'        => fn($p) => $this->runPipeCommand(
                        "$p draw -F png -o - -r 150 -",
                        $lazyPdfBytes ?? ($lazyPdfBytes = $this->renderPdfBytes($standaloneHtml, $dompdfFontDir) ?? '')),
                    'pdftoppm'      => fn($p) => $this->runPipeCommand(
                        "$p -png -r 150 -singlefile - -",
                        $lazyPdfBytes ?? ($lazyPdfBytes = $this->renderPdfBytes($standaloneHtml, $dompdfFontDir) ?? '')),
                ];
                foreach ($cli_methods as $tool => $fn) {
                    $path = trim((string) shell_exec('which ' . escapeshellarg($tool) . ' 2>/dev/null'));
                    if (! $path) {
                        continue;
                    }
                    $result = $fn($path);
                    if ($result && strlen($result) > 500) {
                        $pngBytes    = $result;
                        $method_used = $tool;
                        break;
                    }
                }

                // Chromium/Chrome headless (uses temp files; best rendering quality with correct Khmer shaping)
                if (! $pngBytes) {
                    $chromeBins = [
                        'chromium-browser', 'chromium', 'google-chrome',
                        'google-chrome-stable', 'google-chrome-unstable',
                        '/usr/bin/chromium-browser', '/usr/bin/chromium',
                        '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
                    ];
                    $tempDir = public_path('uploads/temp');
                    foreach ($chromeBins as $bin) {
                        $chromePath = is_file($bin) ? $bin
                            : trim((string) shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null'));
                        if (! $chromePath) {
                            continue;
                        }
                        $tempId  = uniqid('inv_', true);
                        $tmpHtml = $tempDir . '/' . $tempId . '.html';
                        $tmpPng  = $tempDir . '/' . $tempId . '.png';
                        try {
                            file_put_contents($tmpHtml, $standaloneHtml);
                            $fileUrl = 'file://' . str_replace('\\', '/', $tmpHtml);
                            shell_exec(
                                escapeshellarg($chromePath)
                                . ' --headless --no-sandbox --disable-gpu'
                                . ' --disable-software-rasterizer'
                                . ' --screenshot=' . escapeshellarg($tmpPng)
                                . ' --window-size=820,1400'
                                . ' --virtual-time-budget=3000'
                                . ' ' . escapeshellarg($fileUrl)
                                . ' 2>/dev/null'
                            );
                            if (file_exists($tmpPng) && filesize($tmpPng) > 500) {
                                $pngBytes    = file_get_contents($tmpPng);
                                $method_used = 'chromium';
                            }
                        } finally {
                            if (file_exists($tmpHtml)) @unlink($tmpHtml);
                            if (file_exists($tmpPng))  @unlink($tmpPng);
                        }
                        if ($pngBytes) {
                            break;
                        }
                    }
                }
            }

            // Method C — dompdf GD adapter (pure PHP fallback; Khmer renders but syllable
            // shaping may be imperfect because imagettftext lacks HarfBuzz OTL support)
            if (! $pngBytes && extension_loaded('gd')) {
                try {
                    $domOptions = new \Dompdf\Options();
                    $domOptions->setPdfBackend('gd');
                    $domOptions->setIsRemoteEnabled(true);
                    $domOptions->setChroot(public_path());
                    $domOptions->setTempDir(public_path('uploads/temp'));
                    $domOptions->setFontDir($dompdfFontDir);
                    $domOptions->setFontCache($dompdfFontDir);

                    $dompdf = new \Dompdf\Dompdf($domOptions);

                    // Register Battambang font variants so dompdf never falls back to
                    // DejaVu Sans (which has no Khmer glyphs) for bold/italic elements.
                    $battambangCached = glob($dompdfFontDir . '/battambang_normal_*.ttf') ?: [];
                    if (! empty($battambangCached)) {
                        $battambangFilename = basename($battambangCached[0], '.ttf');
                        $fm = $dompdf->getFontMetrics();
                        $fm->setFontFamily('battambang', [
                            'normal'      => $battambangFilename,
                            'bold'        => $battambangFilename,
                            'italic'      => $battambangFilename,
                            'bold_italic' => $battambangFilename,
                        ]);
                    }

                    $dompdf->loadHtml($standaloneHtml, 'UTF-8');
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();

                    /** @var \Dompdf\Adapter\GD $canvas */
                    $canvas    = $dompdf->getCanvas();
                    $candidate = $canvas->output(['type' => 'png']);

                    if (strlen((string) $candidate) > 500) {
                        $pngBytes    = $candidate;
                        $method_used = 'dompdf_gd';
                    }
                } catch (\Exception $ex) {
                    \Log::warning('SalesOrder Telegram: dompdf GD render failed — ' . $ex->getMessage());
                }
            }

            \Log::info('SalesOrder Telegram: method=' . $method_used . ' png_size=' . strlen((string) $pngBytes));

            // Method D — GD info card (last resort, always works)
            if (! $pngBytes) {
                $pngBytes = $this->buildGdInvoiceCard($transaction, $business_details);
            }

            // ── 3b. Persist PNG for invoice_image_url API field ──────────────────────
            if ($pngBytes) {
                try {
                    $imgDir      = public_path('uploads/invoice_images');
                    @mkdir($imgDir, 0755, true);
                    $safeInvoice = preg_replace('/[^A-Za-z0-9_\-]/', '_', $transaction->invoice_no);
                    file_put_contents($imgDir . '/' . $safeInvoice . '.png', $pngBytes);
                } catch (\Exception $e) {
                    \Log::warning('SalesOrder Telegram: invoice image save failed — ' . $e->getMessage());
                }
            }

            // ── 4. Fetch chat_ids that have sales_order_invoice enabled ──────────────
            $chat_ids = DB::table('telegram_schedules')
                ->where('business_id', $business_id)
                ->where('is_active', 1)
                ->whereNotNull('chat_id')
                ->get(['chat_id', 'report_types'])
                ->filter(function ($row) {
                    $types = json_decode($row->report_types ?? '[]', true) ?: [];
                    return in_array('sales_order_invoice', $types);
                })
                ->pluck('chat_id')
                ->unique()
                ->values();

            \Log::info('SalesOrder Telegram: chat_ids_count=' . $chat_ids->count() . ' ids=' . $chat_ids->implode(','));

            if ($chat_ids->isEmpty()) {
                \Log::warning('SalesOrder Telegram: no chat_ids with sales_order_invoice — skipping send');
                return;
            }

            // ── 5. Send as photo image ────────────────────────────────────────────────
            foreach ($chat_ids as $chat_id) {
                $response = Http::attach('photo', $pngBytes, 'invoice_' . $transaction->invoice_no . '.png')
                    ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id'    => $chat_id,
                        'caption'    => $caption,
                        'parse_mode' => 'Markdown',
                    ]);
                \Log::info('SalesOrder Telegram: sent chat_id=' . $chat_id
                    . ' status=' . $response->status()
                    . ' ok=' . ($response->json('ok') ? 'true' : 'false')
                    . ' err=' . ($response->json('description') ?? '-'));
            }

        } catch (\Exception $e) {
            \Log::error('SalesOrder Telegram invoice error (tx=' . $transaction->id . '): ' . $e->getMessage());
        }
    }

    /**
     * Wrap the bare receipt partial HTML in a complete standalone page.
     * Includes minimal Bootstrap 3 grid/table CSS and the Hanuman Khmer font
     * embedded as a base64 data URI, so dompdf has everything it needs without
     * loading external URLs or relying on the server's CSS asset pipeline.
     */
    private function buildStandaloneInvoiceHtml(string $receiptHtml): string
    {
        $fontFace = ''; // no @font-face needed — dompdf finds the font via its cache files

        // ── Minimal Bootstrap 3 CSS covering every class used by classic.blade.php ─
        $css = <<<'CSS'
* { box-sizing: border-box; }
body { font-size: 13px; line-height: 1.5; color: #000; background: #fff; margin: 0; padding: 16px; }
h2 { font-size: 20px; font-weight: bold; margin: 8px 0; }
h3 { font-size: 17px; font-weight: bold; margin: 6px 0; }
h4 { font-size: 15px; font-weight: bold; margin: 4px 0; }
p  { margin: 0 0 8px; }
b, strong { font-weight: bold; }
small { font-size: 85%; }
hr  { border: 0; border-top: 1px solid #ccc; margin: 8px 0; }
img { max-width: 100%; height: auto; display: inline-block; }
.center-block { display: block; margin: 0 auto; }
.img-responsive { max-width: 100%; height: auto; }

/* Grid */
.row { width: 100%; overflow: hidden; }
.row::after { content: ''; display: table; clear: both; }
.col-xs-1,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,
.col-xs-7,.col-xs-8,.col-xs-9,.col-xs-10,.col-xs-11,.col-xs-12,
.col-sm-1,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,
.col-sm-7,.col-sm-8,.col-sm-9,.col-sm-10,.col-sm-11,.col-sm-12,
.col-md-1,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,
.col-md-7,.col-md-8,.col-md-9,.col-md-10,.col-md-11,.col-md-12
{ float: left; padding: 0 4px; }
.col-xs-12,.col-sm-12,.col-md-12 { width:100%; }
.col-xs-11,.col-sm-11,.col-md-11 { width:91.66%; }
.col-xs-10,.col-sm-10,.col-md-10 { width:83.33%; }
.col-xs-9,.col-sm-9,.col-md-9   { width:75%; }
.col-xs-8,.col-sm-8,.col-md-8   { width:66.66%; }
.col-xs-7,.col-sm-7,.col-md-7   { width:58.33%; }
.col-xs-6,.col-sm-6,.col-md-6   { width:50%; }
.col-xs-5,.col-sm-5,.col-md-5   { width:41.66%; }
.col-xs-4,.col-sm-4,.col-md-4   { width:33.33%; }
.col-xs-3,.col-sm-3,.col-md-3   { width:25%; }
.col-xs-2,.col-sm-2,.col-md-2   { width:16.66%; }
.col-xs-1,.col-sm-1,.col-md-1   { width:8.33%; }
.col-md-offset-2 { margin-left:16.66%; }
.col-xs-offset-0 { margin-left:0; }

/* Float helpers */
.pull-left  { float: left !important; }
.pull-right { float: right !important; }
.clearfix::after { content:''; display:table; clear:both; }

/* Text */
.text-left   { text-align: left !important; }
.text-right  { text-align: right !important; }
.text-center { text-align: center !important; }
.text-bold, .bold { font-weight: bold !important; }
.word-wrap   { word-wrap: break-word; }

/* Tables */
table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
th, td { padding: 5px 6px; vertical-align: top; text-align: left; }
.table-bordered, .table-bordered th, .table-bordered td { border: 1px solid #ccc; }
.table-condensed th, .table-condensed td { padding: 3px 5px; }
.table-striped tbody tr:nth-child(odd) td { background-color: #f9f9f9; }

/* Well */
.well { background: #f5f5f5; border: 1px solid #e3e3e3; padding: 10px; border-radius: 3px; margin: 8px 0; }

/* Misc */
.no-print, .btn { display: none !important; }
CSS;

        return '<!DOCTYPE html>'
            . '<html lang="km"><head>'
            . '<meta charset="UTF-8">'
            // Bootstrap 3 from CDN — wkhtmltoimage loads it for pixel-perfect layout.
            // mPDF/dompdf fall back to the inline $css block below.
            . '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">'
            . '<style>'
            . $fontFace
            . 'body, * { font-family: "Battambang", "DejaVu Sans", Arial, sans-serif !important; }'
            . $css
            . '</style>'
            . '</head><body>'
            . '<div style="max-width:780px; margin:0 auto; padding:16px;">'
            . $receiptHtml
            . '</div>'
            . '</body></html>';
    }

    /**
     * Use microlink.io (free, no API key) to screenshot a publicly accessible URL.
     * Returns PNG bytes, or null if the URL is not reachable by the external service.
     */
    private function screenshotViaApi(string $pageUrl): ?string
    {
        try {
            // Step 1 — ask microlink to take a screenshot and return the JSON metadata
            $response = Http::timeout(20)->get('https://api.microlink.io', [
                'url'        => $pageUrl,
                'screenshot' => 'true',
                'meta'       => 'false',
                'waitFor'    => 2000,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $screenshotUrl = $response->json('data.screenshot.url');
            if (empty($screenshotUrl)) {
                return null;
            }

            // Step 2 — download the actual PNG bytes
            $img = Http::timeout(15)->get($screenshotUrl);
            if ($img->successful() && strlen($img->body()) > 1000) {
                return $img->body();
            }

        } catch (\Exception $e) {
            \Log::info('SalesOrder Telegram: microlink screenshot failed — ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Run a CLI command that reads bytes from stdin and writes bytes to stdout.
     * Used for both HTML→PNG (wkhtmltoimage) and PDF→PNG (gs / convert) pipelines.
     * No files are created. Returns the output bytes, or null on failure.
     */
    private function runPipeCommand(string $command, string $inputBytes): ?string
    {
        try {
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin  — we write input bytes
                1 => ['pipe', 'w'],  // stdout — we read output bytes
                2 => ['pipe', 'w'],  // stderr — discard
            ];

            $process = @proc_open($command, $descriptors, $pipes);

            if (! is_resource($process)) {
                return null;
            }

            fwrite($pipes[0], $inputBytes);
            fclose($pipes[0]);

            $outputBytes = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return (strlen($outputBytes) > 500) ? $outputBytes : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Render HTML to PDF bytes in memory using mPDF.
     * When $fontDir is provided, configures mPDF to use the Hanuman Khmer font
     * and enables its PHP-based OpenType Layout processor for correct Khmer shaping.
     * Returns raw PDF string (no file written).
     */
    private function renderPdfBytes(string $html, ?string $fontDir = null): ?string
    {
        try {
            $config = [
                'tempDir' => public_path('uploads/temp'),
                'mode'    => 'utf-8',
                'format'  => 'A4',
            ];

            // Font search order — first match wins.
            // Google Fonts Battambang & Noto Sans Khmer have modern OTL tables that
            // mPDF's PHP OTL engine can use for correct Khmer cluster shaping.
            // KhmerOS fonts use an older OTL structure incompatible with mPDF.
            $fontCandidates = [
                // Google Fonts Battambang downloaded to storage/fonts/
                [
                    'dir'    => storage_path('fonts'),
                    'R'      => 'Battambang-Regular.ttf',
                    'B'      => 'Battambang-Bold.ttf',
                    'family' => 'battambang',
                    'css'    => 'Battambang',
                ],
                // Noto Sans Khmer — apt install fonts-noto-core
                [
                    'dir'    => '/usr/share/fonts/truetype/noto',
                    'R'      => 'NotoSansKhmer-Regular.ttf',
                    'B'      => 'NotoSansKhmer-Bold.ttf',
                    'family' => 'notosanskhmer',
                    'css'    => 'Noto Sans Khmer',
                ],
                [
                    'dir'    => '/usr/share/fonts/truetype/noto',
                    'R'      => 'NotoSerifKhmer-Regular.ttf',
                    'B'      => 'NotoSerifKhmer-Bold.ttf',
                    'family' => 'notoserifkhmer',
                    'css'    => 'Noto Serif Khmer',
                ],
            ];
            $resolvedFont = null;
            foreach ($fontCandidates as $candidate) {
                $rPath = $candidate['dir'] . '/' . $candidate['R'];
                if (file_exists($rPath)) {
                    $bFile          = file_exists($candidate['dir'] . '/' . $candidate['B']) ? $candidate['B'] : $candidate['R'];
                    $resolvedFont   = $candidate;
                    $resolvedFont['B'] = $bFile;
                    break;
                }
            }
            if ($resolvedFont) {
                // Always register under 'battambang' so the CSS font-family: "Battambang"
                // declaration in buildStandaloneInvoiceHtml always resolves correctly,
                // regardless of which physical font file is being used.
                $config['fontDir']  = [$resolvedFont['dir']];
                $config['fontdata'] = [
                    'battambang' => [
                        'R'  => $resolvedFont['R'],
                        'B'  => $resolvedFont['B'],
                        'I'  => $resolvedFont['R'],
                        'BI' => $resolvedFont['B'],
                    ],
                ];
                $config['default_font'] = 'battambang';
                \Log::info('SalesOrder Telegram: mPDF font=' . $resolvedFont['R'] . ' from ' . $resolvedFont['dir']);
            }

            $config['useOTL']           = 0xFF;
            $config['useSubstitutions'] = true;
            $mpdf = new \Mpdf\Mpdf($config);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } catch (\Exception $e) {
            \Log::warning('SalesOrder Telegram: mPDF render failed — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * GD fallback: build a clean invoice-card PNG entirely in PHP memory.
     * Requires only the `gd` extension which ships with every standard PHP build.
     * No external tools, no disk writes.
     */
    private function buildGdInvoiceCard(Transaction $transaction, $business_details): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $w = 800;

            // Count sell lines (max 10 displayed) to size the canvas
            $sell_lines  = $transaction->sell_lines ?? collect();
            $line_count  = min($sell_lines->count(), 10);
            $h           = 320 + ($line_count * 26) + 80;

            $im = imagecreatetruecolor($w, $h);

            // ── Colours ──────────────────────────────────────────────────────────────
            $c_white     = imagecolorallocate($im, 255, 255, 255);
            $c_black     = imagecolorallocate($im,  30,  30,  30);
            $c_blue      = imagecolorallocate($im,  30,  90, 200);
            $c_blue_dark = imagecolorallocate($im,  20,  65, 150);
            $c_gray      = imagecolorallocate($im, 140, 140, 140);
            $c_light     = imagecolorallocate($im, 235, 243, 255);
            $c_border    = imagecolorallocate($im, 200, 215, 235);
            $c_green     = imagecolorallocate($im,  34, 139,  34);

            // ── Background ───────────────────────────────────────────────────────────
            imagefill($im, 0, 0, $c_white);

            // ── Header bar ───────────────────────────────────────────────────────────
            imagefilledrectangle($im, 0, 0, $w, 80, $c_blue);
            imagefilledrectangle($im, 0, 78, $w, 82, $c_blue_dark);

            $biz_name = mb_strimwidth($business_details->name ?? 'Business', 0, 50, '...');
            imagestring($im, 5, 24, 16, $biz_name, $c_white);
            imagestring($im, 4, 24, 46, 'SALES ORDER INVOICE', $c_white);
            imagestring($im, 3, $w - 200, 54, now()->format('Y-m-d H:i'), $c_light);

            // ── Detail card ──────────────────────────────────────────────────────────
            imagefilledrectangle($im, 16, 96, $w - 16, 200, $c_light);
            imagerectangle($im,       16, 96, $w - 16, 200, $c_border);

            $inv_no   = $transaction->invoice_no ?? '-';
            $tx_date  = \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i');
            $customer = mb_strimwidth(optional($transaction->contact)->name ?? '-', 0, 40, '...');
            $status   = strtoupper($transaction->status ?? '-');

            imagestring($im, 5, 32, 110, 'Order No : ' . $inv_no,   $c_black);
            imagestring($im, 4, 32, 138, 'Date     : ' . $tx_date,  $c_black);
            imagestring($im, 4, 32, 162, 'Customer : ' . $customer, $c_black);
            imagestring($im, 4, 560, 138, 'Status: ' . $status,      $c_green);

            // ── Table header ─────────────────────────────────────────────────────────
            $ty = 216;
            imagefilledrectangle($im, 16, $ty, $w - 16, $ty + 24, $c_blue);
            imagestring($im, 3, 28,  $ty + 6, '#',        $c_white);
            imagestring($im, 3, 52,  $ty + 6, 'Product',  $c_white);
            imagestring($im, 3, 536, $ty + 6, 'Qty',      $c_white);
            imagestring($im, 3, 616, $ty + 6, 'Price',    $c_white);
            imagestring($im, 3, 700, $ty + 6, 'Subtotal', $c_white);

            // ── Table rows ───────────────────────────────────────────────────────────
            $ty  += 26;
            $num  = 1;
            $grand_total = 0.0;

            foreach ($sell_lines->take(10) as $line) {
                $prod  = mb_strimwidth(optional($line->product)->name ?? 'Product', 0, 38, '...');
                $qty   = number_format((float) ($line->quantity ?? 0), 0);
                $price = number_format((float) ($line->unit_price_inc_tax ?? 0), 0);
                $sub   = (float) ($line->unit_price_inc_tax ?? 0) * (float) ($line->quantity ?? 0);
                $grand_total += $sub;

                $row_bg = ($num % 2 === 0) ? $c_light : $c_white;
                imagefilledrectangle($im, 16, $ty, $w - 16, $ty + 24, $row_bg);
                imagerectangle($im,       16, $ty, $w - 16, $ty + 24, $c_border);

                imagestring($im, 3, 28,  $ty + 6, (string) $num,                 $c_gray);
                imagestring($im, 3, 52,  $ty + 6, $prod,                          $c_black);
                imagestring($im, 3, 548, $ty + 6, $qty,                           $c_black);
                imagestring($im, 3, 616, $ty + 6, $price,                         $c_black);
                imagestring($im, 3, 700, $ty + 6, number_format($sub, 0), $c_black);

                $ty += 26;
                $num++;
            }

            // ── Total footer ─────────────────────────────────────────────────────────
            $ty += 8;
            imagefilledrectangle($im, 0, $ty, $w, $ty + 60, $c_blue);
            $final = (float) ($transaction->final_total ?? $grand_total);
            imagestring($im, 5, 28,  $ty + 18, 'TOTAL:',                           $c_white);
            imagestring($im, 5, 120, $ty + 18, number_format($final, 0),           $c_white);
            imagestring($im, 3, 28,  $ty + 42, 'Powered by SalePro',               $c_light);

            ob_start();
            imagepng($im);
            $pngBytes = ob_get_clean();
            imagedestroy($im);

            return strlen($pngBytes) > 200 ? $pngBytes : null;

        } catch (\Exception $e) {
            \Log::error('GD invoice card error: ' . $e->getMessage());
            return null;
        }
    }
}