<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Tier</th>
                <th>Phone</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>
                        @if($redactPii)
                            {{ substr($user->email, 0, 1) }}***@{{ explode('@', $user->email)[1] ?? '' }}
                        @else
                            {{ $user->email }}
                        @endif
                    </td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>{{ ucfirst($user->subscription_tier) }}</td>
                    <td>{{ $redactPii ? '***' : $user->phone }}</td>
                    <td>{{ collect([$user->default_city, $user->default_province, $user->default_country])->filter()->implode(', ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
