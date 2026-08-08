<?php
/**
 * CloudinaryService
 *
 * Dependency-free Cloudinary uploader using signed REST uploads (cURL).
 * No Composer/SDK required. Reads credentials from config/config.php
 * (CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET).
 *
 * Design goals:
 *  - Zero-downtime: every public method degrades gracefully. If Cloudinary is
 *    not configured or the API call fails, callers can fall back to local disk.
 *  - Single responsibility: this class only talks to Cloudinary.
 */
class CloudinaryService
{
    /** @return bool true when all three credentials are defined and non-empty */
    public static function isConfigured(): bool
    {
        return defined('CLOUDINARY_CLOUD_NAME') && CLOUDINARY_CLOUD_NAME
            && defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY
            && defined('CLOUDINARY_API_SECRET') && CLOUDINARY_API_SECRET
            && function_exists('curl_init');
    }

    /**
     * Upload a local (or temp) file to Cloudinary using a signed request.
     *
     * @param string      $filePath Absolute or relative path to the source file
     *                              (e.g. $_FILES['x']['tmp_name']).
     * @param string      $folder   Cloudinary folder (e.g. "feedora/posts").
     * @param string|null $publicId Optional public id (without extension).
     * @return array{success:bool,url:?string,public_id:?string,resource_type:?string,error:?string}
     */
    public static function upload(string $filePath, string $folder = 'feedora', ?string $publicId = null): array
    {
        $fail = function (string $msg): array {
            error_log('[CloudinaryService] ' . $msg);
            return ['success' => false, 'url' => null, 'public_id' => null, 'resource_type' => null, 'error' => $msg];
        };

        if (!self::isConfigured()) {
            return $fail('Cloudinary is not configured (missing credentials or cURL).');
        }
        if (!is_file($filePath)) {
            return $fail('Source file not found: ' . $filePath);
        }

        $timestamp = time();

        // Parameters that must be signed (alphabetical order), excluding the
        // file itself, api_key, resource_type and the signature.
        $signParams = ['folder' => $folder, 'timestamp' => $timestamp];
        if ($publicId !== null && $publicId !== '') {
            $signParams['public_id'] = $publicId;
        }
        ksort($signParams);

        $toSign = [];
        foreach ($signParams as $k => $v) {
            $toSign[] = $k . '=' . $v;
        }
        $signature = sha1(implode('&', $toSign) . CLOUDINARY_API_SECRET);

        // "auto" handles both images and videos.
        $endpoint = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/auto/upload';

        $postFields = array_merge($signParams, [
            'api_key'   => CLOUDINARY_API_KEY,
            'signature' => $signature,
            'file'      => new CURLFile($filePath),
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            // Videos are larger and slower to ingest/transcode than images; a
            // 60s cap was aborting legitimate video uploads before they finished.
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return $fail('cURL error: ' . $curlErr);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $fail('Invalid Cloudinary response (HTTP ' . $httpCode . ').');
        }
        if ($httpCode >= 400 || isset($data['error'])) {
            $emsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
            return $fail('Cloudinary rejected upload: ' . $emsg);
        }
        if (empty($data['secure_url'])) {
            return $fail('Cloudinary response missing secure_url.');
        }

        return [
            'success'       => true,
            'url'           => $data['secure_url'],
            'public_id'     => $data['public_id'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'error'         => null,
        ];
    }

    /**
     * Build a transformed delivery URL (f_auto,q_auto + optional width) from a
     * secure_url. Falls back to the original URL if it isn't a Cloudinary URL.
     */
    public static function transform(string $secureUrl, ?int $width = null): string
    {
        if (strpos($secureUrl, '/upload/') === false || strpos($secureUrl, 'res.cloudinary.com') === false) {
            return $secureUrl;
        }
        $t = 'f_auto,q_auto';
        if ($width) {
            $t .= ',c_limit,w_' . $width;
        }
        return preg_replace('#/upload/#', '/upload/' . $t . '/', $secureUrl, 1);
    }

    /**
     * Parse a Cloudinary URL to extract resource_type and public_id.
     *
     * @param string $url
     * @return array{public_id:string,resource_type:string}|null Null if not a Cloudinary URL
     */
    public static function parseUrl(string $url): ?array
    {
        if (strpos($url, 'res.cloudinary.com') === false) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $parts = explode('/', trim($path, '/'));
        if (count($parts) < 4) {
            return null;
        }

        // parts structure:
        // 0: cloud_name
        // 1: resource_type (image, video, raw)
        // 2: type (upload, private, authenticated)
        // 3...: transformations (optional), version (optional, e.g. v12345678), public_id + extension
        $resourceType = $parts[1];
        $remainingParts = array_slice($parts, 3);

        $startIndex = 0;
        foreach ($remainingParts as $index => $part) {
            if (preg_match('/^v\d+$/', $part)) {
                $startIndex = $index + 1;
                break;
            }
            if (preg_match('/^([a-z]_[a-z0-9_]+|f_auto|q_auto|c_limit|w_\d+|h_\d+|dpr_\d+|co_rgb:[a-f0-9]+|b_rgb:[a-f0-9]+)$/i', $part) || strpos($part, ',') !== false) {
                $startIndex = $index + 1;
            }
        }

        $publicIdParts = array_slice($remainingParts, $startIndex);
        if (empty($publicIdParts)) {
            return null;
        }

        $publicIdWithExt = implode('/', $publicIdParts);
        $pathInfo = pathinfo($publicIdWithExt);
        $publicId = $pathInfo['dirname'] === '.' ? $pathInfo['filename'] : $pathInfo['dirname'] . '/' . $pathInfo['filename'];

        return [
            'public_id' => $publicId,
            'resource_type' => $resourceType
        ];
    }

    /**
     * Delete an asset from Cloudinary.
     *
     * @param string $publicId     The public ID of the asset.
     * @param string $resourceType The resource type ('image', 'video', 'raw'). Defaults to 'image'.
     * @return array{success:bool,error:?string}
     */
    public static function destroy(string $publicId, string $resourceType = 'image'): array
    {
        $fail = function (string $msg): array {
            error_log('[CloudinaryService] ' . $msg);
            return ['success' => false, 'error' => $msg];
        };

        if (!self::isConfigured()) {
            return $fail('Cloudinary is not configured.');
        }

        $timestamp = time();

        $signParams = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $toSign = [];
        foreach ($signParams as $k => $v) {
            $toSign[] = $k . '=' . $v;
        }
        $signature = sha1(implode('&', $toSign) . CLOUDINARY_API_SECRET);

        $endpoint = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/destroy';

        $postFields = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key'   => CLOUDINARY_API_KEY,
            'signature' => $signature,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return $fail('cURL error: ' . $curlErr);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $fail('Invalid Cloudinary response (HTTP ' . $httpCode . ').');
        }
        if ($httpCode >= 400 || isset($data['error'])) {
            $emsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
            return $fail('Cloudinary rejected destroy: ' . $emsg);
        }

        $result = $data['result'] ?? '';
        if ($result !== 'ok' && $result !== 'not found') {
            return $fail('Cloudinary destroy status: ' . $result);
        }

        return [
            'success' => true,
            'error'   => null,
        ];
    }

    /**
     * Delete an asset from Cloudinary using its secure URL.
     *
     * @param string $url The Cloudinary secure URL
     * @return array{success:bool,error:?string}|null Null if not a Cloudinary URL
     */
    public static function destroyByUrl(string $url): ?array
    {
        $parsed = self::parseUrl($url);
        if (!$parsed) {
            return null;
        }
        return self::destroy($parsed['public_id'], $parsed['resource_type']);
    }
}
