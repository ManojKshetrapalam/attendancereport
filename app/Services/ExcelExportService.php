<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Font};
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    protected Spreadsheet $spreadsheet;

    /** Theme colours */
    const COLOR_HEADER_BG  = '3B3F5C';   // dark navy
    const COLOR_HEADER_FG  = 'FFFFFF';
    const COLOR_ACCENT     = '6366F1';   // indigo
    const COLOR_PRESENT    = '22C55E';
    const COLOR_ABSENT     = 'EF4444';
    const COLOR_LATE       = 'F59E0B';
    const COLOR_ALT_ROW    = 'F8F9FF';

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
    }

    /**
     * Apply styled header row across columns A..N (up to 14 cols).
     */
    public function applyHeaders(string $sheet, array $headers, int $row = 1): void
    {
        $ws = $this->spreadsheet->getActiveSheet();
        $ws->setTitle($sheet);

        $col = 'A';
        foreach ($headers as $header) {
            $cell = $col . $row;
            $ws->setCellValue($cell, $header);
            $ws->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FF' . self::COLOR_HEADER_FG], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_HEADER_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_ACCENT]]],
            ]);
            $col++;
        }
        $ws->getRowDimension($row)->setRowHeight(22);
    }

    /**
     * Write a data row and optionally highlight status cells.
     */
    public function writeRow(int $rowIndex, array $values, string $status = ''): void
    {
        $ws  = $this->spreadsheet->getActiveSheet();
        $col = 'A';
        foreach ($values as $value) {
            $ws->setCellValue($col . $rowIndex, $value);
            $col++;
        }

        // Alternate row shading
        $bgColor = ($rowIndex % 2 === 0) ? 'FF' . self::COLOR_ALT_ROW : 'FFFFFFFF';
        $ws->getStyle('A' . $rowIndex . ':' . chr(ord('A') + count($values) - 1) . $rowIndex)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($bgColor);

        // Status highlighting
        if ($status) {
            $color = match($status) {
                'Present', 'On Time' => self::COLOR_PRESENT,
                'Absent'             => self::COLOR_ABSENT,
                'Late'               => self::COLOR_LATE,
                default              => null,
            };
            if ($color) {
                $statusCol = chr(ord('A') + count($values) - 1); // last col assumed status
                $ws->getStyle($statusCol . $rowIndex)
                    ->getFont()->getColor()->setARGB('FF' . $color);
                $ws->getStyle($statusCol . $rowIndex)->getFont()->setBold(true);
            }
        }
    }

    /**
     * Auto-size all used columns.
     */
    public function autoSize(): void
    {
        $ws   = $this->spreadsheet->getActiveSheet();
        $last = $ws->getHighestColumn();
        for ($col = 'A'; $col <= $last; $col++) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Add a title row at row 1 and shift headers to row 2.
     */
    public function addTitleRow(string $title, string $subtitle, int $colCount): void
    {
        $ws = $this->spreadsheet->getActiveSheet();

        // Merge across columns for title
        $lastCol = chr(ord('A') + $colCount - 1);
        $ws->mergeCells('A1:' . $lastCol . '1');
        $ws->setCellValue('A1', $title);
        $ws->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF' . self::COLOR_ACCENT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E2035']],
        ]);
        $ws->getRowDimension(1)->setRowHeight(30);

        $ws->mergeCells('A2:' . $lastCol . '2');
        $ws->setCellValue('A2', $subtitle);
        $ws->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF888888']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E2035']],
        ]);
        $ws->getRowDimension(2)->setRowHeight(16);
    }

    /**
     * Stream the .xlsx file to browser.
     */
    public function download(string $filename)
    {
        $this->autoSize();
        $this->spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($this->spreadsheet);

        // Force clear any output buffers to prevent corruption or header issues
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"; filename*=' . "UTF-8''" . rawurlencode($filename),
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma'              => 'public',
        ]);
    }

    public function getSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        return $this->spreadsheet->getActiveSheet();
    }
}
