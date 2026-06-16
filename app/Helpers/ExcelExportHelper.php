<?php
/**
 * Excel export helpers.
 */

if (!function_exists('excelExportUrl'))
	{
	/**
	 * Builds an Excel export URL while preserving the current filter and sort query.
	 *
	 * @param string $route Base list route.
	 * @param string $queryString Existing query string without leading question mark.
	 * @return string
	 */
	function excelExportUrl(string $route, string $queryString = ''): string
		{
		$params = [];
		if ($queryString !== '')
			{
			parse_str($queryString, $params);
			}

		unset($params['page'], $params['url'], $params['format']);

		$path = trim($route, '/') . '/export-excel';
		$query = http_build_query($params);

		return url($path . ($query !== '' ? '?' . $query : ''));
		}
	}

if (!function_exists('excelExportButton'))
	{
	/**
	 * Renders the default Excel export button for index pages.
	 *
	 * @param string $route Base list route.
	 * @param string $queryString Existing query string without leading question mark.
	 * @param string $label Button label.
	 * @param string $class CSS classes.
	 * @return string
	 */
	function excelExportButton(
		string $route,
		string $queryString = '',
		string $label = 'Exportar Excel',
		string $class = 'btn btn-outline-success w-100'
	): string
		{
		$href = excelExportUrl($route, $queryString);

		return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' .
			'<i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' .
			htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
			'</a>';
		}
	}

?>
