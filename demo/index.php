<?php

declare(strict_types=1);

/**
 * QR Studio - demo application.
 *
 * A deliberately dependency-free playground for the library: one file, no
 * framework, no database, no build step. Run it with:
 *
 *     composer demo        (or: php -S localhost:8080 -t demo)
 *
 * This demo is intended for local use. It accepts a file upload for the centre
 * logo, so do not expose it on a public host without adding your own limits.
 */

require __DIR__.'/../vendor/autoload.php';

use QrStudio\ColorContrast;
use QrStudio\DesignOptions;
use QrStudio\PayloadFactory;
use QrStudio\QrRenderer;

const MAX_LOGO_BYTES = 2 * 1024 * 1024;

$renderer = new QrRenderer();
$payloadFactory = new PayloadFactory();

/** Read a field from the request, falling back to a default. */
function field(string $key, mixed $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_scalar($value) ? trim((string) $value) : (string) $default;
}

function checkbox(string $key): bool
{
    return ! empty($_POST[$key]) || ! empty($_GET[$key]);
}

/**
 * Accept an uploaded logo into a temp file for the life of this request only.
 *
 * Nothing is persisted: the file is unlinked before the response ends, so the
 * demo never accumulates uploads and there is no directory to serve by accident.
 */
function acceptLogoUpload(): ?string
{
    $upload = $_FILES['logo'] ?? null;

    if (! is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($upload['size'] ?? 0) > MAX_LOGO_BYTES) {
        return null;
    }

    // getimagesize() both validates that this really is an image and tells us the
    // real type, rather than trusting the client-supplied name or mime type.
    $info = @getimagesize($upload['tmp_name']);
    $allowed = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    if ($info === false || ! in_array($info[2], $allowed, true)) {
        return null;
    }

    $target = tempnam(sys_get_temp_dir(), 'qrstudio_logo_');

    if ($target === false || ! move_uploaded_file($upload['tmp_name'], $target)) {
        return null;
    }

    return $target;
}

$types = $payloadFactory->labels();
$type = array_key_exists(field('type', 'url'), $types) ? field('type', 'url') : 'url';

$presets = DesignOptions::presets();
$presetKey = array_key_exists(field('design_preset', 'generic'), $presets) ? field('design_preset', 'generic') : 'generic';

$values = array_replace(DesignOptions::defaults(), $presets[$presetKey]['values'] ?? []);

// Explicit controls win over the preset they were seeded from.
foreach (['foreground_color', 'background_color', 'eye_color', 'label_color', 'module_style', 'eye_style', 'frame_style', 'error_correction_level'] as $key) {
    if (field($key) !== '') {
        $values[$key] = field($key);
    }
}

foreach (['qr_size', 'margin', 'logo_size_percentage', 'label_font_size'] as $key) {
    if (field($key) !== '') {
        $values[$key] = (int) field($key);
    }
}

$values['transparent_background'] = checkbox('transparent_background');
$values['label_text'] = field('label_text') ?: null;
$values['design_preset'] = $presetKey;

$logoPath = acceptLogoUpload();
$values['logo_path'] = $logoPath;

$payloadInput = [
    'type' => $type,
    'website_url' => field('website_url', 'https://example.com'),
    'plain_text' => field('plain_text', 'Hello from QR Studio'),
    'phone_number' => field('phone_number', '+1 555 010 0100'),
    'email_address' => field('email_address', 'hello@example.com'),
    'email_subject' => field('email_subject'),
    'email_body' => field('email_body'),
    'wifi_ssid' => field('wifi_ssid', 'Guest Network'),
    'wifi_password' => field('wifi_password'),
    'wifi_encryption' => field('wifi_encryption', 'WPA'),
    'wifi_hidden' => checkbox('wifi_hidden'),
    'vcard_name' => field('vcard_name', 'Ada Lovelace'),
    'vcard_organization' => field('vcard_organization'),
    'vcard_title' => field('vcard_title'),
    'vcard_phone' => field('vcard_phone'),
    'vcard_email' => field('vcard_email'),
    'vcard_url' => field('vcard_url'),
];

