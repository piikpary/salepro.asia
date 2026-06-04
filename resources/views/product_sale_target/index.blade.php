@extends('layouts.app')
@section('title', 'Sale Target Setting')

@section('content')
<section class="content-header">
    <h1>Sale Target Setting</h1>
</section>

<section class="content">

    {{-- ===== CREATE / ASSIGN FORM (hidden by default) ===== --}}
    <div id="create-target-panel" style="display:none;">
        <div class="box box-primary">
            <div class="box-header with-border" style="padding-bottom:12px;">
                <h3 class="box-title"><i class="fa fa-plus-circle"></i> Assign Targets</h3>
            </div>
            <div class="box-body">

                {{-- Controls bar --}}
                <div class="st-controls-bar">

                    {{-- Target Period --}}
                    <div class="st-field">
                        <label class="st-label"><i class="fa fa-calendar"></i> Target Period:</label>
                        <input type="text" id="target_date_range" class="form-control" placeholder="Select date range" readonly>
                    </div>

                    {{-- Sales --}}
                    <div class="st-field">
                        <label class="st-label"><i class="fa fa-users"></i> Sales:</label>
                        <select id="user_ids" class="form-control select2" multiple="multiple" style="width:100%;">
                            @foreach($users as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Products --}}
                    <div class="st-field">
                        <label class="st-label">
                            <i class="fa fa-box"></i> Products:
                            <span id="product-count-badge" style="display:none; background:#3c8dbc; color:#fff; border-radius:10px; padding:1px 7px; font-size:11px; font-weight:600; margin-left:4px;"></span>
                        </label>
                        <select id="variation_ids" class="form-control select2" multiple="multiple" style="width:100%;"></select>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="st-actions">
                        <button type="button" id="btn-generate-grid" class="btn btn-primary st-btn">
                            <i class="fa fa-th"></i> Generate Grid
                        </button>
                        <button type="button" id="btn-save-target" class="btn btn-success st-btn" style="display:none;">
                            <i class="fa fa-save"></i> Save Target
                        </button>
                        <button type="button" id="btn-cancel-create" class="btn st-btn st-btn-cancel">
                            Cancel
                        </button>
                    </div>

                </div>

                {{-- Grid output --}}
                <div id="sale-target-grid-container"></div>
            </div>
        </div>
    </div>

    {{-- ===== LIST TABLE (default view) ===== --}}
    <div class="box box-primary" id="list-target-panel">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-list"></i> Sale Target List</h3>
            <div class="box-tools pull-right">
                <button type="button" id="btn-import-target" class="btn btn-success btn-sm" style="margin-right:4px;">
                    <i class="fa fa-file-excel-o"></i> Import Excel
                </button>
                <button type="button" id="btn-show-create" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Add
                </button>
            </div>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="sale_targets_table">
                    <thead>
                        <tr>
                            <th>Salesperson</th>
                            <th>Target Period</th>
                            <th>Product Targets (Qty)</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

</section>

{{-- Edit Modal --}}
<div class="modal fade sale_target_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content"></div>
    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade sale_target_import_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content"></div>
    </div>
</div>

<style>
/* ── Controls bar ───────────────────────────────────── */
.st-controls-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
    margin-bottom: 18px;
}
.st-field {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-width: 160px;
}
.st-label {
    font-weight: 600;
    font-size: 12px;
    color: #555;
    margin-bottom: 5px;
    white-space: nowrap;
}

/* ── Action buttons group ───────────────────────────── */
.st-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding-bottom: 1px;
}
.st-btn {
    white-space: nowrap;
    height: 34px;
    padding: 0 14px;
    font-size: 13px;
    border-radius: 4px;
}

/* Cancel — ghost/outlined style */
.st-btn-cancel {
    background: transparent;
    color: #666;
    border: 1px solid #ccc;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.st-btn-cancel:hover {
    background: #f5f5f5;
    color: #333;
    border-color: #aaa;
}

/* ── Sales & Products select2: fixed single-row height ─ */
#user_ids ~ .select2-container .select2-selection--multiple,
#variation_ids ~ .select2-container .select2-selection--multiple {
    height: 34px !important;
    overflow: hidden;
    box-sizing: border-box;
}
#user_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered,
#variation_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    height: 26px;
    align-items: center;
    padding-bottom: 2px;
    scrollbar-width: thin;
    scrollbar-color: #aab7c4 #f1f1f1;
}
#user_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar,
#variation_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar {
    height: 3px;
}
#user_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-track,
#variation_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}
#user_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-thumb,
#variation_ids ~ .select2-container .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-thumb {
    background: #aab7c4;
    border-radius: 2px;
}
#user_ids ~ .select2-container .select2-selection--multiple .select2-selection__choice,
#variation_ids ~ .select2-container .select2-selection--multiple .select2-selection__choice {
    flex-shrink: 0;
    margin-top: 3px !important;
}
/* Hide the allowClear × button on Sales & Products */
#user_ids ~ .select2-container .select2-selection__clear,
#variation_ids ~ .select2-container .select2-selection__clear {
    display: none !important;
}

