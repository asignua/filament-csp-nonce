<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Support;

use Illuminate\Support\Str;

/**
 * One normalised violation, whichever wire format it arrived in.
 */
final readonly class ViolationReport
{
    public function __construct(
        public string $directive,
        public string $blocked,
        public string $document,
        public ?string $source,
        public ?int $line,
        public ?int $column,
        public ?string $sample,
        public string $disposition,
    ) {}

    /**
     * Parses a `report-uri` body ({"csp-report": {...}}) or a Reporting API body
     * ([{"type": "csp-violation", "body": {...}}]). Anything else yields [].
     *
     * @param array<mixed> $payload
     *
     * @return list<self>
     */
    public static function fromPayload(array $payload): array
    {
        $bodies = [];

        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            $bodies[] = $payload['csp-report'];
        } else {
            foreach ($payload as $entry) {
                if (is_array($entry) && ($entry['type'] ?? null) === 'csp-violation' && is_array($entry['body'] ?? null)) {
                    $bodies[] = $entry['body'];
                }
            }
        }

        $reports = [];

        foreach (array_slice($bodies, 0, 20) as $body) {
            $reports[] = self::fromBody($body);
        }

        return $reports;
    }

    /**
     * @param array<mixed> $body
     */
    private static function fromBody(array $body): self
    {
        return new self(
            directive: self::text($body['effective-directive'] ?? $body['effectiveDirective'] ?? $body['violated-directive'] ?? $body['violatedDirective'] ?? 'unknown', 100),
            blocked: self::url($body['blocked-uri'] ?? $body['blockedURL'] ?? $body['blockedURI'] ?? ''),
            document: self::url($body['document-uri'] ?? $body['documentURL'] ?? $body['documentURI'] ?? ''),
            source: self::nullableUrl($body['source-file'] ?? $body['sourceFile'] ?? null),
            line: self::int($body['line-number'] ?? $body['lineNumber'] ?? null),
            column: self::int($body['column-number'] ?? $body['columnNumber'] ?? null),
            sample: self::nullableText($body['script-sample'] ?? $body['sample'] ?? null, 80),
            disposition: self::text($body['disposition'] ?? 'enforce', 20),
        );
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [$this->directive, $this->blocked, $this->document, $this->source, $this->line]));
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'directive' => $this->directive,
            'blocked' => $this->blocked,
            'document' => $this->document,
            'source' => $this->source,
            'line' => $this->line,
            'column' => $this->column,
            'sample' => $this->sample,
            'disposition' => $this->disposition,
        ];
    }

    private static function text(mixed $value, int $limit): string
    {
        return Str::limit(is_scalar($value) ? (string) $value : '', $limit, '');
    }

    private static function nullableText(mixed $value, int $limit): ?string
    {
        $text = self::text($value, $limit);

        return $text === '' ? null : $text;
    }

    /**
     * Query strings and fragments are dropped: signed URLs and tokens must not
     * end up in a log table.
     */
    private static function url(mixed $value): string
    {
        $text = self::text($value, 2048);

        return (string) preg_replace('/[?#].*$/s', '', $text);
    }

    private static function nullableUrl(mixed $value): ?string
    {
        $url = self::url($value);

        return $url === '' ? null : $url;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
