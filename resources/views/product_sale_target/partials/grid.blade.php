<input type="hidden" id="grid_start_date" value="{{ $start_date }}">
<input type="hidden" id="grid_end_date" value="{{ $end_date }}">

<div class="table-responsive" style="margin-top:10px;">
    <table class="table table-bordered" id="grid-table" style="margin-bottom:0;">
        <thead>
            <tr>
                <th style="width:200px; vertical-align:middle;">Sale Man</th>
                @foreach($variations as $variation)
                    <th class="text-center variation-th" data-variation-id="{{ $variation->id }}" style="vertical-align:middle; min-width:120px;">
                        {{ $variation->product_name }}
                        @if(!empty($variation->sub_sku))
                            <br><small class="text-muted" style="font-weight:normal;">{{ $variation->sub_sku }}</small>
                        @endif
                    </th>
                @endforeach
                <th class="text-center" style="width:110px; vertical-align:middle; color:#3c8dbc;">Total</th>
                <th class="text-center" style="width:70px; vertical-align:middle;">Del</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                @php $rowTotal = 0; @endphp
                <tr class="grid-row">
                    <td class="salesperson-cell" style="vertical-align:middle;">
                        <strong>{{ $user->first_name }} {{ $user->last_name }}</strong><br>
                        <small>{{ $user->username }}</small>
                    </td>
                    @foreach($variations as $variation)
                        @php
                            $qty = $existing[$user->id][$variation->id] ?? 0;
                            $rowTotal += $qty;
                        @endphp
                        <td class="text-center" style="vertical-align:middle;">
                            <input type="number"
                                class="form-control target-qty-input"
                                data-user-id="{{ $user->id }}"
                                data-variation-id="{{ $variation->id }}"
                                value="{{ $qty }}"
                                min="0">
                        </td>
                    @endforeach
                    <td class="text-center" style="vertical-align:middle;">
                        <strong class="row-total" style="color:#3c8dbc; font-size:15px;">{{ $rowTotal }}</strong>
                    </td>
                    <td class="text-center" style="vertical-align:middle;">
                        <button type="button" class="btn btn-remove-grid-row" title="Remove row"
                            style="background:none; border:none; padding:0; cursor:pointer; color:#e74c3c; font-size:22px; line-height:1; opacity:0.85;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'">
                            <i class="fa fa-times-circle"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="font-weight:700; font-size:14px;">TOTAL</td>
                @foreach($variations as $variation)
                    @php
                        $colTotal = 0;
                        foreach ($users as $u) { $colTotal += $existing[$u->id][$variation->id] ?? 0; }
                    @endphp
                    <td class="text-center col-total" style="font-weight:700; color:#27a745; font-size:15px;">{{ $colTotal }}</td>
                @endforeach
                @php
                    $grandTotal = 0;
                    foreach ($users as $u) { foreach ($variations as $v) { $grandTotal += $existing[$u->id][$v->id] ?? 0; } }
                @endphp
                <td class="text-center foot-total" style="font-weight:700; color:#27a745; font-size:15px;">{{ $grandTotal }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
