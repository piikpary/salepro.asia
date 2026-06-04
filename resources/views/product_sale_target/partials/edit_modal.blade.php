@php
    $update_url = action([\App\Http\Controllers\ProductSaleTargetController::class, 'update'], [$target->id]);
@endphp

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal">&times;</button>
    <h4 class="modal-title">
        Edit Target: {{ $target->user->first_name ?? $target->user->username }}
    </h4>
</div>

<form id="edit-target-form" action="{{ $update_url }}" method="POST">
    @csrf
    @method('PUT')

    <div class="modal-body">

        {{-- Period --}}
        <div class="form-group">
            <label style="font-weight:600; font-size:12px; color:#555;">
                <i class="fa fa-calendar"></i> Target Period:
            </label>
            <input type="text" id="edit-date-range" class="form-control" readonly
                style="background:#fff; cursor:pointer;">
            <input type="hidden" id="edit-start-date" name="start_date" value="{{ $target->start_date }}">
            <input type="hidden" id="edit-end-date"   name="end_date"   value="{{ $target->end_date }}">
        </div>

        {{-- Add product --}}
        <div class="form-group">
            <label style="font-weight:600; font-size:12px; color:#555;">
                <i class="fa fa-plus-circle"></i> Add Product:
            </label>
            <select id="modal-product-search" class="form-control select2" style="width:100%;"></select>
        </div>

        <table class="table table-bordered table-sm" id="edit-target-table">
            <thead>
                <tr>
                    <th>Product Details</th>
                    <th style="width:140px; text-align:center;">Target Qty</th>
                    <th style="width:60px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($target->details as $detail)
                    <tr id="detail-row-{{ $detail->id }}">
                        <td>
                            <strong>{{ optional($detail->product)->name ?? '-' }}</strong>
                            @if(!empty(optional($detail->variation)->sub_sku))
                                <br><small class="text-muted">{{ $detail->variation->sub_sku }}</small>
                            @endif
                        </td>
                        <td>
                            <input type="number"
                                name="details[{{ $detail->id }}]"
                                class="form-control input-sm text-center"
                                value="{{ $detail->target_qty }}"
                                min="0">
                        </td>
                        <td class="text-center">
                            <button type="button"
                                class="btn-delete-detail"
                                data-id="{{ $detail->id }}"
                                data-url="{{ action([\App\Http\Controllers\ProductSaleTargetController::class, 'destroyDetail'], [$detail->id]) }}"
                                style="background:none; border:none; color:#e74c3c; font-size:20px; cursor:pointer; opacity:0.85;"
                                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" id="btn-save-edit-target" class="btn btn-success">
            <i class="fa fa-save"></i> Save Changes
        </button>
    </div>
</form>

<style>
#edit-target-table input[type=number]::-webkit-inner-spin-button,
#edit-target-table input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
#edit-target-table input[type=number] { -moz-appearance: textfield; }
</style>

<script>
$(document).ready(function () {

    // ---- Daterangepicker for period ----
    var startDate = moment('{{ $target->start_date }}');
    var endDate   = moment('{{ $target->end_date }}');

    $('#edit-date-range').daterangepicker(
        $.extend(true, {}, dateRangeSettings, {
            startDate: startDate,
            endDate:   endDate,
        }),
        function (start, end) {
            $('#edit-start-date').val(start.format('YYYY-MM-DD'));
            $('#edit-end-date').val(end.format('YYYY-MM-DD'));
            $('#edit-date-range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        }
    );
    $('#edit-date-range').val(startDate.format(moment_date_format) + ' ~ ' + endDate.format(moment_date_format));

    // ---- Select2 AJAX: add product ----
    $('#modal-product-search').select2({
        placeholder: 'Search product to add...',
        allowClear: true,
        minimumInputLength: 1,
        dropdownParent: $('.sale_target_modal'),
        ajax: {
            url: '{{ route("product-sale-targets.search-products") }}',
            dataType: 'json',
            delay: 300,
            data: function (p) { return { q: p.term }; },
            processResults: function (d) { return { results: d.results }; }
        }
    }).on('select2:select', function (e) {
        var data = e.params.data;

        if ($('#edit-target-table tbody tr[data-variation-id="' + data.id + '"]').length) {
            toastr.warning('Product already added.');
            $(this).val(null).trigger('change');
            return;
        }

        var row = '<tr data-variation-id="' + data.id + '">'
            + '<td><strong>' + data.text + '</strong></td>'
            + '<td><input type="number" name="new_qty[' + data.id + ']" class="form-control input-sm text-center" value="0" min="0">'
            + '<input type="hidden" name="new_variation_ids[]" value="' + data.id + '"></td>'
            + '<td class="text-center">'
            + '<button type="button" class="btn-remove-new-row" style="background:none;border:none;color:#e74c3c;font-size:20px;cursor:pointer;opacity:0.85;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.85\'">'
            + '<i class="fa fa-times-circle"></i></button></td>'
            + '</tr>';
        $('#edit-target-table tbody').append(row);
        $(this).val(null).trigger('change');
    });

    // ---- Remove new row ----
    $(document).on('click', '.btn-remove-new-row', function () {
        $(this).closest('tr').remove();
    });

    // ---- Delete existing detail ----
    $(document).on('click', '.btn-delete-detail', function () {
        var url = $(this).data('url');
        var row = $(this).closest('tr');
        swal({ title: 'Remove product?', icon: 'warning', buttons: true, dangerMode: true })
        .then(function (ok) {
            if (ok) {
                $.ajax({
                    url: url, method: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        if (res.success) { row.remove(); toastr.success(res.msg); }
                        else { toastr.error(res.msg); }
                    }
                });
            }
        });
    });

});
</script>
