<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Réception d'images envoyées par formulaire (logos d'agence, puis photos d'annonce au lot 1.6).
 *
 * Sécurité : type réel vérifié par finfo (l'extension et le type annoncé par le navigateur sont ignorés),
 * dimensions contrôlées, image ré-encodée par GD (supprime tout contenu parasite et les métadonnées EXIF),
 * nom de fichier aléatoire, écriture sous public/uploads/ où aucun script ne s'exécute.
 */
final class ImageUploader
{
    public const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_PIXELS = 40_000_000;

    public function __construct(private readonly string $publicRoot)
    {
    }

    /**
     * Contrôle un fichier de $_FILES. Retourne une clé de traduction d'erreur, ou null si le fichier est exploitable.
     * Un champ laissé vide renvoie 'none'.
     *
     * @param array<string, mixed>|null $file
     */
    public function check(?array $file, int $maxBytes): ?string
    {
        if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return 'none';
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int) $file['size'] > $maxBytes) {
            return 'upload.too_large';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return 'upload.failed';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            return 'upload.type';
        }

        $size = @getimagesize((string) $file['tmp_name']);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return 'upload.invalid_image';
        }

        return null;
    }

    /**
     * Enregistre l'image redimensionnée (sans agrandissement) en WebP dans public/uploads/{dossier}.
     * Retourne le chemin relatif à public/ (ex. « uploads/ci/agences/12/logo-3f9a….webp »).
     *
     * @param array<string, mixed> $file Fichier déjà validé par check()
     */
    public function storeWebp(array $file, string $directory, string $prefix, int $maxWidth, int $maxHeight, int $quality = 82): string
    {
        $source = $this->load((string) $file['tmp_name']);
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 255, 255, 255, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        $directory = trim(str_replace('..', '', $directory), '/');
        $absoluteDir = $this->publicRoot . '/uploads/' . $directory;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException("Dossier d'envoi non inscriptible : uploads/{$directory}");
        }

        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.webp';
        if (!imagewebp($target, $absoluteDir . '/' . $name, $quality)) {
            imagedestroy($target);
            throw new RuntimeException('Encodage WebP impossible.');
        }
        imagedestroy($target);

        return 'uploads/' . $directory . '/' . $name;
    }

    /** Supprime un fichier précédemment enregistré (chemin relatif à public/, limité à uploads/). */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || !str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return;
        }
        $file = $this->publicRoot . '/' . $relativePath;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** @return \GdImage */
    private function load(string $path): \GdImage
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if ($image === false) {
            throw new RuntimeException('Image illisible.');
        }

        // Photos de smartphone : orientation EXIF appliquée avant ré-encodage
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };
            if ($rotated instanceof \GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }
}
