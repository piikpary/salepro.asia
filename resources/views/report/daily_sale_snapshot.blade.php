<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #e8edf2;
            padding: 12px;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        /* ── Wrapper — 456px so body padding fills 480px window: 456 + 12 + 12 = 480 ── */
        .wrap { width: 456px; margin: 0 auto; }

        /* ── Banner ── */
        .banner {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            border-radius: 10px; padding: 16px 22px; margin-bottom: 14px;
            display: flex; align-items: center; justify-content: space-between; color: #fff;
        }
        .banner-left { display: flex; align-items: center; }
        .banner-title { font-size: 20px; font-weight: 700; color: #fff; }
        .banner-divider { width: 1px; height: 24px; background: rgba(255,255,255,.4); margin: 0 14px; }
        .banner-biz { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.88); }
        .banner-date {
            background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3);
            border-radius: 14px; padding: 5px 14px; font-size: 12px; font-weight: 600;
            color: #fff; white-space: nowrap;
        }

        /* ── Card ── */
        .card {
            background: #fff; border-radius: 8px; padding: 14px 18px; margin-bottom: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
        }
        .section-title {
            font-size: 14px; font-weight: 700; color: #1565C0;
            border-bottom: 2px solid #e4eaf3; padding-bottom: 7px; margin-bottom: 13px;
        }

        /* ── Metrics — 2 × 2 grid ── */
        .metrics { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .metric {
            flex: 0 0 calc(50% - 4px);
            border: 1px solid #e4eaf3; border-radius: 7px;
            padding: 10px 10px; text-align: center; background: #fafbfd;
        }
        .m-label {
            font-size: 9px; color: #9aa3b0; text-transform: uppercase;
            letter-spacing: .5px; font-weight: 600; margin-bottom: 5px;
        }
        .m-value { font-size: 22px; font-weight: 700; line-height: 1.1; }
        .c-dark   { color: #1a1a2e; }
        .c-green  { color: #16a34a; }
        .c-red    { color: #dc2626; }
        .c-blue   { color: #1976D2; }
        .c-orange { color: #d97706; }

        /* ── Columns — stacked vertically to keep portrait ratio ── */
        .two-col { display: flex; flex-direction: column; gap: 10px; }
        .col { width: 100%; min-width: 0; }
        .sub-label {
            font-size: 12px; font-weight: 700; color: #333;
            margin-bottom: 7px; margin-top: 13px;
        }

        /* ── Tables ── */
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        thead th {
            background: #f4f6f9; color: #888; font-size: 9.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
            padding: 7px 9px; border-bottom: 1px solid #e4eaf3; text-align: left;
        }
        thead th.tr { text-align: right; }
        thead th.tc { text-align: center; }
        tbody td { padding: 8px 9px; color: #2d3748; border-bottom: 1px solid #f0f2f5; }
        tbody td.tr { text-align: right; }
        tbody td.tc { text-align: center; }
        tbody tr:last-child td { border-bottom: none; }
        tfoot td {
            padding: 8px 9px; font-weight: 700;
            border-top: 2px solid #d0d9e8; background: #f8fafc; font-size: 11.5px;
        }
        tfoot td.tr { text-align: right; }
        tfoot td.tc { text-align: center; }

        .out-badge  { font-weight: 700; }
        .out-zero   { color: #16a34a; }
        .out-danger { color: #dc2626; }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ── Banner ── --}}
    <div class="banner">
        <div class="banner-left">
            <span class="banner-title">📊 Daily Sale Summary</span>
            <div class="banner-divider"></div>
            <span class="banner-biz">{{ $business->name ?? '' }}</span>
        </div>
        <div class="banner-date">{{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}</div>
    </div>

    {{-- ── Section 1 ── --}}
    <div class="card">
        <div class="section-title">1. Daily Sale Overview</div>
        <div class="metrics">
            <div class="metric">
                <div class="m-label">Total Invoices</div>
                <div class="m-value c-dark">{{ number_format($sale_totals->total_invoices ?? 0) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">Total Amount</div>
                <div class="m-value c-dark">${{ number_format($total_amount, 2) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">Paid Amount</div>
                <div class="m-value c-green">${{ number_format($paid_amount, 2) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">Due Amount</div>
                <div class="m-value c-red">${{ number_format($due_amount, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- ── Section 2 ── --}}
    <div class="card">
        <div class="section-title">2. Payment &amp; Collections</div>
        <div class="metrics">
            <div class="metric">
                <div class="m-label">Collected Invoices</div>
                <div class="m-value c-dark">{{ number_format($payment_stats->collected_invoices ?? 0) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">Total Received</div>
                <div class="m-value c-dark">${{ number_format($payment_stats->total_received ?? 0, 2) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">Today Sale Coll.</div>
                <div class="m-value c-dark">${{ number_format($payment_stats->today_sale_coll ?? 0, 2) }}</div>
            </div>
            <div class="metric">
                <div class="m-label">AR Collected</div>
                <div class="m-value c-blue">${{ number_format($payment_stats->ar_collected ?? 0, 2) }}</div>
            </div>
        </div>

        {{-- Collection by Methods + AR Breakdown — side by side ── --}}
        <div class="two-col">
            <div class="col">
                <div class="sub-label">Collection by Methods</div>
                <table>
                    <thead><tr><th>Method</th><th class="tr">Amount</th></tr></thead>
                    <tbody>
                        @forelse($payment_by_method as $pm)
                            <tr>
                                <td>{{ $payment_method_labels[$pm->method] ?? ucfirst(str_replace('_',' ',$pm->method)) }}</td>
                                <td class="tr"><strong>${{ number_format($pm->total, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="2" style="text-align:center;color:#bbb;padding:8px;">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>Total Received</strong></td>
                            <td class="tr c-green"><strong>${{ number_format($payment_stats->total_received ?? 0, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="col">
                <div class="sub-label">AR Collected Breakdown</div>
                <table>
                    <thead><tr><th>Origin Date</th><th class="tc">Inv</th><th class="tr">Amount</th></tr></thead>
                    <tbody>
                        @forelse($ar_breakdown ?? [] as $ar)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($ar->origin_date)->format('d-M-Y') }}</td>
                                <td class="tc">{{ $ar->inv_count }}</td>
                                <td class="tr"><strong>${{ number_format($ar->amount, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center;color:#bbb;padding:8px;">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong>Total AR Collected</strong></td>
                            <td class="tr c-blue"><strong>${{ number_format($payment_stats->ar_collected ?? 0, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="sub-label" style="margin-top:15px;">Outstanding AR Aging Summary</div>
        <table>
            <thead><tr><th>Aging Bucket</th><th class="tc">Inv</th><th class="tr">Amount</th></tr></thead>
            <tbody>
                @foreach($aging as $bucket)
                    <tr>
                        <td>{{ $bucket['label'] }}</td>
                        <td class="tc">{{ $bucket['count'] }}</td>
                        <td class="tr">
                            @if($bucket['color'] === 'danger')
                                <span class="c-red"><strong>${{ number_format($bucket['amount'], 2) }}</strong></span>
                            @elseif($bucket['color'] === 'orange')
                                <span class="c-orange"><strong>${{ number_format($bucket['amount'], 2) }}</strong></span>
                            @else
                                <strong>${{ number_format($bucket['amount'], 2) }}</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total Outstanding AR</strong></td>
                    <td class="tc"><strong>{{ $aging_total_count }}</strong></td>
                    <td class="tr"><strong>${{ number_format($aging_total_amount, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ── Section 3 ── --}}
    @if(!empty($reward_summary))
    <div class="card">
        <div class="section-title">3. Daily Reward Exchange</div>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="tr">Stock Out</th>
                    <th class="tr">Ring Received</th>
                    <th class="tr">Outstanding Qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reward_summary as $rw)
                    @php $is_zero = $rw['outstanding_rings'] == 0; @endphp
                    <tr>
                        <td>{{ $rw['product_name'] }}</td>
                        <td class="tr">{{ number_format($rw['stock_out_qty']) }} Case</td>
                        <td class="tr">
                            {{ number_format($rw['ring_received']) }} Ring<br>
                            <span style="font-size:10px;color:#555;">({{ $rw['received_cases_display'] }})</span>
                        </td>
                        <td class="tr">
                            <span class="out-badge {{ $is_zero ? 'out-zero' : 'out-danger' }}">
                                {{ number_format($rw['outstanding_rings']) }} Ring<br>
                                <span style="font-size:10px;">({{ $rw['outstanding_cases_display'] }})</span>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:#bbb;padding:14px;">
                            No reward exchange products sold today
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

</div>
</body>
</html>