/* ── Remove spinner arrows ──────────────────────────── */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] { -moz-appearance: textfield; }

/* ── Grid table ─────────────────────────────────────── */
#grid-table thead th {
    background-color: #f4f6f9;
    color: #333;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
    border-bottom: 2px solid #dee2e6;
}
#grid-table thead th:first-child { text-align: left; }
#grid-table tbody tr:hover        { background-color: #f0f4ff; }
#grid-table tfoot td              { background-color: #f4f6f9; font-weight: 700; }

.target-qty-input {
    border: 1px solid #c8d0da;
    border-radius: 4px;
    text-align: center;
    font-size: 14px;
    width: 80px !important;
    margin: auto;
    padding: 4px 6px;
    transition: border-color 0.2s;
}
.target-qty-input:focus {
    border-color: #3c8dbc;
    outline: none;
    box-shadow: 0 0 0 2px rgba(60,141,188,0.15);
}
.salesperson-cell strong { font-size: 14px; color: #222; }
.salesperson-cell small  { color: #888; font-size: 12px; }
</style>
@endsection

@section('javascript')
<script>
$(document).ready(function () {

    // ---- Sales select2 ----
    $('#user_ids').select2({
        placeholder: 'Select salesperson...',
        allowClear: true,
        width: '100%',
    });

    $('#variation_ids').select2({
        placeholder: 'Search product...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("product-sale-targets.search-products") }}',
            dataType: 'json',
            delay: 300,
            data: function (p) { return { q: p.term }; },
            processResults: function (d) { return { results: d.results }; },
            cache: true
        }
    });

    // ---- Sales & Products select2: mouse wheel → horizontal scroll ----
    $(document).on('wheel',
        '#user_ids ~ .select2-container .select2-selection__rendered, ' +
        '#variation_ids ~ .select2-container .select2-selection__rendered',
        function (e) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY || e.originalEvent.deltaX;
        }
    );

    // ---- Products count badge ----
    function updateProductCountBadge() {
        var count = ($('#variation_ids').val() || []).length;
        if (count > 0) {
            $('#product-count-badge').text(count).show();
        } else {
            $('#product-count-badge').hide();
        }
    }
    $('#variation_ids').on('select2:select select2:unselect', updateProductCountBadge);

    // ---- Remove product column from grid when deselected ----
    $('#variation_ids').on('select2:unselect', function (e) {
        var vid = e.params.data.id;
        var $th = $('#grid-table thead th[data-variation-id="' + vid + '"]');
        if (!$th.length) return;
        var colIndex = $th.index();
        $th.remove();
        $('#grid-table tbody tr').each(function () {
            $(this).find('td').eq(colIndex).remove();
        });
        $('#grid-table tfoot tr td').eq(colIndex).remove();
        recalcTotals();
    });

    // ---- DataTable: load on page ready ----
    $('#sale_targets_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("product-sale-targets.assigned-list") }}',
            data: function (d) { return d; }
        },
        columns: [
            { data: 'salesperson_name', name: 'salesperson_name' },
            { data: 'target_period',    name: 'target_period',   orderable: false },
            { data: 'product_targets',  name: 'product_targets', orderable: false },
            { data: 'status_badge',     name: 'status',          orderable: false },
            { data: 'action',           name: 'action',          orderable: false, searchable: false },
        ],
        columnDefs: [{ targets: [3, 4], className: 'text-center' }]
    });

    // ---- Show create panel ----
    $('#btn-show-create').on('click', function () {
        // Clear previous selections
        $('#user_ids').val(null).trigger('change');
        $('#variation_ids').val(null).trigger('change');
        updateProductCountBadge();
        $('#sale-target-grid-container').html('');
        $('#btn-save-target').hide();

        $('#create-target-panel').slideDown(200);
        $('#list-target-panel').slideUp(200);

        // Init daterangepicker (once)
        if (!$('#target_date_range').data('daterangepicker')) {
            $('#target_date_range').daterangepicker(
                $.extend(true, {}, dateRangeSettings, {
                    startDate: moment().startOf('month'),
                    endDate:   moment().endOf('month'),
                }),
                function (start, end) {
                    $('#target_date_range').val(
                        start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                    );
                }
            );
            // Set default display value
            var drp = $('#target_date_range').data('daterangepicker');
            $('#target_date_range').val(
                drp.startDate.format(moment_date_format) + ' ~ ' + drp.endDate.format(moment_date_format)
            );
        }

    });

    // ---- Cancel create ----
    $('#btn-cancel-create').on('click', function () {
        $('#create-target-panel').slideUp(200);
        $('#list-target-panel').slideDown(200);
        $('#sale-target-grid-container').html('');
        $('#btn-save-target').hide();
    });

    // ---- Generate Grid ----
    $('#btn-generate-grid').on('click', function () {
        var user_ids     = $('#user_ids').val();
        var variation_ids = $('#variation_ids').val();
        var drp           = $('#target_date_range').data('daterangepicker');
        var start_date    = drp ? drp.startDate.format('YYYY-MM-DD') : '';
        var end_date      = drp ? drp.endDate.format('YYYY-MM-DD')   : '';

        if (!user_ids || !user_ids.length)           { toastr.error('Please select at least one salesperson.'); return; }
        if (!variation_ids || !variation_ids.length) { toastr.error('Please select at least one product.');     return; }
        if (!start_date || !end_date)                { toastr.error('Please select the target period.');        return; }

        $.ajax({
            url: '{{ route("product-sale-targets.generate-grid") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', user_ids: user_ids, variation_ids: variation_ids, start_date: start_date, end_date: end_date },
            success: function (res) {
                if (res.success) {
                    $('#sale-target-grid-container').html(res.html);
                    $('#btn-save-target').show();
                } else {
                    toastr.error(res.msg);
                }
            },
            error: function () { toastr.error('Something went wrong.'); }
        });
    });

    // ---- Save Target ----
    $('#btn-save-target').on('click', function () {
        var targets = {};
        $('input.target-qty-input').each(function () {
            var uid = $(this).data('user-id');
            var vid = $(this).data('variation-id');
            if (!targets[uid]) targets[uid] = {};
            targets[uid][vid] = $(this).val() || 0;
        });

        // Validate: each salesperson must have at least one qty > 0
        var valid = true;
        $.each(targets, function (uid, variations) {
            var hasQty = false;
            $.each(variations, function (vid, qty) {
                if (parseFloat(qty) > 0) { hasQty = true; return false; }
            });
            if (!hasQty) { valid = false; return false; }
        });
        if (!valid) {
            toastr.error('Please fill in at least one product quantity (> 0) before saving.');
            return;
        }

        $.ajax({
            url: '{{ route("product-sale-targets.store") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', start_date: $('#grid_start_date').val(), end_date: $('#grid_end_date').val(), targets: targets },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.msg);
                    $('#btn-cancel-create').trigger('click');
                    $('#sale_targets_table').DataTable().ajax.reload();
                } else {
                    toastr.error(res.msg);
                }
            },
            error: function () { toastr.error('Something went wrong.'); }
        });
    });

    // ---- Remove grid row ----
    $(document).on('click', '.btn-remove-grid-row', function () {
        $(this).closest('tr').remove();
        recalcTotals();
    });

    // ---- Recalc totals ----
    $(document).on('input', '.target-qty-input', function () { recalcTotals(); });

    function recalcTotals() {
        $('tr.grid-row').each(function () {
            var t = 0;
            $(this).find('.target-qty-input').each(function () { t += parseFloat($(this).val()) || 0; });
            $(this).find('.row-total').text(t);
        });
        $('#grid-table thead tr th.variation-th').each(function (i) {
            var t = 0;
            $('tr.grid-row').each(function () { t += parseFloat($(this).find('.target-qty-input').eq(i).val()) || 0; });
            $('#grid-table tfoot tr td.col-total').eq(i).text(t);
        });
        var grand = 0;
        $('tr.grid-row').each(function () { grand += parseFloat($(this).find('.row-total').text()) || 0; });
        $('#grid-table tfoot tr td.foot-total').text(grand);
    }

    // ---- Open Import Modal ----
    $('#btn-import-target').on('click', function () {
        $.get('{{ route("product-sale-targets.import-modal") }}', function (result) {
            $('.sale_target_import_modal').find('.modal-content').html(result);
            $('.sale_target_import_modal').modal('show');
        });
    });

    // ---- Open Edit Modal ----
    $(document).on('click', '.btn-modal-sale-target', function (e) {
        e.preventDefault();
        $.get($(this).data('href'), function (result) {
            $('.sale_target_modal').find('.modal-content').html(result);
            $('.sale_target_modal').modal('show');
        });
    });

    // ---- Save Edit Modal ----
    $(document).on('click', '#btn-save-edit-target', function () {
        var form = $('#edit-target-form');
        $.ajax({
            url: form.attr('action'), method: 'POST', data: form.serialize(),
            success: function (res) {
                if (res.success) {
                    toastr.success(res.msg);
                    $('.sale_target_modal').modal('hide');
                    $('#sale_targets_table').DataTable().ajax.reload();
                } else { toastr.error(res.msg); }
            }
        });
    });

    // ---- Delete target ----
    $(document).on('click', '.delete-sale-target', function () {
        var url = $(this).data('href');
        swal({ title: 'Are you sure?', text: 'This will delete the target permanently.', icon: 'warning', buttons: true, dangerMode: true })
        .then(function (ok) {
            if (ok) {
                $.ajax({
                    url: url, method: 'DELETE', data: { _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        if (res.success) {
                            toastr.success(res.msg);
                            $('#sale_targets_table').DataTable().ajax.reload();
                        } else { toastr.error(res.msg); }
                    }
                });
            }
        });
    });

});
</script>
@endsection
