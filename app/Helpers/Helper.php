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
 * Mask CPF and CPNJ
 */
if (!function_exists('  '))
	{

	function maskCpfCnpj(?string $value): string
		{
		$digits = onlyDigits($value);
		if ($digits === '')
			{
			return '';
			}
		// CPF: 000.000.000-00
		if (strlen($digits) === 11)
			{
			return preg_replace(
				'/(\d{3})(\d{3})(\d{3})(\d{2})/',
				'$1.$2.$3-$4',
				$digits
			);
			}
		// CNPJ: 00.000.000/0000-00
		if (strlen($digits) === 14)
			{
			return preg_replace(
				'/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/',
				'$1.$2.$3/$4-$5',
				$digits
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
		// 0800 xxx xxxx (11 dígitos começando com 0800)
		if ($len === 11 && substr($digits, 0, 4) === '0800')
			{
			return preg_replace(
				'/(0800)(\d{3})(\d{4})/',
				'$1 $2 $3',
				$digits
			);
			}
		// Celular com DDD: (11) 91234-5678 (11 dígitos)
		if ($len === 11)
			{
			return preg_replace(
				'/(\d{2})(\d{1})(\d{4})(\d{4})/',
				'($1) $2$3-$4',
				$digits
			);
			}
		// Fixo com DDD: (11) 1234-5678 (10 dígitos)
		if ($len === 10)
			{
			return preg_replace(
				'/(\d{2})(\d{4})(\d{4})/',
				'($1) $2-$3',
				$digits
			);
			}
		// Sem DDD (8 dígitos fixo): 1234-5678
		if ($len === 8)
			{
			return preg_replace(
				'/(\d{4})(\d{4})/',
				'$1-$2',
				$digits
			);
			}
		// Sem DDD (9 dígitos celular): 91234-5678
		if ($len === 9)
			{
			return preg_replace(
				'/(\d{1})(\d{4})(\d{4})/',
				'$1$2-$3',
				$digits
			);
			}
		// Qualquer outro formato: devolve o original
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

		// Valida estritamente YYYY-MM-DD
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
?>
