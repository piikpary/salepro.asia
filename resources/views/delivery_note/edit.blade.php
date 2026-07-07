@extends('layouts.app')
@section('title', 'Edit Delivery Note - ' . $dn->invoice_no)

@section('css')
<style>
#products_table input.inp-deliver-qty::-webkit-outer-spin-button,
#products_table input.inp-deliver-qty::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}
#products_table input.inp-deliver-qty { -moz-appearance: textfield !important; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1>Edit Delivery Note — <strong>{{ $dn->invoice_no }}</strong></h1>
</section>

<section class="content">
<div class="box box-primary">

    {{-- Location bar --}}
    <div class="box-header with-border" style="background:#f4f6f8; padding:10px 15px;">
        <div class="input-group" style="max-width:320px;">
            <span class="input-group-addon" style="background:#fff;">
                <i class="fa fa-map-marker text-primary"></i>
            </span>
            {!! Form::select('_location_display', $business_locations, $dn->location_id, [
                'class' => 'form-control select2',
                'style' => 'width:100%',
                'id'    => 'location_id_display',
            ]) !!}
            <span class="input-group-addon" style="background:#1a73e8; color:#fff;">
                <i class="fa fa-info-circle"></i>
            </span>
        </div>
    </div>

    <div class="box-body">
    {!! Form::open(['route' => ['delivery-note.update', $dn->id], 'method' => 'PUT', 'id' => 'dn_edit_form']) !!}
    <input type="hidden" name="location_id" id="location_id_hidden" value="{{ $dn->location_id }}">

        {{-- Row 1: Customer | Sales Order | DN No | Date --}}
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-user"></i></span>
                        <input type="text" class="form-control" value="{{ $dn->customer_name }}" readonly style="background:#f9f9f9;">
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Sales Order</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        <input type="text" class="form-control" value="{{ $so->invoice_no ?? '—' }}" readonly style="background:#f9f9f9;">
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Note No.</label>
                    <input type="text" class="form-control" value="{{ $dn->invoice_no }}" readonly style="background:#f9f9f9;">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Date</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($dn->transaction_date)->format('m/d/Y H:i') }}" readonly style="background:#f9f9f9;">
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Delivery Person | Status --}}
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Person</label>
                    {!! Form::select('delivery_person', $delivery_persons, $dn->delivery_person, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Status <span class="text-danger">*</span></label>
                    @if($dn->status === 'completed')
                        <input type="hidden" name="status" value="completed">
                        <div class="form-control" style="background:#f5f5f5; cursor:not-allowed;">
                            <span class="label label-primary">Completed</span>
                            <small class="text-muted"> — set automatically when invoiced</small>
                        </div>
                    @else
                        {!! Form::select('status', [
                            'ordered'   => 'Ordered',
                            'delivered' => 'Delivered',
                        ], $dn->status, [
                            'class'    => 'form-control select2',
                            'style'    => 'width:100%',
                            'required' => true,
                        ]) !!}
                    @endif
                </div>
            </div>
        </div>

        @php
            $qty_precision = (int) session('business.quantity_precision', 2);
            $qty_step = $qty_precision > 0 ? number_format(pow(10, -$qty_precision), $qty_precision) : '1';
            // Smart format for input value: preserve actual decimal digits (e.g. 2.5 not 2.5000)
            $fmt_qty_input = function($n) use ($qty_precision) {
                $n = (float) $n;
                $p = $qty_precision;
                if ($n != floor($n)) {
                    $str = rtrim(rtrim(sprintf('%.10f', $n), '0'), '.');
                    $dot = strpos($str, '.');
                    $actual = ($dot !== false) ? (strlen($str) - $dot - 1) : 0;
                    $p = max($p, $actual);
                }
                return number_format($n, $p, '.', '');
            };
        @endphp
        {{-- Products Table --}}
        <div class="row" style="margin-top:15px;">
            <div class="col-md-12">
                <table class="table table-hover" id="products_table"
                       style="border-collapse:collapse; border:1px solid #e5e5e5; table-layout:fixed; width:100%;">
                    <colgroup>
                        <col style="width:30%">
                        <col style="width:16%">
                        <col style="width:10%">
                        <col style="width:14%">
                        <col style="width:15%">
                    </colgroup>
                    <thead>
                        <tr style="border-bottom:2px solid #e5e5e5;">
                            <th style="padding:10px 12px; font-weight:600; color:#333;">Product</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Deliver Qty</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Unit</th>
                            <th class="text-right"  style="padding:10px 8px; font-weight:600; color:#333;">Unit Price</th>
                            <th class="text-right"  style="padding:10px 8px; font-weight:600; color:#333;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dn_lines as $i => $line)
                        @php
                            $children = \Illuminate\Support\Facades\DB::table('transaction_sell_lines as tsl')
                                ->join('products as p', 'tsl.product_id', '=', 'p.id')
                                ->where('tsl.parent_sell_line_id', $line->id)
                                ->select('tsl.product_id','tsl.variation_id','tsl.unit_price','p.enable_stock',
                                    \Illuminate\Support\Facades\DB::raw('tsl.quantity / ' . (float) $line->quantity . ' as unit_qty'))
                                ->get()->toArray();
                        @endphp
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:10px 12px; vertical-align:middle;">
                                {{ $line->product_name }}{{ $line->variation_name !== 'DUMMY' ? ' - '.$line->variation_name : '' }}
                                @if(!empty($line->sub_sku))
                                    <br><small class="text-muted">{{ $line->sub_sku }}</small>
                                @endif
                                <input type="hidden" name="products[{{ $i }}][product_id]"   value="{{ $line->product_id }}">
                                <input type="hidden" name="products[{{ $i }}][variation_id]" value="{{ $line->variation_id }}">
                                <input type="hidden" name="products[{{ $i }}][sub_unit_id]"  value="{{ $line->sub_unit_id }}">
                                <input type="hidden" name="products[{{ $i }}][so_line_id]"   value="{{ $line->so_line_id }}">
                                <input type="hidden" name="products[{{ $i }}][product_type]" value="{{ $line->product_type }}">
                                <input type="hidden" name="products[{{ $i }}][enable_stock]" value="{{ $line->enable_stock }}">
                                <input type="hidden" name="products[{{ $i }}][unit_price]"   value="{{ $line->unit_price }}" class="inp-unit-price">
                                <input type="hidden" name="products[{{ $i }}][children]"     value="{{ json_encode($children) }}">
                            </td>
                            <td class="text-center" style="padding:6px 8px; vertical-align:middle;">
                                <input type="number" name="products[{{ $i }}][deliver_qty]"
                                       class="form-control inp-deliver-qty"
                                       style="text-align:center; width:100%; max-width:120px; margin:0 auto; display:block;"
                                       value="{{ $fmt_qty_input($line->quantity) }}"
                                       min="0" step="{{ $qty_step }}">
                            </td>
                            <td class="text-center" style="padding:10px 8px; vertical-align:middle;">{{ $line->unit_name }}</td>
                            <td class="text-right"  style="padding:10px 8px; vertical-align:middle;">${{ number_format($line->unit_price, 2) }}</td>
                            <td class="text-right td-subtotal" style="padding:10px 8px; vertical-align:middle;">${{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:1px solid #e5e5e5;">
                            <td colspan="3" style="padding:10px 8px; border:none;"></td>
                            <td class="text-right" style="padding:10px 8px; border:none; color:#555;"><strong>Total Amount</strong></td>
                            <td class="text-right" style="padding:10px 8px; border:none;"><strong id="total_amount">${{ number_format($dn->final_total, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Delivery Note --}}
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Delivery Note</label>
                    <textarea name="delivery_note" class="form-control" rows="3"
                              placeholder="Delivery note...">{{ $dn->additional_notes }}</textarea>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" id="btn_update">
                    <i class="fa fa-save"></i> Update
                </button>
            </div>
        </div>

    {!! Form::close() !!}
    </div>
</div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {

    // Sync location display → hidden input
    $('#location_id_display').on('change', function () {
        $('#location_id_hidden').val($(this).val());
    });

    $(document).on('input', '.inp-deliver-qty', function () {
        var $row  = $(this).closest('tr');
        var qty   = parseFloat($(this).val()) || 0;
        var price = parseFloat($row.find('.inp-unit-price').val()) || 0;
        $row.find('.td-subtotal').text('$' + (qty * price).toFixed(2));
        recalcTotal();
    });

    function recalcTotal() {
        var total = 0;
        $('.td-subtotal').each(function () {
            total += parseFloat($(this).text().replace('$', '')) || 0;
        });
        $('#total_amount').text('$' + total.toFixed(2));
    }

    $('#btn_update').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        $.ajax({
            url: '{{ route("delivery-note.update", $dn->id) }}',
            type: 'POST',
            data: $('#dn_edit_form').serialize() + '&_method=PUT',
            success: function (r) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update');
                if (r.success) {
                    toastr.success('Delivery Note updated successfully!');
                    setTimeout(function () { window.location = '{{ route("delivery-note.index") }}'; }, 1200);
                } else {
                    toastr.error(r.message || 'Error updating.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update');
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error.';
                toastr.error(msg);
            }
        });
    });
});
</script>
@endsection