$errors = [];
$payload = '';

try {
    $payload = $payloadFactory->make($payloadInput);
} catch (Throwable $e) {
    $errors[] = 'Could not build the QR payload: '.$e->getMessage();
}

if ($payload === '') {
    $errors[] = 'Enter some content to encode.';
}

// A QR code is only scannable if the modules contrast with what is behind them.
$background = $values['transparent_background'] ? '#ffffff' : (string) $values['background_color'];

foreach ([['foreground_color', 'Foreground'], ['eye_color', 'Eye']] as [$key, $label]) {
    if (ColorContrast::isHexColor($values[$key]) && ColorContrast::isHexColor($background)
        && ColorContrast::contrastRatio((string) $values[$key], $background) < 3.0) {
        $errors[] = $label.' colour does not contrast enough with the background to scan reliably.';
    }
}

$options = DesignOptions::fromArray($values);
$format = in_array(field('format'), ['svg', 'png'], true) ? field('format') : 'svg';

$svg = '';

if ($errors === []) {
    try {
        $svg = $renderer->result($payload, 'svg', $options)->getString();
    } catch (Throwable $e) {
        $errors[] = 'Rendering failed: '.$e->getMessage();
    }
}

// Download endpoint.
if (isset($_REQUEST['download']) && $errors === []) {
    $result = $renderer->result($payload, $format, $options);

    header('Content-Type: '.$result->getMimeType());
    header('Content-Disposition: attachment; filename="qr-code.'.$format.'"');
    echo $result->getString();

    if ($logoPath !== null) {
        @unlink($logoPath);
    }

    exit;
}

// Live-preview endpoint used by the inline script.
if (isset($_REQUEST['preview'])) {
    header('Content-Type: application/json');
    echo json_encode(['svg' => $svg, 'errors' => $errors, 'payload' => $payload]);

    if ($logoPath !== null) {
        @unlink($logoPath);
    }

    exit;
}

