<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Accès à la configuration (config/*.php) en notation pointée : config('database.host').
 * Chaque fichier est chargé à la première lecture.
 */
final class Config
{
    /** @var array<string, array<string, mixed>> */
    private array $items = [];

    public function __construct(private readonly string $directory)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!array_key_exists($file, $this->items)) {
            $path = $this->directory . '/' . $file . '.php';
            $this->items[$file] = is_file($path) ? (array) require $path : [];
        }

        $value = $this->items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $this->get($segments[0]);

        $target = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
    }
}
