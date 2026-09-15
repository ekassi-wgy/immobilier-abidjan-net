<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Envoi d'emails transactionnels (PHPMailer), HTML + texte brut.
 * Pilote « log » : le message MIME complet est écrit dans storage/mail/ au lieu d'être envoyé.
 */
final class Mailer
{
    /** @param array{mailer: string, path: string, smtp: array{host: string, port: int, username: string, password: string, encryption: string, timeout: int}, from: array{address: string, name: string}} $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @return string|null Chemin du fichier .eml avec le pilote « log », null sinon
     *
     * @throws RuntimeException si le message ne peut pas être envoyé
     */
    public function send(string $to, string $subject, string $html, string $text, ?string $toName = null, ?string $replyTo = null): ?string
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->XMailer = ' ';
        $mail->setFrom($this->config['from']['address'], $this->config['from']['name']);
        $mail->addAddress($to, (string) $toName);
        if ($replyTo !== null) {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        try {
            if ($this->config['mailer'] === 'smtp') {
                $smtp = $this->config['smtp'];
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->Port = $smtp['port'];
                $mail->Timeout = $smtp['timeout'];
                $mail->SMTPAuth = $smtp['username'] !== '';
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                $mail->SMTPSecure = match ($smtp['encryption']) {
                    'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                    'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                    default => '',
                };
                $mail->SMTPAutoTLS = $smtp['encryption'] !== '';
                $mail->send();

                return null;
            }

            $mail->preSend();

            return $this->store($mail->getSentMIMEMessage());
        } catch (\PHPMailer\PHPMailer\Exception $exception) {
            throw new RuntimeException('Envoi de l’email impossible : ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function store(string $message): string
    {
        $directory = $this->config['path'];
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Dossier des emails non inscriptible : {$directory}");
        }

        $file = sprintf('%s/%s-%s.eml', $directory, gmdate('Ymd-His'), bin2hex(random_bytes(3)));
        file_put_contents($file, $message);

        return $file;
    }
}
