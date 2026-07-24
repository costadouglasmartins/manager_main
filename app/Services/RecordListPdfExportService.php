<?php

namespace App\Services;

/**
 * Exports filtered record lists to a memory-efficient paged PDF.
 */
class RecordListPdfExportService extends RecordListExportService
	{
	/**
	 * Generates and streams the filtered list PDF.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return void
	 */
	public function stream(string $resource, array $query = []): void
		{
		@set_time_limit(0);

		$payload = null;
		$pdf = '';
		$successful = false;

		try
			{
			$payload = $this->prepareExportPayload($resource, $query);
			$definition = $payload['definition'];
			$columns = $definition['columns'] ?? [];
			$writer = new TabularPdfWriter();
			$pdf = $writer->create(
				(string) ($definition['title'] ?? 'Registros'),
				$columns,
				$this->formattedRows($payload, $columns),
				(int) $payload['total'],
				$this->filtersSummary($payload['filters']),
				(string) ($definition['orientation'] ?? 'landscape')
			);
			$successful = true;
			}
		finally
			{
			if (is_array($payload))
				{
				$this->finishExportPayload($payload, $successful);
				}
			}

		$filename = $this->filename((string) ($payload['definition']['filename'] ?? $resource));
		$this->streamPdf($pdf, $filename);
		}

	/**
	 * Converts database records into formatted PDF rows lazily.
	 *
	 * @param array<string, mixed> $payload Prepared export context.
	 * @param array<int, array<string, mixed>> $columns Export column definitions.
	 * @return \Generator<int, array<int, string>>
	 */
	private function formattedRows(array $payload, array $columns): \Generator
		{
		$completed = false;

		try
			{
			foreach ($this->exportRows($payload) as $row)
				{
				$values = [];
				foreach ($columns as $column)
					{
					$values[] = $this->columnValue($column, $row);
					}

				yield $values;
				}

			$completed = true;
			}
		finally
			{
			$this->finishExportPayload($payload, $completed);
			}
		}

	/**
	 * Streams completed PDF bytes to the browser.
	 *
	 * @param string $pdf Generated PDF bytes.
	 * @param string $filename Download filename.
	 * @return void
	 */
	private function streamPdf(string $pdf, string $filename): void
		{
		if ($pdf === '')
			{
			throw new \RuntimeException('The generated PDF is empty.');
			}

		while (ob_get_level() > 0)
			{
			ob_end_clean();
			}

		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($pdf));
		header('Cache-Control: private, no-store, max-age=0');
		header('Pragma: public');
		header('X-Content-Type-Options: nosniff');
		header('X-Accel-Buffering: no');

		echo $pdf;
		exit;
		}
	}

?>
