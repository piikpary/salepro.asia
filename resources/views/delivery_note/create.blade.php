@extends('layouts.app')
@section('title', 'Add Delivery Note')

@section('css')
<style>
/* Remove spinner arrows from all number inputs in Deliver Qty column */
#products_table input.inp-deliver-qty::-webkit-outer-spin-button,
#products_table input.inp-deliver-qty::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}
#products_table input.inp-deliver-qty {
    -moz-appearance: textfield !important;
}
</style>
@endsection

@section('content')
<section class="content-header no-print">
    <h1>Add Delivery Note</h1>
</section>

<section class="content no-print">
<div class="box box-primary">

    {{-- Location bar (matches Figma top section) --}}
    <div class="box-header with-border" style="background:#f4f6f8; padding:10px 15px;">
        <div class="input-group" style="max-width:320px;">
            <span class="input-group-addon" style="background:#fff;">
                <i class="fa fa-map-marker text-primary"></i>
            </span>
            {!! Form::select('_location_display', $business_locations, $default_location_id ?? null, [
                'class' => 'form-control select2',
                'style' => 'width:100%',
                'id'    => 'location_id',
            ]) !!}
            <span class="input-group-addon" style="background:#1a73e8; color:#fff; cursor:pointer;" title="Location info">
                <i class="fa fa-info-circle"></i>
            </span>
        </div>
    </div>

    <div class="box-body">
    {!! Form::open(['route' => 'delivery-note.store', 'method' => 'POST', 'id' => 'dn_form']) !!}
    {{-- location_id synced from the top selector via JS --}}
    <input type="hidden" name="location_id" id="location_id_hidden" value="{{ $default_location_id ?? '' }}">

        {{-- Row 1: Customer | Sales Order | DN No | Date --}}
        <div class="row">
            {{-- Customer --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-user"></i></span>
                        <select name="customer_id" id="customer_id" class="form-control select2" style="width:100%" required>
                            <option value="">Select Customer</option>
                            @foreach($customers as $cid => $name)
                                <option value="{{ $cid }}" {{ isset($so) && $so->contact_id == $cid ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-primary" tabindex="-1" title="Add Customer">
                                <i class="fa fa-plus"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Sales Order --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Sales Order</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        <select name="sales_order_id" id="sales_order_id" class="form-control select2" style="width:100%">
                            <option value="">Select Sales Order</option>
                            @isset($so)
                                <option value="{{ $so->id }}" selected>{{ $so->invoice_no }}</option>
                            @endisset
                        </select>
                    </div>
                    <small class="text-primary" style="cursor:pointer;" id="auto_fetch_hint">
                        <i class="fa fa-bolt"></i> Auto fetch data from SO
                    </small>
                </div>
            </div>

            {{-- DN No --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Note No.</label>
                    <input type="text" name="dn_no" class="form-control" id="dn_no_display"
                           value="{{ $dn_no }}"
                           placeholder="Keep blank to auto generate">
                    <small class="text-muted">Leave blank to auto generate</small>
                    <span id="dn_no_error" class="text-danger" style="display:none;font-size:12px;"></span>
                </div>
            </div>

            {{-- Date --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Date <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        <input type="text" name="transaction_date" id="dn_date" class="form-control"
                               value="{{ \Carbon\Carbon::now()->format('m/d/Y H:i') }}" required>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Delivery Person | Status --}}
        <div class="row">
            {{-- Delivery Person --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Person</label>
                    {!! Form::select('delivery_person', $delivery_persons, null, [
                        'class'   => 'form-control select2',
                        'style'   => 'width:100%',
                        'id'      => 'delivery_person',
                    ]) !!}
                </div>
            </div>

            {{-- Status --}}
            <div class="col-md-3">
                <div class="form-group">
                    <label>Status <span class="text-danger">*</span></label>
                    {!! Form::select('status', [
                        'ordered'   => 'Ordered',
                        'delivered' => 'Delivered',
                    ], 'ordered', [
                        'class'    => 'form-control select2',
                        'style'    => 'width:100%',
                        'id'       => 'dn_status',
                        'required' => true,
                    ]) !!}
                </div>
            </div>
        </div>

        @php
            $qty_precision = session('business.quantity_precision', 2);
            $qty_step = $qty_precision > 0 ? number_format(pow(10, -$qty_precision), $qty_precision) : '1';
        @endphp
        {{-- Products Table --}}
        <div class="row" style="margin-top:15px;">
            <div class="col-md-12">
                <table class="table table-hover" id="products_table"
                       style="border-collapse:collapse; border:1px solid #e5e5e5; table-layout:fixed; width:100%;">
                    <colgroup>
                        <col style="width:26%">  {{-- Product --}}
                        <col style="width:11%">  {{-- Ordered Qty --}}
                        <col style="width:12%">  {{-- Remaining Qty --}}
                        <col style="width:16%">  {{-- Deliver Qty (wider for input) --}}
                        <col style="width:8%">   {{-- Unit --}}
                        <col style="width:13%">  {{-- Unit Price --}}
                        <col style="width:14%">  {{-- Subtotal --}}
                    </colgroup>
                    <thead>
                        <tr style="border-bottom:2px solid #e5e5e5;">
                            <th style="padding:10px 12px; font-weight:600; color:#333;">Product</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Ordered Qty</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Remaining Qty</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Deliver Qty</th>
                            <th class="text-center" style="padding:10px 8px; font-weight:600; color:#333;">Unit</th>
                            <th class="text-right"  style="padding:10px 8px; font-weight:600; color:#333;">Unit Price</th>
                            <th class="text-right"  style="padding:10px 8px; font-weight:600; color:#333;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="products_tbody">
                        @isset($so_lines)
                            @foreach($so_lines as $i => $line)
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:10px 12px; vertical-align:middle;">
                                    {{ $line['product_name'] }}
                                    @if(!empty($line['sub_sku']))
                                        <br><small class="text-muted">{{ $line['sub_sku'] }}</small>
                                    @endif
                                    <input type="hidden" name="products[{{ $i }}][product_id]"   value="{{ $line['product_id'] }}">
                                    <input type="hidden" name="products[{{ $i }}][variation_id]" value="{{ $line['variation_id'] }}">
                                    <input type="hidden" name="products[{{ $i }}][sub_unit_id]"  value="{{ $line['sub_unit_id'] }}">
                                    <input type="hidden" name="products[{{ $i }}][so_line_id]"   value="{{ $line['so_line_id'] }}">
                                    <input type="hidden" name="products[{{ $i }}][product_type]" value="{{ $line['product_type'] }}">
                                    <input type="hidden" name="products[{{ $i }}][enable_stock]" value="{{ $line['enable_stock'] }}">
                                    <input type="hidden" name="products[{{ $i }}][unit_price]"   value="{{ $line['unit_price'] }}" class="inp-unit-price">
                                    <input type="hidden" name="products[{{ $i }}][children]"     value="{{ json_encode($line['children']) }}">
                                </td>
                                <td class="text-center" style="padding:10px 8px; vertical-align:middle;">
                                    {{@format_quantity($line['ordered_qty'])}}
                                </td>
                                <td class="text-center" style="padding:10px 8px; vertical-align:middle;">
                                    {{@format_quantity($line['remaining_qty'])}}
                                </td>
                                <td class="text-center" style="padding:6px 8px; vertical-align:middle;">
                                    <input type="number" name="products[{{ $i }}][deliver_qty]"
                                           class="form-control inp-deliver-qty"
                                           style="text-align:center; width:100%; max-width:120px; margin:0 auto; display:block;"
                                           value="{{ $line['deliver_qty'] == floor($line['deliver_qty']) ? (int)$line['deliver_qty'] : $line['deliver_qty'] }}"
                                           min="0" step="{{ $qty_step }}" max="{{ $line['remaining_qty'] }}">
                                </td>
                                <td class="text-center" style="padding:10px 8px; vertical-align:middle;">{{ $line['unit_name'] }}</td>
                                <td class="text-right"  style="padding:10px 8px; vertical-align:middle;">${{ number_format($line['unit_price'], 2) }}</td>
                                <td class="text-right td-subtotal" style="padding:10px 8px; vertical-align:middle;">${{ number_format($line['subtotal'], 2) }}</td>
                            </tr>
                            @endforeach
                        @endisset
                    </tbody>
                    <tfoot>
                        <tr style="border-top:1px solid #e5e5e5;">
                            <td colspan="3" style="padding:10px 8px;"></td>
                            <td colspan="2" class="text-right" style="padding:10px 8px; color:#555;"><strong>Total Delivery Qty</strong></td>
                            <td class="text-right" style="padding:10px 8px;"><strong id="total_qty">0</strong></td>
                            <td style="padding:10px 8px;"></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding:4px 8px; border:none;"></td>
                            <td colspan="2" class="text-right" style="padding:4px 8px; border:none; color:#555;"><strong>Total Amount</strong></td>
                            <td class="text-right" style="padding:4px 8px; border:none;"><strong id="total_amount">$0.00</strong></td>
                            <td style="border:none;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Delivery Note --}}
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Delivery note:</label>
                    <textarea name="delivery_note" class="form-control" rows="3"
                              placeholder="Delivery note...">{{ old('delivery_note', $so->additional_notes ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" id="btn_save">
                    <i class="fa fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-success" id="btn_save_print">
                    <i class="fa fa-print"></i> Save and print
                </button>
            </div>
        </div>

    {!! Form::close() !!}
    </div>{{-- /.box-body --}}
</div>{{-- /.box --}}
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {

    var savePrint = false;
    var qtyPrecision = parseInt($('#__quantity_precision').val()) || 2;
    var qtyStep = qtyPrecision > 0 ? (1 / Math.pow(10, qtyPrecision)).toFixed(qtyPrecision) : '1';

    // Sync location_id to hidden input inside form
    $('#location_id').on('change', function () {
        $('#location_id_hidden').val($(this).val());
    }).trigger('change');

    // Load SOs for selected customer
    $('#customer_id').on('change', function () {
        var cid = $(this).val();
        $('#sales_order_id').empty().append('<option value="">Select Sales Order</option>');
        $('#products_tbody').empty();
        recalc();
        if (!cid) return;
        $.get('{{ url("get-sales-orders") }}/' + cid, function (data) {
            $.each(data, function (i, so) {
                $('#sales_order_id').append('<option value="' + so.id + '">' + so.invoice_no + '</option>');
            });
        });
    });

    // Load products when SO selected
    $('#sales_order_id').on('change', loadSOLines);

    // "Auto fetch data from SO" hint click
    $('#auto_fetch_hint').on('click', function () {
        if ($('#sales_order_id').val()) loadSOLines();
    });

    function loadSOLines() {
        var soId = $('#sales_order_id').val();
        if (!soId) { $('#products_tbody').empty(); recalc(); return; }

        $.get('{{ url("get-sales-order-for-dn") }}/' + soId, function (res) {
            if (!res.success) return;
            var html = '';
            $.each(res.lines, function (i, line) {
                var skuHtml = line.sub_sku ? '<br><small class="text-muted">' + line.sub_sku + '</small>' : '';
                var oQty = parseFloat(line.ordered_qty);
                var rQty = parseFloat(line.remaining_qty);
                var dQty = parseFloat(line.deliver_qty);
                var fmtQty = function(n) { return parseFloat(n).toFixed(qtyPrecision); };
                html +=
                    '<tr style="border-bottom:1px solid #f0f0f0;">' +
                    '<td style="padding:10px 12px; vertical-align:middle;">' +
                        line.product_name + skuHtml +
                        '<input type="hidden" name="products[' + i + '][product_id]"   value="' + line.product_id + '">' +
                        '<input type="hidden" name="products[' + i + '][variation_id]" value="' + line.variation_id + '">' +
                        '<input type="hidden" name="products[' + i + '][sub_unit_id]"  value="' + (line.sub_unit_id || '') + '">' +
                        '<input type="hidden" name="products[' + i + '][so_line_id]"   value="' + (line.so_line_id || '') + '">' +
                        '<input type="hidden" name="products[' + i + '][product_type]" value="' + (line.product_type || 'single') + '">' +
                        '<input type="hidden" name="products[' + i + '][enable_stock]" value="' + (line.enable_stock ? 1 : 0) + '">' +
                        '<input type="hidden" name="products[' + i + '][unit_price]"   value="' + line.unit_price + '" class="inp-unit-price">' +
                        '<input type="hidden" name="products[' + i + '][children]"     value=\'' + JSON.stringify(line.children || []).replace(/'/g, "&#39;") + '\'>' +
                    '</td>' +
                    '<td class="text-center" style="padding:10px 8px; vertical-align:middle;">' + fmtQty(oQty) + '</td>' +
                    '<td class="text-center" style="padding:10px 8px; vertical-align:middle;">' + fmtQty(rQty) + '</td>' +
                    '<td class="text-center" style="padding:6px 8px; vertical-align:middle;">' +
                        '<input type="number" name="products[' + i + '][deliver_qty]" ' +
                               'class="form-control inp-deliver-qty" ' +
                               'style="text-align:center; width:100%; max-width:120px; margin:0 auto; display:block;" ' +
                               'value="' + fmtQty(dQty) + '" min="0" step="' + qtyStep + '" max="' + rQty + '">' +
                    '</td>' +
                    '<td class="text-center" style="padding:10px 8px; vertical-align:middle;">' + (line.unit_name || '') + '</td>' +
                    '<td class="text-right"  style="padding:10px 8px; vertical-align:middle;">$' + parseFloat(line.unit_price).toFixed(2) + '</td>' +
                    '<td class="text-right td-subtotal" style="padding:10px 8px; vertical-align:middle;">$' + parseFloat(line.subtotal).toFixed(2) + '</td>' +
                    '</tr>';
            });
            $('#products_tbody').html(html);
            recalc();
        });
    }

    // Recalculate on qty change
    $(document).on('input', '.inp-deliver-qty', function () {
        var $row  = $(this).closest('tr');
        var qty   = parseFloat($(this).val()) || 0;
        var price = parseFloat($row.find('.inp-unit-price').val()) || 0;
        $row.find('.td-subtotal').text('$' + (qty * price).toFixed(2));
        recalc();
    });

    function recalc() {
        var total = 0, qty = 0;
        $('.td-subtotal').each(function () {
            total += parseFloat($(this).text().replace('$', '')) || 0;
        });
        $('.inp-deliver-qty').each(function () {
            qty += parseFloat($(this).val()) || 0;
        });
        $('#total_amount').text('$' + total.toFixed(2));
        $('#total_qty').text(qty.toFixed(qtyPrecision));
    }

    recalc();

    // Save
    function doSave() {
        var $btn = savePrint ? $('#btn_save_print') : $('#btn_save');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '{{ route("delivery-note.store") }}',
            type: 'POST',
            data: $('#dn_form').serialize(),
            success: function (r) {
                $btn.prop('disabled', false).html(savePrint
                    ? '<i class="fa fa-print"></i> Save and print'
                    : '<i class="fa fa-save"></i> Save');
                if (r.success) {
                    toastr.success('Delivery Note ' + r.invoice_no + ' created successfully!');
                    if (savePrint) {
                        // Same mechanism as clicking Print from the DN index:
                        // fetch receipt JSON → inject into #receipt_section → browser print dialog
                        $.ajax({
                            method: 'GET',
                            url: '{{ url("delivery-note") }}/' + r.id + '/get-receipt',
                            dataType: 'json',
                            success: function (rr) {
                                if (rr.success == 1 && rr.receipt && rr.receipt.html_content) {
                                    $('#receipt_section').html(rr.receipt.html_content);
                                    __currency_convert_recursively($('#receipt_section'));
                                    var oldTitle = document.title;
                                    if (rr.receipt.print_title) {
                                        document.title = rr.receipt.print_title;
                                    }
                                    __print_receipt('receipt_section');
                                    setTimeout(function () { document.title = oldTitle; }, 1200);
                                }
                            }
                        });
                    }
                    setTimeout(function () {
                        window.location = '{{ route("delivery-note.index") }}';
                    }, 1500);
                } else {
                    toastr.error(r.message || 'Error saving delivery note.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(savePrint
                    ? '<i class="fa fa-print"></i> Save and print'
                    : '<i class="fa fa-save"></i> Save');
                var msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message : 'Server error. Please try again.';
                toastr.error(msg);
            }
        });
    }

    // Check DN No duplicate on blur
    var dnNoOriginal = $('#dn_no_display').val();
    $('#dn_no_display').on('blur', function () {
        var val = $.trim($(this).val());
        if (!val || val === dnNoOriginal) {
            $('#dn_no_error').hide().text('');
            return;
        }
        $.get('{{ route("delivery-note.checkNo") }}', { dn_no: val }, function (r) {
            if (r.exists) {
                $('#dn_no_error').text('Delivery Note No. "' + val + '" already exists.').show();
            } else {
                $('#dn_no_error').hide().text('');
            }
        });
    });

    function checkDnNoThenSave() {
        var val = $.trim($('#dn_no_display').val());
        if (!val || val === dnNoOriginal) {
            doSave();
            return;
        }
        $.get('{{ route("delivery-note.checkNo") }}', { dn_no: val }, function (r) {
            if (r.exists) {
                $('#dn_no_error').text('Delivery Note No. "' + val + '" already exists.').show();
                toastr.error('Delivery Note No. "' + val + '" already exists. Please use a different number.');
            } else {
                $('#dn_no_error').hide().text('');
                doSave();
            }
        });
    }

    $('#btn_save').on('click', function () { savePrint = false; checkDnNoThenSave(); });
    $('#btn_save_print').on('click', function () { savePrint = true; checkDnNoThenSave(); });

    // Pre-load SO lines if coming from SO page
    @isset($so)
        loadSOLines();
    @endisset
});
</script>
@endsection
