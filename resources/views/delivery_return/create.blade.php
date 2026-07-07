@extends('layouts.app')
@section('title', 'Add Delivery Return')

@section('css')
<style>
/* Remove spinner arrows from all qty inputs */
#dr_products_table input[type=number]::-webkit-outer-spin-button,
#dr_products_table input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}
#dr_products_table input[type=number] { -moz-appearance: textfield !important; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1>Add Delivery Return</h1>
</section>

<section class="content">
<div class="box box-primary">
    <div class="box-header with-border" style="background:#fff7e6; border-left:4px solid #f0ad4e; padding:10px 15px;">
        <h4 style="margin:0; color:#8a6d3b;"><i class="fa fa-link"></i> Parent Delivery Note Reference</h4>
    </div>
    <div class="box-body">
    {!! Form::open(['route' => 'delivery-return.store', 'method' => 'POST', 'id' => 'dr_form']) !!}
        <input type="hidden" name="dn_id"       id="dn_id"            value="{{ $dn->id ?? '' }}">
        <input type="hidden" name="customer_id" id="customer_id_hidden" value="{{ $dn->contact_id ?? '' }}">
        <input type="hidden" name="location_id" id="location_id_hidden" value="{{ $dn->location_id ?? '' }}">

        {{-- Row 1: DN No | Customer | Sales Order --}}
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Delivery Note No. <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        <input type="text" id="dn_no_search" class="form-control"
                               value="{{ $dn->invoice_no ?? '' }}"
                               placeholder="Search DN No...">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-warning" id="btn_check_dn">
                                <i class="fa fa-check"></i> Check
                            </button>
                        </span>
                    </div>
                    <span id="dn_check_msg" class="text-danger" style="display:none;font-size:12px;"></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Customer:</label>
                    <input type="text" id="customer_display" class="form-control"
                           value="{{ $dn->customer_name ?? '' }}" readonly style="background:#f9f9f9;">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Sales Order:</label>
                    <input type="text" id="so_display" class="form-control"
                           value="{{ $so_no ?? '' }}" readonly style="background:#f9f9f9;">
                </div>
            </div>
        </div>

        {{-- Row 2: Return No | Date | Status --}}
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Return No.:</label>
                    <input type="text" name="return_no" class="form-control"
                           placeholder="Leave blank to auto generate">
                    <small class="text-muted">Leave blank to auto generate</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Date: <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        <input type="text" name="return_date" class="form-control"
                               value="{{ \Carbon\Carbon::now()->format('m/d/Y H:i') }}" required>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Status: <span class="text-danger">*</span></label>
                    {!! Form::select('status', ['pending' => 'Pending', 'completed' => 'Completed'], 'pending',
                        ['class' => 'form-control select2', 'style' => 'width:100%', 'required']) !!}
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
                <table class="table table-hover" id="dr_products_table"
                       style="border-collapse:collapse; border:1px solid #e5e5e5; table-layout:fixed; width:100%;">
                    <colgroup>
                        <col style="width:4%">   {{-- # --}}
                        <col style="width:32%">  {{-- Product Name --}}
                        <col style="width:16%">  {{-- Delivered Qty --}}
                        <col style="width:16%">  {{-- Return Quantity --}}
                        <col style="width:16%">  {{-- Good Stock --}}
                        <col style="width:16%">  {{-- Damaged --}}
                    </colgroup>
                    <thead style="background:#5cb85c; color:#fff;">
                        <tr>
                            <th style="padding:10px 8px; text-align:center;">#</th>
                            <th style="padding:10px 12px; font-weight:600;">Product Name</th>
                            <th style="padding:10px 8px; font-weight:600; text-align:center;">Delivered Qty</th>
                            <th style="padding:10px 8px; font-weight:600; text-align:center;">Return Quantity</th>
                            <th style="padding:10px 8px; font-weight:600; text-align:center;">Good Stock</th>
                            <th style="padding:10px 8px; font-weight:600; text-align:center;">Damaged</th>
                        </tr>
                    </thead>
                    <tbody id="dr_products_tbody">
                        @isset($lines)
                            @foreach($lines as $i => $line)
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:10px 8px; text-align:center; vertical-align:middle;">{{ $i+1 }}</td>
                                <td style="padding:10px 12px; vertical-align:middle;">
                                    {{ $line->product_name }}{{ ($line->variation_name ?? '') !== 'DUMMY' ? ' - '.($line->variation_name ?? '') : '' }}
                                    @if(!empty($line->sub_sku ?? null))
                                        <br><small class="text-muted">{{ $line->sub_sku }}</small>
                                    @endif
                                    <input type="hidden" name="products[{{ $i }}][product_id]"  value="{{ $line->product_id }}">
                                    <input type="hidden" name="products[{{ $i }}][variation_id]" value="{{ $line->variation_id }}">
                                    <input type="hidden" name="products[{{ $i }}][sub_unit_id]"  value="{{ $line->sub_unit_id }}">
                                    <br><small class="text-muted">{{ $line->unit_name }}</small>
                                </td>
                                <td style="padding:10px 8px; text-align:center; vertical-align:middle;">
                                    {{@format_quantity($line->quantity)}} {{ $line->unit_name }}
                                </td>
                                <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                    <input type="number" name="products[{{ $i }}][return_qty]"
                                           class="form-control inp-return-qty"
                                           style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                           value="0" min="0" step="{{ $qty_step }}" max="{{ $line->quantity }}">
                                </td>
                                <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                    <input type="number" name="products[{{ $i }}][good_stock]"
                                           class="form-control inp-good-stock"
                                           style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                           value="0" min="0" step="{{ $qty_step }}">
                                </td>
                                <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                    <input type="number" name="products[{{ $i }}][damaged]"
                                           class="form-control inp-damaged"
                                           style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                           value="0" min="0" step="{{ $qty_step }}">
                                </td>
                            </tr>
                            @endforeach
                        @endisset
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Return Note --}}
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Return note:</label>
                    <textarea name="return_note" class="form-control" rows="3"
                              placeholder="Return note..."></textarea>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-default" onclick="history.back()">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn_save_dr">
                    <i class="fa fa-save"></i> Save
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

    var qtyPrecision = parseInt($('#__quantity_precision').val()) || 2;
    var qtyStep = qtyPrecision > 0 ? (1 / Math.pow(10, qtyPrecision)).toFixed(qtyPrecision) : '1';

    // Check DN number manually
    $('#btn_check_dn').on('click', function () {
        var dn_no = $('#dn_no_search').val().trim();
        if (!dn_no) return;
        $.get('{{ url("check-delivery-note-no") }}', { dn_no: dn_no }, function (res) {
            if (!res.success) {
                $('#dn_check_msg').text(res.message).show();
                $('#dn_id').val('');
                $('#customer_id_hidden').val('');
                $('#location_id_hidden').val('');
                $('#dr_products_tbody').empty();
                return;
            }
            $('#dn_check_msg').hide();
            $('#dn_id').val(res.dn_id);
            $('#customer_id_hidden').val(res.customer_id);
            $('#location_id_hidden').val(res.location_id);
            $('#customer_display').val(res.customer_name);
            $('#so_display').val(res.so_no || '');
            renderLines(res.lines);
        });
    });

    function renderLines(lines) {
        var html = '';
        $.each(lines, function (i, line) {
            var qty = parseFloat(line.delivered_qty);
            var qtyFmt = qty.toFixed(qtyPrecision);
            var skuHtml = line.sub_sku ? '<br><small class="text-muted">' + line.sub_sku + '</small>' : '';
            html +=
                '<tr style="border-bottom:1px solid #f0f0f0;">' +
                '<td style="padding:10px 8px; text-align:center; vertical-align:middle;">' + (i+1) + '</td>' +
                '<td style="padding:10px 12px; vertical-align:middle;">' +
                    line.product_name + skuHtml +
                    '<input type="hidden" name="products[' + i + '][product_id]"  value="' + line.product_id + '">' +
                    '<input type="hidden" name="products[' + i + '][variation_id]" value="' + line.variation_id + '">' +
                    '<input type="hidden" name="products[' + i + '][sub_unit_id]"  value="' + (line.sub_unit_id || '') + '">' +
                    '<br><small class="text-muted">' + (line.unit_name || '') + '</small>' +
                '</td>' +
                '<td style="padding:10px 8px; text-align:center; vertical-align:middle;">' + qtyFmt + ' ' + (line.unit_name || '') + '</td>' +
                '<td style="padding:6px 8px; text-align:center; vertical-align:middle;">' +
                    '<input type="number" name="products[' + i + '][return_qty]" class="form-control inp-return-qty" ' +
                    'style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;" ' +
                    'value="0" min="0" step="' + qtyStep + '" max="' + line.delivered_qty + '">' +
                '</td>' +
                '<td style="padding:6px 8px; text-align:center; vertical-align:middle;">' +
                    '<input type="number" name="products[' + i + '][good_stock]" class="form-control inp-good-stock" ' +
                    'style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;" ' +
                    'value="0" min="0" step="' + qtyStep + '">' +
                '</td>' +
                '<td style="padding:6px 8px; text-align:center; vertical-align:middle;">' +
                    '<input type="number" name="products[' + i + '][damaged]" class="form-control inp-damaged" ' +
                    'style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;" ' +
                    'value="0" min="0" step="' + qtyStep + '">' +
                '</td>' +
                '</tr>';
        });
        $('#dr_products_tbody').html(html);
    }

    // Auto-cap good+damaged to return qty
    $(document).on('input', '.inp-return-qty', function () {
        var $row    = $(this).closest('tr');
        var qty     = parseFloat($(this).val()) || 0;
        var good    = parseFloat($row.find('.inp-good-stock').val()) || 0;
        var damaged = parseFloat($row.find('.inp-damaged').val()) || 0;
        if (good + damaged > qty) {
            $row.find('.inp-good-stock').val(qty);
            $row.find('.inp-damaged').val(0);
        }
    });

    // Save
    $('#btn_save_dr').on('click', function () {
        if (!$('#dn_id').val()) {
            toastr.error('Please check a valid Delivery Note first.');
            return;
        }
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '{{ route("delivery-return.store") }}',
            type: 'POST',
            data: $('#dr_form').serialize(),
            success: function (r) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                if (r.success) {
                    toastr.success('Delivery Return ' + r.invoice_no + ' created!');
                    setTimeout(function () { window.location = '{{ route("delivery-return.index") }}'; }, 1200);
                } else {
                    toastr.error(r.message || 'Error saving.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error.';
                toastr.error(msg);
            }
        });
    });
});
</script>
@endsection
