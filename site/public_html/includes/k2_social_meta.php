<?php
/**
 * Social / Open Graph meta for share cards (Discord, X, forums).
 *
 * Optional before k2_head.php:
 *   $k2MetaDescription (string)
 *   $k2OgTitle (string) — defaults to <title> text if set via $k2OgTitle, else document title later
 *   $k2OgDescription (string) — defaults to meta description
 *   $k2OgImage (string) — site-relative path, default /images/og/ratings-default.jpg
 *   $k2OgType (string) — default website
 *
 * Emits absolute https URLs for og:image / og:url when possible.
 */
declare(strict_types=1);

/**
 * @return non-empty-string
 */
function k2_social_public_origin(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === 'ratings.kickoff2.com' || $host === 'www.ratings.kickoff2.com') {
        return 'https://ratings.kickoff2.com';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    if ($host !== '') {
        return $scheme . '://' . $host;
    }
    return 'https://ratings.kickoff2.com';
}

/**
 * @return non-empty-string
 */
function k2_social_absolute_url(string $pathOrUrl): string
{
    $pathOrUrl = trim($pathOrUrl);
    if ($pathOrUrl === '') {
        return k2_social_public_origin() . '/';
    }
    if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
        return $pathOrUrl;
    }
    if ($pathOrUrl[0] !== '/') {
        $pathOrUrl = '/' . $pathOrUrl;
    }
    return k2_social_public_origin() . $pathOrUrl;
}

/**
 * Current request path + query for og:url (no fragment).
 *
 * @return non-empty-string
 */
function k2_social_request_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $query = parse_url($uri, PHP_URL_QUERY);
    $out = $path;
    if (is_string($query) && $query !== '') {
        $out .= '?' . $query;
    }
    return k2_social_absolute_url($out);
}

/**
 * Echo meta description + Open Graph + Twitter card tags.
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   image?: string,
 *   type?: string,
 *   url?: string
 * } $opts
 */
function k2_social_meta_head(array $opts = []): void
{
    $title = trim((string) ($opts['title'] ?? ''));
    if ($title === '') {
        $title = 'Kick Off 2 ratings';
    }

    $description = trim((string) ($opts['description'] ?? ''));
    if ($description === '') {
        $description = 'Live Kick Off 2 ladder and Amiga 500 statistics.';
    }

    $image = trim((string) ($opts['image'] ?? ''));
    if ($image === '') {
        $image = '/images/og/ratings-default.jpg';
    }

    $type = trim((string) ($opts['type'] ?? ''));
    if ($type === '') {
        $type = 'website';
    }

    $url = trim((string) ($opts['url'] ?? ''));
    if ($url === '') {
        $url = k2_social_request_url();
    } else {
        $url = k2_social_absolute_url($url);
    }

    $imageAbs = k2_social_absolute_url($image);
    $siteName = 'Kick Off 2 ratings';

    $esc = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    $imageType = 'image/jpeg';
    if (preg_match('/\.png($|\?)/i', $imageAbs) === 1) {
        $imageType = 'image/png';
    } elseif (preg_match('/\.webp($|\?)/i', $imageAbs) === 1) {
        $imageType = 'image/webp';
    }

    echo '<meta name="description" content="' . $esc($description) . '" />' . "\n";
    echo '<meta property="og:site_name" content="' . $esc($siteName) . '" />' . "\n";
    echo '<meta property="og:type" content="' . $esc($type) . '" />' . "\n";
    echo '<meta property="og:title" content="' . $esc($title) . '" />' . "\n";
    echo '<meta property="og:description" content="' . $esc($description) . '" />' . "\n";
    echo '<meta property="og:url" content="' . $esc($url) . '" />' . "\n";
    echo '<meta property="og:image" content="' . $esc($imageAbs) . '" />' . "\n";
    echo '<meta property="og:image:secure_url" content="' . $esc($imageAbs) . '" />' . "\n";
    echo '<meta property="og:image:type" content="' . $esc($imageType) . '" />' . "\n";
    echo '<meta property="og:image:width" content="1200" />' . "\n";
    echo '<meta property="og:image:height" content="630" />' . "\n";
    echo '<meta property="og:image:alt" content="' . $esc($title) . '" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . $esc($title) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . $esc($description) . '" />' . "\n";
    echo '<meta name="twitter:image" content="' . $esc($imageAbs) . '" />' . "\n";
}