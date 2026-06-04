@extends('layouts.app')

@section('title', __('Daily Sale Summary'))

@section('content')
<style>
/* ── Page wrapper ── */
.dss-wrap {
    padding: 0 4px 30px;
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* ── Blue header banner ── */
.dss-banner {
    background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
    border-radius: 10px;
    padding: 20px 28px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
}
.dss-banner-left {
    display: flex;
    align-items: center;
    gap: 0;
}
.dss-banner-left h2 {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    letter-spacing: .3px;
    color: #fff !important;
    white-space: nowrap;
}
.dss-banner-divider {
    width: 1px;
    height: 32px;
    background: rgba(255,255,255,.4);
    margin: 0 20px;
    flex-shrink: 0;
}
.dss-banner-left .dss-sub {
    font-size: 15px;
    font-weight: 600;
    color: rgba(255,255,255,0.85) !important;
    margin: 0;
    white-space: nowrap;
}
.dss-date-chip {
    background: rgba(255,255,255,.2);
    border-radius: 20px;
    padding: 7px 20px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid rgba(255,255,255,.3);
}

/* ── Section card ── */
.dss-card {
    background: #fff;
    border: 1px solid #e4eaf3;
    border-radius: 8px;
    padding: 20px 22px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}

/* ── Section title ── */
.dss-section-title {
    font-size: 16px;
    font-weight: 700;
    color: #1565C0;
    padding-bottom: 8px;
    margin-bottom: 16px;
    border-bottom: 2px solid #e4eaf3;
}

/* ── Metric boxes ── */
.dss-metrics { display: flex; gap: 12px; flex-wrap: wrap; }
.dss-metric {
    flex: 1; min-width: 130px;
    border: 1px solid #e4eaf3;
    border-radius: 7px;
    padding: 14px 16px;
    text-align: center;
    background: #fafbfd;
}
.dss-metric .m-label {
    font-size: 11.5px;
    color: #9aa3b0;
    margin-bottom: 6px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.dss-metric .m-value {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.1;
}
.mv-dark   { color: #1a1a2e; }
.mv-green  { color: #16a34a; }
.mv-red    { color: #dc2626; }
.mv-blue   { color: #1976D2; }
.mv-orange { color: #d97706; }

/* ── Two-column layout ── */
.dss-cols { display: flex; gap: 20px; margin-top: 18px; }
.dss-col  { flex: 1; min-width: 0; }

/* ── Sub-section label ── */
.dss-sub-label {
    font-size: 13px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}

/* ── Tables ── */
.dss-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dss-tbl thead th {
    background: #f4f6f9;
    color: #888;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 8px 12px;
    border-bottom: 1px solid #e4eaf3;
    text-align: left;
}
.dss-tbl thead th.tr { text-align: right; }
.dss-tbl thead th.tc { text-align: center; }
.dss-tbl tbody td {
    padding: 9px 12px;
    color: #2d3748;
    border-bottom: 1px solid #f0f2f5;
}
.dss-tbl tbody td.tr { text-align: right; }
.dss-tbl tbody td.tc { text-align: center; }
.dss-tbl tbody tr:last-child td { border-bottom: none; }
.dss-tbl tfoot td {
    padding: 10px 12px;
    font-weight: 700;
    border-top: 2px solid #d0d9e8;
    background: #f8fafc;
    font-size: 13px;
}
.dss-tbl tfoot td.tr { text-align: right; }
.dss-tbl tfoot td.tc { text-align: center; }

/* ── Outstanding badge ── */
.out-badge { font-weight: 700; }
.out-zero   { color: #16a34a; }
.out-danger { color: #dc2626; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .dss-metrics { flex-direction: column; }
    .dss-cols    { flex-direction: column; }
}
</style>

<div class="content dss-wrap">

    {{-- ── Blue Banner ── --}}
    <div class="dss-banner">
        <div class="dss-banner-left">
            <h2><i class="fa fa-bar-chart" style="margin-right:9px;"></i>Daily Sale Summary</h2>
            <div class="dss-banner-divider"></div>
            <p class="dss-sub">{{ $business->name ?? '' }}</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="dss-date-chip">
                {{ $dateChip }}
            </div>
            <button id="btnSendTelegram"
                style="background:#0088cc;border:none;border-radius:20px;padding:7px 18px;
                       color:#fff;font-size:13px;font-weight:600;cursor:pointer;
                       display:flex;align-items:center;gap:6px;white-space:nowrap;
                       box-shadow:0 2px 6px rgba(0,0,0,0.25);">
                <i class="fa fa-telegram"></i> Send to Telegram
            </button>
        </div>
    </div>

    {{-- ── Repeat sections for each back-day date ── --}}
    @foreach($reports as $report)
    @php
        $date              = $report['date'];
        $sale_totals       = $report['sale_totals'];
        $total_amount      = $report['total_amount'];
        $paid_amount       = $report['paid_amount'];
        $due_amount        = $report['due_amount'];
        $payment_stats     = $report['payment_stats'];
        $payment_by_method = $report['payment_by_method'];
        $payment_method_labels = $report['payment_method_labels'];
        $ar_breakdown      = $report['ar_breakdown'];
        $aging             = $report['aging'];
        $aging_total_count = $report['aging_total_count'];
        $aging_total_amount= $report['aging_total_amount'];
        $reward_summary    = $report['reward_summary'];
    @endphp

    <div class="dss-date-report" data-date="{{ $date }}"
         style="margin-bottom:{{ !$loop->last ? '32px' : '0' }};">

    {{-- ──────────────────────────────────────── --}}
    {{-- Section 1: Daily Sale Overview           --}}
    {{-- ──────────────────────────────────────── --}}
    <div class="dss-card">
        <div class="dss-section-title">1. Daily Sale Overview</div>
        <div class="dss-metrics">
            <div class="dss-metric">
                <div class="m-label">Total Invoices</div>
                <div class="m-value mv-dark">{{ number_format($sale_totals->total_invoices ?? 0) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">Total Amount</div>
                <div class="m-value mv-dark">${{ number_format($total_amount, 2) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">Paid Amount</div>
                <div class="m-value mv-green">${{ number_format($paid_amount, 2) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">Due Amount</div>
                <div class="m-value mv-red">${{ number_format($due_amount, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────── --}}
    {{-- Section 2: Payment & Collections         --}}
    {{-- ──────────────────────────────────────── --}}
    <div class="dss-card">
        <div class="dss-section-title">2. Payment &amp; Collections</div>

        <div class="dss-metrics">
            <div class="dss-metric">
                <div class="m-label">Collected Invoices</div>
                <div class="m-value mv-dark">{{ number_format($payment_stats->collected_invoices ?? 0) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">Total Received</div>
                <div class="m-value mv-dark">${{ number_format($payment_stats->total_received ?? 0, 2) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">Today Sale Coll.</div>
                <div class="m-value mv-dark">${{ number_format($payment_stats->today_sale_coll ?? 0, 2) }}</div>
            </div>
            <div class="dss-metric">
                <div class="m-label">AR Collected</div>
                <div class="m-value mv-blue">${{ number_format($payment_stats->ar_collected ?? 0, 2) }}</div>
            </div>
        </div>

        <div class="dss-cols">
            <div class="dss-col">
                <div class="dss-sub-label" style="margin-top:18px;">Collection by Methods</div>
                <table class="dss-tbl">
                    <thead><tr><th>Method</th><th class="tr">Amount</th></tr></thead>
                    <tbody>
                        @forelse($payment_by_method as $pm)
                            @php $label = $payment_method_labels[$pm->method] ?? ucfirst(str_replace('_', ' ', $pm->method)); @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="tr"><strong>${{ number_format($pm->total, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="2" style="text-align:center;color:#bbb;padding:14px;">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>Total Received</strong></td>
                            <td class="tr mv-green"><strong>${{ number_format($payment_stats->total_received ?? 0, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="dss-col">
                <div class="dss-sub-label" style="margin-top:18px;">AR Collected Breakdown</div>
                <table class="dss-tbl">
                    <thead><tr><th>Origin Date</th><th class="tc">Inv</th><th class="tr">Amount</th></tr></thead>
                    <tbody>
                        @forelse($ar_breakdown as $ar)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($ar->origin_date)->format('d-M-Y') }}</td>
                                <td class="tc">{{ $ar->inv_count }}</td>
                                <td class="tr"><strong>${{ number_format($ar->amount, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center;color:#bbb;padding:14px;">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong>Total AR Collected</strong></td>
                            <td class="tr mv-blue"><strong>${{ number_format($payment_stats->ar_collected ?? 0, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="dss-sub-label" style="margin-top:22px;">Outstanding AR Aging Summary</div>
        <table class="dss-tbl">
            <thead><tr><th>Aging Bucket</th><th class="tc">Inv</th><th class="tr">Amount</th></tr></thead>
            <tbody>
                @foreach($aging as $bucket)
                    <tr>
                        <td>{{ $bucket['label'] }}</td>
                        <td class="tc">{{ $bucket['count'] }}</td>
                        <td class="tr">
                            @if($bucket['color'] === 'danger')
                                <span class="mv-red"><strong>${{ number_format($bucket['amount'], 2) }}</strong></span>
                            @elseif($bucket['color'] === 'orange')
                                <span class="mv-orange"><strong>${{ number_format($bucket['amount'], 2) }}</strong></span>
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

    {{-- ──────────────────────────────────────── --}}
    {{-- Section 3: Daily Reward Exchange         --}}
    {{-- ──────────────────────────────────────── --}}
    <div class="dss-card">
        <div class="dss-section-title">3. Daily Reward Exchange</div>
        <table class="dss-tbl">
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
                            {{ number_format($rw['ring_received']) }} Ring
                            ({{ $rw['received_cases_display'] }})
                        </td>
                        <td class="tr">
                            <span class="out-badge {{ $is_zero ? 'out-zero' : 'out-danger' }}">
                                {{ number_format($rw['outstanding_rings']) }} Ring
                                ({{ $rw['outstanding_cases_display'] }})
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:#bbb;padding:20px;">
                            No reward exchange products sold today
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    </div>{{-- /.dss-date-report --}}
    @endforeach

</div>
@endsection

@section('javascript')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
$(document).ready(function () {

    var businessName = '{{ addslashes($business->name ?? '') }}';

    // Format "2026-05-29" → "29-May-2026"
    function fmtDate(str) {
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var d = new Date(str);
        return String(d.getDate()).padStart(2,'0') + '-' + months[d.getMonth()] + '-' + d.getFullYear();
    }

    // Build an off-screen capture container: banner (with this date) + section cards
    function buildContainer(sectionEl, date) {
        var wrap = document.createElement('div');
        wrap.style.cssText = [
            'position:fixed', 'left:-9999px', 'top:0', 'z-index:-1',
            'width:480px', 'background:#e8edf2', 'padding:12px',
            'box-sizing:border-box', 'font-family:Segoe UI,Arial,sans-serif'
        ].join(';') + ';';

        // ── Banner ──
        var banner = document.querySelector('.dss-banner').cloneNode(true);
        banner.style.padding      = '12px 14px';
        banner.style.marginBottom = '10px';
        banner.style.flexWrap     = 'wrap';
        banner.style.gap          = '6px';

        var bannerLeft = banner.querySelector('.dss-banner-left');
        if (bannerLeft) { bannerLeft.style.flexWrap = 'wrap'; bannerLeft.style.gap = '4px'; }

        var h2 = banner.querySelector('.dss-banner-left h2');
        if (h2) h2.style.fontSize = '16px';

        var sub = banner.querySelector('.dss-banner-left .dss-sub');
        if (sub) sub.style.fontSize = '11px';

        // Update date chip to show this section's date only
        var chip = banner.querySelector('.dss-date-chip');
        if (chip) {
            chip.textContent = fmtDate(date);
            chip.style.fontSize = '11px';
            chip.style.padding  = '4px 10px';
        }

        // Remove send button from banner clone
        var btn = banner.querySelector('#btnSendTelegram');
        if (btn) btn.parentNode && btn.parentNode.removeChild(btn);

        wrap.appendChild(banner);

        // ── Section content ──
        var content = sectionEl.cloneNode(true);
        content.style.margin = '0';

        content.querySelectorAll('.dss-card').forEach(function (c) {
            c.style.padding = '12px 12px 10px'; c.style.marginBottom = '10px';
        });
        content.querySelectorAll('.dss-section-title').forEach(function (t) {
            t.style.fontSize = '12px'; t.style.marginBottom = '10px';
        });
        content.querySelectorAll('.dss-metrics').forEach(function (m) {
            m.style.display = 'grid'; m.style.gridTemplateColumns = '1fr 1fr';
            m.style.gap = '7px'; m.style.marginBottom = '10px';
        });
        content.querySelectorAll('.dss-metric').forEach(function (m) {
            m.style.minWidth = '0'; m.style.padding = '9px 8px';
        });
        content.querySelectorAll('.dss-metric .m-label').forEach(function (l) { l.style.fontSize = '8px'; });
        content.querySelectorAll('.dss-metric .m-value').forEach(function (v) { v.style.fontSize = '15px'; });
        content.querySelectorAll('.dss-cols').forEach(function (c) {
            c.style.flexDirection = 'column'; c.style.gap = '10px'; c.style.marginTop = '10px';
        });
        content.querySelectorAll('.dss-col').forEach(function (c) {
            c.style.width = '100%'; c.style.minWidth = '0';
        });
        content.querySelectorAll('.dss-sub-label').forEach(function (l) {
            l.style.fontSize = '10px'; l.style.marginTop = '8px';
        });
        content.querySelectorAll('.dss-tbl thead th').forEach(function (th) {
            th.style.fontSize = '8.5px'; th.style.padding = '5px 7px';
        });
        content.querySelectorAll('.dss-tbl tbody td, .dss-tbl tfoot td').forEach(function (td) {
            td.style.fontSize = '10.5px'; td.style.padding = '6px 7px';
        });

        wrap.appendChild(content);
        document.body.appendChild(wrap);
        return wrap;
    }

    function captureSection(sectionEl, date) {
        var container = buildContainer(sectionEl, date);
        return html2canvas(container, { scale: 2, useCORS: true })
            .then(function (canvas) {
                document.body.removeChild(container);
                return canvas;
            })
            .catch(function (err) {
                document.body.removeChild(container);
                throw err;
            });
    }

    function sendImage(base64image, date) {
        return new Promise(function (resolve) {
            $.ajax({
                url: '{{ route("reports.daily-sale-summary.send-telegram") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', image: base64image, date: date },
                success: function (r) { resolve({ ok: r.success, msg: r.message }); },
                error:   function ()  { resolve({ ok: false, msg: 'Network error' }); }
            });
        });
    }

    $('#btnSendTelegram').click(function (e) {
        e.preventDefault();
        var $btn         = $(this);
        var originalText = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin"></i> Sending...').prop('disabled', true);

        // Reverse so oldest date is sent first (e.g. 28-May → 29-May)
        var sections     = Array.from(document.querySelectorAll('.dss-date-report')).reverse();
        var promise      = Promise.resolve();
        var successCount = 0;
        var errors       = [];

        sections.forEach(function (section) {
            var date = section.getAttribute('data-date') || '';
            promise = promise
                .then(function () { return captureSection(section, date); })
                .then(function (canvas) { return sendImage(canvas.toDataURL('image/png'), date); })
                .then(function (result) {
                    if (result.ok) successCount++;
                    else           errors.push(result.msg);
                });
        });

        promise
            .then(function () {
                $btn.html(originalText).prop('disabled', false);
                if (successCount > 0) {
                    toastr.success('Sent ' + successCount + ' report(s) to Telegram successfully!');
                } else {
                    toastr.error('Failed: ' + errors.join(' | '));
                }
            })
            .catch(function (err) {
                $btn.html(originalText).prop('disabled', false);
                toastr.error('Snapshot error: ' + err.message);
            });
    });

});
</script>
@endsection
