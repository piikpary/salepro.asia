@extends('layouts.app')

@section('title', __('App Settings'))

@section('content')
<section class="content-header">
    <h1>{{ __('App Settings') }} <small>{{ __('Manage your products') }}</small></h1>
</section>

<section class="content">

    {{-- Global Settings Card --}}
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-cog"></i> {{ __('Global Settings') }}</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="enable_app_sale_category" id="enable_app_sale_category"
                                {{ $settings['enable_app_sale_category'] ? 'checked' : '' }}>
                            <strong>{{ __('Enable App Sale Category') }}</strong>
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="enable_user_product_visibility" id="enable_user_product_visibility"
                                {{ $settings['enable_user_product_visibility'] ? 'checked' : '' }}>
                            <strong>{{ __('Enable User Product Visibility') }}</strong>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <strong>{{ __('Default Product Display Mode:') }}</strong>
                    <div class="radio" style="margin-top:6px;">
                        <label>
                            <input type="radio" name="display_mode" id="display_mode_all" value="all"
                                {{ !$settings['show_assigned_product'] ? 'checked' : '' }}>
                            {{ __('Show all products (if user has no specific setting) - Failsafe mode') }}
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="display_mode" id="display_mode_assigned" value="assigned"
                                {{ $settings['show_assigned_product'] ? 'checked' : '' }}>
                            {{ __('Show assigned products only - Strict mode') }}
                        </label>
                    </div>
                </div>
                <div class="col-md-2 text-right" style="padding-top:16px;">
                    <span id="global_save_indicator" style="display:none;color:#5cb85c;font-size:12px;">
                        <i class="fa fa-check-circle"></i> Saved
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Sale Control Card --}}
    <div class="box box-success">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-shopping-cart"></i> {{ __('Sale Control') }}</h3>
            <span id="sale_control_save_indicator" style="display:none;color:#5cb85c;font-size:12px;margin-left:10px;">
                <i class="fa fa-check-circle"></i> Saved
            </span>
        </div>
        <div class="box-body">
            <div class="row">
                {{-- Allow Edit Sale Price --}}
                <div class="col-md-4">
                    <div class="sale-control-item">
                        <div class="sale-control-label">
                            <i class="fa fa-tag text-muted" style="margin-right:5px;"></i>
                            <strong>{{ __('Allow Edit Sale Price') }}</strong>
                        </div>
                        <label class="sc-toggle">
                            <input type="checkbox" id="allow_edit_sale_price" class="sale-control-cb"
                                {{ $settings['allow_edit_sale_price'] ? 'checked' : '' }}>
                            <span class="sc-slider"></span>
                            <span class="sc-label-on">ON</span>
                            <span class="sc-label-off">OFF</span>
                        </label>
                    </div>
                </div>
                {{-- Allow Edit Discount Amount --}}
                <div class="col-md-4">
                    <div class="sale-control-item">
                        <div class="sale-control-label">
                            <i class="fa fa-percent text-muted" style="margin-right:5px;"></i>
                            <strong>{{ __('Allow Edit Discount Amount') }}</strong>
                        </div>
                        <label class="sc-toggle">
                            <input type="checkbox" id="allow_edit_discount" class="sale-control-cb"
                                {{ $settings['allow_edit_discount'] ? 'checked' : '' }}>
                            <span class="sc-slider"></span>
                            <span class="sc-label-on">ON</span>
                            <span class="sc-label-off">OFF</span>
                        </label>
                    </div>
                </div>
                {{-- Show Stock on Sale Screen --}}
                <div class="col-md-4">
                    <div class="sale-control-item">
                        <div class="sale-control-label">
                            <i class="fa fa-cubes text-muted" style="margin-right:5px;"></i>
                            <strong>{{ __('Show Stock on Sale Screen') }}</strong>
                        </div>
                        <label class="sc-toggle">
                            <input type="checkbox" id="show_stock_on_sale" class="sale-control-cb"
                                {{ $settings['show_stock_on_sale'] ? 'checked' : '' }}>
                            <span class="sc-slider"></span>
                            <span class="sc-label-on">ON</span>
                            <span class="sc-label-off">OFF</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="active">
                <a href="#tab_app_sale_category" data-toggle="tab">
                    <i class="fa fa-tag"></i> {{ __('App Sale Category') }}
                </a>
            </li>
            <li>
                <a href="#tab_user_visibility" data-toggle="tab">
                    <i class="fa fa-eye"></i> {{ __('User Product Visibility') }}
                </a>
            </li>
        </ul>

        <div class="tab-content">
            {{-- TAB: App Sale Category --}}
            <div class="tab-pane active" id="tab_app_sale_category">

                {{-- Filters --}}
                <div class="row" style="margin-bottom:12px;">
                    <div class="col-md-12">
                        <a href="#" class="btn btn-link" id="toggle_filters_btn" style="padding-left:0;">
                            <i class="fa fa-filter" style="color:#337ab7;"></i>
                            <span style="color:#337ab7;">{{ __('Filters') }}</span>
                        </a>
                        <div id="filters_area" style="display:none; margin-top:10px;">
                            <div class="row">
                                <div class="col-md-6">
                                    <label>{{ __('System Category:') }}</label>
                                    <select id="filter_system_category" class="form-control select2" style="width:100%;">
                                        <option value="">{{ __('All') }}</option>
                                        @foreach($system_categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>{{ __('App Sale Category:') }}</label>
                                    <select id="filter_app_category" class="form-control select2" style="width:100%;">
                                        <option value="">{{ __('All App Categories') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Manage App Categories button + DataTable --}}
                <div class="row" style="margin-bottom:8px;">
                    <div class="col-md-12 text-right">
                        <button class="btn btn-primary btn-sm" id="btn_manage_app_categories">
                            <i class="fa fa-tag"></i> {{ __('Manage App Categories') }}
                        </button>
                    </div>
                </div>

                <table class="table table-bordered table-striped" id="app_cat_products_table">
                    <thead>
                        <tr>
                            <th width="30px">
                                <input type="checkbox" id="check_all_products">
                            </th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>System Category</th>
                            <th>Assigned App Categories</th>
                            <th>Sort Order</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                {{-- Bulk action buttons (repositioned via JS into DataTable wrapper) --}}
                <div id="bulk_actions_bar" style="padding:6px 0;">
                    <button class="btn btn-xs btn-danger" id="btn_remove_from_category">
                        <i class="fa fa-minus-circle"></i> Remove from Category
                    </button>
                    <button class="btn btn-xs btn-success" id="btn_assign_to_category">
                        <i class="fa fa-plus-circle"></i> Assign to App Category
                    </button>
                </div>
            </div>

            {{-- TAB: User Product Visibility --}}
            <div class="tab-pane" id="tab_user_visibility">

                {{-- Filters --}}
                <div class="row" style="margin-bottom:12px;">
                    <div class="col-md-12">
                        <a href="#" class="btn btn-link" id="toggle_uv_filters_btn" style="padding-left:0;">
                            <i class="fa fa-filter" style="color:#337ab7;"></i>
                            <span style="color:#337ab7;">{{ __('Filters') }}</span>
                        </a>
                        <div id="uv_filters_area" style="display:none; margin-top:10px;">
                            <div class="row">
                                <div class="col-md-4">
                                    <label>{{ __('Business Location:') }}</label>
                                    <select id="uv_filter_location" class="form-control select2" style="width:100%;">
                                        <option value="">{{ __('All Locations') }}</option>
                                        @foreach($business_locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>{{ __('User / Seller:') }}</label>
                                    <select id="uv_filter_user" class="form-control select2" style="width:100%;">
                                        <option value="">{{ __('All Users') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>{{ __('System Category:') }}</label>
                                    <select id="uv_filter_system_category" class="form-control select2" style="width:100%;">
                                        <option value="">{{ __('All') }}</option>
                                        @foreach($system_categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- DataTable --}}
                <table class="table table-bordered table-striped" id="user_products_table">
                    <thead>
                        <tr>
                            <th width="30px">
                                <input type="checkbox" id="check_all_user_products">
                            </th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>System Category</th>
                            <th>Visible To Users</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                {{-- Bulk action buttons (repositioned by JS) --}}
                <div id="uv_bulk_actions_bar" style="padding:6px 0;">
                    <button class="btn btn-xs btn-danger" id="btn_remove_from_user">
                        <i class="fa fa-minus-circle"></i> Remove from User
                    </button>
                    <button class="btn btn-xs btn-primary" id="btn_assign_to_user">
                        <i class="fa fa-user-plus"></i> Assign to User
                        <span id="uv_selected_count_badge"
                              style="display:none;background:#fff;color:#337ab7;border-radius:10px;
                                     padding:1px 6px;font-size:11px;margin-left:4px;font-weight:bold;">0</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</section>

{{-- ===== MANAGE APP CATEGORIES MODAL ===== --}}
<div class="modal fade" id="manage_categories_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width:720px;" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#337ab7;color:#fff;padding:10px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-size:15px;">
                    <i class="fa fa-tag"></i> Manage App Sale Categories
                </h4>
            </div>
            <div class="modal-body" style="padding:16px;">

                {{-- Add new category row --}}
                <div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">
                    <input type="text" id="new_cat_name" class="form-control"
                           placeholder="New Category Name..."
                           style="flex:1;height:34px;">
                    <input type="text" id="new_cat_code" class="form-control"
                           placeholder="Code (e.g. C3)"
                           style="width:120px;height:34px;">
                    <button class="btn btn-primary btn-sm" id="btn_add_category"
                            style="height:34px;white-space:nowrap;padding:0 14px;">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>

                {{-- Category list table --}}
                <table class="table table-bordered" id="manage_cat_table"
                       style="margin-bottom:0;font-size:13px;">
                    <thead style="background:#f5f5f5;">
                        <tr>
                            <th style="width:32px;text-align:center;">#</th>
                            <th>Category Name</th>
                            <th style="width:90px;">Code</th>
                            <th style="width:78px;text-align:center;">Products</th>
                            <th style="width:72px;text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="manage_cat_tbody">
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:16px;">
                                Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer" style="padding:10px 15px;text-align:right;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ===== ASSIGN TO APP CATEGORY MODAL ===== --}}
<div class="modal fade" id="assign_category_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#337ab7;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-plus-circle"></i> {{ __('Assign to App Category') }}
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info alert-sm" style="padding:6px 10px;font-size:12px;">
                    <i class="fa fa-info-circle"></i>
                    <strong>{{ __('Sort Order: Auto Increment') }}</strong><br>
                    {{ __('Enter a starting number — each selected product gets +1 automatically, e.g. starting from 5 → 5, 6, 7, 8...') }}
                </div>

                <div class="row" style="margin-bottom:12px;">
                    <div class="col-md-7">
                        <label>{{ __('Select App Category:') }} <span class="text-danger">*</span></label>
                        <select id="assign_app_category_id" class="form-control select2" style="width:100%;">
                            <option value="">{{ __('-- Select --') }}</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label>{{ __('Starting Sort Order:') }}</label>
                        <input type="number" id="assign_starting_sort" class="form-control" value="1" min="1">
                    </div>
                </div>

                {{-- Preview table --}}
                <div>
                    <a href="#" id="btn_preview_sort"><i class="fa fa-eye"></i> {{ __('Preview Sort Order:') }}</a>
                    <table class="table table-bordered table-sm" style="margin-top:8px;" id="preview_sort_table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th width="80px">Sort Order</th>
                            </tr>
                        </thead>
                        <tbody id="preview_sort_tbody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-success" id="btn_save_assignment">
                    <i class="fa fa-save"></i> {{ __('Save Assignment') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Remove from Category modal replaced by inline swal confirm --}}

{{-- ===== ASSIGN TO USER MODAL ===== --}}
<div class="modal fade" id="uv_assign_user_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#337ab7;color:#fff;padding:10px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-size:15px;">
                    <i class="fa fa-user-plus"></i> {{ __('Assign to User') }}
                </h4>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;margin-bottom:12px;">
                    {{ __('Assigning') }} <strong id="uv_assigning_count">0</strong> {{ __('selected product(s) to:') }}
                </p>
                <div class="form-group">
                    <label>{{ __('Select User:') }} <span class="text-danger">*</span></label>
                    <select id="uv_assign_user_id" class="form-control select2" style="width:100%;">
                        <option value="">{{ __('— Select User —') }}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="padding:10px 15px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn_save_assign_user">
                    <i class="fa fa-save"></i> {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== REMOVE FROM USER MODAL ===== --}}
