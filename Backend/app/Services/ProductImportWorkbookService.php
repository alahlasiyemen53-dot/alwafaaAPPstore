<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

final class ProductImportWorkbookService
{
    public const HEADERS = [
        'product_ref', 'name_ar', 'name_en', 'brand', 'category', 'description_ar',
        'variant_ref', 'size', 'shade', 'price', 'availability_type', 'supplier', 'supplier_item_number',
        'warehouse', 'stock_quantity', 'is_active', 'notes',
    ];

    public const MAX_ROWS = 500;

    /** @return array<int, array<string, string>> */
    public function rows(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            if (! $reader instanceof Xlsx) {
                throw ValidationException::withMessages(['workbook' => ['يسمح فقط بملفات Excel بصيغة XLSX.']]);
            }

            $reader->setReadDataOnly(false);
            $worksheet = $reader->load($path)->getActiveSheet();
        } catch (SpreadsheetReaderException) {
            throw ValidationException::withMessages(['workbook' => ['ملف Excel غير صالح أو تالف.']]);
        }

        $lastRow = $worksheet->getHighestDataRow();
        $lastColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        if ($lastRow < 2) {
            throw ValidationException::withMessages(['workbook' => ['الملف لا يحتوي على أي صفوف بيانات.']]);
        }
        if ($lastRow - 1 > self::MAX_ROWS || $lastColumn !== count(self::HEADERS)) {
            throw ValidationException::withMessages(['workbook' => ['تجاوز الملف الحدود المعتمدة أو لا يطابق عدد الأعمدة المطلوب.']]);
        }

        $headers = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $headers[] = $this->cellText($worksheet->getCell([$column, 1])->getValue());
        }
        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages(['workbook' => ['عناوين الأعمدة لا تطابق نموذج الاستيراد المعتمد.']]);
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
            $row = [];
            $empty = true;
            foreach (self::HEADERS as $offset => $header) {
                $cell = $worksheet->getCell([$offset + 1, $rowNumber]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages(['workbook' => ["الصف {$rowNumber} يحتوي على صيغة Excel غير مسموح بها."]]);
                }
                $row[$header] = $this->cellText($cell->getValue());
                $empty = $empty && $row[$header] === '';
            }
            if (! $empty) {
                $rows[$rowNumber] = $row;
            }
        }

        return $rows;
    }

    public function writeTemplate(string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray([[
            'P001', 'مثال منتج', 'Example product', '', 'العناية', 'وصف اختياري',
            'V001', '100ml', 'red', '1000.0000', 'stocked', '', '', 'MAIN', '10', '1', '',
        ]], null, 'A2');
        $sheet->freezePane('A2');
        foreach (range('A', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function cellText(mixed $cellValue): string
    {
        return Str::squish(trim((string) $cellValue));
    }
}
