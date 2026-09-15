<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Traductions de l'interface (lang/{locale}.php, tableaux imbriqués) : __('errors.404.title').
 *
 * Remplacements : __('common.results', ['count' => 12]) avec « :count » dans la chaîne.
 * Clé absente dans la langue courante → langue de repli → clé elle-même (repérable à l'écran).
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(
        private readonly string $directory,
        private string $locale,
        private readonly string $fallback,
    ) {
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /** @param array<string, string|int|float> $replace */
    public function get(string $key, array $replace = []): string
    {
        $line = $this->find($this->locale, $key) ?? $this->find($this->fallback, $key) ?? $key;

        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }

        return $line;
    }

    public function has(string $key): bool
    {
        return $this->find($this->locale, $key) !== null;
    }

    private function find(string $locale, string $key): ?string
    {
        if (!array_key_exists($locale, $this->loaded)) {
            $file = $this->directory . '/' . basename($locale) . '.php';
            $this->loaded[$locale] = is_file($file) ? (array) require $file : [];
        }

        $value = $this->loaded[$locale];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }
}
