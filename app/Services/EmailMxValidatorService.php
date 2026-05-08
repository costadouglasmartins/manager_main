<?php

namespace App\Services;

class EmailMxValidatorService
	{
	/**
	 * Validate the e-mail syntax and check whether its domain has MX records.
	 *
	 * @return array<string, mixed>
	 */
	public function validate(string $email): array
		{
		$input = trim($email);
		$result = $this->baseResult($input);

		if ($input === '')
			{
			$result['message'] = 'Informe um endereço de e-mail para validar.';
			return $result;
			}

		$result['input_valid'] = true;
		if (strlen($input) > 254)
			{
			$result['message'] = 'O endereço de e-mail excede o tamanho máximo permitido.';
			return $result;
			}

		$parts = explode('@', $input);
		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '')
			{
			$result['message'] = 'O endereço de e-mail deve conter usuário e domínio.';
			return $result;
			}

		$localPart = $parts[0];
		$domain = strtolower(rtrim($parts[1], '.'));
		$asciiDomain = $this->toAsciiDomain($domain);
		$normalizedEmail = $localPart . '@' . $asciiDomain;

		$result['normalized_email'] = $normalizedEmail;
		$result['domain'] = $asciiDomain;
		$result['syntax_valid'] = filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false;
		$result['domain_valid'] = $this->isPublicDomainFormat($asciiDomain);

		if (!$result['syntax_valid'])
			{
			$result['message'] = 'A sintaxe do endereço de e-mail é inválida.';
			return $result;
			}

		if (!$result['domain_valid'])
			{
			$result['message'] = 'O domínio do e-mail não possui um formato público válido.';
			return $result;
			}

		$mxRecords = $this->lookupMxRecords($asciiDomain);
		$result['mx_records'] = $mxRecords;
		$result['mx_valid'] = count($mxRecords) > 0;

		if (!$result['mx_valid'])
			{
			$result['address_records'] = $this->lookupAddressRecords($asciiDomain);
			$result['message'] = 'O domínio não possui registros MX publicados.';
			return $result;
			}

		$result['valid'] = true;
		$result['message'] = 'O endereço tem sintaxe válida e o domínio possui MX publicado.';
		return $result;
		}

	/**
	 * Build the default result payload.
	 *
	 * @return array<string, mixed>
	 */
	private function baseResult(string $input): array
		{
		return [
			'input' => $input,
			'input_valid' => false,
			'normalized_email' => null,
			'domain' => null,
			'syntax_valid' => false,
			'domain_valid' => false,
			'mx_valid' => false,
			'valid' => false,
			'message' => '',
			'mx_records' => [],
			'address_records' => [],
			'checked_at' => date('c'),
		];
		}

	/**
	 * Convert internationalized domains when the Intl extension is available.
	 */
	private function toAsciiDomain(string $domain): string
		{
		if ($domain === '' || preg_match('/^[\x20-\x7E]+$/', $domain))
			{
			return $domain;
			}

		if (function_exists('idn_to_ascii'))
			{
			$variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
			$ascii = idn_to_ascii($domain, IDNA_DEFAULT, $variant);
			if (is_string($ascii) && $ascii !== '')
				{
				return strtolower($ascii);
				}
			}

		return $domain;
		}

	/**
	 * Check if a domain can be looked up as a public e-mail domain.
	 */
	private function isPublicDomainFormat(string $domain): bool
		{
		if ($domain === '' || strlen($domain) > 253 || strpos($domain, '.') === false)
			{
			return false;
			}

		$labels = explode('.', $domain);
		foreach ($labels as $label)
			{
			if ($label === '' || strlen($label) > 63)
				{
				return false;
				}

			if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $label))
				{
				return false;
				}
			}

		return true;
		}

	/**
	 * Lookup MX records and normalize their fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function lookupMxRecords(string $domain): array
		{
		$records = @dns_get_record($domain, DNS_MX);
		if (!is_array($records))
			{
			return @checkdnsrr($domain, 'MX') ? [['priority' => null, 'host' => null]] : [];
			}

		$out = [];
		foreach ($records as $record)
			{
			$host = (string) ($record['target'] ?? '');
			if ($host === '')
				{
				continue;
				}

			$out[] = [
				'priority' => isset($record['pri']) ? (int) $record['pri'] : null,
				'host' => rtrim(strtolower($host), '.'),
				'ttl' => isset($record['ttl']) ? (int) $record['ttl'] : null,
			];
			}

		usort($out, function (array $a, array $b): int
			{
			return ((int) ($a['priority'] ?? 0)) <=> ((int) ($b['priority'] ?? 0));
			});

		return $out;
		}

	/**
	 * Lookup A and AAAA records for domains without MX, useful for diagnostics.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function lookupAddressRecords(string $domain): array
		{
		$records = @dns_get_record($domain, DNS_A + DNS_AAAA);
		if (!is_array($records))
			{
			return [];
			}

		$out = [];
		foreach ($records as $record)
			{
			$type = (string) ($record['type'] ?? '');
			$value = (string) ($record['ip'] ?? ($record['ipv6'] ?? ''));
			if ($type === '' || $value === '')
				{
				continue;
				}

			$out[] = [
				'type' => $type,
				'value' => $value,
			];
			}

		return $out;
		}
	}

?>
