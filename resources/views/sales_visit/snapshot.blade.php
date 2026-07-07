<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: #aaeef3;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .summary-wrapper {
            width: 760px;
            margin: 0 auto;
            background-color: #aaeef3;
        }

        /* ── Header ── */
        .header-card {
            background-color: #26c6da;
            border-radius: 10px;
            padding: 18px 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .header-icon {
            background-color: #00bcd4;
            border-radius: 8px;
            width: 16px;
            height: 40px;
            margin-right: 18px;
            flex-shrink: 0;
        }
        .header-info h2 { font-weight: bold; font-size: 24px; color: #000; margin-bottom: 4px; }
        .header-info p  { color: #333; font-size: 15px; line-height: 1.5; }

        /* ── Cards ── */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .stat-title { color: #000; font-size: 20px; font-weight: bold; margin-bottom: 14px; }
        .stat-value  { font-size: 34px; color: #333; font-family: 'Times New Roman', serif; margin-bottom: 10px; }
        .stat-remaining { color: #f44336; font-size: 14px; font-weight: bold; }

        /* ── Top row: 2 columns side-by-side ── */
        .top-row { display: flex; gap: 16px; margin-bottom: 0; }
        .col-left  { width: 42%; display: flex; flex-direction: column; gap: 16px; }
        .col-right { width: 58%; }
        .col-right .stat-card { height: 100%; margin-bottom: 0; }
        .col-left  .stat-card { margin-bottom: 0; }

        /* ── Today Vs Yesterday ── */
        .target-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }
        .change-box {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        /* ── Pure CSS Pie Chart ── */
        .pie-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 0 6px;
        }
        .pie-label-top {
            font-size: 13px;
            color: #4caf50;
            font-weight: bold;
            align-self: flex-start;
            margin-bottom: 10px;
        }
        .pie-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .pie-label-bottom {
            font-size: 13px;
            color: #f44336;
            font-weight: bold;
            align-self: flex-end;
            margin-top: 10px;
        }
        .pie-legend {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #555;
            margin-top: 14px;
        }
        .legend-box {
            display: inline-block;
            width: 14px; height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }
        .bg-green { background-color: #4caf50; }
        .bg-red   { background-color: #f44336; }
        .text-green { color: #4caf50; }
        .text-red   { color: #f44336; }

        /* ── Full-width table section ── */
        .section-table { margin-top: 16px; }

        .custom-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .custom-table th {
            background-color: #c8e6c9; color: #2e7d32;
            padding: 12px 8px; font-size: 14px;
            text-align: center; border: 1px solid #eee;
            white-space: nowrap;
        }
        .custom-table td {
            padding: 12px 8px; text-align: center;
            border: 1px solid #eee; font-size: 14px;
            font-weight: bold; color: #333;
        }
        .custom-table tfoot td { background-color: #fff9c4; font-weight: bold; }
        hr { border: 0; border-top: 1px solid #ddd; margin: 12px 0; }

        /* ── Mapping compare rows ── */
        .map-own-row  { background-color: #f5f5f5; }
        .map-comp-row { background-color: #fff; }
        .map-mkt-row  { background-color: #fff9c4; }
        .map-bar-wrap { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; }
        .map-bar-wrap:last-child { margin-bottom: 0; }
        .map-bar-bg   { flex: 1; height: 8px; border-radius: 3px; overflow: hidden; }
        .map-bar-fill { height: 100%; }
    </style>
</head>
<body>
<div class="summary-wrapper">

    {{-- ── Header ── --}}
    <div class="header-card">
        <div class="header-icon"></div>
        <div class="header-info">
            <h2>Daily Sale Visit Summary</h2>
            <p>Date: {{ \Carbon\Carbon::parse($today)->format('n/j/Y') }}<br>Business : {{ $business_name }}</p>
        </div>
    </div>

    {{-- ── Top 2-column row ── --}}
    <div class="top-row">

        {{-- Left column: Total Sale Visit + Today Vs Yesterday --}}
        <div class="col-left">
            <div class="stat-card">
                <div class="stat-title">Total Sale Visit</div>
                <div class="stat-value">
                    {{ $todays_visits_count }} / {{ $total_target }}
                    <span style="font-size:26px; color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">({{ $overall_variance }}%)</span>
                </div>
                <div class="stat-remaining" style="color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">
                    {{ abs($overall_remaining) }} {{ $overall_remaining <= 0 ? 'remaining' : 'over target' }}
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Today Vs Yesterday</div>
                <div class="target-row">
                    <span>Today's Visit</span>
                    <span>
                        <span style="color:#333;">({{ $todays_visits_count }} / {{ $total_target }})</span>
                        <span class="text-red"> {{ $overall_variance }}%</span>
                    </span>
                </div>
                <div class="target-row">
                    <span>Yesterday's Visit</span>
                    <span>
                        <span style="color:#333;">({{ $yesterdays_visits_count }} / {{ $yesterday_target }})</span>
                        <span class="text-red"> {{ $yesterday_variance }}%</span>
                    </span>
                </div>
                <hr>
                <div class="target-row">
                    <span>Days-Over-Days</span>
                    <span class="change-box" style="
                        color: {{ $dod_change >= 0 ? '#4caf50' : '#e53935' }};
                        background: {{ $dod_change >= 0 ? '#e8f5e9' : '#ffebee' }};">
                        {{ $dod_change >= 0 ? '▲' : '▼' }}
                        {{ $dod_change > 0 ? '+' : '' }}{{ $dod_change }}%
                    </span>
                </div>
            </div>
        </div>

        {{-- Right column: Own vs Other Product (CSS pie) --}}
        <div class="col-right">
            <div class="stat-card" style="display:flex;flex-direction:column;align-items:center;">
                <div class="stat-title" style="text-align:center;width:100%;">Own vs other Product</div>

                <div class="pie-wrap" style="width:100%;">
                    <div class="pie-label-top">Own product {{ $overall_own_pct }}%</div>

                    @php
                        $ownDeg = round($overall_own_pct * 3.6);  // % -> degrees
                    @endphp
                    <div class="pie-circle" style="background: conic-gradient(
                        #4caf50 0deg {{ $ownDeg }}deg,
                        #f44336 {{ $ownDeg }}deg 360deg
                    );"></div>

                    <div class="pie-label-bottom">Other Product {{ $overall_other_pct }}%</div>
                </div>

                <div class="pie-legend">
                    <span class="legend-box bg-green"></span><span class="text-green">Own product</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="legend-box bg-red"></span><span class="text-red">Other Product</span>
                </div>
            </div>
        </div>

    </div>{{-- end top-row --}}

    {{-- ── Sale Visit Report Table (full width) ── --}}
    <div class="section-table">
        <div class="stat-card" style="margin-bottom:0;">
            <div class="stat-title">Sale Visit Report</div>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th style="text-align:left;">SE's Name</th>
                        <th>Visit</th>
                        <th>Remain</th>
                        <th>Own / Other</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales_report as $rep_id => $row)
                    @php $rep_result = $row['own_pct'] > $row['other_pct'] ? 'WIN' : 'LOSE'; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align:left;"><em>{{ $row['name'] }}</em></td>
                        <td>
                            {{ $row['qty_visit'] }} / {{ $row['target'] }}
                            (<span class="{{ $row['variance'] >= 80 ? 'text-green' : 'text-red' }}">{{ $row['variance'] }}%</span>)
                        </td>
                        <td><span class="{{ $row['remaining'] >= 0 ? 'text-green' : 'text-red' }}">{{ $row['remaining'] }}</span></td>
                        <td>
                            <span class="text-green">{{ $row['own_pct'] }}%</span>
                            <span style="color:#aaa;"> / </span>
                            <span class="text-red">{{ $row['other_pct'] }}%</span>
                        </td>
                        <td><span style="background:{{ $rep_result === 'WIN' ? '#e8f5e9' : '#ffebee' }}; color:{{ $rep_result === 'WIN' ? '#2e7d32' : '#c62828' }}; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; display:inline-block;">{{ $rep_result }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6">No visits recorded for today.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php $total_result = $overall_own_pct > $overall_other_pct ? 'WIN' : 'LOSE'; @endphp
                    <tr>
                        <td colspan="2" style="text-align:center;">Total</td>
                        <td>
                            {{ $todays_visits_count }} / {{ $total_target }}
                            (<span class="{{ $overall_variance >= 80 ? 'text-green' : 'text-red' }}">{{ $overall_variance }}%</span>)
                        </td>
                        <td><span class="{{ $overall_remaining <= 0 ? 'text-green' : 'text-red' }}">{{ $overall_remaining }}</span></td>
                        <td>
                            <span class="text-green">{{ $overall_own_pct }}%</span>
                            <span style="color:#aaa;"> / </span>
                            <span class="text-red">{{ $overall_other_pct }}%</span>
                        </td>
                        <td><span style="background:{{ $total_result === 'WIN' ? '#e8f5e9' : '#ffebee' }}; color:{{ $total_result === 'WIN' ? '#2e7d32' : '#c62828' }}; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; display:inline-block;">{{ $total_result }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ── Mapping Product Compare ── --}}
    @if(!empty($mapping_data))
    <div class="section-table">
        <div class="stat-card" style="margin-bottom:0;">
            <div class="stat-title">Mapping Product Compare</div>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="text-align:left; width:30%;">Product</th>
                        <th style="width:8%;">Qty</th>
                        <th style="width:20%;">1 VS 1</th>
                        <th style="width:30%;">Total Compare</th>
                        <th style="width:12%;">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mapping_data as $gi => $group)
                    @php $rowspan = count($group['competitors']) + 1; @endphp
                    {{-- Own product row --}}
                    <tr class="map-own-row">
                        <td style="text-align:left; font-weight:bold; color:#333;">{{ ($gi + 1) . '. ' . $group['own_name'] }}</td>
                        <td style="color:#4caf50; font-weight:bold;">{{ (int)$group['own_qty'] }}</td>
                        <td><span style="background:#e0e0e0; padding:2px 8px; border-radius:3px; font-size:12px; color:#555; font-weight:bold;">Base Own</span></td>
                        <td rowspan="{{ $rowspan }}" style="vertical-align:middle; padding:8px;">
                            <div class="map-bar-wrap">
                                <span style="color:#4caf50; font-size:12px; min-width:36px; text-align:right; font-weight:bold;">Own</span>
                                <div class="map-bar-bg" style="background:#e8f5e9;">
                                    <div class="map-bar-fill" style="width:{{ $group['own_pct'] }}%; background:#4caf50;"></div>
                                </div>
                                <span style="color:#333; font-size:12px; white-space:nowrap; min-width:80px;">{{ (int)$group['own_qty'] }} ({{ $group['own_pct'] }}%)</span>
                            </div>
                            <div class="map-bar-wrap">
                                <span style="color:#f44336; font-size:12px; min-width:36px; text-align:right; font-weight:bold;">Other</span>
                                <div class="map-bar-bg" style="background:#ffebee;">
                                    <div class="map-bar-fill" style="width:{{ $group['other_pct'] }}%; background:#f44336;"></div>
                                </div>
                                <span style="color:#333; font-size:12px; white-space:nowrap; min-width:80px;">{{ (int)$group['total_competitor_qty'] }} ({{ $group['other_pct'] }}%)</span>
                            </div>
                        </td>
                        <td rowspan="{{ $rowspan }}" style="vertical-align:middle;">
                            <span style="background:{{ $group['result'] === 'WIN' ? '#e8f5e9' : '#ffebee' }}; color:{{ $group['result'] === 'WIN' ? '#2e7d32' : '#c62828' }}; padding:4px 12px; border-radius:4px; font-weight:bold; font-size:13px; display:inline-block;">
                                {{ $group['result'] }}
                            </span>
                        </td>
                    </tr>
                    {{-- Competitor rows (Total Compare + Result consumed by rowspan) --}}
                    @foreach($group['competitors'] as $comp)
                    <tr class="map-comp-row">
                        <td style="text-align:left; color:#888; padding-left:24px; font-size:13px; font-weight:normal;">↳ {{ $comp['name'] }}</td>
                        <td style="color:{{ $comp['win'] ? '#4caf50' : '#f44336' }}; font-weight:bold; font-size:13px;">{{ (int)$comp['qty'] }}</td>
                        <td style="color:{{ $comp['win'] ? '#4caf50' : '#f44336' }}; font-weight:bold; font-size:12px;">{{ $comp['result'] }}</td>
                    </tr>
                    @endforeach
                    {{-- Market Size row --}}
                    <tr class="map-mkt-row">
                        <td colspan="2" style="text-align:right; color:#888; font-size:13px; font-style:italic; font-weight:bold;">Market Size</td>
                        <td style="font-weight:bold; color:#333;">{{ (int)$group['market_size'] }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
</body>
</html>