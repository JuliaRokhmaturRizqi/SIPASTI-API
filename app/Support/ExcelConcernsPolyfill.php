<?php

namespace Maatwebsite\Excel\Concerns {
    if (! interface_exists(FromCollection::class)) {
        interface FromCollection
        {
            public function collection();
        }
    }

    if (! interface_exists(WithHeadings::class)) {
        interface WithHeadings
        {
            public function headings(): array;
        }
    }

    if (! interface_exists(WithMapping::class)) {
        interface WithMapping
        {
            public function map($row): array;
        }
    }

    if (! interface_exists(WithStyles::class)) {
        interface WithStyles
        {
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet);
        }
    }

    if (! interface_exists(ShouldAutoSize::class)) {
        interface ShouldAutoSize
        {
        }
    }
}

namespace PhpOffice\PhpSpreadsheet\Style {
    if (! class_exists(Fill::class)) {
        class Fill
        {
            public const FILL_SOLID = 'solid';
        }
    }
}

namespace PhpOffice\PhpSpreadsheet\Worksheet {
    if (! class_exists(Worksheet::class)) {
        class Worksheet
        {
            public function getStyle(string $cellCoordinate): object
            {
                return new class {
                    public function applyFromArray(array $styleArray): self
                    {
                        return $this;
                    }
                };
            }
        }
    }
}
