<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note - {{ $dn->invoice_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; padding: 20px; }
        h2 { margin: 0; }
        .header { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table th, table td { border: 1px solid #ccc; padding: 6px 10px; }
        table thead { background: #f5f5f5; }
        .text-right { text-align: right; }
        .info-grid { display: flex; gap: 20px; margin-bottom: 20px; }
        .info-block { flex: 1; }
        .info-block dt { font-weight: bold; }
        .footer-sig { margin-top: 50px; display: flex; gap: 40px; }
        .footer-sig div { flex: 1; text-align: center; border-top: 1px solid #333; padding-top: 5px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
<button onclick="window.print()" style="margin-bottom:15px;" class="no-print">🖨 Print</button>

<div class="header">
    <h2>{{ $business->name ?? '' }}</h2>
    <h3>Delivery Note</h3>
</div>

<table style="border:none;">
    <tr>
        <td style="border:none;width:50%;">
            <strong>Delivery Note No.:</strong> {{ $dn->invoice_no }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($dn->transaction_date)->format('d-M-Y') }}<br>
            <strong>Status:</strong> {{ ucfirst($dn->status) }}
        </td>
        <td style="border:none;width:50%;">
            <strong>Customer:</strong> {{ $dn->customer_name }}<br>
            <strong>Location:</strong> {{ $dn->location_name }}<br>
            <strong>Delivery Person:</strong> {{ $dn->delivery_person_name ?? '—' }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Product</th>
            <th class="text-right">Delivered Qty</th>
            <th>Unit</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($dn_lines as $i => $line)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $line->product_name }}{{ $line->variation_name !== 'DUMMY' ? ' - '.$line->variation_name : '' }}</td>
            <td class="text-right">{{ number_format($line->quantity, 2) }}</td>
            <td>{{ $line->unit_name }}</td>
            <td class="text-right">${{ number_format($line->unit_price, 2) }}</td>
            <td class="text-right">${{ number_format($line->quantity * $line->unit_price, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-right"><strong>Total Amount:</strong></td>
            <td class="text-right"><strong>${{ number_format($dn->final_total, 2) }}</strong></td>
        </tr>
    </tfoot>
</table>

@if($dn->additional_notes)
<p><strong>Note:</strong> {{ $dn->additional_notes }}</p>
@endif

<div class="footer-sig">
    <div>Prepared By</div>
    <div>Received By</div>
    <div>Authorized By</div>
</div>
</body>
</html>
