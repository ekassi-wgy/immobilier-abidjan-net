<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Rendu des vues PHP (app/Views). Les données sont isolées dans la portée de la vue.
 */
final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): string
    {
        $file = $this->directory . '/' . $view . '.php';
        if (str_contains($view, '..') || !is_file($file)) {
            throw new RuntimeException("Vue introuvable : {$view}");
        }

        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            $__level = ob_get_level();
            ob_start();
            try {
                require $__file;
            } catch (Throwable $exception) {
                while (ob_get_level() > $__level) {
                    ob_end_clean();
                }
                throw $exception;
            }

            return (string) ob_get_clean();
        })($file, $data);
    }

    /**
     * Page complète : la vue est rendue puis injectée dans son layout (variable $content).
     *
     * @param array<string, mixed> $data       Données de la vue
     * @param array<string, mixed> $layoutData Données du layout (titre, description, scripts…)
     */
    public function page(string $layout, string $view, array $data = [], array $layoutData = []): string
    {
        return $this->render($layout, $layoutData + ['content' => $this->render($view, $data)]);
    }
}