<div class="modal fade" id="uv_remove_user_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width:540px;" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#d9534f;color:#fff;padding:10px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-size:15px;">
                    <i class="fa fa-user-times"></i> {{ __('Remove from User') }}
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" style="padding:7px 12px;font-size:12px;margin-bottom:14px;">
                    <i class="fa fa-exclamation-circle"></i>
                    {{ __('Remove') }} <strong id="uv_remove_count">0</strong>
                    {{ __('selected product(s) from the chosen user\'s visibility list.') }}
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label>{{ __('Select User to Remove From:') }} <span class="text-danger">*</span></label>
                    <select id="uv_remove_user_id" class="form-control select2" style="width:100%;">
                        <option value="">{{ __('— Select User —') }}</option>
                        <option value="__all__">{{ __('★ All Users (remove from everyone)') }}</option>
                    </select>
                </div>
                <table class="table table-bordered table-condensed" style="font-size:12px;margin-bottom:0;">
                    <thead style="background:#f5f5f5;">
                        <tr>
                            <th>Product</th>
                            <th style="width:140px;text-align:center;">Currently Assigned?</th>
                        </tr>
                    </thead>
                    <tbody id="uv_remove_products_tbody">
                        <tr><td colspan="2" class="text-center text-muted" style="padding:12px;">
                            Select a user above to check assignment status.
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer" style="padding:10px 15px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn_confirm_remove_user">
                    <i class="fa fa-trash"></i> {{ __('Confirm Remove') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<style>
/* Sale Control toggle switch */
.sale-control-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #f9f9f9;
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    margin-bottom: 4px;
}
.sale-control-label {
    font-size: 13px;
    color: #444;
    flex: 1;
}
.sc-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
    user-select: none;
    flex-shrink: 0;
}
.sc-toggle input[type="checkbox"] {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.sc-slider {
    display: inline-block;
    width: 44px;
    height: 24px;
    background: #ccc;
    border-radius: 24px;
    position: relative;
    transition: background .2s;
    flex-shrink: 0;
}
.sc-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: transform .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.sc-toggle input:checked ~ .sc-slider {
    background: #5cb85c;
}
.sc-toggle input:checked ~ .sc-slider::before {
    transform: translateX(20px);
}
.sc-label-on,
.sc-label-off {
    font-size: 11px;
    font-weight: bold;
    margin-left: 6px;
    width: 24px;
    text-align: left;
}
.sc-toggle input:checked ~ .sc-slider ~ .sc-label-on  { display: inline; color: #5cb85c; }
.sc-toggle input:checked ~ .sc-slider ~ .sc-label-off { display: none; }
.sc-toggle input:not(:checked) ~ .sc-slider ~ .sc-label-on  { display: none; }
.sc-toggle input:not(:checked) ~ .sc-slider ~ .sc-label-off { display: inline; color: #999; }
</style>
@endsection

@section('javascript')
<script>
$(function () {
    var CSRF = '{{ csrf_token() }}';

    // =========================================================
    // DataTable
    // =========================================================
    var table = $('#app_cat_products_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("app-settings.products") }}',
            data: function (d) {
                d.system_category_id = $('#filter_system_category').val();
                d.app_category_id    = $('#filter_app_category').val();
                // d.search is set automatically by DataTable (native search box)
            }
        },
        columns: [
            { data: 'checkbox',        orderable: false, searchable: false, width: '30px' },
            { data: 'name' },
            { data: 'sku' },
            { data: 'system_category' },
            { data: 'assigned_cats',   orderable: false },
            { data: 'sort_order',      orderable: false },
        ],
        pageLength: 25,
        order: [[1, 'asc']],
    });

    // Move bulk action buttons into the DataTable wrapper, before the info/pagination row
    // — same position as the action buttons on the Products list page
    var $bulkBar = $('#bulk_actions_bar').detach();
    $('#app_cat_products_table_wrapper .dataTables_info').closest('div').before($bulkBar);

    // Filters toggle
    $('#toggle_filters_btn').on('click', function (e) {
        e.preventDefault();
        $('#filters_area').slideToggle(150);
    });

    // Filter apply on change
    $('#filter_system_category, #filter_app_category').on('change', function () { table.ajax.reload(); });

    // Check all
    $('#check_all_products').on('change', function () {
        $('.product-checkbox').prop('checked', $(this).is(':checked'));
    });

    function getSelectedIds() {
        var ids = [];
        $('.product-checkbox:checked').each(function () { ids.push($(this).val()); });
        return ids;
    }

    // =========================================================
    // Global Settings — auto-save on any change (no button needed)
    // =========================================================
    function saveGlobalSettings() {
        var data = {
            _token: CSRF,
            enable_app_sale_category:       $('#enable_app_sale_category').is(':checked') ? 1 : 0,
            enable_user_product_visibility: $('#enable_user_product_visibility').is(':checked') ? 1 : 0,
            display_mode:                   $('input[name="display_mode"]:checked').val() || 'all',
            allow_edit_sale_price:          $('#allow_edit_sale_price').is(':checked') ? 1 : 0,
            allow_edit_discount:            $('#allow_edit_discount').is(':checked') ? 1 : 0,
            show_stock_on_sale:             $('#show_stock_on_sale').is(':checked') ? 1 : 0,
        };
        $.ajax({
            url: '{{ route("app-settings.save_global") }}',
            method: 'POST',
            data: data,
            success: function () {
                var $ind = $('#global_save_indicator');
                $ind.fadeIn(150);
                setTimeout(function () { $ind.fadeOut(600); }, 1500);
                var $ind2 = $('#sale_control_save_indicator');
                $ind2.fadeIn(150);
                setTimeout(function () { $ind2.fadeOut(600); }, 1500);
            },
            error: function () {
                toastr.error('Failed to save settings.');
            }
        });
    }

    $('#enable_app_sale_category, #enable_user_product_visibility').on('change', saveGlobalSettings);
    $('input[name="display_mode"]').on('change', saveGlobalSettings);
    $('.sale-control-cb').on('change', saveGlobalSettings);

    // =========================================================
    // Load categories (for modals & filter dropdown)
    // =========================================================
    function loadCategories(callback) {
        $.get('{{ route("app-settings.categories") }}', function (res) {
            if (!res.success) return;
            var cats = res.data;

            // Populate filter dropdown
            var $filter = $('#filter_app_category');
            var filterVal = $filter.val();
            $filter.find('option:not(:first)').remove();
            cats.forEach(function (c) {
                $filter.append('<option value="' + c.id + '">' + c.name + ' (' + c.code + ')</option>');
            });
            $filter.val(filterVal);

            // Populate assign modal dropdown
            var $assign = $('#assign_app_category_id');
            $assign.find('option:not(:first)').remove();
            cats.forEach(function (c) {
                $assign.append('<option value="' + c.id + '">' + c.name + ' (' + c.code + ')</option>');
            });

            // Populate remove modal dropdown
            var $remove = $('#remove_app_category_id');
            $remove.find('option:not(:first)').remove();
            cats.forEach(function (c) {
                $remove.append('<option value="' + c.id + '">' + c.name + ' (' + c.code + ')</option>');
            });

            if (typeof callback === 'function') callback(cats);
        });
    }
    loadCategories(); // initial load

    // =========================================================
    // Manage Categories Modal
    // =========================================================
    $('#btn_manage_app_categories').on('click', function () {
        $('#manage_categories_modal').modal('show');
        loadManageCatTable();
    });

    function loadManageCatTable() {
        $.get('{{ route("app-settings.categories") }}', function (res) {
            var $tbody = $('#manage_cat_tbody');
            $tbody.empty();
            if (!res.success || !res.data.length) {
                $tbody.append(
                    '<tr><td colspan="5" class="text-center text-muted" style="padding:20px;">No categories yet.</td></tr>'
                );
                return;
            }
            res.data.forEach(function (c, idx) {
                $tbody.append(
                    '<tr data-id="' + c.id + '" style="vertical-align:middle;">' +
                    // # column
                    '<td style="text-align:center;color:#888;font-size:12px;">' + (idx + 1) + '</td>' +
                    // Category Name — full-width inline input
                    '<td style="padding:5px 6px;">' +
                        '<input type="text" class="form-control input-sm cat-name-input"' +
                        ' value="' + escHtml(c.name) + '" style="height:28px;font-size:13px;">' +
                    '</td>' +
                    // Code — narrow inline input
                    '<td style="padding:5px 6px;">' +
                        '<input type="text" class="form-control input-sm cat-code-input"' +
                        ' value="' + escHtml(c.code) + '" style="height:28px;font-size:13px;text-align:center;">' +
                    '</td>' +
                    // Products — circular blue badge
                    '<td style="text-align:center;">' +
                        '<span style="display:inline-flex;align-items:center;justify-content:center;' +
                               'width:26px;height:26px;border-radius:50%;background:#337ab7;' +
                               'color:#fff;font-size:12px;font-weight:bold;">' +
                            c.product_count +
                        '</span>' +
                    '</td>' +
                    // Action — blue save + red delete icon buttons
                    '<td style="text-align:center;white-space:nowrap;">' +
                        '<button class="btn btn-xs btn-primary btn-save-cat"' +
                                ' style="width:26px;height:26px;padding:0;margin-right:3px;"' +
                                ' title="Save"><i class="fa fa-save"></i></button>' +
                        '<button class="btn btn-xs btn-danger btn-delete-cat"' +
                                ' style="width:26px;height:26px;padding:0;"' +
                                ' title="Delete"><i class="fa fa-trash"></i></button>' +
                    '</td>' +
                    '</tr>'
                );
            });
            loadCategories(); // refresh filter & modal dropdowns
        });
    }

    // Add new category
    $('#btn_add_category').on('click', function () {
        var name = $.trim($('#new_cat_name').val());
        var code = $.trim($('#new_cat_code').val());
        if (!name || !code) { toastr.warning('{{ __("Name and code are required.") }}'); return; }

        $.ajax({
            url: '{{ route("app-settings.create_category") }}',
            method: 'POST',
            data: { _token: CSRF, name: name, code: code },
            success: function (res) {
                if (res.success) {
                    $('#new_cat_name').val('');
                    $('#new_cat_code').val('');
                    loadManageCatTable();
                    toastr.success('{{ __("Category added.") }}');
                } else {
                    toastr.error(res.message || '{{ __("Error.") }}');
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
            }
        });
    });

    // Save (inline edit)
    $(document).on('click', '.btn-save-cat', function () {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');
        var name = $row.find('.cat-name-input').val();
        var code = $row.find('.cat-code-input').val();

        $.ajax({
            url: '/app-settings/categories/' + id,
            method: 'PUT',
            data: { _token: CSRF, name: name, code: code },
            success: function (res) {
                if (res.success) {
                    loadManageCatTable();
                    toastr.success('{{ __("Category updated.") }}');
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
            }
        });
    });

    // Delete category
    $(document).on('click', '.btn-delete-cat', function () {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');
        if (!confirm('{{ __("Delete this category and all its product assignments?") }}')) return;

        $.ajax({
            url: '/app-settings/categories/' + id,
            method: 'DELETE',
            data: { _token: CSRF },
            success: function (res) {
                if (res.success) {
                    loadManageCatTable();
                    table.ajax.reload(null, false);
                    toastr.success('{{ __("Category deleted.") }}');
                } else {
                    toastr.error(res.message);
                }
            }
        });
    });

    // =========================================================
    // Assign to Category
    // =========================================================
    $('#btn_assign_to_category').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) { toastr.warning('{{ __("Select at least one product.") }}'); return; }
        $('#assign_category_modal').modal('show');
        buildPreviewTable(ids, 1);
    });

    function buildPreviewTable(ids, startSort) {
        var $tbody = $('#preview_sort_tbody');
        $tbody.empty();
        ids.forEach(function (id, i) {
            var name = $('input.product-checkbox[value="' + id + '"]').closest('tr').find('td:nth-child(2)').text().trim();
            var sort = parseInt(startSort) + i;
            $tbody.append('<tr><td>' + escHtml(name) + '</td><td style="color:#337ab7;font-weight:bold;">' + sort + '</td></tr>');
        });
    }

    $('#assign_starting_sort').on('input', function () {
        var ids = getSelectedIds();
        buildPreviewTable(ids, $(this).val() || 1);
    });

    $('#btn_preview_sort').on('click', function (e) {
        e.preventDefault();
        var ids = getSelectedIds();
        buildPreviewTable(ids, $('#assign_starting_sort').val() || 1);
    });

    $('#btn_save_assignment').on('click', function () {
        var ids        = getSelectedIds();
        var cat_id     = $('#assign_app_category_id').val();
        var start_sort = $('#assign_starting_sort').val();

        if (!cat_id) { toastr.warning('{{ __("Please select a category.") }}'); return; }
        if (!ids.length) { toastr.warning('{{ __("No products selected.") }}'); return; }

        $.ajax({
            url: '{{ route("app-settings.assign_category") }}',
            method: 'POST',
            data: { _token: CSRF, product_ids: ids, app_category_id: cat_id, starting_sort: start_sort },
            success: function (res) {
                if (res.success) {
                    $('#assign_category_modal').modal('hide');
                    table.ajax.reload(null, false);
                    toastr.success('{{ __("Products assigned successfully.") }}');
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
            }
        });
    });

    // =========================================================
    // Remove from Category — simple swal confirm, no category picker
    // =========================================================
    $('#btn_remove_from_category').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) { toastr.warning('{{ __("Select at least one product.") }}'); return; }

        swal({
            title: 'Are you sure?',
            text: 'Remove ' + ids.length + ' selected product(s) from all assigned app categories?',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function (confirmed) {
            if (!confirmed) return;
            $.ajax({
                url: '{{ route("app-settings.remove_from_category") }}',
                method: 'POST',
                data: { _token: CSRF, product_ids: ids },
                success: function (res) {
                    if (res.success) {
                        table.ajax.reload(null, false);
                        toastr.success('{{ __("Products removed from category.") }}');
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
                }
            });
        });
    });

    // =========================================================
    // USER PRODUCT VISIBILITY TAB
    // =========================================================
    var userTable = null;

    // Init lazily when tab is first shown
    $('a[href="#tab_user_visibility"]').on('shown.bs.tab', function () {
        if (!userTable) { initUserProductsTable(); }
    });

    function initUserProductsTable() {
        userTable = $('#user_products_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("app-settings.user_products") }}',
                data: function (d) {
                    d.system_category_id = $('#uv_filter_system_category').val();
                    d.user_id            = $('#uv_filter_user').val();
                    d.location_id        = $('#uv_filter_location').val();
                    // d.search set automatically by DataTable native search box
                }
            },
            columns: [
                { data: 'checkbox',         orderable: false, searchable: false, width: '30px' },
                { data: 'name' },
                { data: 'sku',              width: '120px' },
                { data: 'system_category' },
                { data: 'visible_to_users', orderable: false, searchable: false },
            ],
            pageLength: 25,
            order: [[1, 'asc']],
        });

        // Reposition bulk bar once table is in DOM
        var $uvBulkBar = $('#uv_bulk_actions_bar').detach();
        $('#user_products_table_wrapper .dataTables_info').closest('div').before($uvBulkBar);

        // Filters
        $('#toggle_uv_filters_btn').on('click', function (e) {
            e.preventDefault();
            $('#uv_filters_area').slideToggle(150);
        });

        $('#uv_filter_system_category, #uv_filter_user, #uv_filter_location').on('change', function () {
            userTable.ajax.reload();
        });

        // Check all
        $(document).on('change', '#check_all_user_products', function () {
            $('#user_products_table tbody .user-product-checkbox').prop('checked', this.checked);
            updateUVBadge();
        });
        $(document).on('change', '.user-product-checkbox', function () {
            updateUVBadge();
        });

        // Load users into all dropdowns
        loadUserDropdowns();
    }

    function getSelectedUserProductIds() {
        var ids = [];
        $('#user_products_table tbody .user-product-checkbox:checked').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateUVBadge() {
        var count = getSelectedUserProductIds().length;
        var $badge = $('#uv_selected_count_badge');
        if (count > 0) { $badge.text(count).show(); }
        else            { $badge.hide(); }
    }

    function loadUserDropdowns() {
        $.get('{{ route("app-settings.users") }}', function (res) {
            if (!res.success) return;
            var $filter  = $('#uv_filter_user');
            var $assign  = $('#uv_assign_user_id');
            var $remove  = $('#uv_remove_user_id');

            $filter.find('option:not(:first)').remove();
            $assign.empty().append('<option value="">{{ __("— Select User —") }}</option>');
            // Remove dropdown: keep blank + "All Users" + individual users
            $remove.empty()
                   .append('<option value="">{{ __("— Select User —") }}</option>')
                   .append('<option value="__all__">{{ __("★ All Users (remove from everyone)") }}</option>');

            res.data.forEach(function (u) {
                var opt = '<option value="' + u.id + '">' + escHtml(u.display_name) + '</option>';
                $filter.append(opt);
                $assign.append(opt);
                $remove.append(opt);
            });
        });
    }

    // =========================================================
    // Assign to User modal
    // =========================================================
    $('#btn_assign_to_user').on('click', function () {
        var ids = getSelectedUserProductIds();
        if (!ids.length) { toastr.warning('{{ __("Select at least one product.") }}'); return; }
        $('#uv_assigning_count').text(ids.length);
        $('#uv_assign_user_id').val('').trigger('change');
        $('#uv_assign_user_modal').modal('show');
    });

    $('#btn_save_assign_user').on('click', function () {
        var ids    = getSelectedUserProductIds();
        var userId = $('#uv_assign_user_id').val();
        if (!userId) { toastr.warning('{{ __("Please select a user.") }}'); return; }

        $.ajax({
            url:    '{{ route("app-settings.assign_user") }}',
            method: 'POST',
            data:   { _token: CSRF, product_ids: ids, user_id: userId },
            success: function (res) {
                if (res.success) {
                    $('#uv_assign_user_modal').modal('hide');
                    userTable.ajax.reload(null, false);
                    toastr.success('{{ __("Products assigned to user.") }}');
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
            }
        });
    });

    // =========================================================
    // Remove from User modal
    // =========================================================
    $('#btn_remove_from_user').on('click', function () {
        var ids = getSelectedUserProductIds();
        if (!ids.length) { toastr.warning('{{ __("Select at least one product.") }}'); return; }
        $('#uv_remove_count').text(ids.length);
        $('#uv_remove_user_id').val('').trigger('change');
        buildRemoveUserRows(ids, [], null);
        $('#uv_remove_user_modal').modal('show');
    });

    // When user selection changes → refresh assignment table
    $('#uv_remove_user_id').on('change', function () {
        var userId = $(this).val();
        var ids    = getSelectedUserProductIds();
        if (!userId) {
            buildRemoveUserRows(ids, [], null);
            return;
        }
        if (userId === '__all__') {
            buildRemoveUserRows(ids, [], '__all__');
            return;
        }
        // Specific user — fetch which products are assigned
        $.get('{{ route("app-settings.check_user_assignments") }}', {
            user_id: userId, product_ids: ids
        }, function (res) {
            buildRemoveUserRows(ids, res.success ? res.assigned.map(String) : [], userId);
        });
    });

    function buildRemoveUserRows(ids, assignedIds, userId) {
        var $tbody = $('#uv_remove_products_tbody');
        $tbody.empty();
        if (!ids.length) {
            $tbody.append('<tr><td colspan="2" class="text-center text-muted" style="padding:12px;">No products selected.</td></tr>');
            return;
        }
        // Collect product names from DataTable rows
        var names = {};
        $('#user_products_table tbody tr').each(function () {
            var $cb = $(this).find('.user-product-checkbox');
            if ($cb.length) names[$cb.val()] = $(this).find('td:eq(1)').text().trim();
        });
        ids.forEach(function (pid) {
            var statusHtml;
            if (!userId) {
                statusHtml = '<span class="text-muted">—</span>';
            } else if (userId === '__all__') {
                statusHtml = '<span style="color:#e67e22;"><i class="fa fa-users"></i> All users</span>';
            } else {
                var isAssigned = assignedIds.indexOf(String(pid)) !== -1;
                statusHtml = isAssigned
                    ? '<span style="color:#27ae60;"><i class="fa fa-check-circle"></i> Yes</span>'
                    : '<span style="color:#aaa;"><i class="fa fa-times-circle"></i> No</span>';
            }
            $tbody.append(
                '<tr>' +
                '<td>' + escHtml(names[pid] || 'Product #' + pid) + '</td>' +
                '<td style="text-align:center;">' + statusHtml + '</td>' +
                '</tr>'
            );
        });
    }

    $('#btn_confirm_remove_user').on('click', function () {
        var ids    = getSelectedUserProductIds();
        var userId = $('#uv_remove_user_id').val();
        if (!userId) { toastr.warning('{{ __("Please select a user or choose All Users.") }}'); return; }

        var postData = { _token: CSRF, product_ids: ids };
        if (userId !== '__all__') postData.user_id = userId;   // omit → removes from ALL

        $.ajax({
            url:    '{{ route("app-settings.remove_from_user") }}',
            method: 'POST',
            data:   postData,
            success: function (res) {
                if (res.success) {
                    $('#uv_remove_user_modal').modal('hide');
                    userTable.ajax.reload(null, false);
                    toastr.success('{{ __("Products removed from user.") }}');
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || '{{ __("Error.") }}');
            }
        });
    });

    // =========================================================
    // Helpers
    // =========================================================
    function escHtml(str) {
        return $('<div>').text(str).html();
    }
    function debounce(fn, delay) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, delay); };
    }
});
</script>
@endsection
