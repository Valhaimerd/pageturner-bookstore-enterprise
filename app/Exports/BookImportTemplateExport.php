<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class BookImportTemplateExport implements FromArray
{
    public static function headers(): array
    {
        return ['isbn', 'title', 'author', 'price', 'stock', 'category', 'description'];
    }

    public function array(): array
    {
        return [
            static::headers(),
            ['9789715087228', 'Domain-Driven Design', 'Eric Evans', '1299.00', '25', 'Technology', 'Sample description'],
        ];
    }
}
