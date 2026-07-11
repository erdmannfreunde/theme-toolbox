<?php

declare(strict_types=1);

namespace ErdmannFreunde\ThemeToolboxBundle\Service;

use Psr\Log\LoggerInterface;

class GoogleFontsService
{
    private const CATALOG_URL = 'https://gwfh.mranftl.com/api/fonts';
    private const FONT_DETAIL_URL = 'https://gwfh.mranftl.com/api/fonts/%s?subsets=latin';

    private ?array $catalogCache = null;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return list<array{family: string, category: string, variants: list<string>}>
     */
    public function getCatalog(string $search = '', string $category = '', int $limit = 25): array
    {
        $catalog = $this->loadCatalog();
        $search = mb_strtolower(trim($search));
        $category = mb_strtolower(trim($category));

        $filtered = [];

        foreach ($catalog as $font) {
            $family = (string) ($font['family'] ?? '');
            $fontCategory = mb_strtolower((string) ($font['category'] ?? ''));

            if ('' !== $category && $fontCategory !== $category) {
                continue;
            }

            if ('' !== $search && !str_contains(mb_strtolower($family), $search)) {
                continue;
            }

            $variants = array_values(array_filter(
                array_map(static fn ($v) => (string) $v, (array) ($font['variants'] ?? [])),
                static fn (string $v): bool => '' !== $v,
            ));

            $filtered[] = [
                'family' => $family,
                'category' => (string) ($font['category'] ?? ''),
                'variants' => $variants,
            ];

            if (\count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * @return array{files: list<array{filename: string, content: string, format: string}>}
     */
    public function downloadFontFiles(string $family, string $weight, string $style): array
    {
        $familyId = $this->slugify($family);
        $detailUrl = sprintf(self::FONT_DETAIL_URL, rawurlencode($familyId));
        $json = $this->httpGet($detailUrl, ['Accept: application/json']);
        $detail = json_decode($json, true);

        if (!\is_array($detail)) {
            throw new \RuntimeException('Google-Font-Details konnten nicht geladen werden.');
        }

        $variantId = $this->resolveVariantId($weight, $style);
        $variants = (array) ($detail['variants'] ?? []);
        $matchedVariant = null;

        foreach ($variants as $variant) {
            if (!\is_array($variant)) {
                continue;
            }

            if ((string) ($variant['id'] ?? '') === $variantId) {
                $matchedVariant = $variant;
                break;
            }
        }

        if (null === $matchedVariant) {
            throw new \RuntimeException(sprintf('Variante %s für %s nicht verfügbar.', $variantId, $family));
        }

        $downloadTargets = [
            ['key' => 'woff2', 'cssFormat' => 'woff2', 'ext' => 'woff2'],
            ['key' => 'ttf', 'cssFormat' => 'truetype', 'ext' => 'ttf'],
        ];

        $files = [];

        foreach ($downloadTargets as $target) {
            $url = (string) ($matchedVariant[$target['key']] ?? '');
            if ('' === $url) {
                continue;
            }

            $content = $this->httpGet($url, ['User-Agent: Mozilla/5.0']);
            $files[] = [
                'filename' => sprintf('%s-%s-%s.%s', $this->slugify($family), $weight, $style, $target['ext']),
                'content' => $content,
                'format' => $target['cssFormat'],
            ];
        }

        if ([] === $files) {
            throw new \RuntimeException('Keine unterstützten Font-Dateien (WOFF2/TTF) gefunden.');
        }

        return ['files' => $files];
    }

    private function resolveVariantId(string $weight, string $style): string
    {
        if ('400' === $weight) {
            return 'italic' === $style ? 'italic' : 'regular';
        }

        return 'italic' === $style ? $weight . 'italic' : $weight;
    }

    private function loadCatalog(): array
    {
        if (null !== $this->catalogCache) {
            return $this->catalogCache;
        }

        $json = $this->httpGet(self::CATALOG_URL, ['Accept: application/json']);
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            $this->logger?->error('Google Fonts catalog JSON decode failed', [
                'url' => self::CATALOG_URL,
                'sample' => mb_substr($json, 0, 600),
            ]);
            throw new \RuntimeException('Google-Fonts-Katalog konnte nicht geladen werden.');
        }

        return $this->catalogCache = $data;
    }

    /** @param list<string> $headers */
    private function httpGet(string $url, array $headers = []): string
    {
        // Prefer curl: it does IPv4/IPv6 fallback ("happy eyeballs"), which avoids the
        // multi-second hang the stream wrapper hits on hosts with broken IPv6 routing.
        if (\function_exists('curl_init')) {
            return $this->httpGetCurl($url, $headers);
        }

        return $this->httpGetStream($url, $headers);
    }

    /** @param list<string> $headers */
    private function httpGetCurl(string $url, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $result = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!\is_string($result) || $statusCode >= 400) {
            $this->logger?->error('Google Fonts HTTP request failed (curl)', [
                'url' => $url,
                'status' => $statusCode,
                'error' => $error,
                'response_sample' => \is_string($result) ? mb_substr($result, 0, 1000) : null,
            ]);

            throw new \RuntimeException(sprintf('HTTP-Request fehlgeschlagen (%s): %s', $statusCode ?: 'n/a', $url));
        }

        return $result;
    }

    /** @param list<string> $headers */
    private function httpGetStream(string $url, array $headers = []): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && \is_array($http_response_header) ? $http_response_header : [];

        $statusCode = null;
        if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', (string) $responseHeaders[0], $m)) {
            $statusCode = (int) $m[1];
        }

        if (false === $result || null === $statusCode || $statusCode >= 400) {
            $this->logger?->error('Google Fonts HTTP request failed', [
                'url' => $url,
                'status' => $statusCode,
                'response_headers' => $responseHeaders,
                'response_sample' => \is_string($result) ? mb_substr($result, 0, 1000) : null,
            ]);

            throw new \RuntimeException(sprintf('HTTP-Request fehlgeschlagen (%s): %s', $statusCode ?? 'n/a', $url));
        }

        return $result;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\-_]+/', '-', $value) ?? '';

        return trim($value, '-_');
    }
}
