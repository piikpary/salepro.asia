@extends('layouts.app')

@section('title', __('Product Sale Visit Setting'))

@section('content')
<section class="content-header">
    <h1>{{ __('Product Sale Visit Setting') }}</h1>
    <small>{{ __('Manage app visibility for your products and assign market competitors.') }}</small>
</section>

<section class="content">
    {{-- Search bar --}}
    <div class="row" style="margin-bottom:16px;">
        <div class="col-md-4 col-md-offset-8">
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                <input type="text" id="global_search" class="form-control" placeholder="{{ __('Search product name or SKU...') }}">
            </div>
        </div>
    </div>

    {{-- Own Products & Mappings --}}
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">
                <i class="fa fa-tag text-primary"></i>
                &nbsp;{{ __('Own Products & Mappings') }}
            </h3>
            <span id="own_products_count" class="label label-primary pull-right" style="font-size:13px;padding:5px 10px;margin-top:2px;">0</span>
        </div>
        <div class="box-body no-padding">
            <table class="table table-bordered table-hover" id="own_products_table" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="width:40%;">{{ __('Product Information') }}</th>
                        <th style="width:15%;text-align:center;">{{ __('App Visibility') }}</th>
                        <th>{{ __('Assigned Competitors') }}</th>
                    </tr>
                </thead>
                <tbody id="own_products_body">
                    <tr>
                        <td colspan="3" class="text-center text-muted" style="padding:30px;">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('Loading...') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">
            <div class="pull-left">
                <select id="own_per_page" class="form-control input-sm" style="width:auto;display:inline-block;">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                </select>
                &nbsp;<span id="own_pagination_info" class="text-muted small"></span>
            </div>
            <div class="pull-right" id="own_pagination_links"></div>
        </div>
    </div>

    {{-- Unlinked Competitors (Fallback Zone) --}}
    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">
                <i class="fa fa-random text-muted"></i>
                &nbsp;{{ __('Unlinked Competitors (Fallback Zone)') }}
            </h3>
            <span id="other_products_count" class="label label-default pull-right" style="font-size:13px;padding:5px 10px;margin-top:2px;">0</span>
        </div>
        <div class="box-body no-padding">
            <table class="table table-bordered table-hover" id="other_products_table" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="width:55%;">{{ __('Competitor Information') }}</th>
                        <th style="width:20%;text-align:center;">{{ __('App Visibility (Fallback)') }}</th>
                    </tr>
                </thead>
                <tbody id="other_products_body">
                    <tr>
                        <td colspan="2" class="text-center text-muted" style="padding:30px;">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('Loading...') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">
            <div class="pull-left">
                <select id="other_per_page" class="form-control input-sm" style="width:auto;display:inline-block;">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                </select>
                &nbsp;<span id="other_pagination_info" class="text-muted small"></span>
            </div>
            <div class="pull-right" id="other_pagination_links"></div>
        </div>
    </div>
</section>

{{-- Bind Competitors Modal --}}
<div class="modal fade" id="bind_competitors_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">
                    {{ __('Bind Competitors') }}
                    <br>
                    <small class="text-muted">{{ __('Targeting:') }} <strong id="bind_target_product_name"></strong></small>
                </h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <div style="padding:12px 16px;border-bottom:1px solid #eee;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        <input type="text" id="competitor_search" class="form-control" placeholder="{{ __('Search competitor to link...') }}">
                    </div>
                </div>
                <div id="competitor_list" style="max-height:360px;overflow-y:auto;">
                    <div class="text-center text-muted" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin"></i> {{ __('Loading...') }}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="confirm_bind_btn">
                    <i class="fa fa-check"></i> {{ __('Confirm Selection') }}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Toggle switch */
