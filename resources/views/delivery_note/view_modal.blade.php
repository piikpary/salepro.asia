<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">
        Delivery Note Details &nbsp;
        <small>( <strong>{{ $dn->invoice_no }}</strong> )</small>
    </h4>
</div>

<div class="modal-body">

    {{-- Date top-right --}}
    <div class="row">
        <div class="col-xs-12">
            <p class="pull-right"><b>Date:</b> {{ \Carbon\Carbon::parse($dn->transaction_date)->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    {{-- Info columns --}}
    <div class="row">
        {{-- Left: DN info --}}
        <div class="col-sm-4">
            <b>DN No.:</b> #{{ $dn->invoice_no }}<br>
            <b>Sales Order:</b> {{ $so->invoice_no ?? '—' }}<br>
            <b>Status:</b>
            @if($dn->status == 'ordered')
                <span class="label label-warning">Ordered</span>
            @elseif($dn->status == 'delivered')
                <span class="label label-success">Delivered</span>
            @elseif($dn->status == 'completed')
                <span class="label label-primary">Completed</span>
            @else
                <span class="label label-default">{{ ucfirst($dn->status) }}</span>
            @endif
            <br>
            <b>Stock Status:</b>
            @if(in_array($dn->status, ['delivered','completed']))
                <span class="label label-success">Deducted</span>
            @else
                <span class="label label-danger">Not Deducted</span>
            @endif
            <br>
            <b>Delivery Person:</b> {{ $dn->delivery_person_name ?? '—' }}<br>
        </div>

        {{-- Middle: Customer --}}
        <div class="col-sm-4">
            <b>Customer Name:</b> {{ $dn->customer_name }}<br>
            <b>Address:</b><br>
            {{ $dn->customer_address ?? '—' }}<br>
            <b>Mobile:</b> {{ $dn->customer_mobile ?? '—' }}
        </div>

        {{-- Right: Location --}}
        <div class="col-sm-4">
            <b>Location:</b> {{ $dn->location_name }}<br>
            @if(!empty($dn->additional_notes))
                <b>Note:</b> {{ $dn->additional_notes }}<br>
            @endif
        </div>
    </div>

    <br>

    {{-- Products --}}
    <p><b>Products:</b></p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr style="background-color:#00a65a; color:#fff;">
                    <th width="4%">#</th>
                    <th>Product</th>
                    <th class="text-right" width="14%">Qty</th>
                    <th class="text-right" width="12%">Unit Price</th>
                    <th class="text-right" width="10%">Discount</th>
                    <th class="text-right" width="8%">Tax</th>
                    <th class="text-right" width="12%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $i = 0; @endphp
                @foreach($dn_lines as $line)
                @php
                    $subtotal  = $line->quantity * $line->unit_price;
                    $discount  = $line->line_discount_amount ?? 0;
                    $tax       = $line->item_tax ?? 0;
                @endphp
                <tr>
                    <td>{{ ++$i }}</td>
                    <td>
                        <b>{{ $line->product_name }}</b>
                        @if($line->variation_name !== 'DUMMY')
                            &nbsp;{{ $line->variation_name }}
                        @endif
                        @if(!empty($line->sub_sku))
                            <small class="text-muted">&nbsp;{{ $line->sub_sku }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{@format_quantity($line->quantity)}} {{ $line->unit_name }}</td>
                    <td class="text-right">${{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-right">${{ number_format($discount, 2) }}</td>
                    <td class="text-right">${{ number_format($tax, 2) }}</td>
                    <td class="text-right">${{ number_format($subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals summary (right-aligned) + Notes --}}
    <div class="row">
        {{-- Sell note --}}
        <div class="col-sm-6">
            <div class="form-group">
                <label>Delivery Note:</label>
                <p class="well well-sm" style="min-height:36px;">{{ $dn->additional_notes ?: '—' }}</p>
            </div>
        </div>

        {{-- Summary --}}
        <div class="col-sm-6">
            <table class="table table-condensed" style="margin-top:0;">
                <tr>
                    <td class="text-right"><b>Total:</b></td>
                    <td class="text-right" style="width:120px;">${{ number_format($dn->final_total, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right"><b>Total Qty Delivered:</b></td>
                    <td class="text-right">{{@format_quantity($total_delivered)}}</td>
                </tr>
                <tr style="border-top:2px solid #ddd;">
                    <td class="text-right"><b>Total Payable:</b></td>
                    <td class="text-right"><strong>${{ number_format($dn->final_total, 2) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Activity Log --}}
    @if($activities->isNotEmpty())
    <br>
    <p><b>Activities:</b></p>
    <table class="table table-bordered table-condensed">
        <thead>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>By</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activities as $act)
            <tr>
                <td>{{ \Carbon\Carbon::parse($act->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $act->action }}</td>
                <td>{{ $act->by }}</td>
                <td>{{ $act->note ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

</div>

<div class="modal-footer">
    @can('delivery_note.print')
    <a href="#" class="print-invoice btn btn-primary" data-href="{{ route('delivery-note.getReceipt', $dn->id) }}">
        <i class="fa fa-print"></i> Print
    </a>
    @endcan
    <button type="button" class="btn btn-default" data-dismiss="modal">
        Close
    </button>
</div>
