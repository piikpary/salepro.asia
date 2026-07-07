<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Return - {{ $dr->invoice_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; padding: 20px; }
        h2 { margin: 0; }
        .header { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table th, table td { border: 1px solid #ccc; padding: 6px 10px; }
        table thead { background: #f5f5f5; }
        .text-right { text-align: right; }
        .footer-sig { margin-top: 50px; display: flex; gap: 40px; }
        .footer-sig div { flex: 1; text-align: center; border-top: 1px solid #333; padding-top: 5px; }
        @media print { button { display: none; } }
        @page { margin: 15mm; }
    </style>
</head>
<body>
<button onclick="window.print()" style="margin-bottom:15px;" class="no-print">&#128438; Print</button>

<div class="header">
    <h2>{{ $business->name ?? '' }}</h2>
    <h3>Delivery Return</h3>
</div>

<table style="border:none;">
    <tr>
        <td style="border:none;width:50%;">
            <strong>Return No.:</strong> {{ $dr->invoice_no }}<br>
            <strong>Parent DN No.:</strong> {{ $dn->invoice_no ?? '—' }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($dr->transaction_date)->format('d-M-Y') }}<br>
            <strong>Status:</strong> {{ ucfirst($dr->status) }}
        </td>
        <td style="border:none;width:50%;">
            <strong>Customer:</strong> {{ $dr->customer_name }}<br>
            <strong>Mobile:</strong> {{ $dr->customer_mobile ?? '—' }}<br>
            <strong>Location:</strong> {{ $dr->location_name }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Product Name</th>
            <th class="text-right">Delivered Qty</th>
            <th class="text-right">Return Qty</th>
            <th class="text-right">Good Stock</th>
            <th class="text-right">Damaged</th>
        </tr>
    </thead>
    <tbody>
        @php
            $qty_p = (int) session('business.quantity_precision', 2);
            // Smart format: preserve actual decimals even when precision=0 (0.5 shows as 0.5 not 1)
            $fmt_qty = function($n) use ($qty_p) {
                $n = (float) $n; $p = $qty_p;
                if ($n != floor($n)) {
                    $str = rtrim(rtrim(sprintf('%.10f', $n), '0'), '.');
                    $dot = strpos($str, '.'); $actual = ($dot !== false) ? (strlen($str) - $dot - 1) : 0;
                    $p = max($p, $actual);
                }
                return number_format($n, $p);
            };
        @endphp
        @foreach($dr_lines as $i => $line)
        @php
            $dn_qty = $dn_qty_map[$line->product_id . '_' . $line->variation_id] ?? 0;
        @endphp
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $line->product_name }}{{ $line->variation_name !== 'DUMMY' ? ' - '.$line->variation_name : '' }}</td>
            <td class="text-right">{{ $fmt_qty($dn_qty) }} {{ $line->unit_name }}</td>
            <td class="text-right">{{ $fmt_qty($line->quantity_returned ?? 0) }} {{ $line->unit_name }}</td>
            <td class="text-right">{{ $fmt_qty($line->good_stock_qty ?? 0) }}</td>
            <td class="text-right">{{ $fmt_qty($line->damaged_qty ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-right"><strong>Total Return Qty:</strong></td>
            <td class="text-right"><strong>{{ $fmt_qty($dr_lines->sum('quantity_returned')) }}</strong></td>
            <td class="text-right"><strong>{{ $fmt_qty($dr_lines->sum('good_stock_qty')) }}</strong></td>
            <td class="text-right"><strong>{{ $fmt_qty($dr_lines->sum('damaged_qty')) }}</strong></td>
        </tr>
    </tfoot>
</table>

@if($dr->additional_notes)
<p><strong>Return note:</strong> {{ $dr->additional_notes }}</p>
@endif

<div class="footer-sig">
    <div>Prepared By</div>
    <div>Received By</div>
    <div>Authorized By</div>
</div>
</body>
</html>
