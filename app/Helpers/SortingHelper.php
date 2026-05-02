<?php

/**
 * Sorting Helper
 *
 * Provides safe helpers for sortable table headers and SQL ORDER BY clauses.
 */

/**
 * Returns the first key from an allowed columns map.
 *
 * @param array<string, string> $allowedColumns Sort key to SQL expression map.
 * @return string
 */
if (!function_exists('sortFirstAllowedKey'))
	{
	function sortFirstAllowedKey(array $allowedColumns): string
		{
		foreach ($allowedColumns as $key => $_expression)
			{
			return (string) $key;
			}

		return '';
		}
	}

/**
 * Normalizes a requested sort state against the allowed column map.
 *
 * @param array<string, string> $allowedColumns Sort key to SQL expression map.
 * @param string $defaultSort Default sort key.
 * @param string $defaultDirection Default direction (`asc` or `desc`).
 * @param array<string, mixed>|null $source Query source. Defaults to $_GET.
 * @return array{sort: string, direction: string}
 */
if (!function_exists('sortRequest'))
	{
	function sortRequest(array $allowedColumns, string $defaultSort = 'id', string $defaultDirection = 'desc', ?array $source = null): array
		{
		$source = $source ?? $_GET;
		$firstAllowed = sortFirstAllowedKey($allowedColumns);

		if ($firstAllowed !== '' && !array_key_exists($defaultSort, $allowedColumns))
			{
			$defaultSort = $firstAllowed;
			}

		$direction = strtolower((string) ($source['direction'] ?? $defaultDirection));
		if (!in_array($direction, ['asc', 'desc'], true))
			{
			$direction = strtolower($defaultDirection);
			}
		if (!in_array($direction, ['asc', 'desc'], true))
			{
			$direction = 'asc';
			}

		$sort = (string) ($source['sort'] ?? $defaultSort);
		if (!array_key_exists($sort, $allowedColumns))
			{
			$sort = $defaultSort;
			}

		return [
			'sort'      => $sort,
			'direction' => $direction,
		];
		}
	}

/**
 * Builds a safe SQL ORDER BY expression from a normalized sort state.
 *
 * @param array<string, mixed>|null $sort Sort state.
 * @param array<string, string> $allowedColumns Sort key to SQL expression map.
 * @param string $defaultSort Default sort key.
 * @param string $defaultDirection Default direction (`asc` or `desc`).
 * @return string SQL fragment without the `ORDER BY` keyword.
 */
if (!function_exists('sortOrderBy'))
	{
	function sortOrderBy(?array $sort, array $allowedColumns, string $defaultSort = 'id', string $defaultDirection = 'desc'): string
		{
		$state = sortRequest($allowedColumns, $defaultSort, $defaultDirection, is_array($sort) ? $sort : []);
		$expression = (string) ($allowedColumns[$state['sort']] ?? '');

		if ($expression === '')
			{
			$firstAllowed = sortFirstAllowedKey($allowedColumns);
			$expression = (string) ($allowedColumns[$firstAllowed] ?? $defaultSort);
			}

		return $expression . ' ' . strtoupper($state['direction']);
		}
	}

/**
 * Adds the current sort state to an existing query string.
 *
 * @param string $queryFilters Existing query string without leading question mark.
 * @param array<string, mixed> $sort Sort state.
 * @return string
 */
if (!function_exists('sortQueryString'))
	{
	function sortQueryString(string $queryFilters = '', array $sort = []): string
		{
		$params = [];
		if ($queryFilters !== '')
			{
			parse_str($queryFilters, $params);
			}

		unset($params['page']);

		if (!empty($sort['sort']))
			{
			$params['sort'] = (string) $sort['sort'];
			}

		if (!empty($sort['direction']) && in_array(strtolower((string) $sort['direction']), ['asc', 'desc'], true))
			{
			$params['direction'] = strtolower((string) $sort['direction']);
			}

		return http_build_query($params);
		}
	}

/**
 * Renders hidden inputs that keep the active sort when submitting filters.
 *
 * @param array<string, mixed> $sort Sort state.
 * @return string
 */
if (!function_exists('sortHiddenInputs'))
	{
	function sortHiddenInputs(array $sort = []): string
		{
		if (empty($sort['sort']) || empty($sort['direction']))
			{
			return '';
			}

		$direction = strtolower((string) $sort['direction']);
		if (!in_array($direction, ['asc', 'desc'], true))
			{
			$direction = 'asc';
			}

		$html  = '<input type="hidden" name="sort" value="' . htmlspecialchars((string) $sort['sort'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
		$html .= '<input type="hidden" name="direction" value="' . htmlspecialchars($direction, ENT_QUOTES, 'UTF-8') . '">' . "\n";

		return $html;
		}
	}

/**
 * Renders a sortable table header cell.
 *
 * @param string $route Base route name passed to url().
 * @param string $column Sort key.
 * @param string $label Header label.
 * @param array<string, mixed> $sort Current sort state.
 * @param string $queryFilters Existing query string without leading question mark.
 * @param string $attributes Additional attributes for the th element.
 * @return string
 */
if (!function_exists('sortableTh'))
	{
	function sortableTh(string $route, string $column, string $label, array $sort = [], string $queryFilters = '', string $attributes = ''): string
		{
		$currentSort = (string) ($sort['sort'] ?? '');
		$currentDirection = strtolower((string) ($sort['direction'] ?? 'asc'));
		$isActive = $currentSort === $column;
		$nextDirection = ($isActive && $currentDirection === 'asc') ? 'desc' : 'asc';

		$params = [];
		if ($queryFilters !== '')
			{
			parse_str($queryFilters, $params);
			}

		unset($params['page']);
		$params['sort'] = $column;
		$params['direction'] = $nextDirection;

		$href = url($route . '?' . http_build_query($params));
		$icon = 'bi-arrow-down-up opacity-50';
		$ariaSort = '';

		if ($isActive)
			{
			$icon = $currentDirection === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
			$ariaSort = ' aria-sort="' . ($currentDirection === 'asc' ? 'ascending' : 'descending') . '"';
			}

		$attributes = trim($attributes);
		$attributes = $attributes !== '' ? ' ' . $attributes : '';

		$html  = '<th scope="col"' . $ariaSort . $attributes . '>';
		$html .= '<a class="text-reset text-decoration-none d-inline-flex align-items-center gap-1" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" title="Ordenar por ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
		$html .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
		$html .= '<i class="bi ' . $icon . '" aria-hidden="true"></i>';
		$html .= '</a>';
		$html .= '</th>';

		return $html;
		}
	}
