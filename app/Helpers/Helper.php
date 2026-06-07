<?php
/**
 * Only digits
 */
if (!function_exists('onlyDigits'))
	{

	function onlyDigits(?string $value): string
		{
		if ($value === null)
			{
			return '';
			}
		return preg_replace('/\D+/', '', $value);
		}

	}

/**
 * Only alphanumeric chars
 */
if (!function_exists('onlyAlphanumeric'))
	{

	function onlyAlphanumeric(?string $value): string
		{
		if ($value === null)
			{
			return '';
			}
		return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value));
		}

	}

/**
 * Mask CPF and CPNJ
 */
if (!function_exists('maskCpfCnpj'))
	{

		function maskCpfCnpj(?string $value): string
			{
		$alphanumeric = onlyAlphanumeric($value);
		if ($alphanumeric === '')
			{
			return '';
			}
		// CPF: 000.000.000-00
		if (ctype_digit($alphanumeric) && strlen($alphanumeric) === 11)
			{
			return preg_replace(
				'/(\d{3})(\d{3})(\d{3})(\d{2})/',
				'$1.$2.$3-$4',
				$alphanumeric
			);
			}
		// CNPJ: 00.000.000/0000-00 or alphanumeric equivalent
		if (strlen($alphanumeric) === 14)
			{
			return preg_replace(
				'/([A-Z0-9]{2})([A-Z0-9]{3})([A-Z0-9]{3})([A-Z0-9]{4})([A-Z0-9]{2})/',
				'$1.$2.$3/$4-$5',
				$alphanumeric
			);
			}
		// If there is not 11 or 14 digits, returns original value without mask
		return $value ?? '';
		}

	}

/**
 * Mask phone numbers
 */
if (!function_exists('maskPhoneBr'))
	{

	function maskPhoneBr(?string $value): string
		{
		$digits = onlyDigits($value);
		if ($digits === '')
			{
			return '';
			}
		$len = strlen($digits);
		// Brazilian mobile with country code: +55(11)91234-5678
		if ($len === 13 && substr($digits, 0, 2) === '55')
			{
			return preg_replace(
				'/(\d{2})(\d{2})(\d{1})(\d{4})(\d{4})/',
				'+$1($2)$3$4-$5',
				$digits
			);
			}
		// Brazilian landline with country code: +55(11)1234-5678
		if ($len === 12 && substr($digits, 0, 2) === '55')
			{
			return preg_replace(
				'/(\d{2})(\d{2})(\d{4})(\d{4})/',
				'+$1($2)$3-$4',
				$digits
			);
			}
		// 0800 xxx xxxx (11 digits starting with 0800)
		if ($len === 11 && substr($digits, 0, 4) === '0800')
			{
			return preg_replace(
				'/(0800)(\d{3})(\d{4})/',
				'$1 $2 $3',
				$digits
			);
			}
		// Mobile with area code: (11) 91234-5678 (11 digits)
		if ($len === 11)
			{
			return preg_replace(
				'/(\d{2})(\d{1})(\d{4})(\d{4})/',
				'($1) $2$3-$4',
				$digits
			);
			}
		// Landline with area code: (11) 1234-5678 (10 digits)
		if ($len === 10)
			{
			return preg_replace(
				'/(\d{2})(\d{4})(\d{4})/',
				'($1) $2-$3',
				$digits
			);
			}
		// No area code (8-digit landline): 1234-5678
		if ($len === 8)
			{
			return preg_replace(
				'/(\d{4})(\d{4})/',
				'$1-$2',
				$digits
			);
			}
		// No area code (9-digit mobile): 91234-5678
		if ($len === 9)
			{
			return preg_replace(
				'/(\d{1})(\d{4})(\d{4})/',
				'$1$2-$3',
				$digits
			);
			}
		// Any other format: return original input
		return $value ?? '';
		}

	/**
	 * Helper to create urls
	 */
	if (!function_exists('url'))
		{

		function url(string $path = ''): string
			{
			return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
			}

		}
	}
	
/**
 * Masks brazilian dates
 */
if (!function_exists('maskDateBr'))
	{

	function maskDateBr(?string $input): ?string
		{
		if ($input === null)
			{
			return null;
			}

		$s = trim($input);
		if ($s === '')
			{
			return null;
			}

		// Strictly validate YYYY-MM-DD
		$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
		$errors = DateTimeImmutable::getLastErrors();

		if ($dt === false || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
			{
			return null;
			}

		return $dt->format('d/m/Y'); // DD/MM/YYYY
		}

	}

/**
 * Masks date and datetime strings using the Brazilian format.
 */
if (!function_exists('maskDateTimeBr'))
	{

	function maskDateTimeBr(?string $input): string
		{
		if ($input === null)
			{
			return '';
			}

		$value = trim($input);
		if ($value === '')
			{
			return '';
			}

		$value = str_replace('T', ' ', $value);
		$formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];

		foreach ($formats as $format)
			{
			$date = DateTimeImmutable::createFromFormat('!' . $format, $value);
			$errors = DateTimeImmutable::getLastErrors();

			if ($date !== false && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)
				{
				return $format === 'Y-m-d' ? $date->format('d/m/Y') : $date->format('d/m/Y H:i');
				}
			}

		return $input;
		}

	}
	
/**
 * Convers brazilian dates to american dates 
 */
if (!function_exists('dateBrToUs'))
	{

	function dateBrToUs($date)
		{
		$dateTmp = explode('/', $date);
		/**
		 * 0 => day
		 * 1 => month
		 * 2 => year
		 */
		return $dateTmp[2] . '-' . $dateTmp[1] . '-' . $dateTmp[0];
		}

	}

/**
 * Authorization helper for views.
 */
if (!function_exists('authCan'))
	{
	function authCan(string $ability): bool
		{
		if (!class_exists('App\\Core\\Authorization'))
			{
			return false;
			}

		return \App\Core\Authorization::can($ability);
		}
	}

if (!function_exists('authIsRoot'))
	{
	function authIsRoot(): bool
		{
		if (!class_exists('App\\Core\\Authorization'))
			{
			return false;
			}

		return \App\Core\Authorization::isRoot();
		}
	}
?>
