<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
    </style>
</head>
<body>
    <h1>Audit Trail Export</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Event</th>
                <th>Model</th>
                <th>Values</th>
                <th>Context</th>
            </tr>
        </thead>
        <tbody>
            @foreach($audits as $audit)
                <tr>
                    <td>{{ $audit->id }}</td>
                    <td>{{ $audit->user?->name ?: 'System' }}</td>
                    <td>{{ $audit->event }}</td>
                    <td>{{ class_basename((string) $audit->auditable_type) }} #{{ $audit->auditable_id }}</td>
                    <td>
                        <strong>Old:</strong> {{ json_encode($audit->old_values) }}<br>
                        <strong>New:</strong> {{ json_encode($audit->new_values) }}
                    </td>
                    <td>{{ $audit->method ?: 'CLI' }} {{ $audit->url ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
