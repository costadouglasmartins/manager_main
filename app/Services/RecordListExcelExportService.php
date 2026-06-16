<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports filtered record lists to Excel using the shared list export metadata.
 */
class RecordListExcelExportService extends RecordListExportService
	{
	/**
	 * Streams the filtered list Excel file to the browser.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return void
	 */
	public function stream(string $resource, array $query = []): void
		{
		$payload = $this->buildExportPayload($resource, $query);
		$spreadsheet = $this->buildSpreadsheet($payload);
		$filename = $this->filename((string) ($payload['definition']['filename'] ?? $resource), 'xlsx');

		$this->streamSpreadsheet($spreadsheet, $filename);
		}

	/**
	 * Builds the spreadsheet object.
	 *
	 * @param array{definition: array<string, mixed>, filters: array<string, string>, sort: array<string, string>|null, total: int, rows: array<int, array<string, mixed>>} $payload Export payload.
	 * @return Spreadsheet
	 */
	private function buildSpreadsheet(array $payload): Spreadsheet
		{
		if (!class_exists(Spreadsheet::class))
			{
			throw new \RuntimeException('PhpSpreadsheet is not available. Check Composer autoload configuration.');
			}

		$definition = $payload['definition'];
		$columns = $definition['columns'] ?? [];
		$rows = $payload['rows'];
		$total = (int) $payload['total'];
		$title = (string) ($definition['title'] ?? 'Registros');
		$lastColumnIndex = max(1, count($columns));
		$lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);
		$headerRow = 5;
		$dataStartRow = $headerRow + 1;

		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()
			->setCreator('Manager Main')
			->setTitle($title)
			->setSubject('Exportação de registros filtrados');

		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle($this->sheetTitle($title));
		$sheet->mergeCells('A1:' . $lastColumn . '1');
		$sheet->mergeCells('A2:' . $lastColumn . '2');
		$sheet->mergeCells('A3:' . $lastColumn . '3');

		$this->setString($sheet, 'A1', $title);
		$this->setString($sheet, 'A2', 'Gerado em ' . date('d/m/Y H:i') . ' - ' . number_format($total, 0, ',', '.') . ' registro(s) filtrado(s)');
		$this->setString($sheet, 'A3', $this->filtersSummary($payload['filters']));

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
		$sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('5F6C7B');

		foreach ($columns as $index => $column)
			{
			$cell = Coordinate::stringFromColumnIndex($index + 1) . $headerRow;
			$this->setString($sheet, $cell, (string) ($column['label'] ?? ''));
			}

		$headerRange = 'A' . $headerRow . ':' . $lastColumn . $headerRow;
		$sheet->getStyle($headerRange)->applyFromArray([
			'font' => [
				'bold' => true,
				'color' => ['rgb' => 'FFFFFF'],
			],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => '1F4E3D'],
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		]);

		if (empty($rows))
			{
			$sheet->mergeCells('A' . $dataStartRow . ':' . $lastColumn . $dataStartRow);
			$this->setString($sheet, 'A' . $dataStartRow, 'Nenhum registro encontrado.');
			$sheet->getStyle('A' . $dataStartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$lastRow = $dataStartRow;
			}
		else
			{
			$currentRow = $dataStartRow;
			foreach ($rows as $row)
				{
				foreach ($columns as $index => $column)
					{
					$cell = Coordinate::stringFromColumnIndex($index + 1) . $currentRow;
					$this->setString($sheet, $cell, $this->columnValue($column, $row));
					}
				$currentRow++;
				}
			$lastRow = $currentRow - 1;
			}

		$tableRange = 'A' . $headerRow . ':' . $lastColumn . $lastRow;
		$sheet->getStyle($tableRange)->applyFromArray([
			'borders' => [
				'allBorders' => [
					'borderStyle' => Border::BORDER_THIN,
					'color' => ['rgb' => 'D9E2EC'],
				],
			],
			'alignment' => [
				'vertical' => Alignment::VERTICAL_TOP,
				'wrapText' => true,
			],
		]);
		$sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . $lastRow);
		$sheet->freezePane('A' . $dataStartRow);

		foreach ($columns as $index => $column)
			{
			$letter = Coordinate::stringFromColumnIndex($index + 1);
			$sheet->getColumnDimension($letter)->setAutoSize(true);
			if (($column['align'] ?? '') === 'right')
				{
				$sheet->getStyle($letter . $dataStartRow . ':' . $letter . $lastRow)
					->getAlignment()
					->setHorizontal(Alignment::HORIZONTAL_RIGHT);
				}
			}

		return $spreadsheet;
		}

	/**
	 * Streams the spreadsheet as an XLSX response.
	 *
	 * @param Spreadsheet $spreadsheet Spreadsheet object.
	 * @param string $filename Download filename.
	 * @return void
	 */
	private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
		{
		while (ob_get_level() > 0)
			{
			ob_end_clean();
			}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		header('Pragma: public');

		$writer = new Xlsx($spreadsheet);
		$writer->setPreCalculateFormulas(false);
		$writer->save('php://output');
		$spreadsheet->disconnectWorksheets();
		exit;
		}

	/**
	 * Writes a string value explicitly to avoid Excel guessing IDs and masks.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet.
	 * @param string $cell Cell coordinate.
	 * @param mixed $value Cell value.
	 * @return void
	 */
	private function setString(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, $value): void
		{
		$sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
		}

	/**
	 * Builds a readable active filter summary.
	 *
	 * @param array<string, string> $filters Active filters.
	 * @return string
	 */
	private function filtersSummary(array $filters): string
		{
		$parts = [];
		foreach ($filters as $key => $value)
			{
			if ($value === '')
				{
				continue;
				}

			$parts[] = $this->humanLabel($key) . ': ' . $value;
			}

		return empty($parts) ? 'Nenhum filtro aplicado.' : implode(' | ', $parts);
		}

	/**
	 * Builds a safe Excel worksheet title.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	private function sheetTitle(string $title): string
		{
		$title = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]+/', ' ', $title) ?: 'Registros';
		$title = trim($title);
		if ($title === '')
			{
			$title = 'Registros';
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($title, 0, 31, 'UTF-8');
			}

		return substr($title, 0, 31);
		}
	}

?>
