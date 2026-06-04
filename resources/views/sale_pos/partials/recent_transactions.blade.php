@php
    $subtype = '';
@endphp
@if(!empty($transaction_sub_type))
    @php
        $subtype = '?sub_type='.$transaction_sub_type;
    @endphp
@endif

@if(!empty($transactions))
    <table class="table table-bordered table-hover" style="width:100%; margin-bottom:0;">
        <thead>
            <tr style="background:#f5f5f5;">
                <th style="width:35px; text-align:center;">#</th>
                <th>Invoice / Customer</th>
                <th style="width:120px; text-align:right;">Amount</th>
                <th style="width:380px; text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($transactions as $transaction)
            <tr class="cursor-pointer"
                title="Customer: {{$transaction->contact?->name}}
                    @if(!empty($transaction->contact->mobile) && $transaction->contact->is_default == 0)
                        | Mobile: {{$transaction->contact->mobile}}
                    @endif
                ">
                <td style="text-align:center; vertical-align:middle; color:#999;">
                    {{ $loop->iteration }}.
                </td>
                <td style="vertical-align:middle;">
                    <strong>{{ $transaction->invoice_no }}</strong>
                    <span class="text-muted">({{ $transaction->contact?->name }})</span>
                    @if(!empty($transaction->table))
                        &nbsp;<span class="label label-default" style="font-size:11px;">{{ $transaction->table->name }}</span>
                    @endif
                </td>
                <td class="display_currency" style="text-align:right; vertical-align:middle; font-weight:600;">
                    {{ $transaction->final_total }}
                </td>
                <td style="text-align:center; vertical-align:middle; white-space:nowrap;">

                    @if(auth()->user()->can('sell.update') || auth()->user()->can('direct_sell.update'))
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'edit'], [$transaction->id]).$subtype }}"
                       class="btn btn-xs btn-info" style="margin:2px;">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    @endif

                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'printInvoice'], [$transaction->id]) }}"
                       class="print-invoice-link btn btn-xs btn-default" style="margin:2px; border:1px solid #ccc;">
                        <i class="fa fa-print"></i> Print
                    </a>

                    @if(auth()->user()->can('sell.delete') || auth()->user()->can('direct_sell.delete'))
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$transaction->id]) }}"
                       class="delete-sale btn btn-xs btn-danger" style="margin:2px;">
                        <i class="fa fa-trash"></i> Delete
                    </a>
                    @endif

                    @if(!auth()->user()->can('sell.update') && auth()->user()->can('edit_pos_payment'))
                    <a href="{{ route('edit-pos-payment', ['id' => $transaction->id]) }}"
                       class="btn btn-xs btn-default" style="margin:2px; border:1px solid #ccc;"
                       title="@lang('lang_v1.add_edit_payment')">
                        <i class="fas fa-money-bill-alt"></i>
                    </a>
                    @endif

                    <a class="print-delivery-label btn btn-xs btn-default"
                       href="{{ route('sell.getDeliveryLabel', $transaction->id) }}"
                       style="margin:2px; border:1px solid #ccc;">
                        <i class="fa fa-print"></i> Delivery Label
                    </a>

                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p>@lang('sale.no_recent_transactions')</p>
@endif
