<table style="width:100%;">
    <thead>
        <tr>
            <td>
                <p class="text-right color-555 font-30">
                    <b>Delivery Return</b>
                </p>
            </td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>

<!-- business info -->
<div class="row invoice-info">
    <div class="col-md-6 invoice-col width-50 color-555">
        @if(!empty($rd->logo))
            <img style="max-height:120px;width:auto;" src="{{ $rd->logo }}" class="img"><br/>
        @endif
        @if(!empty($rd->display_name))
            <p>
                <span style="font-size:24px;font-weight:900;color:black;">{{ $rd->display_name }}</span>
                @if(!empty($rd->address))<br/>{!! $rd->address !!}@endif
                @if(!empty($rd->contact))<br/>{!! $rd->contact !!}@endif
            </p>
        @endif
    </div>

    <div class="col-md-6 invoice-col width-50">
        <p class="text-right font-17">
            <span class="pull-left">Return No.</span>
            {{ $rd->invoice_no }}
        </p>
        <p class="text-right font-17">
            <span class="pull-left">Parent DN No.</span>
            {{ $rd->dn_invoice_no }}
        </p>
        <p class="text-right font-17">
            <span class="pull-left">Date:</span>
            {{ $rd->invoice_date }}
        </p>
        <p class="text-right font-17">
            <span class="pull-left">Status:</span>
            {{ $rd->status }}
        </p>
    </div>

    <div class="col-md-6 invoice-col width-50 word-wrap">
        <b>Customer</b><br/>
        {!! $rd->customer_info !!}
    </div>
</div>

<!-- product table -->
<div class="row color-555">
    <div class="col-xs-12">
        <br/>
        @php
            $show_marketing_col = !empty($rd->marketing_price_label)
                && collect($rd->lines)->contains(function($l) { return !empty($l['weight']); });
        @endphp
        <table class="table table-bordered table-no-top-cell-border">
            <thead>
                <tr style="background-color:#357ca5!important;color:white!important;font-size:18px!important;" class="table-no-side-cell-border table-no-top-cell-border text-center">
                    <td style="background-color:#357ca5!important;color:white!important;width:5%!important">#</td>
                    <td style="background-color:#357ca5!important;color:white!important;width:30%!important">Product Name</td>
                    @if($show_marketing_col)
                    <td style="background-color:#357ca5!important;color:white!important;width:10%!important">{{ $rd->marketing_price_label }}</td>
                    @endif
                    <td style="background-color:#357ca5!important;color:white!important;width:15%!important">Delivered Qty</td>
                    <td style="background-color:#357ca5!important;color:white!important;width:15%!important">Return Qty</td>
                    <td style="background-color:#357ca5!important;color:white!important;width:10%!important">Good Stock</td>
                    <td style="background-color:#357ca5!important;color:white!important;width:10%!important">Damaged</td>
                </tr>
            </thead>
            <tbody>
                @foreach($rd->lines as $i => $line)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td style="word-break:break-all;">{{ $line['name'] }}</td>
                    @if($show_marketing_col)
                    <td class="text-right">
                        @if(!empty($line['weight']) && isset($line['default_sell_price']))
                            {{ number_format($line['default_sell_price'] / $line['weight'], 2) }}
                        @else
                            &nbsp;
                        @endif
                    </td>
                    @endif
                    <td class="text-right">{{ $line['dn_qty'] }} {{ $line['unit'] }}</td>
                    <td class="text-right">{{ $line['return_qty'] }} {{ $line['unit'] }}</td>
                    <td class="text-right">{{ $line['good_stock'] }}</td>
                    <td class="text-right">{{ $line['damaged'] }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right"><strong>Total Return Qty:</strong></td>
                    <td class="text-right"><strong>{{ $rd->total_return_qty }}</strong></td>
                    <td class="text-right"><strong>{{ $rd->total_good_stock }}</strong></td>
                    <td class="text-right"><strong>{{ $rd->total_damaged }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@if(!empty($rd->notes))
<div class="row color-555">
    <div class="col-xs-12">
        <b>Return Note:</b> {{ $rd->notes }}
    </div>
</div>
<br/>
@endif

<!-- signatures -->
<div class="row invoice-info color-555" style="page-break-inside:avoid!important;margin-top:40px;">
    <div class="col-xs-4 text-center">
        <div style="border-top:1px solid #333;padding-top:5px;">Prepared By</div>
    </div>
    <div class="col-xs-4 text-center">
        <div style="border-top:1px solid #333;padding-top:5px;">Received By</div>
    </div>
    <div class="col-xs-4 text-center">
        <div style="border-top:1px solid #333;padding-top:5px;">Authorized By</div>
    </div>
</div>

@if(!empty($rd->footer_text))
<div class="row color-555" style="margin-top:20px;">
    <div class="col-xs-12">
        {!! $rd->footer_text !!}
    </div>
</div>
@endif

            </td>
        </tr>
    </tbody>
</table>
