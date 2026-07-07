<div class="pos-tab-content">
    <div class="row">
        <div class="col-md-12">
            <h4>Stamp Rule Settings</h4>
        </div>

        <div class="col-md-12">
            <div class="well well-sm bg-light-gray" style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <strong>Enable Stamp Point</strong>
                </div>
                <div>
                    <input type="hidden" name="enable_stamp_point" value="0">

                    <label class="stamp-switch">
                        <input type="checkbox"
                               name="enable_stamp_point"
                               id="enable_stamp_point"
                               value="1"
                               {{ !empty($business->enable_stamp_point) ? 'checked' : '' }}>
                        <span class="stamp-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-md-12" id="stamp_rule_setting_area" style="{{ empty($business->enable_stamp_point) ? 'display:none;' : '' }}">
            <button type="button" class="btn btn-primary btn-sm" id="add_stamp_product_group">
                <i class="fa fa-plus"></i> Add Rule
            </button>

            <br><br>

            <div id="stamp_rule_group_container">
                @php
                    $grouped_stamp_rules = $stamp_rules->groupBy('product_id');
                    $row_index = 0;
                    $group_index = 0;
                @endphp

                @forelse($grouped_stamp_rules as $product_id => $rules)
                    <div class="stamp-product-group panel panel-default">
                        <div class="panel-heading stamp-group-heading">
                            <div class="stamp-group-left">
                                <span class="stamp-group-toggle"
                                      data-toggle="collapse"
                                      data-target="#stamp_group_body_{{ $group_index }}">
                                    <i class="fa fa-chevron-down stamp-group-icon"></i>
                                    <strong>Product</strong>
                                </span>

                                <select class="form-control select2 stamp-group-product-select">
                                    <option value="">Select Product</option>
                                    @foreach($business_products as $product)
                                        <option value="{{ $product->id }}" {{ $product_id == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                            @if(!empty($product->sku))
                                                - {{ $product->sku }}
                                            @elseif(!empty($product->sub_sku))
                                                - {{ $product->sub_sku }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="stamp-group-actions">
                                <button type="button" class="btn btn-success btn-xs add_stamp_tier_row">
                                    <i class="fa fa-plus"></i> Add Tier
                                </button>

                                <button type="button" class="btn btn-danger btn-xs remove_stamp_product_group">
                                    <i class="fa fa-trash"></i> Remove Group
                                </button>
                            </div>
                        </div>

                        <div id="stamp_group_body_{{ $group_index }}" class="panel-collapse collapse in stamp-group-collapse">
                            <div class="panel-body">
                                <table class="table table-bordered table-striped stamp-tier-table">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;">Qty</th>
                                            <th style="width:18%;">Point</th>
                                            <th style="width:32%;">Claim Product</th>
                                            <th style="width:15%;">Claim Qty</th>
                                            <th style="width:10%;">Active</th>
                                            <th style="width:10%;">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach($rules as $rule)
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="stamp_rule_id[{{ $row_index }}]" value="{{ $rule->id }}">
                                                    <input type="hidden" name="stamp_product_id[{{ $row_index }}]" class="stamp_product_id_hidden" value="{{ $rule->product_id }}">

                                                    <input type="text"
                                                           name="stamp_qty[{{ $row_index }}]"
                                                           class="form-control"
                                                           value="{{ rtrim(rtrim((string) $rule->stamp_qty, '0'), '.') }}"
                                                           placeholder="500">
                                                </td>

                                                <td>
                                                    <input type="text"
                                                           name="earn_point[{{ $row_index }}]"
                                                           class="form-control"
                                                           value="{{ rtrim(rtrim((string) ($rule->earn_point ?? 0), '0'), '.') }}"
                                                           placeholder="500">
                                                </td>

                                                <td>
                                                    <select name="claim_product_id[{{ $row_index }}]" class="form-control select2 stamp-claim-product-select">
                                                        <option value="">Select Claim Product</option>
                                                        @foreach($business_products as $product)
                                                            <option value="{{ $product->id }}" {{ ($rule->claim_product_id ?? null) == $product->id ? 'selected' : '' }}>
                                                                {{ $product->name }}
                                                                @if(!empty($product->sku))
                                                                    - {{ $product->sku }}
                                                                @elseif(!empty($product->sub_sku))
                                                                    - {{ $product->sub_sku }}
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                <td>
                                                    <input type="text"
                                                           name="claim_qty[{{ $row_index }}]"
                                                           class="form-control"
                                                           value="{{ rtrim(rtrim((string) ($rule->claim_qty ?? 0), '0'), '.') }}"
                                                           placeholder="12">
                                                </td>

                                                <td class="text-center">
                                                    <input type="hidden" name="stamp_is_active[{{ $row_index }}]" value="0">
                                                    <input type="checkbox"
                                                           name="stamp_is_active[{{ $row_index }}]"
                                                           value="1"
                                                           {{ $rule->is_active ? 'checked' : '' }}>
                                                </td>

                                                <td class="text-center">
                                                    <button type="button"
                                                            class="btn btn-danger btn-xs remove_stamp_tier_row"
                                                            data-rule-id="{{ $rule->id }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>

                                            @php
                                                $row_index++;
                                            @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @php
                        $group_index++;
                    @endphp
                @empty
                    <div class="stamp-product-group panel panel-default">
                        <div class="panel-heading stamp-group-heading">
                            <div class="stamp-group-left">
                                <span class="stamp-group-toggle"
                                      data-toggle="collapse"
                                      data-target="#stamp_group_body_0">
                                    <i class="fa fa-chevron-down stamp-group-icon"></i>
                                    <strong>Product</strong>
                                </span>

                                <select class="form-control select2 stamp-group-product-select">
                                    <option value="">Select Product</option>
                                    @foreach($business_products as $product)
                                        <option value="{{ $product->id }}">
                                            {{ $product->name }}
                                            @if(!empty($product->sku))
                                                - {{ $product->sku }}
                                            @elseif(!empty($product->sub_sku))
                                                - {{ $product->sub_sku }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="stamp-group-actions">
                                <button type="button" class="btn btn-success btn-xs add_stamp_tier_row">
                                    <i class="fa fa-plus"></i> Add Tier
                                </button>

                                <button type="button" class="btn btn-danger btn-xs remove_stamp_product_group">
                                    <i class="fa fa-trash"></i> Remove Group
                                </button>
                            </div>
                        </div>

                        <div id="stamp_group_body_0" class="panel-collapse collapse in stamp-group-collapse">
                            <div class="panel-body">
                                <table class="table table-bordered table-striped stamp-tier-table">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;">Qty</th>
                                            <th style="width:18%;"> Point</th>
                                            <th style="width:32%;">Claim Product</th>
                                            <th style="width:15%;">Claim Qty</th>
                                            <th style="width:10%;">Active</th>
                                            <th style="width:10%;">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="stamp_rule_id[0]" value="">
                                                <input type="hidden" name="stamp_product_id[0]" class="stamp_product_id_hidden" value="">

                                                <input type="text" name="stamp_qty[0]" class="form-control" placeholder="500">
                                            </td>

                                            <td>
                                                <input type="text" name="earn_point[0]" class="form-control" placeholder="500">
                                            </td>

                                            <td>
                                                <select name="claim_product_id[0]" class="form-control select2 stamp-claim-product-select">
                                                    <option value="">Select Claim Product</option>
                                                    @foreach($business_products as $product)
                                                        <option value="{{ $product->id }}">
                                                            {{ $product->name }}
                                                            @if(!empty($product->sku))
                                                                - {{ $product->sku }}
                                                            @elseif(!empty($product->sub_sku))
                                                                - {{ $product->sub_sku }}
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td>
                                                <input type="text" name="claim_qty[0]" class="form-control" placeholder="12">
                                            </td>

                                            <td class="text-center">
                                                <input type="hidden" name="stamp_is_active[0]" value="0">
                                                <input type="checkbox" name="stamp_is_active[0]" value="1" checked>
                                            </td>

                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-xs remove_stamp_tier_row">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @php
                        $row_index = 1;
                        $group_index = 1;
                    @endphp
                @endforelse
            </div>

            <div id="deleted_stamp_rule_ids_container"></div>
            <input type="hidden" id="stamp_rule_next_index" value="{{ $row_index }}">
            <input type="hidden" id="stamp_group_next_index" value="{{ $group_index }}">
        </div>
    </div>

    <table style="display:none;">
        <tbody>
            <tr id="stamp_tier_row_template">
                <td>
                    <input type="hidden" name="stamp_rule_id[__INDEX__]" value="">
                    <input type="hidden" name="stamp_product_id[__INDEX__]" class="stamp_product_id_hidden" value="">

                    <input type="text" name="stamp_qty[__INDEX__]" class="form-control" placeholder="500">
                </td>

                <td>
                    <input type="text" name="earn_point[__INDEX__]" class="form-control" placeholder="500">
                </td>

                <td>
                    <select name="claim_product_id[__INDEX__]" class="form-control stamp-claim-product-select-template">
                        <option value="">Select Claim Product</option>
                        @foreach($business_products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name }}
                                @if(!empty($product->sku))
                                    - {{ $product->sku }}
                                @elseif(!empty($product->sub_sku))
                                    - {{ $product->sub_sku }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </td>

                <td>
                    <input type="text" name="claim_qty[__INDEX__]" class="form-control" placeholder="12">
                </td>

                <td class="text-center">
                    <input type="hidden" name="stamp_is_active[__INDEX__]" value="0">
                    <input type="checkbox" name="stamp_is_active[__INDEX__]" value="1" checked>
                </td>

                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-xs remove_stamp_tier_row">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

    <div id="stamp_product_group_template" style="display:none;">
        <div class="stamp-product-group panel panel-default">
            <div class="panel-heading stamp-group-heading">
                <div class="stamp-group-left">
                    <span class="stamp-group-toggle"
                          data-toggle="collapse"
                          data-target="#stamp_group_body___GROUP_INDEX__">
                        <i class="fa fa-chevron-down stamp-group-icon"></i>
                        <strong>Earn Product</strong>
                    </span>

                    <select class="form-control stamp-group-product-select-template">
                        <option value="">Select Product</option>
                        @foreach($business_products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name }}
                                @if(!empty($product->sku))
                                    - {{ $product->sku }}
                                @elseif(!empty($product->sub_sku))
                                    - {{ $product->sub_sku }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="stamp-group-actions">
                    <button type="button" class="btn btn-success btn-xs add_stamp_tier_row">
                        <i class="fa fa-plus"></i> Add Tier
                    </button>

                    <button type="button" class="btn btn-danger btn-xs remove_stamp_product_group">
                        <i class="fa fa-trash"></i> Remove Group
                    </button>
                </div>
            </div>

            <div id="stamp_group_body___GROUP_INDEX__" class="panel-collapse collapse in stamp-group-collapse">
                <div class="panel-body">
                    <table class="table table-bordered table-striped stamp-tier-table">
                        <thead>
                            <tr>
                                <th style="width:15%;">Qty</th>
                                <th style="width:18%;">Earn Point</th>
                                <th style="width:32%;">Claim Product</th>
                                <th style="width:15%;">Claim Qty</th>
                                <th style="width:10%;">Active</th>
                                <th style="width:10%;">Action</th>
                            </tr>
                        </thead>

                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .stamp-switch {
            position: relative;
            display: inline-block;
            width: 56px;
            height: 28px;
        }

        .stamp-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .stamp-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 30px;
        }

        .stamp-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        .stamp-switch input:checked + .stamp-slider {
            background-color: #00a65a;
        }

        .stamp-switch input:checked + .stamp-slider:before {
            transform: translateX(28px);
        }

        .stamp-product-group {
            margin-bottom: 15px;
        }

        .stamp-group-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stamp-group-left {
            width: 55%;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stamp-group-toggle {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .stamp-group-product-select,
        .stamp-group-product-select-template {
            max-width: 260px;
        }

        .stamp-group-actions {
            white-space: nowrap;
        }
    </style>

    <script type="text/javascript">
        $(document).on('show.bs.collapse', '.stamp-group-collapse', function () {
            $(this)
                .closest('.stamp-product-group')
                .find('.stamp-group-icon')
                .removeClass('fa-chevron-right')
                .addClass('fa-chevron-down');
        });

        $(document).on('hide.bs.collapse', '.stamp-group-collapse', function () {
            $(this)
                .closest('.stamp-product-group')
                .find('.stamp-group-icon')
                .removeClass('fa-chevron-down')
                .addClass('fa-chevron-right');
        });

        $(document).on('click', '.stamp-group-product-select, .stamp-group-product-select-template, .stamp-group-actions button', function (e) {
            e.stopPropagation();
        });

        $(document).on('click', '#add_stamp_product_group', function () {
            setTimeout(function () {
                $('.stamp-product-group').each(function (index) {
                    var collapseId = 'stamp_group_body_' + index;

                    $(this).find('.stamp-group-collapse').attr('id', collapseId);
                    $(this).find('.stamp-group-toggle').attr('data-target', '#' + collapseId);
                });

                $('#stamp_group_next_index').val($('.stamp-product-group').length);
            }, 100);
        });
    </script>
</div>