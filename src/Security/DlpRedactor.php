<?php

namespace Vinkius\Vurb\Security;

class DlpRedactor
{
    protected bool $enabled;
    protected array $patterns;
    protected string $strategy;

    public function __construct()
    {
        $this->enabled = config('vurb.dlp.enabled', false);
        $this->patterns = config('vurb.dlp.patterns', []);
        $this->strategy = config('vurb.dlp.strategy', 'mask');
    }

    /**
     * Redact sensitive data from a value (string, array, or nested).
     */
    public function redact(mixed $data): mixed
    {
        if (! $this->enabled || empty($this->patterns)) {
            return $data;
        }

        if (is_string($data)) {
            return $this->redactString($data);
        }

        if (is_array($data)) {
            return $this->redactArray($data);
        }

        return $data;
    }

    /**
     * Apply all DLP patterns to a string.
     */
    protected function redactString(string $value): string
    {
        foreach ($this->patterns as $name => $pattern) {
            $value = preg_replace_callback($pattern, function (array $match) use ($name) {
                return $this->applyStrategy($match[0], $name);
            }, $value);
        }

        return $value;
    }

    /**
     * Recursively redact all string values in an array.
     */
    protected function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->redact($value);
        }

        return $data;
    }

    /**
     * Apply the configured redaction strategy.
     */
    protected function applyStrategy(string $match, string $patternName): string
    {
        return match ($this->strategy) {
            'mask' => $this->mask($match),
            'remove' => '[REDACTED]',
            'hash' => '[HASH:' . substr(hash('sha256', $match), 0, 8) . ']',
            default => $this->mask($match),
        };
    }

    /**
     * Mask a value, preserving first and last characters for context.
     */
    protected function mask(string $value): string
    {
        $len = mb_strlen($value);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $visible = max(1, (int) floor($len * 0.2));
        $start = mb_substr($value, 0, $visible);
        $end = mb_substr($value, -$visible);

        return $start . str_repeat('*', $len - ($visible * 2)) . $end;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
