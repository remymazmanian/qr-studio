# Security policy

## Reporting a vulnerability

Please report security problems privately through GitHub: open the [Security tab](https://github.com/remymazmanian/qr-studio/security) and choose **Report a vulnerability**. Don't open a public issue for a vulnerability.

Include the version you're using, what you found and, if you can, a small example that reproduces it. You'll get a reply within a few days, and a fix is released as soon as it's ready.

## Supported versions

Security fixes go into the latest release.

## Scope

QR Studio renders untrusted input into SVG and PNG, so reports about these are especially welcome:

- payload injection (Wi-Fi, email, vCard fields)
- markup injection in SVG output
- logo and font file handling (paths, oversized images)
- memory or CPU exhaustion from crafted options

The `demo/` folder is a local development tool and isn't part of the package. Don't expose it on a public server without adding your own limits.
