<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use RuntimeException;

/**
 * Fichiers confidentiels envoyés par des tiers : pièces justificatives d'un dossier de partenariat
 * (registre du commerce, pièce d'identité…) et photos ou documents d'un bien confié par un particulier.
 *
 * Ils sont écrits dans `storage/private`, **hors de la racine web** : aucune URL ne permet de les
 * atteindre. Seul un contrôleur qui a vérifié les droits du demandeur les transmet, en téléchargement
 * (`Content-Disposition: attachment`, `nosniff`, bac à sable CSP).
 *
 * Sécurité à la réception : type réel lu par finfo (l'extension et le type annoncé sont ignorés),
 * taille plafonnée, nom de fichier aléatoire ; les photos sont en plus ré-encodées en WebP par
 * `ImageUploader` (métadonnées EXIF et contenu parasite supprimés).
 */
final class PrivateFiles
{
    /** Pièces justificatives : PDF ou image. */
    public const DOCUMENTS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private readonly ImageUploader $photos;

    public function __construct(private readonly string $root)
    {
        // ImageUploader écrit sous {racine}/uploads/… : pointé ici sur storage/private, jamais sur public/.
        $this->photos = new ImageUploader($this->root);
    }

    /**
     * Entrée de `$_FILES` (champ simple ou multiple « name[] ») mise à plat en liste de fichiers.
     * Les champs laissés vides sont écartés.
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $input): array
    {
        if (!is_array($input) || !isset($input['error'])) {
            return [];
        }
        if (!is_array($input['error'])) {
            return $input['error'] === UPLOAD_ERR_NO_FILE ? [] : [$input];
        }

        $files = [];
        foreach (array_keys($input['error']) as $index) {
            if ($input['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $input['name'][$index] ?? '',
                'type' => $input['type'][$index] ?? '',
                'tmp_name' => $input['tmp_name'][$index] ?? '',
                'error' => $input['error'][$index],
                'size' => $input['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    /** Clé de traduction d'erreur (`upload.*`), ou null si le document est recevable. */
    public function checkDocument(array $file, int $maxBytes): ?string
    {
        if (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? null) === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > $maxBytes) {
            return 'upload.too_large';
        }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return 'upload.failed';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);

        return isset(self::DOCUMENTS[$mime]) ? null : 'upload.document_type';
    }

    /** Clé de traduction d'erreur (`upload.*`), ou null si la photo est exploitable. */
    public function checkPhoto(array $file, int $maxBytes): ?string
    {
        $error = $this->photos->check($file, $maxBytes);

        return $error === 'none' ? 'upload.failed' : $error;
    }

    /**
     * Enregistre un document tel quel (un PDF ne se ré-encode pas) sous un nom aléatoire.
     *
     * @return array{path: string, mime: string, size: int, original_name: string}
     */
    public function storeDocument(array $file, string $directory): array
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::DOCUMENTS[$mime] ?? throw new RuntimeException('Type de document refusé.');

        $relative = 'documents/' . $this->cleanDirectory($directory) . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $absolute = $this->root . '/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0770, true) && !is_dir(dirname($absolute))) {
            throw new RuntimeException('Dossier de stockage privé impossible à créer.');
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) {
            throw new RuntimeException('Enregistrement du document impossible.');
        }

        return ['path' => $relative, 'mime' => $mime, 'size' => (int) filesize($absolute), 'original_name' => $this->originalName($file, $extension)];
    }

    /**
     * Enregistre une photo ré-encodée en WebP (1600 px au plus).
     *
     * @return array{path: string, mime: string, size: int, original_name: string}
     */
    public function storePhoto(array $file, string $directory): array
    {
        $relative = $this->photos->storeWebp($file, $this->cleanDirectory($directory), 'photo', 1600, 1600);

        return ['path' => $relative, 'mime' => 'image/webp', 'size' => (int) filesize($this->root . '/' . $relative), 'original_name' => $this->originalName($file, 'webp')];
    }

    /** Chemin absolu d'un fichier stocké, ou null s'il n'existe pas ou sort de storage/private. */
    public function absolutePath(string $relative): ?string
    {
        $root = realpath($this->root);
        $path = realpath($this->root . '/' . ltrim($relative, '/'));

        return $root !== false && $path !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    public function delete(string $relative): void
    {
        $path = $this->absolutePath($relative);
        if ($path !== null) {
            @unlink($path);
        }
    }

    /**
     * Réponse qui transmet le fichier. À n'appeler qu'après avoir vérifié les droits du demandeur.
     * `inline` n'est accepté que pour les images (aperçu dans le back-office) ; un PDF est toujours téléchargé.
     */
    public function response(string $relative, string $mime, string $downloadName, bool $inline = false): Response
    {
        $path = $this->absolutePath($relative);
        if ($path === null) {
            return new Response('', 404);
        }
        $inline = $inline && str_starts_with($mime, 'image/');
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $downloadName) ?: 'document';

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function cleanDirectory(string $directory): string
    {
        return trim(preg_replace('#[^a-z0-9/_-]+#i', '', str_replace('..', '', $directory)) ?? '', '/');
    }

    /** Nom d'origine conservé pour l'affichage (jamais utilisé comme nom de fichier sur le disque). */
    private function originalName(array $file, string $extension): string
    {
        $name = trim(basename((string) ($file['name'] ?? '')));
        $name = $name !== '' ? mb_substr($name, 0, 150) : 'fichier.' . $extension;

        return preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? 'fichier.' . $extension;
    }
}
