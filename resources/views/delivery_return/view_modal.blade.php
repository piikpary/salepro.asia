<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">
        Delivery Return Details &nbsp;
        <small>( <strong>{{ $dr->invoice_no }}</strong> )</small>
    </h4>
</div>

<div class="modal-body">

    {{-- Date top-right --}}
    <div class="row">
        <div class="col-xs-12">
            <p class="pull-right"><b>Date:</b> {{ \Carbon\Carbon::parse($dr->transaction_date)->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    {{-- Info columns --}}
    <div class="row">
        {{-- Left: Return info --}}
        <div class="col-sm-4">
            <b>Return No.:</b> #{{ $dr->invoice_no }}<br>
            <b>Parent DN No.:</b> {{ $dn->invoice_no ?? '—' }}<br>
            <b>Status:</b>
            @if($dr->status == 'completed')
                <span class="label label-success">Completed</span>
            @else
                <span class="label label-warning">Pending</span>
            @endif
            <br>
            <b>Stock Status:</b>
            @if($dr->status == 'completed')
                <span class="label label-success">Added Back</span>
            @else
                <span class="label label-warning">Not Added Back</span>
            @endif
        </div>

        {{-- Middle: Customer --}}
        <div class="col-sm-4">
            <b>Customer Name:</b> {{ $dr->customer_name }}<br>
            <b>Address:</b><br>
            {{ $dr->customer_address ?? '—' }}<br>
            <b>Mobile:</b> {{ $dr->customer_mobile ?? '—' }}
        </div>

        {{-- Right: Location --}}
        <div class="col-sm-4">
            <b>Location:</b> {{ $dr->location_name }}<br>
        </div>
    </div>

    <br>

    {{-- Products --}}
    <p><b>Returned Products:</b></p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr style="background-color:#00a65a; color:#fff;">
                    <th width="4%">#</th>
                    <th>Product</th>
                    <th class="text-right" width="14%">Delivered Qty</th>
                    <th class="text-right" width="12%">Return Qty</th>
                    <th class="text-right" width="10%">Good Stock</th>
                    <th class="text-right" width="10%">Damaged</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dr_lines as $i => $line)
                @php
                    $dn_qty = $dn_qty_map[$line->product_id . '_' . $line->variation_id] ?? 0;
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <b>{{ $line->product_name }}</b>
                        @if($line->variation_name !== 'DUMMY')
                            &nbsp;{{ $line->variation_name }}
                        @endif
                    </td>
                    <td class="text-right">{{@format_quantity($dn_qty)}} {{ $line->unit_name }}</td>
                    <td class="text-right">{{@format_quantity($line->quantity_returned ?? 0)}} {{ $line->unit_name }}</td>
                    <td class="text-right">{{@format_quantity($line->good_stock_qty ?? 0)}}</td>
                    <td class="text-right">{{@format_quantity($line->damaged_qty ?? 0)}}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color:#f9f9f9; font-weight:bold;">
                    <td colspan="3" class="text-right">Totals:</td>
                    <td class="text-right">{{@format_quantity($total_return_qty)}}</td>
                    <td class="text-right">{{@format_quantity($total_good_stock)}}</td>
                    <td class="text-right">{{@format_quantity($dr_lines->sum('damaged_qty'))}}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Notes + Summary --}}
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label>Return Note:</label>
                <p class="well well-sm" style="min-height:36px;">{{ $dr->additional_notes ?: '—' }}</p>
            </div>
        </div>
        <div class="col-sm-6">
            <table class="table table-condensed" style="margin-top:0;">
                <tr>
                    <td class="text-right"><b>Total Return Qty:</b></td>
                    <td class="text-right" style="width:120px;">{{@format_quantity($total_return_qty)}}</td>
                </tr>
                <tr>
                    <td class="text-right"><b>Good Stock Added Back:</b></td>
                    <td class="text-right">{{@format_quantity($total_good_stock)}}</td>
                </tr>
                <tr>
                    <td class="text-right"><b>Damaged:</b></td>
                    <td class="text-right">{{@format_quantity($dr_lines->sum('damaged_qty'))}}</td>
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
    <a href="{{ route('delivery-return.print', $dr->id) }}" target="_blank" class="btn btn-primary">
        <i class="fa fa-print"></i> Print
    </a>
    <button type="button" class="btn btn-default" data-dismiss="modal">
        Close
    </button>
</div>
