<?php

namespace Tests\Unit;

use App\Services\ProductImportWorkbookService;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ProductImportWorkbookServiceTest extends TestCase
{
    public function test_template_and_parser_use_the_same_header_contract(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'product-import-').'.xlsx';
        $service = app(ProductImportWorkbookService::class);

        $service->writeTemplate($path);
        $rows = $service->rows($path);

        self::assertSame('P001', $rows[2]['product_ref']);
        self::assertSame('stocked', $rows[2]['availability_type']);
        @unlink($path);
    }

    public function test_formula_cells_are_rejected_before_preview(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'product-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ProductImportWorkbookService::HEADERS, null, 'A1');
        $sheet->setCellValue('A2', '=CONCAT("P", "001")');
        (new Xlsx($spreadsheet))->save($path);

        $this->expectException(ValidationException::class);
        app(ProductImportWorkbookService::class)->rows($path);
    }
}
