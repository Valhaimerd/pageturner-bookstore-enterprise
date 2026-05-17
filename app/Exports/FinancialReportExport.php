<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class FinancialReportExport implements FromArray
{
    public function __construct(
        protected string $reportType,
        protected array $rows
    ) {}

    public function array(): array
    {
        return match ($this->reportType) {
            'tax_report' => array_merge([
                ['Date', 'Taxable Revenue', 'Estimated Tax'],
            ], $this->rows),
            default => array_merge([
                ['Date', 'Completed Orders', 'Revenue'],
            ], $this->rows),
        };
    }
}
