# Changelog

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/remymazmanian/qr-studio/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/remymazmanian/qr-studio/releases/tag/v0.1.0
