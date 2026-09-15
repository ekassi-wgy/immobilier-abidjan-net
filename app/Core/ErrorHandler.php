<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use ErrorException;
use Throwable;

/**
 * Gestion centralisée des erreurs.
 *
 * - toute erreur PHP (warning, notice…) devient une exception ;
 * - les exceptions non prévues sont journalisées et donnent une page 500 ;
 * - détail technique (message, pile) affiché uniquement si app.debug (jamais en production) ;
 * - page d'erreur du back-office pour /cmsadmin, du site public sinon, JSON pour les appels AJAX.
 */
final class ErrorHandler
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    private ?Request $request = null;

    public function __construct(
        private readonly Logger $logger,
        private readonly bool $debug,
        private readonly ?View $view = null,
    ) {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        if (!$this->debug) {
            // Pas d'arguments de fonctions dans les piles d'appels journalisées (données sensibles)
            ini_set('zend.exception_ignore_args', '1');
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleUncaught']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        // Opérateur @ : erreur volontairement ignorée
        if (!(error_reporting() & $level)) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function handleUncaught(Throwable $exception): void
    {
        // Scripts bin/ et tâches CRON : message lisible sur la sortie d'erreur, code de sortie 1
        if (PHP_SAPI === 'cli') {
            $this->logger->exception($exception, ['sapi' => 'cli']);
            fwrite(STDERR, (string) $exception . PHP_EOL);
            exit(1);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response = $this->render($exception);
        $response->send($this->request?->isMethod('HEAD') !== true);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !($error['type'] & self::FATAL)) {
            return;
        }

        $this->handleUncaught(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
    }

    /** Transforme une exception en réponse HTTP (et la journalise si ce n'est pas une erreur HTTP prévue). */
    public function render(Throwable $exception): Response
    {
        $status = $exception instanceof HttpException ? $exception->status() : 500;

        if (!$exception instanceof HttpException || $status >= 500) {
            $this->logger->exception($exception, array_filter([
                'method' => $this->request?->method(),
                'path' => $this->request?->path(),
                'ip' => $this->request?->ip(),
            ]));
        }

        try {
            $response = match (true) {
                $this->request?->isAjax() === true => Response::json(['error' => ['status' => $status, 'message' => $this->title($status)]], $status),
                $this->debug && !$exception instanceof HttpException => $this->debugPage($exception),
                default => $this->errorPage($status),
            };
        } catch (Throwable $renderingError) {
            $this->logger->exception($renderingError, ['while' => 'rendering error page']);
            $response = Response::html($this->fallbackPage($status), $status);
        }

        if ($exception instanceof HttpException) {
            foreach ($exception->headers() as $name => $value) {
                $response->setHeader($name, $value);
            }
        }

        return $response->setHeader('Cache-Control', 'no-store');
    }

    private function errorPage(int $status): Response
    {
        if ($this->view === null) {
            return Response::html($this->fallbackPage($status), $status);
        }

        $data = ['code' => $status];

        $html = $this->request?->isCmsadmin() === true
            ? $this->view->page('cmsadmin/layouts/auth', 'cmsadmin/pages/errors/error', $data, [
                'title' => $this->title($status),
                'variant' => 'center',
            ])
            : $this->view->page('front/layouts/app', 'front/pages/errors/error', $data, [
                'title' => $this->title($status),
                'description' => '',
                'noindex' => true,
            ]);

        return Response::html($html, $status);
    }

    private function debugPage(Throwable $exception): Response
    {
        if ($this->view === null) {
            return Response::html('<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>', 500);
        }

        return Response::html($this->view->render('errors/debug', [
            'exception' => $exception,
            'request' => $this->request,
        ]), 500);
    }

    private function title(int $status): string
    {
        $key = "errors.{$status}.title";
        $title = function_exists('__') ? __($key) : $key;

        return $title !== $key ? $title : 'Erreur ' . $status;
    }

    /** Page minimale, sans vue ni traduction : dernier recours si le rendu normal échoue. */
    private function fallbackPage(int $status): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Erreur ' . $status . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;padding:3rem 1.5rem;max-width:40rem;margin:auto">'
            . '<h1>Erreur ' . $status . '</h1><p>Le service est momentanément indisponible. Merci de réessayer dans quelques instants.</p>'
            . '</body></html>';
    }
}
