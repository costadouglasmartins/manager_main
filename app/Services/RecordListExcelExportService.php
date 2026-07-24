<?php

namespace App\Services;

/**
 * Exports filtered record lists to a memory-efficient XLSX file.
 */
class RecordListExcelExportService extends RecordListExportService
	{
	/**
	 * Generates and streams the filtered list Excel file.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return void
	 */
	public function stream(string $resource, array $query = []): void
		{
		@set_time_limit(0);

		$payload = null;
		$temporaryFile = '';
		$successful = false;

		try
			{
			$payload = $this->prepareExportPayload($resource, $query);
			$definition = $payload['definition'];
			$columns = $definition['columns'] ?? [];
			$title = (string) ($definition['title'] ?? 'Registros');
			$writer = new StreamingXlsxWriter();
			$temporaryFile = $writer->create(
				$title,
				$columns,
				$this->formattedRows($payload, $columns),
				(int) $payload['total'],
				$this->filtersSummary($payload['filters'])
			);
			$successful = true;
			}
		finally
			{
			if (is_array($payload))
				{
				$this->finishExportPayload($payload, $successful);
				}

			if (!$successful && $temporaryFile !== '' && is_file($temporaryFile))
				{
				@unlink($temporaryFile);
				}
			}

		$filename = $this->filename(
			(string) ($payload['definition']['filename'] ?? $resource),
			'xlsx'
		);
		$this->streamFile($temporaryFile, $filename);
		}

	/**
	 * Converts database records into formatted worksheet rows lazily.
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
	 * Streams a completed temporary XLSX file and removes it afterward.
	 *
	 * @param string $path Temporary XLSX path.
	 * @param string $filename Download filename.
	 * @return void
	 */
	private function streamFile(string $path, string $filename): void
		{
		if (!is_file($path) || !is_readable($path))
			{
			throw new \RuntimeException('The generated Excel file is not available.');
			}

		while (ob_get_level() > 0)
			{
			ob_end_clean();
			}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('Cache-Control: private, no-store, max-age=0');
		header('Pragma: public');
		header('X-Content-Type-Options: nosniff');
		header('X-Accel-Buffering: no');

		try
			{
			readfile($path);
			}
		finally
			{
			@unlink($path);
			}

		exit;
		}
	}

?>
