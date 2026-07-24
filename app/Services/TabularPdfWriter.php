<?php

namespace App\Services;

use Dompdf\Adapter\CPDF;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Writes large tabular PDFs directly to a paged canvas without building an HTML DOM.
 */
class TabularPdfWriter
	{
	private const PAGE_MARGIN_X = 24.0;
	private const PAGE_MARGIN_BOTTOM = 26.0;
	private const HEADER_HEIGHT = 18.0;

	/**
	 * Creates a compact PDF document from an iterable row source.
	 *
	 * @param string $title Document title.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param iterable<int, array<int, string>> $rows Formatted table rows.
	 * @param int $total Expected number of exported records.
	 * @param string $filtersSummary Readable active filter summary.
	 * @param string $orientation PDF paper orientation.
	 * @return string Generated PDF bytes.
	 */
	public function create(
		string $title,
		array $columns,
		iterable $rows,
		int $total,
		string $filtersSummary,
		string $orientation = 'landscape'
	): string
		{
		if (!class_exists(Dompdf::class) || !class_exists(CPDF::class))
			{
			throw new \RuntimeException('Dompdf is required for PDF exports.');
			}

		if (empty($columns))
			{
			throw new \InvalidArgumentException('At least one PDF export column is required.');
			}

		$orientation = strtolower($orientation) === 'portrait' ? 'portrait' : 'landscape';
		$options = new Options();
		$options->setDefaultFont('DejaVu Sans');
		$options->setIsRemoteEnabled(false);
		$options->setTempDir(sys_get_temp_dir());
		if (defined('ROOT_PATH'))
			{
			$options->setChroot(ROOT_PATH);
			}

		$dompdf = new Dompdf($options);
		$canvas = new CPDF('a4', $orientation, $dompdf);
		$fontMetrics = $dompdf->getFontMetrics();
		$regularFont = $fontMetrics->getFont('DejaVu Sans', 'normal');
		$boldFont = $fontMetrics->getFont('DejaVu Sans', 'bold');
		if (!is_string($regularFont) || !is_string($boldFont))
			{
			throw new \RuntimeException('The PDF fonts could not be loaded.');
			}

		$pageWidth = (float) $canvas->get_width();
		$pageHeight = (float) $canvas->get_height();
		$contentWidth = $pageWidth - (self::PAGE_MARGIN_X * 2);
		$columnWidths = $this->columnWidths($columns, $contentWidth);
		$fontSize = $this->dataFontSize(count($columns));
		$lineHeight = max(8.5, $fontSize + 1.5);
		$rowPadding = 4.0;
		$generatedAt = date('d/m/Y H:i');
		$dataTop = $this->drawPageHeader(
			$canvas,
			$title,
			$columns,
			$columnWidths,
			$total,
			$filtersSummary,
			$generatedAt,
			$regularFont,
			$boldFont,
			$pageWidth
		);
		$freshPageDataTop = $dataTop;
		$freshPageCapacity = $this->pageLineCapacity(
			$freshPageDataTop,
			$pageHeight,
			$lineHeight,
			$rowPadding
		);
		if ($freshPageCapacity < 1)
			{
			throw new \RuntimeException('The PDF title and filters leave no room for exported records.');
			}

		$rowIndex = 0;
		foreach ($rows as $row)
			{
			$wrappedCells = $this->wrapRow(
				$canvas,
				$columns,
				$columnWidths,
				array_values($row),
				$regularFont,
				$fontSize
			);
			$remainingLines = $this->maximumLineCount($wrappedCells);
			$availableLines = $this->pageLineCapacity(
				$dataTop,
				$pageHeight,
				$lineHeight,
				$rowPadding
			);

			if ($remainingLines <= $freshPageCapacity && $remainingLines > $availableLines)
				{
				$canvas->new_page();
				$dataTop = $this->drawPageHeader(
					$canvas,
					$title,
					$columns,
					$columnWidths,
					$total,
					$filtersSummary,
					$generatedAt,
					$regularFont,
					$boldFont,
					$pageWidth
				);
				}

			while ($remainingLines > 0)
				{
				$availableLines = $this->pageLineCapacity(
					$dataTop,
					$pageHeight,
					$lineHeight,
					$rowPadding
				);
				if ($availableLines < 1)
					{
					$canvas->new_page();
					$dataTop = $this->drawPageHeader(
						$canvas,
						$title,
						$columns,
						$columnWidths,
						$total,
						$filtersSummary,
						$generatedAt,
						$regularFont,
						$boldFont,
						$pageWidth
					);
					$availableLines = $freshPageCapacity;
					}

				$segmentLineCount = min($remainingLines, $availableLines);
				$segmentCells = [];
				foreach ($wrappedCells as &$cellLines)
					{
					$segmentCells[] = array_splice($cellLines, 0, $segmentLineCount);
					}
				unset($cellLines);

				$rowHeight = ($segmentLineCount * $lineHeight) + $rowPadding;
				$this->drawDataRow(
					$canvas,
					$columns,
					$columnWidths,
					$segmentCells,
					$dataTop,
					$rowHeight,
					$lineHeight,
					$fontSize,
					$regularFont,
					($rowIndex % 2) === 1
				);
				$dataTop += $rowHeight;
				$remainingLines = $this->maximumLineCount($wrappedCells, 0);

				if ($remainingLines > 0)
					{
					$canvas->new_page();
					$dataTop = $this->drawPageHeader(
						$canvas,
						$title,
						$columns,
						$columnWidths,
						$total,
						$filtersSummary,
						$generatedAt,
						$regularFont,
						$boldFont,
						$pageWidth
					);
					}
				}

			$rowIndex++;
			}

		if ($rowIndex === 0)
			{
			$canvas->text(
				self::PAGE_MARGIN_X + 6,
				$dataTop + 5,
				'Nenhum registro encontrado.',
				$regularFont,
				8,
				[0.38, 0.49, 0.60]
			);
			}

		$canvas->page_line(
			self::PAGE_MARGIN_X,
			$pageHeight - 22,
			$pageWidth - self::PAGE_MARGIN_X,
			$pageHeight - 22,
			[0.85, 0.89, 0.93],
			0.5
		);
		$canvas->page_text(
			$pageWidth - self::PAGE_MARGIN_X - 92,
			$pageHeight - 17,
			'Página {PAGE_NUM} de {PAGE_COUNT}',
			$regularFont,
			7,
			[0.38, 0.49, 0.60]
		);
		$canvas->add_info('Title', $title);
		$canvas->add_info('Author', 'Manager Main');
		$canvas->add_info('Subject', 'Exportação de registros filtrados');

		return $canvas->output(['compress' => true]);
		}

	/**
	 * Draws the repeated title, metadata, filters, and table header.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param string $title Document title.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param array<int, float> $columnWidths Calculated column widths.
	 * @param int $total Expected number of records.
	 * @param string $filtersSummary Readable active filter summary.
	 * @param string $generatedAt Consistent document generation timestamp.
	 * @param string $regularFont Regular font path.
	 * @param string $boldFont Bold font path.
	 * @param float $pageWidth Current page width.
	 * @return float First data row Y coordinate.
	 */
	private function drawPageHeader(
		Canvas $canvas,
		string $title,
		array $columns,
		array $columnWidths,
		int $total,
		string $filtersSummary,
		string $generatedAt,
		string $regularFont,
		string $boldFont,
		float $pageWidth
	): float
		{
		$contentWidth = $pageWidth - (self::PAGE_MARGIN_X * 2);
		$titleLines = $this->wrapText(
			$canvas,
			$this->normalizeText($title),
			$boldFont,
			15,
			$contentWidth
		);
		$meta = 'Gerado em ' . $generatedAt . ' - ' . number_format(max(0, $total), 0, ',', '.') . ' registro(s) filtrado(s)';
		$filterLines = $this->wrapText(
			$canvas,
			'Filtros: ' . $this->normalizeText($filtersSummary),
			$regularFont,
			7.5,
			$contentWidth
		);

		$titleTop = 21.0;
		$titleLineHeight = 18.0;
		foreach ($titleLines as $lineIndex => $titleLine)
			{
			$canvas->text(
				self::PAGE_MARGIN_X,
				$titleTop + ($lineIndex * $titleLineHeight),
				$titleLine,
				$boldFont,
				15,
				[0.12, 0.16, 0.20]
			);
			}

		$metaTop = $titleTop + (count($titleLines) * $titleLineHeight) + 4;
		$canvas->text(self::PAGE_MARGIN_X, $metaTop, $meta, $regularFont, 8, [0.38, 0.49, 0.60]);

		$filterTop = $metaTop + 11;
		$filterLineHeight = 9.0;
		$filterHeight = (count($filterLines) * $filterLineHeight) + 6;
		$canvas->filled_rectangle(self::PAGE_MARGIN_X, $filterTop, $contentWidth, $filterHeight, [0.96, 0.97, 0.98]);
		foreach ($filterLines as $lineIndex => $filterLine)
			{
			$canvas->text(
				self::PAGE_MARGIN_X + 5,
				$filterTop + 3.5 + ($lineIndex * $filterLineHeight),
				$filterLine,
				$regularFont,
				7.5,
				[0.20, 0.31, 0.41]
			);
			}

		$tableHeaderTop = $filterTop + $filterHeight + 7;
		$canvas->filled_rectangle(
			self::PAGE_MARGIN_X,
			$tableHeaderTop,
			$contentWidth,
			self::HEADER_HEIGHT,
			[0.12, 0.31, 0.24]
		);

		$x = self::PAGE_MARGIN_X;
		$headerFontSize = $this->dataFontSize(count($columns));
		foreach ($columns as $index => $column)
			{
			$width = $columnWidths[$index];
			$label = $this->fitText(
				$canvas,
				$this->normalizeText((string) ($column['label'] ?? '')),
				$boldFont,
				$headerFontSize,
				$width - 8
			);
			$canvas->text($x + 4, $tableHeaderTop + 5, $label, $boldFont, $headerFontSize, [1, 1, 1]);
			$x += $width;
			if ($index < (count($columns) - 1))
				{
				$canvas->line(
					$x,
					$tableHeaderTop,
					$x,
					$tableHeaderTop + self::HEADER_HEIGHT,
					[0.35, 0.52, 0.45],
					0.4
				);
				}
			}

		return $tableHeaderTop + self::HEADER_HEIGHT;
		}

	/**
	 * Draws one table row segment containing one or more wrapped text lines.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param array<int, float> $columnWidths Calculated column widths.
	 * @param array<int, array<int, string>> $linesByColumn Wrapped lines by column.
	 * @param float $y Row Y coordinate.
	 * @param float $rowHeight Row height.
	 * @param float $lineHeight Text line height.
	 * @param float $fontSize Data font size.
	 * @param string $font Regular font path.
	 * @param bool $striped Whether to draw a striped background.
	 * @return void
	 */
	private function drawDataRow(
		Canvas $canvas,
		array $columns,
		array $columnWidths,
		array $linesByColumn,
		float $y,
		float $rowHeight,
		float $lineHeight,
		float $fontSize,
		string $font,
		bool $striped
	): void
		{
		$contentWidth = array_sum($columnWidths);
		if ($striped)
			{
			$canvas->filled_rectangle(self::PAGE_MARGIN_X, $y, $contentWidth, $rowHeight, [0.975, 0.982, 0.989]);
			}

		$x = self::PAGE_MARGIN_X;
		foreach ($columns as $index => $column)
			{
			$width = $columnWidths[$index];
			$lines = $linesByColumn[$index] ?? [];
			foreach ($lines as $lineIndex => $line)
				{
				$textX = $x + 4;
				if (($column['align'] ?? '') === 'right')
					{
					$textWidth = (float) $canvas->get_text_width($line, $font, $fontSize);
					$textX = $x + $width - 4 - $textWidth;
					}

				$canvas->text(
					$textX,
					$y + 2.5 + ($lineIndex * $lineHeight),
					$line,
					$font,
					$fontSize,
					[0.12, 0.16, 0.20]
				);
				}
			$x += $width;
			}

		$canvas->line(
			self::PAGE_MARGIN_X,
			$y + $rowHeight,
			self::PAGE_MARGIN_X + $contentWidth,
			$y + $rowHeight,
			[0.85, 0.89, 0.93],
			0.35
		);
		}

	/**
	 * Wraps each row cell without discarding exported content.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param array<int, float> $columnWidths Calculated column widths.
	 * @param array<int, mixed> $values Formatted row values.
	 * @param string $font Regular font path.
	 * @param float $fontSize Data font size.
	 * @return array<int, array<int, string>> Wrapped lines by column.
	 */
	private function wrapRow(
		Canvas $canvas,
		array $columns,
		array $columnWidths,
		array $values,
		string $font,
		float $fontSize
	): array
		{
		$wrapped = [];
		foreach ($columns as $index => $column)
			{
			$wrapped[] = $this->wrapText(
				$canvas,
				$this->normalizeText((string) ($values[$index] ?? '')),
				$font,
				$fontSize,
				$columnWidths[$index] - 8
			);
			}

		return $wrapped;
		}

	/**
	 * Wraps text by words and, when necessary, by Unicode character.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param string $text Normalized text.
	 * @param string $font Font path.
	 * @param float $fontSize Font size.
	 * @param float $maximumWidth Available width.
	 * @return array<int, string> Complete text split into drawable lines.
	 */
	private function wrapText(
		Canvas $canvas,
		string $text,
		string $font,
		float $fontSize,
		float $maximumWidth
	): array
		{
		if ($text === '')
			{
			return [''];
			}

		if ($maximumWidth <= 1 || (float) $canvas->get_text_width($text, $font, $fontSize) <= $maximumWidth)
			{
			return [$text];
			}

		$words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
		$lines = [];
		$currentLine = '';

		foreach ($words as $word)
			{
			$candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;
			if ((float) $canvas->get_text_width($candidate, $font, $fontSize) <= $maximumWidth)
				{
				$currentLine = $candidate;
				continue;
				}

			if ($currentLine !== '')
				{
				$lines[] = $currentLine;
				$currentLine = '';
				}

			while ($word !== '')
				{
				if ((float) $canvas->get_text_width($word, $font, $fontSize) <= $maximumWidth)
					{
					$currentLine = $word;
					break;
					}

				$prefixLength = max(
					1,
					$this->fittingPrefixLength($canvas, $word, $font, $fontSize, $maximumWidth)
				);
				$lines[] = $this->textSlice($word, 0, $prefixLength);
				$wordLength = $this->textLength($word);
				$word = $this->textSlice($word, $prefixLength, $wordLength - $prefixLength);
				}
			}

		if ($currentLine !== '')
			{
			$lines[] = $currentLine;
			}

		return $lines ?: [''];
		}

	/**
	 * Finds the longest leading substring that fits in the available width.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param string $text Text to measure.
	 * @param string $font Font path.
	 * @param float $fontSize Font size.
	 * @param float $maximumWidth Available width.
	 * @return int Number of characters that fit.
	 */
	private function fittingPrefixLength(
		Canvas $canvas,
		string $text,
		string $font,
		float $fontSize,
		float $maximumWidth
	): int
		{
		$low = 0;
		$high = $this->textLength($text);
		while ($low < $high)
			{
			$middle = (int) ceil(($low + $high) / 2);
			$candidate = $this->textSlice($text, 0, $middle);
			if ((float) $canvas->get_text_width($candidate, $font, $fontSize) <= $maximumWidth)
				{
				$low = $middle;
				}
			else
				{
				$high = $middle - 1;
				}
			}

		return $low;
		}

	/**
	 * Returns how many wrapped lines can still fit on the current page.
	 *
	 * @param float $dataTop Current data Y coordinate.
	 * @param float $pageHeight Current page height.
	 * @param float $lineHeight Text line height.
	 * @param float $rowPadding Vertical row padding.
	 * @return int Available line count.
	 */
	private function pageLineCapacity(
		float $dataTop,
		float $pageHeight,
		float $lineHeight,
		float $rowPadding
	): int
		{
		$availableHeight = ($pageHeight - self::PAGE_MARGIN_BOTTOM) - $dataTop - $rowPadding;
		return max(0, (int) floor($availableHeight / $lineHeight));
		}

	/**
	 * Returns the largest number of wrapped lines among all cells.
	 *
	 * @param array<int, array<int, string>> $wrappedCells Wrapped lines by column.
	 * @param int $minimum Minimum return value.
	 * @return int Maximum line count.
	 */
	private function maximumLineCount(array $wrappedCells, int $minimum = 1): int
		{
		$maximum = $minimum;
		foreach ($wrappedCells as $cellLines)
			{
			$maximum = max($maximum, count($cellLines));
			}

		return $maximum;
		}

	/**
	 * Calculates proportional widths for all export columns.
	 *
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param float $contentWidth Available table width.
	 * @return array<int, float> Column widths in points.
	 */
	private function columnWidths(array $columns, float $contentWidth): array
		{
		$weights = [];
		foreach ($columns as $column)
			{
			if (isset($column['pdfWeight']))
				{
				$weights[] = max(0.25, (float) $column['pdfWeight']);
				continue;
				}

			$label = strtolower((string) ($column['label'] ?? ''));
			$weight = (($column['align'] ?? '') === 'right') ? 0.55 : 1.0;
			if (str_contains($label, 'descri') || str_contains($label, 'logradouro') || str_contains($label, 'entidade'))
				{
				$weight = 1.45;
				}
			$weights[] = $weight;
			}

		$totalWeight = array_sum($weights);
		$widths = [];
		foreach ($weights as $weight)
			{
			$widths[] = $contentWidth * ($weight / $totalWeight);
			}

		return $widths;
		}

	/**
	 * Returns a readable font size based on table width pressure.
	 *
	 * @param int $columnCount Number of table columns.
	 * @return float Font size in points.
	 */
	private function dataFontSize(int $columnCount): float
		{
		if ($columnCount <= 4)
			{
			return 8.0;
			}
		if ($columnCount <= 6)
			{
			return 7.0;
			}

		return 5.8;
		}

	/**
	 * Truncates a table column label with an ellipsis when it exceeds its width.
	 *
	 * @param Canvas $canvas Active PDF canvas.
	 * @param string $text Normalized text.
	 * @param string $font Font path.
	 * @param float $fontSize Font size.
	 * @param float $maximumWidth Available width.
	 * @return string Fitted text.
	 */
	private function fitText(Canvas $canvas, string $text, string $font, float $fontSize, float $maximumWidth): string
		{
		if ($text === '' || $maximumWidth <= 1)
			{
			return '';
			}

		if ((float) $canvas->get_text_width($text, $font, $fontSize) <= $maximumWidth)
			{
			return $text;
			}

		$ellipsis = '…';
		$ellipsisWidth = (float) $canvas->get_text_width($ellipsis, $font, $fontSize);
		if ($ellipsisWidth >= $maximumWidth)
			{
			return '';
			}

		$low = 0;
		$high = $this->textLength($text);
		while ($low < $high)
			{
			$middle = (int) ceil(($low + $high) / 2);
			$candidate = rtrim($this->textSlice($text, 0, $middle)) . $ellipsis;
			if ((float) $canvas->get_text_width($candidate, $font, $fontSize) <= $maximumWidth)
				{
				$low = $middle;
				}
			else
				{
				$high = $middle - 1;
				}
			}

		return rtrim($this->textSlice($text, 0, $low)) . $ellipsis;
		}

	/**
	 * Normalizes whitespace and invalid control characters in PDF text.
	 *
	 * @param string $text Raw text.
	 * @return string Normalized text.
	 */
	private function normalizeText(string $text): string
		{
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
		return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
		}

	/**
	 * Returns a multibyte-safe character count.
	 *
	 * @param string $text UTF-8 text.
	 * @return int Character count.
	 */
	private function textLength(string $text): int
		{
		return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
		}

	/**
	 * Returns a multibyte-safe text slice.
	 *
	 * @param string $text UTF-8 text.
	 * @param int $start Starting character offset.
	 * @param int $length Maximum character count.
	 * @return string Text slice.
	 */
	private function textSlice(string $text, int $start, int $length): string
		{
		return function_exists('mb_substr')
			? mb_substr($text, $start, $length, 'UTF-8')
			: substr($text, $start, $length);
		}
	}

?>
