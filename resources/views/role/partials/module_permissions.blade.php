
@if(count($module_permissions) > 0)
    @php
        $module_role_permissions = [];

        if (!empty($role_permissions)) {
            $module_role_permissions = $role_permissions;
        }
    @endphp

    @foreach($module_permissions as $key => $value)
        <hr>

        <div class="row check_group">
            <div class="col-md-3">
                <h4>{{ $key }}</h4>
            </div>

            <div class="col-md-9">
                @foreach($value as $module_permission)
                    @php
                        if (empty($role_permissions) && !empty($module_permission['default'])) {
                            $module_role_permissions[] = $module_permission['value'];
                        }
                    @endphp

                    <div class="col-md-12">
                        <div class="checkbox">
                            <label>
                                @if(!empty($module_permission['is_radio']))
                                    {!! Form::radio(
                                        'radio_option[' . $module_permission['radio_input_name'] . ']',
                                        $module_permission['value'],
                                        in_array(
                                            $module_permission['value'],
                                            $module_role_permissions
                                        ),
                                        ['class' => 'input-icheck']
                                    ) !!}

                                    {{ $module_permission['label'] }}
                                @else
                                    {!! Form::checkbox(
                                        'permissions[]',
                                        $module_permission['value'],
                                        in_array(
                                            $module_permission['value'],
                                            $module_role_permissions
                                        ),
                                        ['class' => 'input-icheck']
                                    ) !!}

                                    {{ $module_permission['label'] }}
                                @endif
                            </label>
                        </div>

                        @if(isset($module_permission['end_group']) && $module_permission['end_group'])
                            <hr>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

<hr>

<div class="row check_group">
    <div class="col-md-3">
        <h4>Manufacturing</h4>
    </div>

    <div class="col-md-9">
        @php
            $role_permissions = $role_permissions ?? [];

            $manufacturing_permissions = [
                'manufacturing.access_recipe' => 'Access Recipe',
                'manufacturing.add_recipe' => 'Add Recipe',
                'manufacturing.edit_recipe' => 'Edit Recipe',
                'manufacturing.delete_recipe' => 'Delete Recipe',

                'manufacturing.access_production' => 'Access Production',
                'manufacturing.add_production' => 'Add Production',
                'manufacturing.edit_production' => 'Edit Production',
                'manufacturing.delete_production' => 'Delete Production',

                'manufacturing.access_ingredient_group' => 'Access Ingredient Group',
                'manufacturing.add_ingredient_group' => 'Add Ingredient Group',
                'manufacturing.edit_ingredient_group' => 'Edit Ingredient Group',
                'manufacturing.delete_ingredient_group' => 'Delete Ingredient Group',

                'manufacturing.access_settings' => 'Access Settings',
                'manufacturing.access_report' => 'Access Report',
            ];
        @endphp

        @foreach($manufacturing_permissions as $permission => $label)
            <div class="col-md-12">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox(
                            'permissions[]',
                            $permission,
                            in_array($permission, $role_permissions),
                            ['class' => 'input-icheck']
                        ) !!}

                        {{ $label }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

<hr>

<div class="row check_group">
    <div class="col-md-3">
        <h4>Accounting</h4>
    </div>

    <div class="col-md-9">
        @php
            $role_permissions = $role_permissions ?? [];

            $accounting_permissions = [
                'accounting.access_accounting_module' => 'Access Accounting Module',
                'accounting.manage_accounts' => 'Manage Accounts',

                'accounting.view_journal' => 'View Journal',
                'accounting.add_journal' => 'Add Journal',
                'accounting.edit_journal' => 'Edit Journal',
                'accounting.delete_journal' => 'Delete Journal',

                'accounting.map_transactions' => 'Map Transactions',

                'accounting.view_transfer' => 'View Transfer',
                'accounting.add_transfer' => 'Add Transfer',
                'accounting.edit_transfer' => 'Edit Transfer',
                'accounting.delete_transfer' => 'Delete Transfer',

                'accounting.manage_budget' => 'Manage Budget',
                'accounting.view_reports' => 'View Accounting Reports',
            ];
        @endphp

        @foreach($accounting_permissions as $permission => $label)
            <div class="col-md-12">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox(
                            'permissions[]',
                            $permission,
                            in_array($permission, $role_permissions),
                            ['class' => 'input-icheck']
                        ) !!}

                        {{ $label }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

