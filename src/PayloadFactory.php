<?php

namespace QrStudio;

use InvalidArgumentException;

class PayloadFactory
{
    public function make(array $data): string
    {
        return match ($data['type'] ?? null) {
            'url' => (string) $data['website_url'],
            'phone' => 'tel:'.preg_replace('/\s+/', '', (string) $data['phone_number']),
            'email' => $this->emailPayload($data),
            'vcard' => $this->vcardPayload($data),
            'wifi' => $this->wifiPayload($data),
            'text' => (string) $data['plain_text'],
            default => throw new InvalidArgumentException('type must be one of: '.implode(', ', array_keys($this->labels())).'.'),
        };
    }

    public function labels(): array
    {
        return [
            'url' => 'Website URL',
            'phone' => 'Phone number',
            'email' => 'Email address',
            'vcard' => 'vCard contact',
            'wifi' => 'Wi-Fi login',
            'text' => 'Plain text',
        ];
    }

    private function emailPayload(array $data): string
    {
        $address = trim((string) $data['email_address']);

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('email_address must be a valid email address.');
        }

        $params = [];

        if (! empty($data['email_subject'])) {
            $params['subject'] = $data['email_subject'];
        }

        if (! empty($data['email_body'])) {
            $params['body'] = $data['email_body'];
        }

        // RFC 6068: characters such as ? and & in the address are percent-encoded so
        // they can't add headers or recipients, and spaces in values are %20, not +.
        $address = implode('@', array_map('rawurlencode', explode('@', $address)));
        $query = $params === [] ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return 'mailto:'.$address.$query;
    }

    private function vcardPayload(array $data): string
    {
        $name = trim((string) $data['vcard_name']);
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.$this->structuredName($name),
            'FN:'.$this->escapeVcard($name),
        ];

        foreach ([
            'ORG' => $this->escapeVcard((string) ($data['vcard_organization'] ?? '')),
            'TITLE' => $this->escapeVcard((string) ($data['vcard_title'] ?? '')),
            'TEL;TYPE=WORK,VOICE' => $this->singleLine((string) ($data['vcard_phone'] ?? '')),
            'EMAIL' => $this->singleLine((string) ($data['vcard_email'] ?? '')),
            // URL is a URI value, not text, so commas and semicolons stay as they are.
            'URL' => $this->singleLine((string) ($data['vcard_url'] ?? '')),
        ] as $label => $value) {
            if ($value !== '') {
                $lines[] = $label.':'.$value;
            }
        }

        $lines[] = 'END:VCARD';

        // vCard 3.0 (RFC 2426) lines end in CRLF.
        return implode("\r\n", $lines);
    }

    // vCard 3.0 requires N (family;given;additional;prefix;suffix). Derived from the
    // full name: the last word is the family name and the rest is the given name.
    private function structuredName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $family = count($parts) > 1 ? array_pop($parts) : '';

        return $this->escapeVcard($family).';'.$this->escapeVcard(implode(' ', $parts)).';;;';
    }

    private function wifiPayload(array $data): string
    {
        $encryption = $this->wifiEncryption((string) ($data['wifi_encryption'] ?? ''));
        $password = $encryption === 'nopass' ? '' : (string) ($data['wifi_password'] ?? '');
        $hidden = ! empty($data['wifi_hidden']) ? 'true' : 'false';

        return sprintf(
            'WIFI:T:%s;S:%s;P:%s;H:%s;;',
            $encryption,
            $this->escapeWifi((string) $data['wifi_ssid']),
            $this->escapeWifi($password),
            $hidden,
        );
    }

    // Only the values phones understand. WPA2 and WPA3 networks are written as WPA,
    // which is what scanners expect for both.
    private function wifiEncryption(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'WPA', 'WPA2', 'WPA3' => 'WPA',
            'WEP' => 'WEP',
            'NOPASS', '', 'NONE', 'OPEN' => 'nopass',
            default => throw new InvalidArgumentException('wifi_encryption must be WPA, WEP or nopass.'),
        };
    }

    private function escapeVcard(string $value): string
    {
        return str_replace(["\\", "\r\n", "\n", "\r", ';', ','], ['\\\\', '\\n', '\\n', '', '\\;', '\\,'], $value);
    }

    private function singleLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function escapeWifi(string $value): string
    {
        return str_replace(["\\", ';', ',', ':', '"'], ['\\\\', '\\;', '\\,', '\\:', '\\"'], $value);
    }
}
