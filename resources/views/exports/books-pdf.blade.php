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
                @foreach($columns as $column)
                    <th>{{ \App\Exports\BooksExport::availableColumns()[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($books as $book)
                <tr>
                    @foreach($columns as $column)
                        <td>
                            @switch($column)
                                @case('category')
                                    {{ $book->category?->name }}
                                    @break
                                @case('published_at')
                                    {{ optional($book->published_at)->format('Y-m-d') }}
                                    @break
                                @default
                                    {{ $book->{$column} }}
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
