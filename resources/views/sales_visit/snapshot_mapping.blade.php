<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #aaeef3; padding: 20px; font-family: Arial, sans-serif; }
        .summary-wrapper { width: 760px; margin: 0 auto; background-color: #aaeef3; }

        .header-card { background-color: #26c6da; border-radius: 10px; padding: 18px 25px; margin-bottom: 20px; display: flex; align-items: center; }
        .header-icon { background-color: #00bcd4; border-radius: 8px; width: 16px; height: 40px; margin-right: 18px; flex-shrink: 0; }
        .header-info h2 { font-weight: bold; font-size: 24px; color: #000; margin-bottom: 4px; }
        .header-info p  { color: #333; font-size: 15px; line-height: 1.5; }

        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-title { color: #000; font-size: 20px; font-weight: bold; margin-bottom: 14px; }

        .custom-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .custom-table th { background-color: #c8e6c9; color: #2e7d32; padding: 12px 8px; font-size: 14px; text-align: center; border: 1px solid #eee; white-space: nowrap; }
        .custom-table td { padding: 12px 8px; text-align: center; border: 1px solid #eee; font-size: 14px; font-weight: bold; color: #333; }

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

    <div class="header-card">
        <div class="header-icon"></div>
        <div class="header-info">
            <h2>Mapping Product Compare</h2>
            <p>Date: {{ \Carbon\Carbon::parse($today)->format('n/j/Y') }}<br>Business : {{ $business_name }}</p>
        </div>
    </div>

    <div class="stat-card">
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
                @foreach($group['competitors'] as $comp)
                <tr class="map-comp-row">
                    <td style="text-align:left; color:#888; padding-left:24px; font-size:13px; font-weight:normal;">↳ {{ $comp['name'] }}</td>
                    <td style="color:{{ $comp['win'] ? '#4caf50' : '#f44336' }}; font-weight:bold; font-size:13px;">{{ (int)$comp['qty'] }}</td>
                    <td style="color:{{ $comp['win'] ? '#4caf50' : '#f44336' }}; font-weight:bold; font-size:12px;">{{ $comp['result'] }}</td>
                </tr>
                @endforeach
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
</body>
</html>
