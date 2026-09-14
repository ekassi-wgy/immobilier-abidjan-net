<?php
/**
 * Création / modification d'annonce (modèle de page « formulaire » du back-office).
 * Les critères spécifiques seront générés dynamiquement selon la catégorie (lot 1.6, attributs EAV).
 *
 * @var array      $user
 * @var array|null $property     Annonce existante (null en création)
 * @var array      $old          Valeurs soumises à réafficher après erreur
 * @var array      $errors       [champ => message]
 * @var array      $transactions [clé => libellé]
 * @var array      $categories   [groupe => [id => libellé]]
 * @var array      $communes     [id => libellé]
 * @var array      $districts    [id => libellé]
 * @var array      $features     [id => libellé]
 * @var array      $titleTypes   [clé => libellé]
 * @var array      $standings    [clé => libellé]
 * @var string     $csrfToken
 */
$property ??= null;
$old ??= [];
$errors ??= [];
$isAgency = $user['role'] === 'agency';
$isEdit = $property !== null;

$value = static fn (string $key, mixed $default = ''): mixed => $old[$key] ?? $property[$key] ?? $default;
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="invalid-feedback" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>'
    : '';
$describedBy = static fn (string $key): string => isset($errors[$key]) ? ' aria-describedby="err-' . e($key) . '" aria-invalid="true"' : '';
$selectedFeatures = array_map('strval', (array) $value('features', []));

$sections = [
    'general' => 'Informations générales',
    'prix' => 'Prix & conditions',
    'localisation' => 'Localisation',
    'caracteristiques' => 'Caractéristiques',
    'equipements' => 'Équipements',
    'juridique' => 'Situation juridique',
    'medias' => 'Photos & médias',
    'contact' => 'Contact de l’annonce',
];
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce',
    'subtitle' => $isEdit ? $property['reference'] . ' · ' . $property['title'] : 'Renseignez le bien avec précision : une fiche complète se consulte davantage.',
    'breadcrumb' => [
        ['label' => 'Tableau de bord', 'url' => '/'],
        ['label' => $isAgency ? 'Mes annonces' : 'Annonces', 'url' => 'annonces'],
        ['label' => $isEdit ? 'Modifier' : 'Nouvelle annonce'],
    ],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p>Le formulaire contient <?= e(count($errors)) ?> erreur<?= count($errors) > 1 ? 's' : '' ?>. Corrigez les champs signalés.</p>
</div>
<?php endif; ?>

