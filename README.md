# QR Studio

[![Tests](https://github.com/remymazmanian/qr-studio/actions/workflows/tests.yml/badge.svg)](https://github.com/remymazmanian/qr-studio/actions/workflows/tests.yml)

A framework-agnostic PHP library for generating **styled** QR codes as SVG or PNG.

**Docs, demo & setup guide:** [remymazmanian.com/qr-studio/](https://remymazmanian.com/qr-studio/)

Most QR libraries give you black squares. This one gives you control over module
shape, eye shape, framing, colour, a centre logo and a caption, while keeping the
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

```bash
composer require remymazmanian/qr-studio
```

Requires **PHP 8.4+** (inherited from `endroid/qr-code` 6.x). PNG output also needs
the **GD** extension; SVG output works without it. Asking for a PNG without GD throws
a `RuntimeException` that says so.

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
| `foreground_color` / `background_color` / `eye_color` / `label_color` | `#rrggbb` or `#rgb` |
| `transparent_background` | `bool`. Removes the background fill. The gap inside each eye and the panel behind a logo stay white, so the code still scans. |
| `qr_size` | Size of the code itself in pixels, 64 to 4096. It's rounded down to a whole number of pixels per module, and the margin, frame and caption are added around it. |
| `margin` | Pixels, 0 to 1024. Never less than the 4-module quiet zone scanners need. |
| `logo_path` / `logo_size_percentage` | Local file path, and 0 to 25% of the QR (larger values are capped at 25%) |
| `label_text` / `label_font_size` | One-line caption beneath the code, 8 to 200 px. Long captions shrink to fit, then end in "…". |
| `label_font_path` | Optional TTF or OTF file for PNG captions, for scripts the bundled Open Sans doesn't cover |

Options outside these values throw an `InvalidArgumentException` naming the option,
so a typo such as `'module_style' => 'dot'` fails loudly instead of rendering squares.

Three starting points are bundled: `generic`, `rounded_square` and `circle`:

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

- **Email:** the address must be valid, and it's percent-encoded so it can't add recipients or headers.
- **Wi-Fi:** `wifi_encryption` is `WPA` (WPA2 and WPA3 are written as WPA, which is what phones expect), `WEP` or `nopass`.
- **vCard:** follows vCard 3.0, with CRLF line endings and the required `N` field derived from the name.
- **Unknown values:** an unknown `type` or encryption throws an `InvalidArgumentException`.

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

**Logos without headroom.** A centre logo covers data modules. When a logo is
drawn, `low` and `medium` error correction are raised to `quartile` automatically;
use `high` for the most headroom. `logo_size_percentage` is capped at 25 for the
same reason.

**Too few pixels per module.** Very small PNGs leave each module only a few pixels.
Modules are drawn to exact pixel boundaries, and below 6 px per module the `dots`
style is drawn as squares so it stays readable.

## Logos

`logo_path` is a **local filesystem path**. The library deliberately knows nothing
about storage disks, upload directories or URL schemes; resolving a user upload to
a real path is the calling application's job. That is what keeps this package free
of any framework convention.

Logos must be PNG, JPEG, GIF or WebP. Paths with a stream wrapper such as `phar://`
or `https://` are refused, and so are images larger than 4096 pixels on either side,
which are checked before anything is decoded.

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

Besides unit tests, the suite renders codes across sizes, styles, error-correction
levels and logos, then decodes each PNG with an independent QR reader, so a code
that looks right but doesn't scan fails the build. GitHub Actions runs it on PHP 8.4
and 8.5, and without GD.

## Credits

Created by **Remy Mazmanian**.

## License

MIT. See [LICENSE](LICENSE).
