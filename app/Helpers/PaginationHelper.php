<?php

/**
 * Pagination Helper
 *
 * Provides functions to build Bootstrap 5 pagination for list views.
 * Usage in views:
 *   echo paginationLinks('user', $page, $totalPages, $queryFilters);
 */

/**
 * Builds the sorted list of page numbers to display.
 *
 * Always includes the first and last page plus up to $delta
 * pages on each side of the current page.
 *
 * @param int $page       Current page number.
 * @param int $totalPages Total number of pages.
 * @param int $delta      Number of pages shown on each side of current.
 *
 * @return int[]
 */
if (!function_exists('paginationPageList'))
  {

  function paginationPageList(int $page, int $totalPages, int $delta = 2): array
    {
    if ($totalPages <= 1)
      {
      return [1];
      }

    $range = range(
      max(2, $page - $delta),
      min($totalPages - 1, $page + $delta)
    );

    $pages = array_unique(array_merge([1], $range, [$totalPages]));
    sort($pages);

    return $pages;
    }

  }

/**
 * Renders Bootstrap 5 pagination links as an HTML string.
 *
 * Only renders anything when $totalPages > 1.
 *
 * @param string $route         Base route name passed to url() — e.g. 'user', 'person'.
 * @param int    $page          Current page number.
 * @param int    $totalPages    Total number of pages.
 * @param string $queryFilters  Query string from http_build_query(), without a leading '&'.
 * @param int    $delta         Pages shown on each side of the current page (default 2).
 * @param string $labelPrevious Label for the "previous" button.
 * @param string $labelNext     Label for the "next" button.
 *
 * @return string HTML ready to echo in the view, or empty string when not needed.
 */
if (!function_exists('paginationLinks'))
  {

  function paginationLinks(
    string $route,
    int    $page,
    int    $totalPages,
    string $queryFilters  = '',
    int    $delta         = 2,
    string $labelPrevious = 'Previous',
    string $labelNext     = 'Next'
  ): string
    {
    if ($totalPages <= 1)
      {
      return '';
      }

    $qs       = $queryFilters !== '' ? '&' . $queryFilters : '';
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);

    // Wrapper
    $html  = '<div class="overflow-auto">' . "\n";
    $html .= '  <nav aria-label="Page navigation">' . "\n";
    $html .= '    <ul class="pagination pagination-sm flex-nowrap mb-0">' . "\n";

    // Previous button
    $prevClass = $page <= 1 ? ' disabled' : '';
    $prevHref  = url($route . '?page=' . $prevPage) . $qs;
    $html .= '      <li class="page-item' . $prevClass . '">' . "\n";
    $html .= '        <a class="page-link" href="' . $prevHref . '">' . htmlspecialchars($labelPrevious, ENT_QUOTES, 'UTF-8') . '</a>' . "\n";
    $html .= '      </li>' . "\n";

    // Page number buttons
    $pages  = paginationPageList($page, $totalPages, $delta);
    $prevPg = null;

    foreach ($pages as $p)
      {
      // Ellipsis gap
      if ($prevPg !== null && $p - $prevPg > 1)
        {
        $html .= '      <li class="page-item disabled"><span class="page-link">&#8230;</span></li>' . "\n";
        }

      $activeClass = ($p === $page) ? ' active' : '';
      $href        = url($route . '?page=' . $p) . $qs;

      $html .= '      <li class="page-item' . $activeClass . '">' . "\n";
      $html .= '        <a class="page-link" href="' . $href . '">' . $p . '</a>' . "\n";
      $html .= '      </li>' . "\n";

      $prevPg = $p;
      }

    // Next button
    $nextClass = $page >= $totalPages ? ' disabled' : '';
    $nextHref  = url($route . '?page=' . $nextPage) . $qs;
    $html .= '      <li class="page-item' . $nextClass . '">' . "\n";
    $html .= '        <a class="page-link" href="' . $nextHref . '">' . htmlspecialchars($labelNext, ENT_QUOTES, 'UTF-8') . '</a>' . "\n";
    $html .= '      </li>' . "\n";

    $html .= '    </ul>' . "\n";
    $html .= '  </nav>' . "\n";
    $html .= '</div>' . "\n";

    return $html;
    }

  }
