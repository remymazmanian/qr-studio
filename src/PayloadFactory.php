<?php

namespace QrStudio;

class PayloadFactory
{
    public function make(array $data): string
    {
        return match ($data['type']) {
            'url' => (string) $data['website_url'],
            'phone' => 'tel:'.preg_replace('/\s+/', '', (string) $data['phone_number']),
            'email' => $this->emailPayload($data),
            'vcard' => $this->vcardPayload($data),
            'wifi' => $this->wifiPayload($data),
            'text' => (string) $data['plain_text'],
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
        $params = [];

        if (! empty($data['email_subject'])) {
            $params['subject'] = $data['email_subject'];
        }

        if (! empty($data['email_body'])) {
            $params['body'] = $data['email_body'];
        }

        $query = $params === [] ? '' : '?'.http_build_query($params);

        return 'mailto:'.$data['email_address'].$query;
    }

    private function vcardPayload(array $data): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$this->escapeVcard((string) $data['vcard_name']),
        ];

        foreach ([
            'ORG' => $data['vcard_organization'] ?? null,
            'TITLE' => $data['vcard_title'] ?? null,
            'TEL;TYPE=WORK,VOICE' => $data['vcard_phone'] ?? null,
            'EMAIL' => $data['vcard_email'] ?? null,
            'URL' => $data['vcard_url'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $label.':'.$this->escapeVcard((string) $value);
            }
        }

        $lines[] = 'END:VCARD';

        return implode("\n", $lines);
    }

    private function wifiPayload(array $data): string
    {
        $encryption = (string) $data['wifi_encryption'];
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

    private function escapeVcard(string $value): string
    {
        return str_replace(["\\", "\n", "\r", ';', ','], ['\\\\', '\\n', '', '\\;', '\\,'], $value);
    }

    private function escapeWifi(string $value): string
    {
        return str_replace(["\\", ';', ',', ':', '"'], ['\\\\', '\\;', '\\,', '\\:', '\\"'], $value);
    }
}
