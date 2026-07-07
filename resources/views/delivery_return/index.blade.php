@extends('layouts.app')
@section('title', 'Delivery Returns')

@section('content')
<section class="content-header no-print">
    <h1>Delivery Returns</h1>
</section>

<section class="content no-print">
    <div class="box box-info collapsed-box" id="filters_box">
        <div class="box-header with-border" style="cursor: pointer;" id="filters_header">
            <h3 class="box-title">@lang('report.filters')</h3>
        </div>
        <div class="box-body" style="display: none;" id="filters_content">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dr_filter_location', __('purchase.business_location') . ':') !!}
                        {!! Form::select('dr_filter_location', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'dr_filter_location']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dr_filter_customer', __('contact.customer') . ':') !!}
                        {!! Form::select('dr_filter_customer', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'dr_filter_customer']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('dr_filter_date', __('report.date_range') . ':') !!}
                        {!! Form::text('dr_filter_date', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'dr_filter_date']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="dr_table">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>Return No.</th>
                        <th>Parent DN No.</th>
                        <th>@lang('sale.customer_name')</th>
                        <th>@lang('sale.location')</th>
                        <th>@lang('sale.status')</th>
                        <th>Stock Status</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

{{-- View Modal --}}
<div class="modal fade" id="viewDRModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="width:90%; max-width:1200px;">
        <div class="modal-content" id="viewDRModalContent"></div>
    </div>
</div>
@endsection

@section('javascript')
<script>
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
    $('#dr_filter_date').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#dr_filter_date').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            drTable.ajax.reload();
        }
    );
    $('#dr_filter_date').on('cancel.daterangepicker', function () {
        $(this).val('');
        drTable.ajax.reload();
    });

    var drTable = $('#dr_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        initComplete: function () {
            var params = new URLSearchParams(window.location.search);
            var autoViewId = params.get('auto_view');
            if (autoViewId) {
                $('#viewDRModalContent').html('<div class="modal-body text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
                $('#viewDRModal').modal('show');
                $.get('{{ url("delivery-return") }}/' + autoViewId + '/view', function (html) {
                    $('#viewDRModalContent').html(html);
                });
            }
        },
        ajax: {
            url: '{{ route("delivery-return.index") }}',
            data: function (d) {
                d.location_id = $('#dr_filter_location').val();
                d.customer_id = $('#dr_filter_customer').val();
                if ($('#dr_filter_date').val()) {
                    var dr = $('#dr_filter_date').data('daterangepicker');
                    d.start_date = dr.startDate.format('YYYY-MM-DD');
                    d.end_date   = dr.endDate.format('YYYY-MM-DD');
                }
            }
        },
        columns: [
            { data: 'action',           name: 'action',           orderable: false, searchable: false },
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'invoice_no',       name: 'invoice_no' },
            {
                data: null, name: 'dn_no', orderable: false, searchable: false,
                render: function (data) {
                    return $(data.action).filter('.dn_no').text() || '—';
                }
            },
            { data: 'customer_name', name: 'customer_name' },
            { data: 'location_name', name: 'location_name' },
            {
                data: null, name: 'status', orderable: false,
                render: function (data) {
                    return $(data.action).filter('.status_label_raw').html() || data.status;
                }
            },
            {
                data: null, name: 'stock_status', orderable: false,
                render: function (data) {
                    return $(data.action).filter('.stock_status_raw').html() || '—';
                }
            },
        ]
    });

    $('#dr_filter_location, #dr_filter_customer').on('change', function () {
        drTable.ajax.reload();
    });

    $(document).on('click', '.btn-view-dr', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#viewDRModalContent').html('<div class="modal-body text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $('#viewDRModal').modal('show');
        $.get('{{ url("delivery-return") }}/' + id + '/view', function (html) {
            $('#viewDRModalContent').html(html);
        });
    });

    $(document).on('click', '.delete-dr', function (e) {
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
                url: '{{ url("delivery-return") }}/' + id,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function (r) {
                    if (r.success) {
                        toastr.success('Deleted successfully.');
                        drTable.ajax.reload();
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
