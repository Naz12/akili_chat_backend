<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 20px;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #667eea;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        td {
            padding: 6px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p style="color: #666; margin-bottom: 15px;">Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    @if(is_array($row))
                        @foreach($headers as $header)
                            <td>{{ $row[$header] ?? '-' }}</td>
                        @endforeach
                    @elseif(is_object($row))
                        @foreach($headers as $header)
                            <td>{{ $row->{$header} ?? (isset($row[$header]) ? $row[$header] : '-') }}</td>
                        @endforeach
                    @else
                        @foreach($headers as $header)
                            <td>-</td>
                        @endforeach
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align: center; padding: 20px;">
                        No data available
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>Total Records: {{ $data->count() }}</p>
        <p>Page 1</p>
    </div>
</body>
</html>

