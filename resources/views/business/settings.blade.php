@extends('layouts.app')
@section('title', __('business.business_settings'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('business.business_settings')</h1>
    <br>
    @include('layouts.partials.search_settings')
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\BusinessController::class, 'postBusinessSettings']), 'method' => 'post', 'id' => 'bussiness_edit_form',
           'files' => true ]) !!}
    <div class="row">
        <div class="col-xs-12">
       <!--  <pos-tab-container> -->
        <div class="col-xs-12 pos-tab-container">
            <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 pos-tab-menu">
                <div class="list-group">
                    <a href="#" class="list-group-item text-center active">@lang('business.business')</a>
                    <a href="#" class="list-group-item text-center">@lang('business.tax') @show_tooltip(__('tooltip.business_tax'))</a>
                    <a href="#" class="list-group-item text-center">@lang('business.product')</a>
                    <a href="#" class="list-group-item text-center">@lang('contact.contact')</a>
                    <a href="#" class="list-group-item text-center">@lang('business.sale')</a>
                    <a href="#" class="list-group-item text-center">@lang('sale.pos_sale')</a>
                    <a href="#" class="list-group-item text-center">@lang('purchase.purchases')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.payment')</a>
                    <a href="#" class="list-group-item text-center">@lang('business.dashboard')</a>
                    <a href="#" class="list-group-item text-center">@lang('business.system')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.prefixes')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.email_settings')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.sms_settings')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.reward_point_settings')</a>
			 <a href="#" class="list-group-item text-center">Stamp Rule Settings</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.modules')</a>
                    <a href="#" class="list-group-item text-center">@lang('lang_v1.custom_labels')</a>
                </div>
            </div>
            <div class="col-lg-10 col-md-10 col-sm-10 col-xs-10 pos-tab">
                <!-- tab 1 start -->
                @include('business.partials.settings_business')
                <!-- tab 1 end -->
                <!-- tab 2 start -->
                @include('business.partials.settings_tax')
                <!-- tab 2 end -->
                <!-- tab 3 start -->
                @include('business.partials.settings_product')

                @include('business.partials.settings_contact')
                <!-- tab 3 end -->
                <!-- tab 4 start -->
                @include('business.partials.settings_sales')
                @include('business.partials.settings_pos')
                <!-- tab 4 end -->
                <!-- tab 5 start -->
                @include('business.partials.settings_purchase')

                @include('business.partials.settings_payment')
                <!-- tab 5 end -->
                <!-- tab 6 start -->
                @include('business.partials.settings_dashboard')
                <!-- tab 6 end -->
                <!-- tab 7 start -->
                @include('business.partials.settings_system')
                <!-- tab 7 end -->
                <!-- tab 8 start -->
                @include('business.partials.settings_prefixes')
                <!-- tab 8 end -->
                <!-- tab 9 start -->
                @include('business.partials.settings_email')
                <!-- tab 9 end -->
                <!-- tab 10 start -->
                @include('business.partials.settings_sms')
                <!-- tab 10 end -->
                <!-- tab 11 start -->
                @include('business.partials.settings_reward_point')

		@include('business.partials.stamp_rule_settings')
                <!-- tab 11 end -->
                <!-- tab 12 start -->
                @include('business.partials.settings_modules')
                <!-- tab 12 end -->
                @include('business.partials.settings_custom_labels')
            </div>
        </div>
        <!--  </pos-tab-container> -->
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 text-center">
            <button class="btn btn-danger btn-big" type="submit">@lang('business.update_settings')</button>
        </div>
    </div>
{!! Form::close() !!}
</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">
    __page_leave_confirmation('#bussiness_edit_form');
    $(document).on('ifToggled', '#use_superadmin_settings', function() {
        if ($('#use_superadmin_settings').is(':checked')) {
            $('#toggle_visibility').addClass('hide');
            $('.test_email_btn').addClass('hide');
        } else {
            $('#toggle_visibility').removeClass('hide');
            $('.test_email_btn').removeClass('hide');
        }
    });

    $(document).ready(function(){

    
        $('#test_email_btn').click( function() {
            var data = {
                mail_driver: $('#mail_driver').val(),
                mail_host: $('#mail_host').val(),
                mail_port: $('#mail_port').val(),
                mail_username: $('#mail_username').val(),
                mail_password: $('#mail_password').val(),
                mail_encryption: $('#mail_encryption').val(),
                mail_from_address: $('#mail_from_address').val(),
                mail_from_name: $('#mail_from_name').val(),
            };
            $.ajax({
                method: 'post',
                data: data,
                url: "{{ action([\App\Http\Controllers\BusinessController::class, 'testEmailConfiguration']) }}",
                dataType: 'json',
                success: function(result) {
                    if (result.success == true) {
                        swal({
                            text: result.msg,
                            icon: 'success'
                        });
                    } else {
                        swal({
                            text: result.msg,
                            icon: 'error'
                        });
                    }
                },
            });
        });

        $('#test_sms_btn').click( function() {
            var test_number = $('#test_number').val();
            if (test_number.trim() == '') {
                toastr.error('{{__("lang_v1.test_number_is_required")}}');
                $('#test_number').focus();

                return false;
            }

            var data = {
                url: $('#sms_settings_url').val(),
                send_to_param_name: $('#send_to_param_name').val(),
                msg_param_name: $('#msg_param_name').val(),
                request_method: $('#request_method').val(),
                param_1: $('#sms_settings_param_key1').val(),
                param_2: $('#sms_settings_param_key2').val(),
                param_3: $('#sms_settings_param_key3').val(),
                param_4: $('#sms_settings_param_key4').val(),
                param_5: $('#sms_settings_param_key5').val(),
                param_6: $('#sms_settings_param_key6').val(),
                param_7: $('#sms_settings_param_key7').val(),
                param_8: $('#sms_settings_param_key8').val(),
                param_9: $('#sms_settings_param_key9').val(),
                param_10: $('#sms_settings_param_key10').val(),

                param_val_1: $('#sms_settings_param_val1').val(),
                param_val_2: $('#sms_settings_param_val2').val(),
                param_val_3: $('#sms_settings_param_val3').val(),
                param_val_4: $('#sms_settings_param_val4').val(),
                param_val_5: $('#sms_settings_param_val5').val(),
                param_val_6: $('#sms_settings_param_val6').val(),
                param_val_7: $('#sms_settings_param_val7').val(),
                param_val_8: $('#sms_settings_param_val8').val(),
                param_val_9: $('#sms_settings_param_val9').val(),
                param_val_10: $('#sms_settings_param_val10').val(),
                test_number: test_number
            };

            $.ajax({
                method: 'post',
                data: data,
                url: "{{ action([\App\Http\Controllers\BusinessController::class, 'testSmsConfiguration']) }}",
                dataType: 'json',
                success: function(result) {
                    if (result.success == true) {
                        swal({
                            text: result.msg,
                            icon: 'success'
                        });
                    } else {
                        swal({
                            text: result.msg,
                            icon: 'error'
                        });
                    }
                },
            });

        });

        $('select.custom_labels_products').change(function(){
            var value = $(this).val();
            var textarea = $(this).parents('div.custom_label_product_div').find('div.custom_label_product_dropdown');

            if (value == 'dropdown') {
                textarea.removeClass('hide');
            } else {
                textarea.addClass('hide');
            }
        });

        var stampRuleIndex = parseInt($('#stamp_rule_next_index').val() || 1);

        function initStampSelect2(container) {
            if ($.fn.select2) {
                container.find('.select2').select2();
            }
        }

        function refreshStampProductHidden(group) {
            var productId = group.find('.stamp-group-product-select').val();

            group.find('.stamp_product_id_hidden').val(productId);
        }

        function refreshAllStampProductHidden() {
            $('.stamp-product-group').each(function () {
                refreshStampProductHidden($(this));
            });
        }

        $(document).on('change ifChanged', '#enable_stamp_point', function () {
            if ($('#enable_stamp_point').is(':checked')) {
                $('#stamp_rule_setting_area').slideDown(150);
            } else {
                $('#stamp_rule_setting_area').slideUp(150);
            }
        });

        $(document).on('change', '.stamp-group-product-select', function () {
            refreshStampProductHidden($(this).closest('.stamp-product-group'));
        });

        $(document).on('click', '#add_stamp_product_group', function () {
            var template = $('#stamp_product_group_template').html();

            $('#stamp_rule_group_container').append(template);

            var group = $('#stamp_rule_group_container .stamp-product-group:last');

            group.find('.stamp-group-product-select-template')
                .removeClass('stamp-group-product-select-template')
                .addClass('select2 stamp-group-product-select');

            initStampSelect2(group);

            group.find('.add_stamp_tier_row').trigger('click');
        });

        $(document).on('click', '.add_stamp_tier_row', function () {
            var group = $(this).closest('.stamp-product-group');
            var template = $('#stamp_tier_row_template').prop('outerHTML');

            template = template.replace('id="stamp_tier_row_template"', '');
            template = template.replace(/__INDEX__/g, stampRuleIndex);

            group.find('.stamp-tier-table tbody').append(template);

            var row = group.find('.stamp-tier-table tbody tr:last');

            row.find('.stamp-claim-product-select-template')
                .removeClass('stamp-claim-product-select-template')
                .addClass('select2 stamp-claim-product-select');

            initStampSelect2(row);

            refreshStampProductHidden(group);

            stampRuleIndex++;
            $('#stamp_rule_next_index').val(stampRuleIndex);
        });

        $(document).on('click', '.remove_stamp_tier_row', function () {
            var row = $(this).closest('tr');
            var group = $(this).closest('.stamp-product-group');
            var ruleId = $(this).data('rule-id');

            if (ruleId) {
                $('#deleted_stamp_rule_ids_container').append(
                    '<input type="hidden" name="deleted_stamp_rule_ids[]" value="' + ruleId + '">'
                );
            }

            if (group.find('.stamp-tier-table tbody tr').length > 1) {
                row.remove();
            } else {
                row.find('input[type="text"]').val('');
                row.find('input[type="hidden"][name^="stamp_rule_id"]').val('');
                row.find('select').val('').trigger('change');
                row.find('input[type="checkbox"]').prop('checked', true);
                refreshStampProductHidden(group);
            }
        });

        $(document).on('click', '.remove_stamp_product_group', function () {
            var group = $(this).closest('.stamp-product-group');

            group.find('.remove_stamp_tier_row').each(function () {
                var ruleId = $(this).data('rule-id');

                if (ruleId) {
                    $('#deleted_stamp_rule_ids_container').append(
                        '<input type="hidden" name="deleted_stamp_rule_ids[]" value="' + ruleId + '">'
                    );
                }
            });

            if ($('#stamp_rule_group_container .stamp-product-group').length > 1) {
                group.remove();
            } else {
                group.find('input[type="text"]').val('');
                group.find('input[type="hidden"][name^="stamp_rule_id"]').val('');
                group.find('select').val('').trigger('change');
                group.find('input[type="checkbox"]').prop('checked', true);
                refreshStampProductHidden(group);
            }
        });

        initStampSelect2($(document));
        refreshAllStampProductHidden();
    });
</script>
@endsection