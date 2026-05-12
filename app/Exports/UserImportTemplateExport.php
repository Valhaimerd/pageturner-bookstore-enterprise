<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class UserImportTemplateExport implements FromArray
{
    public static function headers(): array
    {
        return [
            'name',
            'email',
            'role',
            'subscription_tier',
            'phone',
            'city',
            'province',
            'country',
            'password',
        ];
    }

    public function array(): array
    {
        return [
            static::headers(),
            [
                'Jane Customer',
                'jane.customer@example.com',
                'customer',
                'standard',
                '+639171234567',
                'Manila',
                'Metro Manila',
                'Philippines',
                'password123',
            ],
        ];
    }
}