if ($logoPath !== null) {
    @unlink($logoPath);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected(string $a, string $b): string
{
    return $a === $b ? ' selected' : '';
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Studio</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="masthead">
    <div class="wrap">
        <h1>QR Studio</h1>
        <p>Styled QR codes as SVG or PNG &mdash; module shapes, eye styles, frames, centre logos and captions.</p>
    </div>
</header>

<main class="wrap layout">
    <form class="panel controls" method="post" enctype="multipart/form-data" id="form">

        <fieldset>
            <legend>Content</legend>

            <label for="type">Type</label>
            <select name="type" id="type">
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= selected($key, $type) ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>

            <div class="type-fields" data-type="url">
                <label for="website_url">Website URL</label>
                <input type="url" name="website_url" id="website_url" value="<?= e($payloadInput['website_url']) ?>">
            </div>

            <div class="type-fields" data-type="text">
                <label for="plain_text">Text</label>
                <textarea name="plain_text" id="plain_text" rows="3"><?= e($payloadInput['plain_text']) ?></textarea>
            </div>

            <div class="type-fields" data-type="phone">
                <label for="phone_number">Phone number</label>
                <input type="tel" name="phone_number" id="phone_number" value="<?= e($payloadInput['phone_number']) ?>">
            </div>

            <div class="type-fields" data-type="email">
                <label for="email_address">Email address</label>
                <input type="email" name="email_address" id="email_address" value="<?= e($payloadInput['email_address']) ?>">
                <label for="email_subject">Subject <span class="hint">optional</span></label>
                <input type="text" name="email_subject" id="email_subject" value="<?= e($payloadInput['email_subject']) ?>">
                <label for="email_body">Body <span class="hint">optional</span></label>
                <textarea name="email_body" id="email_body" rows="2"><?= e($payloadInput['email_body']) ?></textarea>
            </div>

            <div class="type-fields" data-type="wifi">
                <label for="wifi_ssid">Network name (SSID)</label>
                <input type="text" name="wifi_ssid" id="wifi_ssid" value="<?= e($payloadInput['wifi_ssid']) ?>">
                <label for="wifi_encryption">Security</label>
                <select name="wifi_encryption" id="wifi_encryption">
                    <?php foreach (['WPA' => 'WPA / WPA2', 'WEP' => 'WEP', 'nopass' => 'Open (no password)'] as $k => $v): ?>
                        <option value="<?= e($k) ?>"<?= selected($k, $payloadInput['wifi_encryption']) ?>><?= e($v) ?></option>
                    <?php endforeach ?>
                </select>
                <label for="wifi_password">Password</label>
                <input type="text" name="wifi_password" id="wifi_password" value="<?= e($payloadInput['wifi_password']) ?>">
                <label class="check"><input type="checkbox" name="wifi_hidden" value="1"<?= $payloadInput['wifi_hidden'] ? ' checked' : '' ?>> Hidden network</label>
            </div>

            <div class="type-fields" data-type="vcard">
                <label for="vcard_name">Full name</label>
                <input type="text" name="vcard_name" id="vcard_name" value="<?= e($payloadInput['vcard_name']) ?>">
                <label for="vcard_organization">Organisation</label>
                <input type="text" name="vcard_organization" id="vcard_organization" value="<?= e($payloadInput['vcard_organization']) ?>">
                <label for="vcard_title">Job title</label>
                <input type="text" name="vcard_title" id="vcard_title" value="<?= e($payloadInput['vcard_title']) ?>">
                <label for="vcard_phone">Phone</label>
                <input type="tel" name="vcard_phone" id="vcard_phone" value="<?= e($payloadInput['vcard_phone']) ?>">
                <label for="vcard_email">Email</label>
                <input type="email" name="vcard_email" id="vcard_email" value="<?= e($payloadInput['vcard_email']) ?>">
                <label for="vcard_url">Website</label>
                <input type="url" name="vcard_url" id="vcard_url" value="<?= e($payloadInput['vcard_url']) ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend>Style</legend>

            <label for="design_preset">Preset</label>
            <select name="design_preset" id="design_preset">
                <?php foreach ($presets as $key => $preset): ?>
                    <option value="<?= e($key) ?>"<?= selected($key, $presetKey) ?>><?= e($preset['label']) ?></option>
                <?php endforeach ?>
            </select>
            <p class="hint"><?= e($presets[$presetKey]['description'] ?? '') ?></p>

            <div class="grid-2">
                <div>
                    <label for="module_style">Module style</label>
                    <select name="module_style" id="module_style">
                        <?php foreach (DesignOptions::moduleStyles() as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= selected($k, (string) $values['module_style']) ?>><?= e($v) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label for="eye_style">Eye style</label>
                    <select name="eye_style" id="eye_style">
                        <?php foreach (DesignOptions::eyeStyles() as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= selected($k, (string) $values['eye_style']) ?>><?= e($v) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label for="frame_style">Frame</label>
                    <select name="frame_style" id="frame_style">
                        <?php foreach (DesignOptions::frameStyles() as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= selected($k, (string) $values['frame_style']) ?>><?= e($v) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label for="error_correction_level">Error correction</label>
                    <select name="error_correction_level" id="error_correction_level">
                        <?php foreach (DesignOptions::errorCorrectionLevels() as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= selected($k, (string) $values['error_correction_level']) ?>><?= e($v) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label for="foreground_color">Foreground</label>
                    <input type="color" name="foreground_color" id="foreground_color" value="<?= e((string) $values['foreground_color']) ?>">
                </div>
                <div>
                    <label for="background_color">Background</label>
                    <input type="color" name="background_color" id="background_color" value="<?= e((string) $values['background_color']) ?>">
                </div>
                <div>
                    <label for="eye_color">Eye colour</label>
                    <input type="color" name="eye_color" id="eye_color" value="<?= e((string) $values['eye_color']) ?>">
                </div>
                <div>
                    <label for="label_color">Caption colour</label>
                    <input type="color" name="label_color" id="label_color" value="<?= e((string) $values['label_color']) ?>">
                </div>
                <div>
                    <label for="qr_size">Size (px)</label>
                    <input type="number" name="qr_size" id="qr_size" min="256" max="2048" step="8" value="<?= e((string) $values['qr_size']) ?>">
                </div>
                <div>
                    <label for="margin">Margin (px)</label>
                    <input type="number" name="margin" id="margin" min="8" max="160" step="4" value="<?= e((string) $values['margin']) ?>">
                </div>
            </div>

            <label class="check"><input type="checkbox" name="transparent_background" value="1"<?= $values['transparent_background'] ? ' checked' : '' ?>> Transparent background</label>
        </fieldset>

        <fieldset>
            <legend>Logo &amp; caption</legend>

            <label for="logo">Centre logo <span class="hint">PNG, JPEG, GIF or WebP, max 2&nbsp;MB</span></label>
            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/gif,image/webp">

            <label for="logo_size_percentage">Logo size (% of QR)</label>
            <input type="range" name="logo_size_percentage" id="logo_size_percentage" min="0" max="25" value="<?= e((string) $values['logo_size_percentage']) ?>">

            <label for="label_text">Caption <span class="hint">optional</span></label>
            <input type="text" name="label_text" id="label_text" maxlength="80" value="<?= e((string) ($values['label_text'] ?? '')) ?>">

            <label for="label_font_size">Caption size</label>
            <input type="number" name="label_font_size" id="label_font_size" min="10" max="48" value="<?= e((string) $values['label_font_size']) ?>">
        </fieldset>

        <noscript><button type="submit" class="button">Update preview</button></noscript>
    </form>

    <div class="panel preview">
        <h2>Preview</h2>

        <div id="errors" class="errors"<?= $errors === [] ? ' hidden' : '' ?>>
            <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach ?>
        </div>

        <div class="qr-frame" id="qr"><?= $svg ?></div>

        <h3>Encoded payload</h3>
        <pre id="payload"><?= e($payload) ?></pre>

        <div class="downloads">
            <button type="submit" form="form" name="download" value="1" class="button primary" formaction="?format=svg">Download SVG</button>
            <button type="submit" form="form" name="download" value="1" class="button" formaction="?format=png">Download PNG</button>
        </div>
    </div>
</main>

<footer class="wrap footnote">
    <p>QR Studio &mdash; MIT licensed. This page is the demo; the library lives in <code>src/</code> and has no framework dependency.</p>
</footer>

<script>
(function () {
    var form = document.getElementById('form');
    var qr = document.getElementById('qr');
    var payload = document.getElementById('payload');
    var errorBox = document.getElementById('errors');
    var timer = null;

    function showTypeFields() {
        var type = form.querySelector('[name=type]').value;
        form.querySelectorAll('.type-fields').forEach(function (el) {
            el.hidden = el.dataset.type !== type;
        });
    }

    function refresh() {
        var data = new FormData(form);
        fetch('?preview=1', { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (out) {
                qr.innerHTML = out.svg || '';
                payload.textContent = out.payload || '';
                errorBox.innerHTML = '';
                out.errors.forEach(function (message) {
                    var p = document.createElement('p');
                    p.textContent = message;
                    errorBox.appendChild(p);
                });
                errorBox.hidden = out.errors.length === 0;
            })
            .catch(function () { /* leave the last good preview on screen */ });
    }

    function scheduleRefresh() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, 180);
    }

    form.addEventListener('input', scheduleRefresh);
    form.addEventListener('change', function (event) {
        if (event.target.name === 'type') {
            showTypeFields();
        }
        // Changing the preset should visibly reseed the colour and shape controls,
        // so reload rather than patching each field by hand.
        if (event.target.name === 'design_preset') {
            form.querySelectorAll('input[type=color], select').forEach(function (el) {
                if (el.name !== 'design_preset' && el.name !== 'type') {
                    el.removeAttribute('value');
                }
            });
            form.submit();
            return;
        }
        scheduleRefresh();
    });

    showTypeFields();
    refresh();
})();
</script>

</body>
</html>
