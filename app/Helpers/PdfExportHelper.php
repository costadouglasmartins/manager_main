<?php
/**
 * PDF export helpers.
 */

if (!function_exists('pdfExportUrl'))
	{
	/**
	 * Builds a PDF export URL while preserving the current filter and sort query.
	 *
	 * @param string $route Base list route.
	 * @param string $queryString Existing query string without leading question mark.
	 * @return string
	 */
	function pdfExportUrl(string $route, string $queryString = ''): string
		{
		$params = [];
		if ($queryString !== '')
			{
			parse_str($queryString, $params);
			}

		unset($params['page'], $params['url'], $params['format']);

		$path = trim($route, '/') . '/export-pdf';
		$query = http_build_query($params);

		return url($path . ($query !== '' ? '?' . $query : ''));
		}
	}

if (!function_exists('pdfExportButton'))
	{
	/**
	 * Renders the default PDF export button for index pages.
	 *
	 * @param string $route Base list route.
	 * @param string $queryString Existing query string without leading question mark.
	 * @param string $label Button label.
	 * @param string $class CSS classes.
	 * @return string
	 */
	function pdfExportButton(
		string $route,
		string $queryString = '',
		string $label = 'Exportar PDF',
		string $class = 'btn btn-outline-danger btn-sm index-search-action'
	): string
		{
		$href = pdfExportUrl($route, $queryString);
		$escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

		return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-label="' . $escapedLabel . '" title="' . $escapedLabel . '">' .
			'<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>' .
			'<span class="visually-hidden">' . $escapedLabel . '</span>' .
			'</a>';
		}
	}

?>
