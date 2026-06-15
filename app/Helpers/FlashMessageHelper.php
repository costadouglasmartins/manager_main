<?php

/**
 * Normalizes flash message types to Bootstrap alert variants.
 */
if (!function_exists('normalizeFlashMessageType'))
	{
	function normalizeFlashMessageType(string $type): string
		{
		$type = strtolower(trim($type));

		$aliases = [
			'error'   => 'danger',
			'fail'    => 'danger',
			'failure' => 'danger',
		];

		if (isset($aliases[$type]))
			{
			return $aliases[$type];
			}

		$validTypes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
		return in_array($type, $validTypes, true) ? $type : 'info';
		}
	}

/**
 * Stores a flash message to be rendered on the next page load.
 */
if (!function_exists('setFlashMessage'))
	{
	function setFlashMessage(string $type, string $message): void
		{
		if (session_status() === PHP_SESSION_NONE)
			{
			session_start();
			}

		$message = trim($message);
		if ($message === '')
			{
			return;
			}

		if (!isset($_SESSION['manager_main']) || !is_array($_SESSION['manager_main']))
			{
			$_SESSION['manager_main'] = [];
			}

		if (!isset($_SESSION['manager_main']['flash_messages']) || !is_array($_SESSION['manager_main']['flash_messages']))
			{
			$_SESSION['manager_main']['flash_messages'] = [];
			}

		$_SESSION['manager_main']['flash_messages'][] = [
			'type'    => normalizeFlashMessageType($type),
			'message' => $message,
		];
		}
	}

/**
 * Returns and clears flash messages from the session.
 *
 * @return array<int, array{type: string, message: string}>
 */
if (!function_exists('getFlashMessages'))
	{
	function getFlashMessages(): array
		{
		if (session_status() !== PHP_SESSION_ACTIVE)
			{
			return [];
			}

		$messages = $_SESSION['manager_main']['flash_messages'] ?? [];
		unset($_SESSION['manager_main']['flash_messages']);

		if (!is_array($messages))
			{
			return [];
			}

		$filtered = [];
		foreach ($messages as $message)
			{
			if (!is_array($message))
				{
				continue;
				}

			$text = trim((string) ($message['message'] ?? ''));
			if ($text === '')
				{
				continue;
				}

			$filtered[] = [
				'type'    => normalizeFlashMessageType((string) ($message['type'] ?? 'info')),
				'message' => $text,
			];
			}

		return $filtered;
		}
	}

/**
 * Renders Bootstrap alert markup for current flash messages.
 */
if (!function_exists('renderFlashMessages'))
	{
	function renderFlashMessages(): string
		{
		$messages = getFlashMessages();
		if (empty($messages))
			{
			return '';
			}

		$html = '<div class="flash-message-container" data-flash-message-container>' . PHP_EOL;
		foreach ($messages as $message)
			{
			$type = htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8');
			$text = htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8');

			$html .= '  <div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert" data-flash-message>' . PHP_EOL;
			$html .= '    ' . $text . PHP_EOL;
			$html .= '    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>' . PHP_EOL;
			$html .= '  </div>' . PHP_EOL;
			}
		$html .= '</div>' . PHP_EOL;

		return $html;
		}
	}

?>
