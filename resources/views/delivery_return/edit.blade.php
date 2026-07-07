@extends('layouts.app')
@section('title', 'Edit Delivery Return - ' . $dr->invoice_no)

@section('css')
<style>
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
    <h1>Edit Delivery Return — <strong>{{ $dr->invoice_no }}</strong></h1>
</section>

<section class="content">
<div class="box box-primary">
    <div class="box-header with-border" style="background:#fff7e6; border-left:4px solid #f0ad4e; padding:10px 15px;">
        <h4 style="margin:0; color:#8a6d3b;"><i class="fa fa-link"></i> Parent Delivery Note Reference</h4>
    </div>
    <div class="box-body">
    {!! Form::open(['route' => ['delivery-return.update', $dr->id], 'method' => 'PUT', 'id' => 'dr_edit_form']) !!}
    <input type="hidden" name="location_id" value="{{ $dr->location_id }}">

        {{-- Row 1: DN No | Customer | Return No | Date --}}
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Note No.</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        <input type="text" class="form-control" value="{{ $dn->invoice_no ?? '—' }}" readonly style="background:#f9f9f9;">
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer</label>
                    <input type="text" class="form-control" value="{{ $dr->customer_name }}" readonly style="background:#f9f9f9;">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Return No.</label>
                    <input type="text" class="form-control" value="{{ $dr->invoice_no }}" readonly style="background:#f9f9f9;">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Date</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($dr->transaction_date)->format('m/d/Y H:i') }}" readonly style="background:#f9f9f9;">
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Status --}}
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Status <span class="text-danger">*</span></label>
                    {!! Form::select('status', ['pending' => 'Pending', 'completed' => 'Completed'], $dr->status, [
                        'class'    => 'form-control select2',
                        'style'    => 'width:100%',
                        'required' => true,
                    ]) !!}
                </div>
            </div>
        </div>

        @php
            $qty_precision = (int) session('business.quantity_precision', 2);
            $qty_step = $qty_precision > 0 ? number_format(pow(10, -$qty_precision), $qty_precision) : '1';
        @endphp
        {{-- Products Table --}}
        <div class="row" style="margin-top:15px;">
            <div class="col-md-12">
                <table class="table table-hover" id="dr_products_table"
                       style="border-collapse:collapse; border:1px solid #e5e5e5; table-layout:fixed; width:100%;">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:32%">
                        <col style="width:16%">
                        <col style="width:16%">
                        <col style="width:16%">
                        <col style="width:16%">
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
                    <tbody>
                        @foreach($dr_lines as $i => $line)
                        @php
                            $delivered_qty = 0;
                            if ($dr->delivery_note_id) {
                                $dn_line = \Illuminate\Support\Facades\DB::table('transaction_sell_lines')
                                    ->where('transaction_id', $dr->delivery_note_id)
                                    ->where('product_id', $line->product_id)
                                    ->where('variation_id', $line->variation_id)
                                    ->value('quantity');
                                $delivered_qty = (float) ($dn_line ?? 0);
                            }
                            $ret_qty  = (float) ($line->quantity_returned ?? 0);
                            $good_qty = (float) ($line->good_stock_qty ?? 0);
                            $dmg_qty  = (float) ($line->damaged_qty ?? 0);
                            // Smart format: preserve actual decimal digits even when precision=0
                            $fmt = function($n) use ($qty_precision) {
                                $n = (float)$n; $p = $qty_precision;
                                if ($n != floor($n)) {
                                    $str = rtrim(rtrim(sprintf('%.10f', $n), '0'), '.');
                                    $dot = strpos($str, '.'); $actual = ($dot !== false) ? (strlen($str) - $dot - 1) : 0;
                                    $p = max($p, $actual);
                                }
                                return number_format($n, $p, '.', '');
                            };
                        @endphp
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:10px 8px; text-align:center; vertical-align:middle;">{{ $i+1 }}</td>
                            <td style="padding:10px 12px; vertical-align:middle;">
                                {{ $line->product_name }}{{ ($line->variation_name ?? '') !== 'DUMMY' ? ' - '.($line->variation_name ?? '') : '' }}
                                @if(!empty($line->sub_sku ?? null))
                                    <br><small class="text-muted">{{ $line->sub_sku }}</small>
                                @endif
                                <br><small class="text-muted">{{ $line->unit_name }}</small>
                                <input type="hidden" name="products[{{ $i }}][product_id]"  value="{{ $line->product_id }}">
                                <input type="hidden" name="products[{{ $i }}][variation_id]" value="{{ $line->variation_id }}">
                                <input type="hidden" name="products[{{ $i }}][sub_unit_id]"  value="{{ $line->sub_unit_id }}">
                            </td>
                            <td style="padding:10px 8px; text-align:center; vertical-align:middle;">
                                {{ $fmt($delivered_qty) }} {{ $line->unit_name }}
                            </td>
                            <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                <input type="number" name="products[{{ $i }}][return_qty]"
                                       class="form-control"
                                       style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                       value="{{ $fmt($ret_qty) }}" min="0" step="{{ $qty_step }}">
                            </td>
                            <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                <input type="number" name="products[{{ $i }}][good_stock]"
                                       class="form-control"
                                       style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                       value="{{ $fmt($good_qty) }}" min="0" step="{{ $qty_step }}">
                            </td>
                            <td style="padding:6px 8px; vertical-align:middle; text-align:center;">
                                <input type="number" name="products[{{ $i }}][damaged]"
                                       class="form-control"
                                       style="text-align:center; width:100%; max-width:100px; margin:0 auto; display:block;"
                                       value="{{ $fmt($dmg_qty) }}" min="0" step="{{ $qty_step }}">
                            </td>
                        </tr>
                        @endforeach
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
                              placeholder="Return note...">{{ $dr->additional_notes }}</textarea>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" id="btn_update_dr">
                    <i class="fa fa-save"></i> Update
                </button>
                <a href="{{ route('delivery-return.index') }}" class="btn btn-default">Cancel</a>
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
    $('#btn_update_dr').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        $.ajax({
            url: '{{ route("delivery-return.update", $dr->id) }}',
            type: 'POST',
            data: $('#dr_edit_form').serialize() + '&_method=PUT',
            success: function (r) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update');
                if (r.success) {
                    toastr.success('Delivery Return updated!');
                    setTimeout(function () { window.location = '{{ route("delivery-return.index") }}'; }, 1200);
                } else {
                    toastr.error(r.message || 'Error.');
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