.psvs-toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    vertical-align: middle;
}
.psvs-toggle input { display: none; }
.psvs-toggle .slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    border-radius: 24px;
    transition: .3s;
}
.psvs-toggle .slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: .3s;
}
.psvs-toggle input:checked + .slider { background-color: #3c8dbc; }
.psvs-toggle input:checked + .slider:before { transform: translateX(20px); }

/* Competitor tags */
.competitor-tag {
    display: inline-block;
    background: #e8f4fd;
    border: 1px solid #3c8dbc;
    color: #3c8dbc;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    margin: 2px 2px 2px 0;
    white-space: nowrap;
}
.competitor-tag .remove-competitor {
    cursor: pointer;
    margin-left: 4px;
    color: #999;
    font-weight: bold;
}
.competitor-tag .remove-competitor:hover { color: #c0392b; }

/* Competitor list items in modal */
.competitor-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
    transition: background .15s;
}
.competitor-item:hover { background: #f9f9f9; }
.competitor-item.selected { background: #eaf4fb; }
.competitor-item .competitor-check {
    width: 18px; height: 18px;
    border: 2px solid #ccc;
    border-radius: 3px;
    margin-right: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: white;
}
.competitor-item.selected .competitor-check {
    background: #3c8dbc;
    border-color: #3c8dbc;
}
.competitor-item .competitor-info strong { display:block; font-size:14px; }
.competitor-item .competitor-info small { color:#888; }

.hidden-label { color:#aaa; font-style:italic; font-size:12px; }
.product-sku { color:#888; font-size:12px; }
</style>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var ownCurrentPage = 1;
    var otherCurrentPage = 1;
    var searchTimer = null;
    var bindProductId = null;
    var bindSelectedIds = [];
    var competitorSearchTimer = null;
    var allCompetitors = [];

    /* ===== LOAD OWN PRODUCTS ===== */
    function loadOwnProducts(page) {
        page = page || 1;
        ownCurrentPage = page;
        $('#own_products_body').html('<tr><td colspan="3" class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        $.get('{{ route("product_sale_visit.own_products") }}', {
            page: page,
            per_page: $('#own_per_page').val(),
            search: $('#global_search').val()
        }, function (data) {
            $('#own_products_count').text(data.total);
            renderOwnProducts(data.data);
            renderPagination('#own_pagination_links', data, loadOwnProducts);
            $('#own_pagination_info').text('Showing ' + data.from + '–' + data.to + ' of ' + data.total + ' records');
        });
    }

    function renderOwnProducts(rows) {
        if (!rows || rows.length === 0) {
            $('#own_products_body').html('<tr><td colspan="3" class="text-center text-muted" style="padding:30px;">No own products found.</td></tr>');
            return;
        }

        var html = '';
        $.each(rows, function (i, p) {
            var isVisible = (p.product_sale_visit == 1);
            var toggleChecked = isVisible ? 'checked' : '';

            var competitorsHtml = '';
            if (!isVisible) {
                competitorsHtml = '<span class="hidden-label"><i class="fa fa-eye-slash"></i> Hidden in App &mdash; No competitors linked</span>';
            } else {
                if (p.competitors && p.competitors.length > 0) {
                    $.each(p.competitors, function (j, c) {
                        competitorsHtml += '<span class="competitor-tag">' + escHtml(c.name) +
                            ' <span class="remove-competitor" data-own-id="' + p.id + '" data-comp-id="' + c.id + '" title="Remove">&times;</span></span>';
                    });
                }
                competitorsHtml += ' <button type="button" class="btn btn-xs btn-default link-competitor-btn" data-product-id="' + p.id + '" data-product-name="' + escHtml(p.name) + '">' +
                    '<i class="fa fa-plus"></i> Link Competitor</button>';
            }

            html += '<tr data-product-id="' + p.id + '">' +
                '<td>' +
                    '<strong>' + escHtml(p.name) + '</strong>' +
                    '<br><span class="product-sku">' + escHtml(p.sku) + '</span>' +
                '</td>' +
                '<td style="text-align:center;vertical-align:middle;">' +
                    '<label class="psvs-toggle">' +
                        '<input type="checkbox" class="visibility-toggle" data-product-id="' + p.id + '" data-kind="0" ' + toggleChecked + '>' +
                        '<span class="slider"></span>' +
                    '</label>' +
                '</td>' +
                '<td style="vertical-align:middle;">' +
                    '<div class="competitor-tags-wrap" id="comp-wrap-' + p.id + '">' + competitorsHtml + '</div>' +
                '</td>' +
            '</tr>';
        });
        $('#own_products_body').html(html);
    }

    /* ===== LOAD OTHER PRODUCTS ===== */
    function loadOtherProducts(page) {
        page = page || 1;
        otherCurrentPage = page;
        $('#other_products_body').html('<tr><td colspan="2" class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        $.get('{{ route("product_sale_visit.other_products") }}', {
            page: page,
            per_page: $('#other_per_page').val(),
            search: $('#global_search').val()
        }, function (data) {
            $('#other_products_count').text(data.total);
            renderOtherProducts(data.data);
            renderPagination('#other_pagination_links', data, loadOtherProducts);
            $('#other_pagination_info').text('Showing ' + data.from + '–' + data.to + ' of ' + data.total + ' records');
        });
    }

    function renderOtherProducts(rows) {
        if (!rows || rows.length === 0) {
            $('#other_products_body').html('<tr><td colspan="2" class="text-center text-muted" style="padding:30px;">No competitor products found.</td></tr>');
            return;
        }

        var html = '';
        $.each(rows, function (i, p) {
            var isVisible = (p.product_sale_visit == 1);
            var toggleChecked = isVisible ? 'checked' : '';

            html += '<tr data-product-id="' + p.id + '">' +
                '<td>' +
                    '<strong>' + escHtml(p.name) + '</strong>' +
                    '<br><span class="product-sku">' + escHtml(p.sku) + '</span>' +
                '</td>' +
                '<td style="text-align:center;vertical-align:middle;">' +
                    '<label class="psvs-toggle">' +
                        '<input type="checkbox" class="visibility-toggle" data-product-id="' + p.id + '" data-kind="1" ' + toggleChecked + '>' +
                        '<span class="slider"></span>' +
                    '</label>' +
                '</td>' +
            '</tr>';
        });
        $('#other_products_body').html(html);
    }

    /* ===== PAGINATION RENDERER ===== */
    function renderPagination(container, data, loadFn) {
        if (data.last_page <= 1) {
            $(container).html('');
            return;
        }
        var html = '<ul class="pagination pagination-sm" style="margin:0;">';

        if (data.current_page > 1) {
            html += '<li><a href="#" data-page="' + (data.current_page - 1) + '">&laquo;</a></li>';
        } else {
            html += '<li class="disabled"><span>&laquo;</span></li>';
        }

        var start = Math.max(1, data.current_page - 2);
        var end = Math.min(data.last_page, data.current_page + 2);

        for (var p = start; p <= end; p++) {
            if (p === data.current_page) {
                html += '<li class="active"><span>' + p + '</span></li>';
            } else {
                html += '<li><a href="#" data-page="' + p + '">' + p + '</a></li>';
            }
        }

        if (data.current_page < data.last_page) {
            html += '<li><a href="#" data-page="' + (data.current_page + 1) + '">&raquo;</a></li>';
        } else {
            html += '<li class="disabled"><span>&raquo;</span></li>';
        }

        html += '</ul>';
        $(container).html(html);

        $(container + ' a[data-page]').on('click', function (e) {
            e.preventDefault();
            loadFn($(this).data('page'));
        });
    }

    /* ===== VISIBILITY TOGGLE ===== */
    $(document).on('change', '.visibility-toggle', function () {
        var $toggle = $(this);
        var productId = $toggle.data('product-id');
        var kind = $toggle.data('kind');
        var visible = $toggle.is(':checked') ? 1 : 0;

        $.post('{{ route("product_sale_visit.toggle_visibility") }}', {
            product_id: productId,
            visible: visible,
            _token: csrfToken
        }, function (res) {
            if (res.success) {
                if (kind == 0) {
                    loadOwnProducts(ownCurrentPage);
                } else {
                    loadOtherProducts(otherCurrentPage);
                }
            } else {
                toastr.error(res.msg || 'Failed to update visibility.');
                $toggle.prop('checked', !$toggle.is(':checked'));
            }
        }).fail(function () {
            toastr.error('Network error.');
            $toggle.prop('checked', !$toggle.is(':checked'));
        });
    });

    /* ===== REMOVE SINGLE COMPETITOR ===== */
    $(document).on('click', '.remove-competitor', function (e) {
        e.stopPropagation();
        var ownId = $(this).data('own-id');
        var compId = $(this).data('comp-id');

        var $wrap = $('#comp-wrap-' + ownId);
        var currentIds = [];
        $wrap.find('.competitor-tag').each(function () {
            var cid = $(this).find('.remove-competitor').data('comp-id');
            if (cid != compId) {
                currentIds.push(cid);
            }
        });

        $.post('{{ route("product_sale_visit.bind_competitors") }}', {
            product_id: ownId,
            competitor_ids: currentIds,
            _token: csrfToken
        }, function (res) {
            if (res.success) {
                loadOwnProducts(ownCurrentPage);
            } else {
                toastr.error(res.msg || 'Failed to remove competitor.');
            }
        });
    });

    /* ===== OPEN BIND COMPETITORS MODAL ===== */
    $(document).on('click', '.link-competitor-btn', function () {
        bindProductId = $(this).data('product-id');
        var productName = $(this).data('product-name');
        var $wrap = $('#comp-wrap-' + bindProductId);

        // Pre-select already linked competitors
        bindSelectedIds = [];
        $wrap.find('.remove-competitor').each(function () {
            bindSelectedIds.push(parseInt($(this).data('comp-id')));
        });

        $('#bind_target_product_name').text(productName);
        $('#competitor_search').val('');
        $('#bind_competitors_modal').modal('show');
        loadCompetitorList('');
    });

    function loadCompetitorList(search) {
        $('#competitor_list').html('<div class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

        $.get('{{ route("product_sale_visit.available_competitors") }}', { search: search }, function (data) {
            allCompetitors = data;
            renderCompetitorList(data);
        });
    }

    function renderCompetitorList(competitors) {
        if (!competitors || competitors.length === 0) {
            $('#competitor_list').html('<div class="text-center text-muted" style="padding:30px;">No competitors found.</div>');
            return;
        }

        var html = '';
        $.each(competitors, function (i, c) {
            var isSelected = bindSelectedIds.indexOf(c.id) !== -1;
            var selectedClass = isSelected ? ' selected' : '';
            var checkMark = isSelected ? '<i class="fa fa-check"></i>' : '';
            html += '<div class="competitor-item' + selectedClass + '" data-id="' + c.id + '">' +
                '<div class="competitor-check">' + checkMark + '</div>' +
                '<div class="competitor-info">' +
                    '<strong>' + escHtml(c.name) + '</strong>' +
                    '<small>' + escHtml(c.sku) + '</small>' +
                '</div>' +
            '</div>';
        });
        $('#competitor_list').html(html);
    }

    $('#competitor_search').on('input', function () {
        clearTimeout(competitorSearchTimer);
        var val = $(this).val();
        competitorSearchTimer = setTimeout(function () {
            loadCompetitorList(val);
        }, 300);
    });

    /* Toggle selection in modal */
    $(document).on('click', '.competitor-item', function () {
        var id = parseInt($(this).data('id'));
        var idx = bindSelectedIds.indexOf(id);
        if (idx === -1) {
            bindSelectedIds.push(id);
            $(this).addClass('selected').find('.competitor-check').html('<i class="fa fa-check"></i>');
        } else {
            bindSelectedIds.splice(idx, 1);
            $(this).removeClass('selected').find('.competitor-check').html('');
        }
    });

    /* Confirm bind */
    $('#confirm_bind_btn').on('click', function () {
        if (!bindProductId) return;

        $.post('{{ route("product_sale_visit.bind_competitors") }}', {
            product_id: bindProductId,
            competitor_ids: bindSelectedIds,
            _token: csrfToken
        }, function (res) {
            if (res.success) {
                $('#bind_competitors_modal').modal('hide');
                loadOwnProducts(ownCurrentPage);
                toastr.success('Competitors updated successfully.');
            } else {
                toastr.error(res.msg || 'Failed to bind competitors.');
            }
        }).fail(function () {
            toastr.error('Network error.');
        });
    });

    /* ===== SEARCH ===== */
    $('#global_search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            loadOwnProducts(1);
            loadOtherProducts(1);
        }, 400);
    });

    /* ===== PER PAGE CHANGE ===== */
    $('#own_per_page').on('change', function () { loadOwnProducts(1); });
    $('#other_per_page').on('change', function () { loadOtherProducts(1); });

    /* ===== HELPER ===== */
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ===== INITIAL LOAD ===== */
    loadOwnProducts(1);
    loadOtherProducts(1);
});
</script>
@endsection
