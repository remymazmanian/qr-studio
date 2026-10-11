<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use PHPUnit\Framework\TestCase;
use QrStudio\PayloadFactory;

class PayloadFactoryTest extends TestCase
{
    private PayloadFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PayloadFactory();
    }

    public function test_url_payload(): void
    {
        $this->assertSame('https://example.com', $this->factory->make([
            'type' => 'url',
            'website_url' => 'https://example.com',
        ]));
    }

    public function test_phone_payload_strips_whitespace(): void
    {
        $this->assertSame('tel:+15550100100', $this->factory->make([
            'type' => 'phone',
            'phone_number' => '+1 555 010 0100',
        ]));
    }

    public function test_email_payload_encodes_subject_and_body(): void
    {
        $payload = $this->factory->make([
            'type' => 'email',
            'email_address' => 'hello@example.com',
            'email_subject' => 'Hi there',
            'email_body' => 'Line one',
        ]);

        $this->assertStringStartsWith('mailto:hello@example.com?', $payload);
        // RFC 6068: spaces are %20 in mailto links; + would show up literally.
        $this->assertStringContainsString('subject=Hi%20there', $payload);
        $this->assertStringContainsString('body=Line%20one', $payload);
    }

    public function test_email_payload_without_extras_has_no_query_string(): void
    {
        $this->assertSame('mailto:hello@example.com', $this->factory->make([
            'type' => 'email',
            'email_address' => 'hello@example.com',
        ]));
    }

    public function test_wifi_payload(): void
    {
        $this->assertSame('WIFI:T:WPA;S:Guest Network;P:s3cret;H:false;;', $this->factory->make([
            'type' => 'wifi',
            'wifi_ssid' => 'Guest Network',
            'wifi_password' => 's3cret',
            'wifi_encryption' => 'WPA',
        ]));
    }

    public function test_open_wifi_network_omits_the_password(): void
    {
        $this->assertSame('WIFI:T:nopass;S:Cafe;P:;H:false;;', $this->factory->make([
            'type' => 'wifi',
            'wifi_ssid' => 'Cafe',
            'wifi_password' => 'ignored',
            'wifi_encryption' => 'nopass',
        ]));
    }

    /**
     * Wi-Fi payloads are delimited by ; and :, so an SSID containing them has to be
     * escaped or the scanning device reads the wrong network name.
     */
    public function test_wifi_payload_escapes_delimiters(): void
    {
        $payload = $this->factory->make([
            'type' => 'wifi',
            'wifi_ssid' => 'Net;work:name',
            'wifi_password' => 'pa,ss',
            'wifi_encryption' => 'WPA',
        ]);

        $this->assertStringContainsString('S:Net\;work\:name;', $payload);
        $this->assertStringContainsString('P:pa\,ss;', $payload);
    }

    public function test_vcard_payload(): void
    {
        $payload = $this->factory->make([
            'type' => 'vcard',
            'vcard_name' => 'Ada Lovelace',
            'vcard_organization' => 'Analytical Engines',
            'vcard_email' => 'ada@example.com',
        ]);

        // vCard 3.0 lines end in CRLF and require N as well as FN.
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", $payload);
        $this->assertStringContainsString("\r\nN:Lovelace;Ada;;;\r\n", $payload);
        $this->assertStringContainsString('FN:Ada Lovelace', $payload);
        $this->assertStringContainsString('ORG:Analytical Engines', $payload);
        $this->assertStringContainsString('EMAIL:ada@example.com', $payload);
        $this->assertStringEndsWith('END:VCARD', $payload);
    }

    public function test_vcard_omits_empty_fields(): void
    {
        $payload = $this->factory->make([
            'type' => 'vcard',
            'vcard_name' => 'Ada Lovelace',
            'vcard_organization' => '',
        ]);

        $this->assertStringNotContainsString('ORG:', $payload);
    }

    public function test_vcard_escapes_structural_characters(): void
    {
        $payload = $this->factory->make([
            'type' => 'vcard',
            'vcard_name' => 'Lovelace, Ada; Countess',
        ]);

        $this->assertStringContainsString('FN:Lovelace\, Ada\; Countess', $payload);
    }

    public function test_text_payload(): void
    {
        $this->assertSame('Hello', $this->factory->make([
            'type' => 'text',
            'plain_text' => 'Hello',
        ]));
    }

    public function test_every_advertised_type_is_buildable(): void
    {
        foreach (array_keys($this->factory->labels()) as $type) {
            $payload = $this->factory->make([
                'type' => $type,
                'website_url' => 'https://example.com',
                'plain_text' => 'text',
                'phone_number' => '5550100100',
                'email_address' => 'a@example.com',
                'wifi_ssid' => 'net',
                'wifi_encryption' => 'WPA',
                'wifi_password' => 'pw',
                'vcard_name' => 'Ada',
            ]);

            $this->assertNotSame('', $payload, "Type [{$type}] produced an empty payload.");
        }
    }

    public function test_email_address_characters_cannot_add_recipients(): void
    {
        $payload = $this->factory->make(['type' => 'email', 'email_address' => 'a?cc=evil@example.com']);

        $this->assertSame('mailto:a%3Fcc%3Devil@example.com', $payload);
    }

    public function test_an_invalid_email_address_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email_address must be a valid email address');

        $this->factory->make(['type' => 'email', 'email_address' => 'not an address']);
    }

    public function test_wifi_encryption_cannot_inject_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wifi_encryption must be WPA, WEP or nopass');

        $this->factory->make(['type' => 'wifi', 'wifi_ssid' => 'Home', 'wifi_encryption' => 'WPA;S:Evil']);
    }

    public function test_wpa2_and_wpa3_networks_are_written_as_wpa(): void
    {
        foreach (['WPA2', 'wpa3', 'wpa'] as $encryption) {
            $payload = $this->factory->make(['type' => 'wifi', 'wifi_ssid' => 'Home', 'wifi_password' => 'secret', 'wifi_encryption' => $encryption]);
            $this->assertStringStartsWith('WIFI:T:WPA;S:Home;', $payload);
        }
    }

    public function test_vcard_urls_keep_their_commas(): void
    {
        $payload = $this->factory->make(['type' => 'vcard', 'vcard_name' => 'Ada', 'vcard_url' => 'https://example.com/a,b;c']);

        $this->assertStringContainsString("\r\nURL:https://example.com/a,b;c\r\n", $payload);
        $this->assertStringContainsString("\r\nN:;Ada;;;\r\n", $payload);
    }

    public function test_an_unknown_type_is_rejected_with_a_clear_message(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('type must be one of: url, phone, email, vcard, wifi, text');

        $this->factory->make(['type' => 'sms']);
    }
}
