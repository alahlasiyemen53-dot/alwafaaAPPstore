<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a report as UTF-8 CSV without building the whole file in memory,
 * and protects against CSV formula injection by prefixing any cell whose
 * first character could be interpreted as a spreadsheet formula.
 */
final class ReportCsvExporter
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function stream(
        string $filename,
        array $headers,
        iterable $rows,
        ?User $actor,
        string $reportName,
        array $filters,
    ): StreamedResponse {
        $safeFilename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $filename).'-'.now()->format('Ymd-His').'.csv';

        $response = new StreamedResponse(function () use ($headers, $rows, $actor, $reportName, $filters, $safeFilename): void {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(self::safeCell(...), $headers));

            $rowCount = 0;
            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::safeCell(...), $row));
                $rowCount++;
            }

            fclose($handle);

            $this->auditLogger->record('admin.report.exported', null, metadata: [
                'report' => $reportName,
                'filters' => $filters,
                'filename' => $safeFilename,
                'row_count' => $rowCount,
                'actor_id' => $actor?->id,
            ]);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$safeFilename.'"');

        return $response;
    }

    public static function safeCell(string|int|float|null $value): string
    {
        $stringValue = (string) ($value ?? '');

        if ($stringValue === '') {
            return $stringValue;
        }

        if (in_array($stringValue[0], self::DANGEROUS_PREFIXES, true)) {
            return "'".$stringValue;
        }

        return $stringValue;
    }
}