<form class="im-form" method="post" enctype="multipart/form-data" action="<?= e(cmsadmin_url($isEdit ? 'annonces/' . $property['reference'] : 'annonces')) ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

  <div class="im-form__layout">
    <div class="im-form__main">

      <section class="card im-panel im-form-section" id="general">
        <header class="im-form-section__head">
          <span class="im-form-section__index">01</span>
          <h2 class="im-panel__title"><?= e($sections['general']) ?></h2>
        </header>

        <fieldset class="mb-4">
          <legend class="form-label">Type de transaction</legend>
          <div class="im-segmented" role="radiogroup">
            <?php foreach ($transactions as $key => $label): ?>
            <label class="im-segmented__option">
              <input type="radio" name="transaction" value="<?= e($key) ?>"<?= (string) $value('transaction', 'sale') === (string) $key ? ' checked' : '' ?>>
              <span><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label" for="p-title">Titre de l’annonce</label>
            <input class="form-control<?= $invalid('title') ?>" id="p-title" name="title" type="text" maxlength="120" required value="<?= e($value('title')) ?>" placeholder="Ex. Villa 5 pièces avec piscine à la Riviera Golf"<?= $describedBy('title') ?>>
            <?= $error('title') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-reference">Référence interne <span class="im-optional">facultatif</span></label>
            <input class="form-control" id="p-reference" name="internal_reference" type="text" value="<?= e($value('internal_reference')) ?>" placeholder="Générée automatiquement">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="p-category">Catégorie</label>
            <select class="form-select<?= $invalid('category_id') ?>" id="p-category" name="category_id" required data-select2 data-placeholder="Choisir une catégorie"<?= $describedBy('category_id') ?>>
              <option value=""></option>
              <?php foreach ($categories as $group => $items): ?>
              <optgroup label="<?= e($group) ?>">
                <?php foreach ($items as $id => $label): ?>
                <option value="<?= e($id) ?>"<?= (string) $value('category_id') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <?php endforeach; ?>
            </select>
            <?= $error('category_id') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="p-availability">Disponible à partir du</label>
            <input class="form-control" id="p-availability" name="available_at" type="date" value="<?= e($value('available_at')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label" for="p-description">Description</label>
            <textarea class="form-control<?= $invalid('description') ?>" id="p-description" name="description" rows="7" required placeholder="Décrivez le bien, son environnement, ses atouts…"<?= $describedBy('description') ?>><?= e($value('description')) ?></textarea>
            <p class="form-text">Évitez les coordonnées dans la description : elles sont affichées automatiquement.</p>
            <?= $error('description') ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="prix">
        <header class="im-form-section__head">
          <span class="im-form-section__index">02</span>
          <h2 class="im-panel__title"><?= e($sections['prix']) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label" for="p-price">Prix</label>
            <div class="im-input-affix">
              <input class="form-control im-num<?= $invalid('price') ?>" id="p-price" name="price" type="number" min="0" step="1000" inputmode="numeric" value="<?= e($value('price')) ?>"<?= $describedBy('price') ?>>
              <span class="im-input-affix__suffix">FCFA</span>
            </div>
            <?= $error('price') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-charges">Charges mensuelles <span class="im-optional">facultatif</span></label>
            <div class="im-input-affix">
              <input class="form-control im-num" id="p-charges" name="charges" type="number" min="0" step="500" inputmode="numeric" value="<?= e($value('charges')) ?>">
              <span class="im-input-affix__suffix">FCFA</span>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="p-commission">Commission agence</label>
            <div class="im-input-affix">
              <input class="form-control im-num" id="p-commission" name="commission_rate" type="number" min="0" max="100" step="0.5" value="<?= e($value('commission_rate')) ?>">
              <span class="im-input-affix__suffix">%</span>
            </div>
          </div>
          <div class="col-12">
            <label class="im-switch">
              <input type="checkbox" name="negotiable" value="1"<?= $value('negotiable') ? ' checked' : '' ?>>
              <span class="im-switch__track" aria-hidden="true"></span>
              <span>Prix négociable</span>
            </label>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="localisation">
        <header class="im-form-section__head">
          <span class="im-form-section__index">03</span>
          <h2 class="im-panel__title"><?= e($sections['localisation']) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="p-country">Pays</label>
            <input class="form-control" id="p-country" type="text" value="Côte d’Ivoire" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-commune">Commune</label>
            <select class="form-select<?= $invalid('commune_id') ?>" id="p-commune" name="commune_id" required data-select2 data-placeholder="Choisir"<?= $describedBy('commune_id') ?>>
              <option value=""></option>
              <?php foreach ($communes as $id => $label): ?>
              <option value="<?= e($id) ?>"<?= (string) $value('commune_id') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $error('commune_id') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-district">Quartier</label>
            <select class="form-select" id="p-district" name="district_id" data-select2 data-placeholder="Choisir">
              <option value=""></option>
              <?php foreach ($districts as $id => $label): ?>
              <option value="<?= e($id) ?>"<?= (string) $value('district_id') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="p-address">Adresse ou repère <span class="im-optional">facultatif</span></label>
            <input class="form-control" id="p-address" name="address" type="text" value="<?= e($value('address')) ?>" placeholder="Ex. Derrière la pharmacie Les Oscars">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="p-lat">Latitude</label>
            <input class="form-control im-num" id="p-lat" name="latitude" type="text" inputmode="decimal" value="<?= e($value('latitude')) ?>" placeholder="5.3599">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="p-lng">Longitude</label>
            <input class="form-control im-num" id="p-lng" name="longitude" type="text" inputmode="decimal" value="<?= e($value('longitude')) ?>" placeholder="-3.9870">
          </div>
          <div class="col-12">
            <div class="im-map-placeholder" id="p-map" aria-hidden="true">
              <span class="mdi mdi-map-marker-outline"></span>
              <span>Positionnez le repère sur la carte pour renseigner les coordonnées</span>
            </div>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="caracteristiques">
        <header class="im-form-section__head">
          <span class="im-form-section__index">04</span>
          <div>
            <h2 class="im-panel__title"><?= e($sections['caracteristiques']) ?></h2>
            <p class="im-panel__subtitle">Les champs s’adaptent à la catégorie choisie.</p>
          </div>
        </header>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <label class="form-label" for="p-living">Surface habitable</label>
            <div class="im-input-affix"><input class="form-control im-num" id="p-living" name="attributes[living_area]" type="number" min="0" value="<?= e($value('living_area')) ?>"><span class="im-input-affix__suffix">m²</span></div>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="p-land">Superficie terrain</label>
            <div class="im-input-affix"><input class="form-control im-num" id="p-land" name="attributes[land_area]" type="number" min="0" value="<?= e($value('land_area')) ?>"><span class="im-input-affix__suffix">m²</span></div>
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label" for="p-rooms">Pièces</label>
            <input class="form-control im-num" id="p-rooms" name="attributes[rooms]" type="number" min="0" value="<?= e($value('rooms')) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label" for="p-bedrooms">Chambres</label>
            <input class="form-control im-num" id="p-bedrooms" name="attributes[bedrooms]" type="number" min="0" value="<?= e($value('bedrooms')) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label" for="p-bathrooms">Salles de bain</label>
            <input class="form-control im-num" id="p-bathrooms" name="attributes[bathrooms]" type="number" min="0" value="<?= e($value('bathrooms')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-standing">Standing</label>
            <select class="form-select" id="p-standing" name="attributes[standing]">
              <option value="">Non précisé</option>
              <?php foreach ($standings as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= (string) $value('standing') === (string) $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-condition">État du bien</label>
            <select class="form-select" id="p-condition" name="attributes[condition]">
              <option value="">Non précisé</option>
              <?php foreach (['new' => 'Neuf', 'good' => 'Bon état', 'renovate' => 'À rénover'] as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= (string) $value('condition') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <label class="im-switch">
              <input type="checkbox" name="attributes[furnished]" value="1"<?= $value('furnished') ? ' checked' : '' ?>>
              <span class="im-switch__track" aria-hidden="true"></span>
              <span>Meublé</span>
            </label>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="equipements">
        <header class="im-form-section__head">
          <span class="im-form-section__index">05</span>
          <h2 class="im-panel__title"><?= e($sections['equipements']) ?></h2>
        </header>
        <div class="im-checks">
          <?php foreach ($features as $id => $label): ?>
          <label class="im-check">
            <input type="checkbox" name="features[]" value="<?= e($id) ?>"<?= in_array((string) $id, $selectedFeatures, true) ? ' checked' : '' ?>>
            <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
            <span><?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="juridique">
        <header class="im-form-section__head">
          <span class="im-form-section__index">06</span>
          <h2 class="im-panel__title"><?= e($sections['juridique']) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="p-title-type">Titre de propriété</label>
            <select class="form-select" id="p-title-type" name="attributes[title_type]">
              <option value="">Non précisé</option>
              <?php foreach ($titleTypes as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= (string) $value('title_type') === (string) $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <fieldset>
              <legend class="form-label">Litige en cours</legend>
              <div class="im-segmented im-segmented--compact">
                <label class="im-segmented__option"><input type="radio" name="attributes[litigation]" value="0"<?= !$value('litigation') ? ' checked' : '' ?>><span>Non</span></label>
                <label class="im-segmented__option"><input type="radio" name="attributes[litigation]" value="1"<?= $value('litigation') ? ' checked' : '' ?>><span>Oui</span></label>
              </div>
            </fieldset>
          </div>
          <div class="col-12">
            <div class="im-private-zone">
              <p class="im-private-zone__label"><span class="mdi mdi-lock-outline" aria-hidden="true"></span> Informations internes — jamais affichées sur le site</p>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="p-notary">Notaire</label>
                  <input class="form-control" id="p-notary" name="notary_name" type="text" value="<?= e($value('notary_name')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="p-file-ref">Référence du dossier</label>
                  <input class="form-control" id="p-file-ref" name="notary_reference" type="text" value="<?= e($value('notary_reference')) ?>">
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="medias">
        <header class="im-form-section__head">
          <span class="im-form-section__index">07</span>
          <div>
            <h2 class="im-panel__title"><?= e($sections['medias']) ?></h2>
            <p class="im-panel__subtitle">JPEG, PNG ou WebP · 10 Mo max par photo · la première photo sert de couverture.</p>
          </div>
        </header>
        <label class="im-dropzone" for="p-photos">
          <span class="mdi mdi-image-plus-outline" aria-hidden="true"></span>
          <span class="im-dropzone__title">Glissez vos photos ici</span>
          <span class="im-dropzone__text">ou <u>parcourez vos fichiers</u></span>
          <input class="visually-hidden" id="p-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
        </label>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label" for="p-video">Vidéo YouTube ou Vimeo <span class="im-optional">facultatif</span></label>
            <input class="form-control" id="p-video" name="video_url" type="url" value="<?= e($value('video_url')) ?>" placeholder="https://">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="p-tour">Visite virtuelle 360° <span class="im-optional">facultatif</span></label>
            <input class="form-control" id="p-tour" name="virtual_tour_url" type="url" value="<?= e($value('virtual_tour_url')) ?>" placeholder="https://">
          </div>
          <div class="col-12">
            <label class="form-label" for="p-plan">Plan ou document PDF <span class="im-optional">facultatif</span></label>
            <input class="form-control" id="p-plan" name="document" type="file" accept="application/pdf">
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="contact">
        <header class="im-form-section__head">
          <span class="im-form-section__index">08</span>
          <h2 class="im-panel__title"><?= e($sections['contact']) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="p-agent">Agent en charge</label>
            <input class="form-control" id="p-agent" name="agent_name" type="text" value="<?= e($value('agent_name')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-phone">Téléphone</label>
            <input class="form-control im-num" id="p-phone" name="agent_phone" type="tel" value="<?= e($value('agent_phone')) ?>" placeholder="+225 07 00 00 00 00">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="p-whatsapp">WhatsApp</label>
            <input class="form-control im-num" id="p-whatsapp" name="agent_whatsapp" type="tel" value="<?= e($value('agent_whatsapp')) ?>" placeholder="+225 07 00 00 00 00">
          </div>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title">Publication</h2>
          <dl class="im-meta-list">
            <div><dt>Statut</dt><dd><?= $isEdit ? cmsadmin_partial('status-badge', ['status' => $property['status']]) : '<span class="im-muted">Nouvelle annonce</span>' ?></dd></div>
            <?php if ($isEdit): ?>
            <div><dt>Dernière mise à jour</dt><dd><?= e($property['updated']) ?></dd></div>
            <?php endif; ?>
          </dl>
          <?php if ($isAgency): ?>
          <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> Après envoi, l’annonce est vérifiée par notre équipe avant d’être publiée. Vous serez notifié par email.</p>
          <?php else: ?>
          <label class="im-switch mb-3">
            <input type="checkbox" name="featured" value="1"<?= $value('featured') ? ' checked' : '' ?>>
            <span class="im-switch__track" aria-hidden="true"></span>
            <span>Mettre à la une</span>
          </label>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit" name="intent" value="submit">
              <?= $isAgency ? 'Envoyer pour validation' : ($isEdit ? 'Enregistrer' : 'Publier l’annonce') ?>
            </button>
            <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('annonces')) ?>">Annuler</a>
          </div>
        </section>

        <nav class="im-toc" aria-label="Sections du formulaire">
          <p class="im-toc__title">Sur cette page</p>
          <ol>
            <?php foreach ($sections as $anchor => $label): ?>
            <li><a href="#<?= e($anchor) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </nav>
      </div>
    </aside>
  </div>
</form>
