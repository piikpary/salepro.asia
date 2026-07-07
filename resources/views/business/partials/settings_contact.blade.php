<!--Purchase related settings -->
<div class="pos-tab-content">
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('default_credit_limit',__('lang_v1.default_credit_limit') . ':') !!}
                {!! Form::text('common_settings[default_credit_limit]', $common_settings['default_credit_limit'] ?? '', ['class' => 'form-control input_number',
                'placeholder' => __('lang_v1.default_credit_limit'), 'id' => 'default_credit_limit']); !!}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('credit_term_days', __('Credit Term (Days)') . ':') !!}
                {!! Form::text('common_settings[credit_term_days]', $common_settings['credit_term_days'] ?? '', ['class' => 'form-control input_number', 'placeholder' => __('Credit Term (Days)'), 'id' => 'credit_term_days']); !!}
            </div>
        </div>
    </div>
</div>