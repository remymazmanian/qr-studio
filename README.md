# QR Studio

A framework-agnostic PHP library for generating **styled** QR codes as SVG or PNG.

Most QR libraries give you black squares. This one gives you control over module
shape, eye shape, framing, colour, a centre logo and a caption — while keeping the
result scannable.

```php
use QrStudio\DesignOptions;
use QrStudio\QrRenderer;

$renderer = new QrRenderer();

// Simplest case.
file_put_contents('qr.svg', $renderer->result('https://example.com', 'svg')->getString());

// Styled.
$options = DesignOptions::fromArray([
    'module_style'     => DesignOptions::MODULE_DOTS,
    'eye_style'        => DesignOptions::EYE_CIRCLE,
    'frame_style'      => DesignOptions::FRAME_ROUNDED_CARD,
    'foreground_color' => '#1f3d7a',
    'eye_color'        => '#c2410c',
    'label_text'       => 'Scan me',
    'qr_size'          => 720,
]);

file_put_contents('styled.png', $renderer->result('https://example.com', 'png', $options)->getString());
```

## Install

QR Studio isn't on Packagist yet, so add the GitHub repository first:

```bash
composer config repositories.qr-studio vcs https://github.com/remymazmanian/qr-studio
composer require remymazmanian/qr-studio:^0.1
```

Requires **PHP 8.4+** (inherited from `endroid/qr-code` 6.x) and the **GD**
extension. GD does the PNG drawing, and `composer.json` requires `ext-gd` for every
install, including projects that only render SVG.

## Demo

A single-file playground with live preview, all payload types and every style
control:

```bash
composer install
composer demo     # or: php -S localhost:8080 -t demo
```

Then open <http://localhost:8080>.

The demo accepts a logo file upload, so treat it as a local development tool
rather than something to expose on a public host as-is.

## What you can control

| Option | Values |
| --- | --- |
| `module_style` | `square`, `rounded`, `dots`, `extra_rounded` |
| `eye_style` | `square`, `rounded`, `circle` |
| `frame_style` | `none`, `simple_border`, `rounded_card` |
| `error_correction_level` | `low`, `medium`, `quartile`, `high` |
| `foreground_color` / `background_color` / `eye_color` / `label_color` | any `#rrggbb` |
| `transparent_background` | `bool` — omits the background rect entirely |
| `qr_size` / `margin` | pixels |
| `logo_path` / `logo_size_percentage` | absolute path, and 0–25% of the QR |
| `label_text` / `label_font_size` | caption rendered beneath the code |

Three starting points are bundled — `generic`, `rounded_square` and `circle`:

```php
$values = array_replace(
    DesignOptions::defaults(),
    DesignOptions::presets()['circle']['values'],
);

$options = DesignOptions::fromArray($values);
```

## Payload types

`PayloadFactory` builds correctly-escaped payload strings, which matters more than
it sounds: Wi-Fi and vCard payloads are delimiter-separated, so an unescaped `;`
or `:` in a network name silently produces the wrong result on the scanning device.

```php
use QrStudio\PayloadFactory;

$factory = new PayloadFactory();

$factory->make(['type' => 'url',   'website_url' => 'https://example.com']);
$factory->make(['type' => 'text',  'plain_text' => 'Hello']);
$factory->make(['type' => 'phone', 'phone_number' => '+1 555 010 0100']);
$factory->make(['type' => 'email', 'email_address' => 'a@example.com', 'email_subject' => 'Hi']);
$factory->make(['type' => 'wifi',  'wifi_ssid' => 'Guest', 'wifi_password' => 'pw', 'wifi_encryption' => 'WPA']);
$factory->make(['type' => 'vcard', 'vcard_name' => 'Ada Lovelace', 'vcard_email' => 'ada@example.com']);
```

## Keeping codes scannable

Two things reliably break a "designed" QR code, and both are easy to do by accident:

**Low contrast.** If the modules do not contrast with what is behind them, phones
fail to lock on. `ColorContrast` implements the WCAG relative-luminance formula so
you can refuse a bad combination before rendering:

```php
use QrStudio\ColorContrast;

if (ColorContrast::contrastRatio('#888888', '#ffffff') < 3.0) {
    // too low - reject it
}
```

**Logos without headroom.** A centre logo covers data modules. Raise the error
correction level to `high` whenever a logo is present, and keep
`logo_size_percentage` at or below 25 — the library caps it there for this reason.

## Logos

`logo_path` is an **absolute filesystem path**. The library deliberately knows
nothing about storage disks, upload directories or URL schemes; resolving a user
upload to a real path is the calling application's job. That is what keeps this
package free of any framework convention.

## Output

`result()` returns an `Endroid\QrCode\Writer\Result\ResultInterface`:

```php
$result = $renderer->result($payload, 'svg', $options);

$result->getString();    // raw SVG or PNG bytes
$result->getDataUri();   // data: URI, handy for inline <img>
$result->getMimeType();
$result->saveToFile('/path/to/qr.svg');

$renderer->dataUri($payload);  // shorthand
```

## Tests

```bash
composer test
```

## Credits

Created by **Remy Mazmanian**.

## License

MIT — see [LICENSE](LICENSE).
