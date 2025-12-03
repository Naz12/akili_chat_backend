<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice['invoice_number'] }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #6366f1;
            margin: 0;
            font-size: 28px;
        }
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .invoice-info-left, .invoice-info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .invoice-info-right {
            text-align: right;
        }
        .info-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        .info-value {
            margin-bottom: 15px;
        }
        .line-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .line-items th {
            background-color: #f3f4f6;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
            font-weight: bold;
        }
        .line-items td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .line-items tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .total-label {
            display: table-cell;
            width: 70%;
            text-align: right;
            padding-right: 20px;
            font-weight: bold;
        }
        .total-value {
            display: table-cell;
            width: 30%;
            text-align: right;
            font-size: 16px;
        }
        .grand-total {
            border-top: 2px solid #6366f1;
            padding-top: 10px;
            font-size: 18px;
            font-weight: bold;
            color: #6366f1;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-paid {
            background-color: #10b981;
            color: white;
        }
        .status-pending {
            background-color: #f59e0b;
            color: white;
        }
        .status-cancelled {
            background-color: #ef4444;
            color: white;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
    </div>

    <div class="invoice-info">
        <div class="invoice-info-left">
            <div class="info-label">Bill To:</div>
            <div class="info-value">
                <strong>{{ $user->name }}</strong><br>
                {{ $user->email }}
            </div>
        </div>
        <div class="invoice-info-right">
            <div class="info-label">Invoice Number:</div>
            <div class="info-value">{{ $invoice['invoice_number'] }}</div>
            
            <div class="info-label">Invoice Date:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($invoice['created_at'])->format('F d, Y') }}</div>
            
            @if(isset($invoice['due_date']))
            <div class="info-label">Due Date:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($invoice['due_date'])->format('F d, Y') }}</div>
            @endif
            
            <div class="info-label">Status:</div>
            <div class="info-value">
                <span class="status-badge status-{{ strtolower($invoice['status']) }}">
                    {{ strtoupper($invoice['status']) }}
                </span>
            </div>
        </div>
    </div>

    @if(isset($invoice['period_start']) && isset($invoice['period_end']))
    <div style="margin-bottom: 20px;">
        <div class="info-label">Billing Period:</div>
        <div class="info-value">
            {{ \Carbon\Carbon::parse($invoice['period_start'])->format('F d, Y') }} - 
            {{ \Carbon\Carbon::parse($invoice['period_end'])->format('F d, Y') }}
        </div>
    </div>
    @endif

    <table class="line-items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice['line_items'] as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="text-right">{{ $item['quantity'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }} {{ $invoice['currency'] }}</td>
                <td class="text-right">{{ number_format($item['total'], 2) }} {{ $invoice['currency'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <div class="total-label">Subtotal:</div>
            <div class="total-value">{{ number_format($invoice['amount'], 2) }} {{ $invoice['currency'] }}</div>
        </div>
        <div class="total-row grand-total">
            <div class="total-label">Total:</div>
            <div class="total-value">{{ number_format($invoice['amount'], 2) }} {{ $invoice['currency'] }}</div>
        </div>
    </div>

    @if(isset($invoice['paid_at']))
    <div style="margin-top: 30px; text-align: right;">
        <div class="info-label">Paid On:</div>
        <div class="info-value">{{ \Carbon\Carbon::parse($invoice['paid_at'])->format('F d, Y') }}</div>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is an automated invoice generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
    </div>
</body>
</html>

