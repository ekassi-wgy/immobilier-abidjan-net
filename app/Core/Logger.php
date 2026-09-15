<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Journal applicatif : storage/logs/app-AAAA-MM-JJ.log (une ligne par entrée, horodatage UTC).
 * Ne lève jamais d'exception : un journal en échec ne doit pas casser la page.
 */
final class Logger
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function exception(Throwable $exception, array $context = []): void
    {
        $this->error(
            sprintf('%s: %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()),
            $context + ['trace' => $exception->getTraceAsString()]
        );
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        try {
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                error_log("[{$level}] {$message}");

                return;
            }

            $trace = $context['trace'] ?? null;
            unset($context['trace']);

            $line = sprintf(
                "[%s] %s: %s%s\n%s",
                gmdate('Y-m-d\TH:i:s\Z'),
                $level,
                str_replace(["\r", "\n"], ' ', $message),
                $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '',
                is_string($trace) ? $trace . "\n" : ''
            );

            @file_put_contents($this->directory . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            error_log("[{$level}] {$message}");
        }
    }
}
