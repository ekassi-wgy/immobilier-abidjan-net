<?php
/**
 * Gabarit HTML des emails transactionnels.
 * Les clients de messagerie ignorent les feuilles de style : styles en ligne, tableaux, couleurs de la charte écrites en clair.
 *
 * @var App\Models\Site $site
 * @var string          $content
 * @var string          $preheader Texte d'aperçu affiché par les messageries
 */
$preheader ??= '';
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= e($site->name) ?></title>
</head>
<body style="margin:0;padding:0;background:#F6F9FC;color:#1B2540;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
  <span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;"><?= e($preheader) ?></span>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F6F9FC;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;">
          <tr>
            <td style="padding:0 4px 20px;font-size:17px;font-weight:700;letter-spacing:-.02em;color:#143D8A;"><?= e($site->name) ?></td>
          </tr>
          <tr>
            <td style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;padding:36px 32px;font-size:15px;line-height:1.6;">
              <?= $content ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
