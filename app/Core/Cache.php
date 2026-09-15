<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Throwable;

/**
 * Cache fichier (storage/cache) : une entrée = un fichier PHP retournant un tableau, donc servi par OPcache.
 * Réservé à des données non sensibles et reconstructibles (référentiels, configuration des sites).
 */
final class Cache
{
    public function __construct(private readonly string $directory)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }

        try {
            $entry = include $file;
        } catch (Throwable) {
            return $default;
        }

        if (!is_array($entry) || !array_key_exists('value', $entry) || ($entry['expires'] !== 0 && $entry['expires'] < time())) {
            return $default;
        }

        return $entry['value'];
    }

    /** @param int $ttl Durée en secondes (0 = sans expiration) */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return;
        }

        $file = $this->path($key);
        $export = '<?php return ' . var_export(['expires' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value], true) . ';' . PHP_EOL;

        // Écriture atomique : un lecteur concurrent ne voit jamais un fichier à moitié écrit
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $export, LOCK_EX) !== false && @rename($temporary, $file)) {
            $this->invalidateOpcache($file);
        } else {
            @unlink($temporary);
        }
    }

    /**
     * Valeur en cache, ou calculée par $callback puis mise en cache.
     * Avec $ttl = null, le cache est contourné (utile en développement).
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        if ($ttl === null) {
            return $callback();
        }

        $sentinel = "\0miss";
        $value = $this->get($key, $sentinel);
        if ($value === $sentinel) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    public function forget(string $key): void
    {
        $file = $this->path($key);
        if (is_file($file)) {
            @unlink($file);
            $this->invalidateOpcache($file);
        }
    }

    /** Vide tout le cache. Retourne le nombre de fichiers supprimés. */
    public function clear(): int
    {
        $count = 0;
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            if (@unlink($file)) {
                $this->invalidateOpcache($file);
                $count++;
            }
        }

        return $count;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . preg_replace('/[^a-z0-9_.-]/i', '_', $key) . '.php';
    }

    private function invalidateOpcache(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}
