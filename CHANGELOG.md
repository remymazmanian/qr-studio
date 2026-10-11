# Changelog

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-10-10

Fixes codes that didn't scan, small PNGs and logos with low error correction among them, tightens how options and payloads are checked, and prepares the package for Packagist. Invalid options now throw an error instead of rendering something unexpected, so check any values your app builds dynamically.

### Fixed

- PNG modules are drawn at their exact size. Each module came out one pixel too large, so PNGs under about 245 px often didn't scan. SVG output was not affected.
- Codes with a centre logo scan at every error-correction level. When a logo is drawn, `low` and `medium` are raised to `quartile`.
- The `dots` style is drawn as squares below 6 px per module, where a circle can't be centred and scanners misread it.
- Every code keeps at least the 4-module quiet zone scanners need, whatever `margin` is set to.
- Short colours such as `#fff` render correctly in PNG, and their contrast ratios are correct.
- Email links use `%20` for spaces as RFC 6068 requires, and the address is percent-encoded so characters such as `?` can't add recipients.
- vCards follow vCard 3.0, with CRLF line endings, the required `N` field, and URLs left unescaped.
- Captions use the same font, Open Sans, and the same size in SVG and PNG. Long captions shrink to fit and end in "…" when needed, and control characters no longer break the SVG.
- No more deprecation warnings on PHP 8.5.

### Added

- `label_font_path` option for PNG captions in scripts that Open Sans doesn't cover.
- Tests that decode rendered codes with an independent QR reader, run by GitHub Actions on PHP 8.4 and 8.5 and without GD.
- A security policy in `SECURITY.md`.

### Changed

- Invalid options and payloads throw `InvalidArgumentException` naming the field. A typo such as `'module_style' => 'dot'` used to render squares silently.
- Wi-Fi encryption accepts `WPA`, `WEP` or `nopass`. WPA2 and WPA3 are written as `WPA`, which is what phones expect.
- Email addresses are validated.
- GD is only needed for PNG output and is now a suggested extension. SVG works without it, and asking for a PNG without GD throws a `RuntimeException` that says so.
- The package no longer ships the demo, the tests or the test configuration.

### Security

- Logos larger than 4096 pixels on either side are refused before decoding, so a small file that declares a huge image can't exhaust memory.
- `qr_size`, `margin` and `label_font_size` have upper limits, which prevents multi-gigabyte images.
- Logo and font paths that use a stream wrapper, such as `phar://` or `https://`, are refused.
- Colours must be hex values, so other strings can't reach SVG attributes.
- The Wi-Fi encryption field can no longer be used to inject other fields.

## [0.1.0] - 2026-10-08

First release of QR Studio, a PHP library for generating styled QR codes as SVG or PNG, with no framework dependency. It needs PHP 8.4 or newer and the GD extension.

### Added

- SVG and PNG output, returned as a result object with the raw bytes, a data URI, the MIME type and a save-to-file method.
- Module styles (square, rounded, dots, extra rounded) and eye styles (square, rounded, circle).
- Frames (none, simple border, rounded card) and an optional caption beneath the code, with an adjustable font size.
- Colour settings for the modules, background, eyes and caption in `#rrggbb` format, plus a transparent background option.
- Centre logo from an absolute file path, in PNG, JPEG, GIF or WebP format, with its size capped at 25% of the code's width. Other file types are rejected with an `InvalidArgumentException`.
- Error correction levels: low, medium, quartile and high.
- Three bundled presets: `generic`, `rounded_square` and `circle`.
- `PayloadFactory` for URL, plain text, phone, email, Wi-Fi and vCard payloads, with the escaping that Wi-Fi and vCard need so names containing `;` or `:` scan correctly.
- `ColorContrast`, a WCAG contrast-ratio check for rejecting low-contrast colour pairs before rendering.
- Single-file demo app with a live preview, started with `composer demo` and intended for local use only.

[Unreleased]: https://github.com/remymazmanian/qr-studio/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/remymazmanian/qr-studio/releases/tag/v0.1.1
[0.1.0]: https://github.com/remymazmanian/qr-studio/releases/tag/v0.1.0
