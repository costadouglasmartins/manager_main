<?php

namespace App\Services;

use XMLWriter;
use ZipArchive;

/**
 * Writes tabular XLSX files incrementally without retaining worksheet cells in memory.
 */
class StreamingXlsxWriter
	{
	private const HEADER_ROW = 5;
	private const DATA_START_ROW = 6;
	private const MAX_CELL_LENGTH = 32767;

	/**
	 * Creates a temporary XLSX file from an iterable row source.
	 *
	 * @param string $title Worksheet and document title.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param iterable<int, array<int, string>> $rows Formatted worksheet rows.
	 * @param int $total Expected number of exported records.
	 * @param string $filtersSummary Readable active filter summary.
	 * @return string Temporary XLSX path. The caller is responsible for deleting it.
	 */
	public function create(string $title, array $columns, iterable $rows, int $total, string $filtersSummary): string
		{
		if (!class_exists(XMLWriter::class) || !class_exists(ZipArchive::class))
			{
			throw new \RuntimeException('XMLWriter and ZipArchive are required for Excel exports.');
			}

		if (empty($columns))
			{
			throw new \InvalidArgumentException('At least one Excel export column is required.');
			}

		$worksheetPath = $this->temporaryPath('manager_xlsx_sheet_');
		$workbookPath = $this->temporaryPath('manager_xlsx_');

		try
			{
			$lastRow = $this->writeWorksheet($worksheetPath, $title, $columns, $rows, $total, $filtersSummary);
			$this->writePackage($workbookPath, $worksheetPath, $title, $lastRow);
			}
		catch (\Throwable $exception)
			{
			$this->deleteFile($workbookPath);
			throw $exception;
			}
		finally
			{
			$this->deleteFile($worksheetPath);
			}

		return $workbookPath;
		}

	/**
	 * Writes the worksheet XML and returns its final row number.
	 *
	 * @param string $path Temporary worksheet XML path.
	 * @param string $title Worksheet title.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @param iterable<int, array<int, string>> $rows Formatted worksheet rows.
	 * @param int $total Expected number of exported records.
	 * @param string $filtersSummary Readable active filter summary.
	 * @return int Final worksheet row number.
	 */
	private function writeWorksheet(string $path, string $title, array $columns, iterable $rows, int $total, string $filtersSummary): int
		{
		$writer = new XMLWriter();
		if (!$writer->openUri($path))
			{
			throw new \RuntimeException('Unable to create the temporary Excel worksheet.');
			}

		$columnCount = count($columns);
		$lastColumn = $this->columnName($columnCount);

		$writer->startDocument('1.0', 'UTF-8');
		$writer->startElementNs(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$this->writeSheetViews($writer);
		$this->writeColumns($writer, $columns);
		$writer->startElement('sheetData');

		$this->writeRow($writer, 1, [$title], [1]);
		$this->writeRow(
			$writer,
			2,
			['Gerado em ' . date('d/m/Y H:i') . ' - ' . number_format(max(0, $total), 0, ',', '.') . ' registro(s) filtrado(s)'],
			[2]
		);
		$this->writeRow($writer, 3, [$filtersSummary], [2]);
		$this->writeRow($writer, 4, [''], [0]);

		$headerValues = [];
		foreach ($columns as $column)
			{
			$headerValues[] = (string) ($column['label'] ?? '');
			}
		$this->writeRow($writer, self::HEADER_ROW, $headerValues, array_fill(0, $columnCount, 3), 22);

		$currentRow = self::DATA_START_ROW;
		$dataCount = 0;
		foreach ($rows as $row)
			{
			$values = array_values($row);
			$values = array_slice(array_pad($values, $columnCount, ''), 0, $columnCount);
			$styles = [];
			foreach ($columns as $column)
				{
				$styles[] = (($column['align'] ?? '') === 'right') ? 5 : 4;
				}

			$this->writeRow($writer, $currentRow, $values, $styles);
			$currentRow++;
			$dataCount++;

			if (($dataCount % 500) === 0)
				{
				$writer->flush();
				}
			}

		if ($dataCount === 0)
			{
			$this->writeRow($writer, self::DATA_START_ROW, ['Nenhum registro encontrado.'], [6]);
			$currentRow = self::DATA_START_ROW + 1;
			}

		$lastRow = $currentRow - 1;
		$writer->endElement();

		$writer->startElement('autoFilter');
		$writer->writeAttribute('ref', 'A' . self::HEADER_ROW . ':' . $lastColumn . max(self::HEADER_ROW, $lastRow));
		$writer->endElement();

		if ($lastColumn !== 'A')
			{
			$mergeCount = $dataCount === 0 ? 4 : 3;
			$writer->startElement('mergeCells');
			$writer->writeAttribute('count', (string) $mergeCount);
			foreach ([1, 2, 3] as $rowNumber)
				{
				$writer->startElement('mergeCell');
				$writer->writeAttribute('ref', 'A' . $rowNumber . ':' . $lastColumn . $rowNumber);
				$writer->endElement();
				}
			if ($dataCount === 0)
				{
				$writer->startElement('mergeCell');
				$writer->writeAttribute('ref', 'A' . self::DATA_START_ROW . ':' . $lastColumn . self::DATA_START_ROW);
				$writer->endElement();
				}
			$writer->endElement();
			}

		$writer->startElement('pageMargins');
		$writer->writeAttribute('left', '0.3');
		$writer->writeAttribute('right', '0.3');
		$writer->writeAttribute('top', '0.5');
		$writer->writeAttribute('bottom', '0.5');
		$writer->writeAttribute('header', '0.2');
		$writer->writeAttribute('footer', '0.2');
		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
		$writer->flush();

		return $lastRow;
		}

	/**
	 * Writes the frozen worksheet view.
	 *
	 * @param XMLWriter $writer Worksheet XML writer.
	 * @return void
	 */
	private function writeSheetViews(XMLWriter $writer): void
		{
		$writer->startElement('sheetViews');
		$writer->startElement('sheetView');
		$writer->writeAttribute('workbookViewId', '0');
		$writer->startElement('pane');
		$writer->writeAttribute('ySplit', (string) (self::DATA_START_ROW - 1));
		$writer->writeAttribute('topLeftCell', 'A' . self::DATA_START_ROW);
		$writer->writeAttribute('activePane', 'bottomLeft');
		$writer->writeAttribute('state', 'frozen');
		$writer->endElement();
		$writer->startElement('selection');
		$writer->writeAttribute('pane', 'bottomLeft');
		$writer->writeAttribute('activeCell', 'A' . self::DATA_START_ROW);
		$writer->writeAttribute('sqref', 'A' . self::DATA_START_ROW);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		}

	/**
	 * Writes fixed column widths to avoid an expensive full-sheet autosize pass.
	 *
	 * @param XMLWriter $writer Worksheet XML writer.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @return void
	 */
	private function writeColumns(XMLWriter $writer, array $columns): void
		{
		$columnCount = count($columns);
		$defaultWidth = $columnCount <= 4 ? 32 : ($columnCount <= 6 ? 24 : 18);

		$writer->startElement('cols');
		foreach ($columns as $index => $column)
			{
			$width = isset($column['excelWidth'])
				? (float) $column['excelWidth']
				: ((($column['align'] ?? '') === 'right') ? 12 : $defaultWidth);
			$label = strtolower((string) ($column['label'] ?? ''));
			if (str_contains($label, 'descri') || str_contains($label, 'logradouro') || str_contains($label, 'rota'))
				{
				$width = max($width, 32);
				}

			$writer->startElement('col');
			$writer->writeAttribute('min', (string) ($index + 1));
			$writer->writeAttribute('max', (string) ($index + 1));
			$writer->writeAttribute('width', number_format(max(8, min(60, $width)), 2, '.', ''));
			$writer->writeAttribute('customWidth', '1');
			$writer->endElement();
			}
		$writer->endElement();
		}

	/**
	 * Writes one worksheet row using inline strings.
	 *
	 * @param XMLWriter $writer Worksheet XML writer.
	 * @param int $rowNumber One-based worksheet row number.
	 * @param array<int, mixed> $values Cell values.
	 * @param array<int, int> $styles Cell style indexes.
	 * @param float|null $height Optional row height.
	 * @return void
	 */
	private function writeRow(XMLWriter $writer, int $rowNumber, array $values, array $styles, ?float $height = null): void
		{
		$writer->startElement('row');
		$writer->writeAttribute('r', (string) $rowNumber);
		if ($height !== null)
			{
			$writer->writeAttribute('ht', number_format($height, 2, '.', ''));
			$writer->writeAttribute('customHeight', '1');
			}

		foreach ($values as $index => $value)
			{
			$this->writeCell(
				$writer,
				$this->columnName($index + 1) . $rowNumber,
				(string) $value,
				(int) ($styles[$index] ?? 0)
			);
			}

		$writer->endElement();
		}

	/**
	 * Writes one safe inline-string worksheet cell.
	 *
	 * @param XMLWriter $writer Worksheet XML writer.
	 * @param string $coordinate Excel cell coordinate.
	 * @param string $value Raw cell value.
	 * @param int $styleIndex Workbook style index.
	 * @return void
	 */
	private function writeCell(XMLWriter $writer, string $coordinate, string $value, int $styleIndex): void
		{
		$value = $this->sanitizeCellValue($value);

		$writer->startElement('c');
		$writer->writeAttribute('r', $coordinate);
		$writer->writeAttribute('t', 'inlineStr');
		if ($styleIndex > 0)
			{
			$writer->writeAttribute('s', (string) $styleIndex);
			}
		$writer->startElement('is');
		$writer->startElement('t');
		if ($value !== trim($value))
			{
			$writer->writeAttributeNs('xml', 'space', 'http://www.w3.org/XML/1998/namespace', 'preserve');
			}
		$writer->text($value);
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		}

	/**
	 * Packages the generated worksheet and fixed workbook parts into XLSX.
	 *
	 * @param string $workbookPath Temporary XLSX path.
	 * @param string $worksheetPath Temporary worksheet XML path.
	 * @param string $title Document title.
	 * @param int $lastRow Final worksheet row number.
	 * @return void
	 */
	private function writePackage(string $workbookPath, string $worksheetPath, string $title, int $lastRow): void
		{
		$archive = new ZipArchive();
		$result = $archive->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($result !== true)
			{
			throw new \RuntimeException('Unable to create the temporary Excel package.');
			}

		try
			{
			$this->addString($archive, '[Content_Types].xml', $this->contentTypesXml());
			$this->addString($archive, '_rels/.rels', $this->rootRelationshipsXml());
			$this->addString($archive, 'docProps/app.xml', $this->applicationPropertiesXml($title));
			$this->addString($archive, 'docProps/core.xml', $this->corePropertiesXml($title));
			$this->addString($archive, 'xl/workbook.xml', $this->workbookXml($title));
			$this->addString($archive, 'xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
			$this->addString($archive, 'xl/styles.xml', $this->stylesXml());

			if (!$archive->addFile($worksheetPath, 'xl/worksheets/sheet1.xml'))
				{
				throw new \RuntimeException('Unable to add the worksheet to the Excel package.');
				}

			$archive->setArchiveComment('Manager Main export - ' . max(self::HEADER_ROW, $lastRow) . ' worksheet rows');
			}
		catch (\Throwable $exception)
			{
			$archive->close();
			throw $exception;
			}

		if (!$archive->close())
			{
			throw new \RuntimeException('Unable to finalize the Excel package.');
			}
		}

	/**
	 * Adds a required XML part to the XLSX archive.
	 *
	 * @param ZipArchive $archive Open XLSX archive.
	 * @param string $path Package-relative path.
	 * @param string $contents XML contents.
	 * @return void
	 */
	private function addString(ZipArchive $archive, string $path, string $contents): void
		{
		if (!$archive->addFromString($path, $contents))
			{
			throw new \RuntimeException('Unable to add a required part to the Excel package.');
			}
		}

	/**
	 * Returns XLSX content type declarations.
	 */
	private function contentTypesXml(): string
		{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '</Types>';
		}

	/**
	 * Returns root package relationships.
	 */
	private function rootRelationshipsXml(): string
		{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
		}

	/**
	 * Returns extended workbook properties.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	private function applicationPropertiesXml(string $title): string
		{
		$title = $this->xmlValue($this->sheetTitle($title));

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
			. '<Application>Manager Main</Application>'
			. '<DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
			. '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Planilhas</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
			. '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $title . '</vt:lpstr></vt:vector></TitlesOfParts>'
			. '<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>1.0</AppVersion>'
			. '</Properties>';
		}

	/**
	 * Returns core workbook properties.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	private function corePropertiesXml(string $title): string
		{
		$title = $this->xmlValue($title);
		$createdAt = gmdate('Y-m-d\TH:i:s\Z');

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. '<dc:creator>Manager Main</dc:creator><cp:lastModifiedBy>Manager Main</cp:lastModifiedBy>'
			. '<dc:title>' . $title . '</dc:title><dc:subject>Exportação de registros filtrados</dc:subject>'
			. '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:created>'
			. '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:modified>'
			. '</cp:coreProperties>';
		}

	/**
	 * Returns the workbook definition.
	 *
	 * @param string $title Worksheet title.
	 * @return string
	 */
	private function workbookXml(string $title): string
		{
		$sheetTitle = $this->xmlValue($this->sheetTitle($title));

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<bookViews><workbookView activeTab="0" firstSheet="0" showSheetTabs="1"/></bookViews>'
			. '<sheets><sheet name="' . $sheetTitle . '" sheetId="1" r:id="rId1"/></sheets>'
			. '<calcPr calcId="0" calcMode="manual" fullCalcOnLoad="0" forceFullCalc="0"/>'
			. '</workbook>';
		}

	/**
	 * Returns workbook relationships.
	 */
	private function workbookRelationshipsXml(): string
		{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
		}

	/**
	 * Returns shared workbook styles.
	 */
	private function stylesXml(): string
		{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="4">'
			. '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
			. '<font><b/><sz val="16"/><color rgb="FF1F2933"/><name val="Calibri"/><family val="2"/></font>'
			. '<font><sz val="10"/><color rgb="FF5F6C7B"/><name val="Calibri"/><family val="2"/></font>'
			. '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
			. '</fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E3D"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="2">'
			. '<border><left/><right/><top/><bottom/><diagonal/></border>'
			. '<border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom><diagonal/></border>'
			. '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="7">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
			. '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
			. '</styleSheet>';
		}

	/**
	 * Converts a one-based column index to an Excel column name.
	 *
	 * @param int $index One-based column index.
	 * @return string Excel column letters.
	 */
	private function columnName(int $index): string
		{
		$name = '';
		while ($index > 0)
			{
			$index--;
			$name = chr(65 + ($index % 26)) . $name;
			$index = intdiv($index, 26);
			}

		return $name;
		}

	/**
	 * Returns a safe, non-empty Excel worksheet title.
	 *
	 * @param string $title Raw title.
	 * @return string Safe worksheet title.
	 */
	private function sheetTitle(string $title): string
		{
		$title = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]+/', ' ', $title) ?: 'Registros';
		$title = trim($title, " \t\n\r\0\x0B'");
		if ($title === '')
			{
			$title = 'Registros';
			}

		return mb_substr($title, 0, 31, 'UTF-8');
		}

	/**
	 * Sanitizes text for an inline-string Excel cell.
	 *
	 * @param string $value Raw cell value.
	 * @return string XML-safe, Excel-compatible text.
	 */
	private function sanitizeCellValue(string $value): string
		{
		if (!mb_check_encoding($value, 'UTF-8'))
			{
			$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
			}

		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
		if (mb_strlen($value, 'UTF-8') > self::MAX_CELL_LENGTH)
			{
			$value = mb_substr($value, 0, self::MAX_CELL_LENGTH, 'UTF-8');
			}

		return $value;
		}

	/**
	 * Escapes a value used in a generated XML attribute or text node.
	 *
	 * @param string $value Raw XML value.
	 * @return string Escaped XML value.
	 */
	private function xmlValue(string $value): string
		{
		return htmlspecialchars($this->sanitizeCellValue($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
		}

	/**
	 * Creates a secure temporary file and returns its path.
	 *
	 * @param string $prefix Temporary filename prefix.
	 * @return string Temporary file path.
	 */
	private function temporaryPath(string $prefix): string
		{
		$path = tempnam(sys_get_temp_dir(), $prefix);
		if ($path === false)
			{
			throw new \RuntimeException('Unable to allocate a temporary export file.');
			}

		return $path;
		}

	/**
	 * Deletes a temporary file when it still exists.
	 *
	 * @param string $path Temporary file path.
	 * @return void
	 */
	private function deleteFile(string $path): void
		{
		if ($path !== '' && is_file($path))
			{
			@unlink($path);
			}
		}
	}

?>
