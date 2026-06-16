<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Exports filtered record lists to PDF.
 */
class RecordListPdfExportService extends RecordListExportService
	{
	/**
	 * Streams the filtered list PDF to the browser.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return void
	 */
	public function stream(string $resource, array $query = []): void
		{
		$payload = $this->buildExportPayload($resource, $query);
		$definition = $payload['definition'];
		$filters = $payload['filters'];
		$rows = $payload['rows'];
		$total = $payload['total'];

		$html = $this->buildHtml($definition, $rows, $filters, $total);
		$this->streamPdf($html, $this->filename($definition['filename'] ?? $resource), $definition['orientation'] ?? 'landscape');
		}

	/**
	 * Builds the HTML document rendered by Dompdf.
	 *
	 * @param array<string, mixed> $definition Resource definition.
	 * @param array<int, array<string, mixed>> $rows Export rows.
	 * @param array<string, string> $filters Active filters.
	 * @param int $total Total records.
	 * @return string
	 */
	private function buildHtml(array $definition, array $rows, array $filters, int $total): string
		{
		$title = htmlspecialchars((string) $definition['title'], ENT_QUOTES, 'UTF-8');
		$generatedAt = htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
		$totalLabel = number_format($total, 0, ',', '.');

		return '<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
	@page { margin: 24px 22px; }
	body { color: #1f2933; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.35; }
	h1 { font-size: 18px; margin: 0 0 4px; }
	.meta { color: #5f6c7b; margin-bottom: 10px; }
	.filters { background: #f4f7fb; border: 1px solid #d9e2ec; margin: 10px 0 14px; padding: 8px 10px; }
	.filters strong { color: #334e68; }
	table { border-collapse: collapse; width: 100%; }
	thead { display: table-header-group; }
	th { background: #243b53; color: #fff; font-size: 9px; padding: 6px; text-align: left; }
	td { border-bottom: 1px solid #d9e2ec; padding: 5px 6px; vertical-align: top; }
	tr:nth-child(even) td { background: #f8fafc; }
	.text-right { text-align: right; }
	.empty { color: #627d98; padding: 16px; text-align: center; }
</style>
</head>
<body>
	<h1>' . $title . '</h1>
	<div class="meta">Gerado em ' . $generatedAt . ' &middot; ' . $totalLabel . ' registro(s) filtrado(s)</div>
	' . $this->filtersHtml($filters) . '
	' . $this->tableHtml($definition['columns'], $rows) . '
</body>
</html>';
		}

	/**
	 * Builds the active filter summary.
	 *
	 * @param array<string, string> $filters Active filters.
	 * @return string
	 */
	private function filtersHtml(array $filters): string
		{
		$parts = [];
		foreach ($filters as $key => $value)
			{
			if ($value === '')
				{
				continue;
				}

			$parts[] = '<strong>' . htmlspecialchars($this->humanLabel($key), ENT_QUOTES, 'UTF-8') . ':</strong> ' .
				htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			}

		$content = empty($parts)
			? 'Nenhum filtro aplicado.'
			: implode(' &nbsp; ', $parts);

		return '<div class="filters">' . $content . '</div>';
		}

	/**
	 * Builds the result table.
	 *
	 * @param array<int, array<string, mixed>> $columns Export columns.
	 * @param array<int, array<string, mixed>> $rows Export rows.
	 * @return string
	 */
	private function tableHtml(array $columns, array $rows): string
		{
		$html = '<table><thead><tr>';
		foreach ($columns as $column)
			{
			$alignClass = (($column['align'] ?? '') === 'right') ? ' class="text-right"' : '';
			$html .= '<th' . $alignClass . '>' . htmlspecialchars((string) $column['label'], ENT_QUOTES, 'UTF-8') . '</th>';
			}
		$html .= '</tr></thead><tbody>';

		if (empty($rows))
			{
			$html .= '<tr><td class="empty" colspan="' . count($columns) . '">Nenhum registro encontrado.</td></tr>';
			return $html . '</tbody></table>';
			}

		foreach ($rows as $row)
			{
			$html .= '<tr>';
			foreach ($columns as $column)
				{
				$alignClass = (($column['align'] ?? '') === 'right') ? ' class="text-right"' : '';
				$value = $this->columnValue($column, $row);
				$html .= '<td' . $alignClass . '>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
				}
			$html .= '</tr>';
			}

		return $html . '</tbody></table>';
		}

	/**
	 * Streams the generated PDF.
	 *
	 * @param string $html HTML document.
	 * @param string $filename Download filename.
	 * @param string $orientation Paper orientation.
	 * @return void
	 */
	private function streamPdf(string $html, string $filename, string $orientation): void
		{
		if (!class_exists(Dompdf::class))
			{
			throw new \RuntimeException('Dompdf is not available. Check Composer autoload configuration.');
			}

		$options = new Options();
		$options->setDefaultFont('DejaVu Sans');
		$options->setIsRemoteEnabled(false);
		$options->setTempDir(sys_get_temp_dir());
		if (defined('ROOT_PATH'))
			{
			$options->setChroot(ROOT_PATH);
			}

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4', $orientation);
		$dompdf->render();
		$dompdf->stream($filename, ['Attachment' => true]);
		exit;
		}
	}

?>
