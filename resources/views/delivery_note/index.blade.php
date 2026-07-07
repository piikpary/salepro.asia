@extends('layouts.app')
@section('title', 'Delivery Notes')

@section('content')

<section class="content-header no-print">
    <h1>Delivery Notes</h1>
</section>

<section class="content no-print">

    {{-- Filters --}}
    <div class="box box-info collapsed-box" id="filters_box">
        <div class="box-header with-border" style="cursor: pointer;" id="filters_header">
            <h3 class="box-title">@lang('report.filters')</h3>
        </div>
        <div class="box-body" style="display: none;" id="filters_content">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dn_filter_location', __('purchase.business_location') . ':') !!}
                        {!! Form::select('dn_filter_location', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'dn_filter_location']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dn_filter_customer', __('contact.customer') . ':') !!}
                        {!! Form::select('dn_filter_customer', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'dn_filter_customer']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dn_filter_status', __('sale.status') . ':') !!}
                        {!! Form::select('dn_filter_status', ['' => __('lang_v1.all'), 'ordered' => 'Ordered', 'delivered' => 'Delivered', 'completed' => 'Completed'], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'dn_filter_status']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dn_filter_date', __('report.date_range') . ':') !!}
                        {!! Form::text('dn_filter_date', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'dn_filter_date']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dn_filter_driver', 'Delivery Person:') !!}
                        {!! Form::select('dn_filter_driver', $drivers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'dn_filter_driver']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="dn_table">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>Delivery Note No.</th>
                        <th>Delivery Return</th>
                        <th>Sales Order</th>
                        <th>Sell Invoice</th>
                        <th>@lang('sale.customer_name')</th>
                        <th>Status</th>
                        <th>Stock Status</th>
                        <th>Invoice Status</th>
                        <th>Delivery Person</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent

</section>

{{-- View DN Modal --}}
<div class="modal fade" id="viewDNModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="width:90%; max-width:1200px;">
        <div class="modal-content" id="viewDNModalContent"></div>
    </div>
</div>


@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function () {

    // Filter toggle
    $('#filters_header').click(function () {
        var $content = $('#filters_content');
        var $box     = $('#filters_box');
        if ($content.is(':visible')) {
            $content.slideUp();
            $box.addClass('collapsed-box');
        } else {
            $content.slideDown();
            $box.removeClass('collapsed-box');
        }
    });

    // Date range picker
    $('#dn_filter_date').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#dn_filter_date').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            dn_table.ajax.reload();
        }
    );
    $('#dn_filter_date').on('cancel.daterangepicker', function () {
        $(this).val('');
        dn_table.ajax.reload();
    });

    var dn_table = $('#dn_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        ajax: {
            url: '{{ route("delivery-note.index") }}',
            data: function (d) {
                d.location_id = $('#dn_filter_location').val();
                d.customer_id = $('#dn_filter_customer').val();
                d.status      = $('#dn_filter_status').val();
                d.driver_id   = $('#dn_filter_driver').val();
                if ($('#dn_filter_date').val()) {
                    var dr = $('#dn_filter_date').data('daterangepicker');
                    d.start_date = dr.startDate.format('YYYY-MM-DD');
                    d.end_date   = dr.endDate.format('YYYY-MM-DD');
                }
            }
        },
        columns: [
            { data: 'action',               name: 'action',              orderable: false, searchable: false },
            { data: 'transaction_date',     name: 'transaction_date',    searchable: false },
            { data: 'invoice_no',           name: 'invoice_no',          searchable: true },
            { data: 'delivery_return_no',   name: 'delivery_return_no',  orderable: false, searchable: false },
            { data: 'so_no',                name: 'so_no',               orderable: false, searchable: true },
            { data: 'sell_invoice_no',      name: 'sell_invoice_no',     orderable: false, searchable: false },
            { data: 'customer_name',        name: 'customer_name',       searchable: false },
            { data: 'status_label',         name: 'status_label',        orderable: false, searchable: false },
            { data: 'stock_status_label',   name: 'stock_status_label',  orderable: false, searchable: false },
            { data: 'invoice_status_label', name: 'invoice_status_label',orderable: false, searchable: false },
            { data: 'delivery_person_label',name: 'delivery_person_label',orderable: false, searchable: false },
            { data: 'final_total',          name: 'final_total',         searchable: false },
        ],
        initComplete: function () {
            var autoViewId = new URLSearchParams(window.location.search).get('auto_view');
            if (autoViewId) {
                $('#viewDNModalContent').html('<div class="modal-body text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
                $('#viewDNModal').modal('show');
                $.get('{{ url("delivery-note") }}/' + autoViewId + '/view', function (html) {
                    $('#viewDNModalContent').html(html);
                });
            }
        }
    });

    // Reload on filter change
    $('#dn_filter_location, #dn_filter_customer, #dn_filter_status, #dn_filter_driver').on('change', function () {
        dn_table.ajax.reload();
    });

    // View modal
    $(document).on('click', '.btn-view-dn', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#viewDNModalContent').html('<div class="modal-body text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $('#viewDNModal').modal('show');
        $.get('{{ url("delivery-note") }}/' + id + '/view', function (html) {
            $('#viewDNModalContent').html(html);
        });
    });

    // Delete
    $(document).on('click', '.delete-dn', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function (confirmed) {
            if (!confirmed) return;
            $.ajax({
                url: '{{ url("delivery-note") }}/' + id,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function (r) {
                    if (r.success) {
                        toastr.success('Deleted successfully.');
                        dn_table.ajax.reload();
                    } else {
                        toastr.error(r.message);
                    }
                }
            });
        });
    });
});
</script>
@endsection
