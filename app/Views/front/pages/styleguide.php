<?php
/**
 * Charte graphique interne du site public (lot 0.3).
 *
 * @var array $colors     Liste de ['token', 'hex', 'name', 'usage']
 * @var array $icons      Identifiants d'icônes
 * @var array $properties Annonces d'exemple
 * @var array $search     Données du module de recherche
 */
$sections = [
    'couleurs' => 'Couleurs',
    'typographie' => 'Typographie',
    'formes' => 'Espacements & formes',
    'icones' => 'Icônes',
    'boutons' => 'Boutons & liens',
    'formulaires' => 'Champs & filtres',
    'badges' => 'Badges',
    'recherche' => 'Module de recherche',
    'cartes' => 'Cartes annonces',
];
$typeScale = [
    ['im-display', 'Display', '40 → 76 px · 700 · −4,5 %', 'Votre prochaine adresse'],
    ['im-h1', 'Titre 1', '32 → 52 px · 700 · −3,5 %', 'Villas à vendre à Cocody'],
    ['im-h2', 'Titre 2', '26 → 38 px · 700 · −3 %', 'Biens à la une'],
    ['im-h3', 'Titre 3', '20 → 24 px · 600 · −2 %', 'Caractéristiques du logement'],
    ['im-h4', 'Titre 4', '17 → 18 px · 600', 'Situation juridique'],
    ['im-lead', 'Chapô', '17 → 20 px · 400', 'Des annonces complètes, photographiées et vérifiées par notre équipe.'],
    ['', 'Texte courant', '16 px · 400 · interligne 1,6', 'Belle villa contemporaine sur 850 m² de terrain, dans une rue calme de la Riviera Golf, à cinq minutes des écoles internationales.'],
    ['im-small', 'Petit texte', '14 px · 400', 'Mise en ligne le 12 septembre 2026 · Réf. IAN-24531'],
    ['im-eyebrow', 'Surtitre', '12 px · 600 · capitales · +12 %', 'Sélection de la semaine'],
];
?>
<div class="sg">
  <div class="im-container">
    <div class="sg__layout">
      <nav class="sg__toc" aria-label="Sections de la charte">
        <ol>
          <?php foreach ($sections as $anchor => $label): ?>
          <li><a href="#<?= e($anchor) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>

      <div>
        <header class="sg__intro">
          <p class="im-eyebrow">Charte graphique · usage interne</p>
          <h1 class="im-h1" style="margin-top: .9rem">Système de design du site public</h1>
          <p class="im-lead" style="margin-top: 1rem">Référence des couleurs, de la typographie et des composants <code>im-</code>. Toute nouvelle interface s’appuie sur ces éléments ; un besoin non couvert s’ajoute ici avant d’être utilisé dans une page.</p>
          <ul class="sg__principles">
            <li><strong>La photo d’abord</strong><span>Les biens et les lieux réels portent l’émotion ; l’interface reste en retrait.</span></li>
            <li><strong>Des informations scannables</strong><span>Prix, lieu et surfaces se lisent en un coup d’œil, chiffres alignés.</span></li>
            <li><strong>La confiance visible</strong><span>Agence vérifiée, annonce contrôlée, contact direct : jamais cachés.</span></li>
          </ul>
        </header>

        <!-- 01 Couleurs -->
        <section class="sg__section" id="couleurs">
          <div class="sg__section-head"><span class="sg__index">01</span><h2 class="im-h2">Couleurs</h2></div>
          <div class="sg__swatches">
            <?php foreach ($colors as $color): ?>
            <div class="sg__swatch">
              <div class="sg__swatch-color" style="background: <?= e($color['hex']) ?>"></div>
              <div class="sg__swatch-meta">
                <strong><?= e($color['name']) ?></strong>
                <code>--im-<?= e($color['token']) ?> · <?= e($color['hex']) ?></code>
                <span><?= e($color['usage']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 02 Typographie -->
        <section class="sg__section" id="typographie">
          <div class="sg__section-head"><span class="sg__index">02</span><h2 class="im-h2">Typographie</h2></div>
          <p class="sg__label">Plus Jakarta Sans · variable 200–800 · auto-hébergée</p>
          <?php foreach ($typeScale as [$class, $name, $meta, $sample]): ?>
          <div class="sg__type-row">
            <div><strong class="im-small"><?= e($name) ?></strong><div class="sg__type-meta"><?= e($meta) ?></div></div>
            <p class="<?= e($class) ?>"><?= e($sample) ?></p>
          </div>
          <?php endforeach; ?>
          <div class="sg__type-row">
            <div><strong class="im-small">Chiffres</strong><div class="sg__type-meta">tabulaires · .im-num</div></div>
            <p class="im-h3 im-num">385 000 000 FCFA · 1 200 000 FCFA / mois</p>
          </div>
        </section>

        <!-- 03 Espacements & formes -->
        <section class="sg__section" id="formes">
          <div class="sg__section-head"><span class="sg__index">03</span><h2 class="im-h2">Espacements & formes</h2></div>
          <p class="sg__label">Espacements · base 4 px</p>
          <div class="sg__tokens">
            <?php foreach (['4' => '16 px', '6' => '24 px', '8' => '32 px', '12' => '48 px', '16' => '64 px', '24' => '96 px'] as $step => $px): ?>
            <div class="sg__token"><div class="sg__space-bar" style="width: <?= e($px) ?>"></div><span><strong>space-<?= e($step) ?></strong> · <?= e($px) ?></span></div>
            <?php endforeach; ?>
          </div>
          <p class="sg__label" style="margin-top: 2.5rem">Rayons</p>
          <div class="sg__tokens">
            <?php foreach (['xs' => '6 px', 'sm' => '8 px', '' => '12 px', 'lg' => '16 px', 'xl' => '24 px'] as $size => $px): ?>
            <div class="sg__token"><div class="sg__token-box" style="border-radius: var(--im-radius<?= $size !== '' ? '-' . e($size) : '' ?>)"></div><span><strong>radius<?= $size !== '' ? '-' . e($size) : '' ?></strong> · <?= e($px) ?></span></div>
            <?php endforeach; ?>
          </div>
          <p class="sg__label" style="margin-top: 2.5rem">Ombres · rares et diffuses</p>
          <div class="sg__tokens">
            <?php foreach (['shadow-sm' => 'Survol discret', 'shadow' => 'Carte en liste au survol', 'shadow-lg' => 'Éléments flottants'] as $shadow => $usage): ?>
            <div class="sg__token"><div class="sg__token-box" style="border: 0; border-radius: var(--im-radius); box-shadow: var(--im-<?= e($shadow) ?>)"></div><span><strong><?= e($shadow) ?></strong> · <?= e($usage) ?></span></div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 04 Icônes -->
        <section class="sg__section" id="icones">
          <div class="sg__section-head"><span class="sg__index">04</span><h2 class="im-h2">Icônes</h2></div>
          <p class="sg__label">Phosphor Icons · light · <?= count($icons) ?> icônes · <code>&lt;?= icon('nom') ?&gt;</code></p>
          <div class="sg__icons">
            <?php foreach ($icons as $name): ?>
            <div class="sg__icon"><?= icon($name) ?><code><?= e($name) ?></code></div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 05 Boutons -->
        <section class="sg__section" id="boutons">
          <div class="sg__section-head"><span class="sg__index">05</span><h2 class="im-h2">Boutons & liens</h2></div>
          <div class="sg__stage">
            <p class="sg__label">Variantes</p>
            <div class="sg__row">
              <button class="im-btn" type="button"><?= icon('search') ?> Rechercher</button>
              <button class="im-btn im-btn--dark" type="button">Déposer un bien</button>
              <button class="im-btn im-btn--outline" type="button">Voir les photos</button>
              <button class="im-btn im-btn--ghost" type="button"><?= icon('share') ?> Partager</button>
              <a class="im-btn im-btn--whatsapp" href="#boutons"><?= icon('whatsapp') ?> WhatsApp</a>
            </div>
            <p class="sg__label" style="margin-top: 2rem">Tailles</p>
            <div class="sg__row">
              <button class="im-btn im-btn--sm" type="button">Petit</button>
              <button class="im-btn" type="button">Standard</button>
              <button class="im-btn im-btn--lg" type="button">Grand</button>
              <button class="im-btn im-btn--outline im-btn--icon" type="button" aria-label="Ajouter aux favoris"><?= icon('heart') ?></button>
              <button class="im-btn im-btn--outline im-btn--icon im-btn--sm" type="button" aria-label="Partager"><?= icon('share') ?></button>
            </div>
            <p class="sg__label" style="margin-top: 2rem">Liens</p>
            <div class="sg__row">
              <a class="im-link" href="#boutons">Toutes les annonces <?= icon('arrow-right', 'im-icon--arrow') ?></a>
              <a class="im-link-underline" href="#boutons">Mentions légales</a>
            </div>
          </div>
          <div class="sg__stage sg__stage--photo" style="margin-top: 1rem">
            <p class="sg__label" style="color: rgba(255,255,255,.7)">Sur photo</p>
            <div class="sg__row">
              <button class="im-btn im-btn--light" type="button">Déposer un bien</button>
              <button class="im-btn im-btn--outline-light" type="button">Voir la visite 360°</button>
            </div>
          </div>
        </section>

        <!-- 06 Champs -->
        <section class="sg__section" id="formulaires">
          <div class="sg__section-head"><span class="sg__index">06</span><h2 class="im-h2">Champs & filtres</h2></div>
          <div class="sg__stage">
            <div class="row g-4">
              <div class="col-md-6">
                <div class="im-field">
                  <label class="im-field__label" for="sg-name">Nom complet</label>
                  <input class="im-control" id="sg-name" type="text" placeholder="Ex. Mariam Traoré">
                </div>
              </div>
              <div class="col-md-6">
                <div class="im-field">
                  <label class="im-field__label" for="sg-commune">Commune</label>
                  <select class="im-control" id="sg-commune"><option>Cocody</option><option>Marcory</option><option>Plateau</option></select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="im-field">
                  <label class="im-field__label" for="sg-phone">Téléphone</label>
                  <div class="im-control-icon"><?= icon('phone') ?><input class="im-control is-invalid" id="sg-phone" type="tel" value="07 00" aria-describedby="sg-phone-error" aria-invalid="true"></div>
                  <p class="im-field__error" id="sg-phone-error">Numéro incomplet.</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="im-field">
                  <label class="im-field__label" for="sg-message">Message</label>
                  <textarea class="im-control" id="sg-message" rows="3" placeholder="Bonjour, je souhaite visiter ce bien…"></textarea>
                  <p class="im-field__help">Votre message est transmis directement à l’agence.</p>
                </div>
              </div>
              <div class="col-12">
                <label class="im-check"><input type="checkbox" checked><span class="im-check__box"><?= icon('check') ?></span> J’accepte que mes données soient transmises à l’agence</label>
              </div>
            </div>
            <p class="sg__label" style="margin-top: 2.5rem">Puces de filtre</p>
            <div class="sg__row">
              <button class="im-chip is-active" type="button">Acheter <?= icon('caret-down') ?></button>
              <button class="im-chip" type="button">Villa</button>
              <button class="im-chip" type="button"><?= icon('pin') ?> Cocody</button>
              <button class="im-chip" type="button">Budget <?= icon('caret-down') ?></button>
              <button class="im-chip" type="button"><?= icon('filters') ?> Filtres <span class="im-chip__count">3</span></button>
            </div>
            <p class="sg__label" style="margin-top: 2.5rem">Choix unique en puces (radio, sans JavaScript) — <code>.im-choices</code></p>
            <fieldset class="im-choices">
              <legend class="visually-hidden">Objet</legend>
              <div class="im-choices__list">
                <label class="im-choices__item"><input type="radio" name="sg-choice" checked><span class="im-chip">Acheter un bien</span></label>
                <label class="im-choices__item"><input type="radio" name="sg-choice"><span class="im-chip">Louer un bien</span></label>
                <label class="im-choices__item"><input type="radio" name="sg-choice"><span class="im-chip">Autre demande</span></label>
              </div>
            </fieldset>
            <p class="sg__label" style="margin-top: 2.5rem">Coordonnées avec action directe — <code>.im-channels</code> (page Contact)</p>
            <ul class="im-channels" style="max-width: 26rem">
              <li class="im-channel">
                <span class="im-channel__icon"><?= icon('phone') ?></span>
                <span class="im-channel__body"><span class="im-channel__label">Téléphone</span><span class="im-channel__value">+225 05 64 00 00 80</span></span>
                <a class="im-channel__action" href="#">Appeler <?= icon('arrow-right') ?></a>
              </li>
              <li class="im-channel">
                <span class="im-channel__icon im-channel__icon--whatsapp"><?= icon('whatsapp') ?></span>
                <span class="im-channel__body"><span class="im-channel__label">WhatsApp</span><span class="im-channel__value">+225 05 64 00 00 80</span></span>
                <a class="im-channel__action" href="#">Écrire <?= icon('arrow-up-right') ?></a>
              </li>
            </ul>
            <p class="sg__label" style="margin-top: 2.5rem">Onglets</p>
            <div class="im-tabs" role="tablist" style="border-bottom: 1px solid var(--im-line)">
              <button class="im-tabs__tab" role="tab" aria-selected="true" type="button">Présentation</button>
              <button class="im-tabs__tab" role="tab" aria-selected="false" type="button">Caractéristiques</button>
              <button class="im-tabs__tab" role="tab" aria-selected="false" type="button">Localisation</button>
            </div>
          </div>
        </section>

        <!-- 07 Badges -->
        <section class="sg__section" id="badges">
          <div class="sg__section-head"><span class="sg__index">07</span><h2 class="im-h2">Badges</h2></div>
          <div class="sg__stage sg__stage--snow">
            <div class="sg__row">
              <span class="im-badge im-badge--new">Nouveau</span>
              <span class="im-badge im-badge--featured">À la une</span>
              <span class="im-badge im-badge--light">Titre foncier</span>
              <span class="im-badge">Haut standing</span>
              <span class="im-badge im-badge--success">Disponible</span>
              <span class="im-badge im-badge--neutral">Vendu</span>
              <span class="im-badge im-badge--soft"><?= icon('verified') ?> Agence vérifiée</span>
            </div>
            <p class="im-small im-muted" style="margin-top: 1.25rem">Le rouge est réservé au badge « Nouveau » et aux erreurs.</p>
          </div>
        </section>

        <!-- 08 Recherche -->
        <section class="sg__section" id="recherche">
          <div class="sg__section-head"><span class="sg__index">08</span><h2 class="im-h2">Module de recherche</h2></div>
          <div class="sg__stage sg__stage--snow" style="padding: clamp(1rem, 3vw, 2.5rem)">
            <?= render_view('front/partials/search', $search) ?>
          </div>
          <p class="im-small im-muted" style="margin-top: 1rem">Le hero complet (diaporama, recherche flottante) est visible sur la <a class="im-link-underline" href="<?= e(url()) ?>">maquette de l’accueil</a>.</p>
        </section>

        <!-- 09 Cartes -->
        <section class="sg__section" id="cartes" style="border-bottom: 0">
          <div class="sg__section-head"><span class="sg__index">09</span><h2 class="im-h2">Cartes annonces</h2></div>
          <p class="sg__label">Grille · accueil et résultats</p>
          <div class="im-grid-cards">
            <?php foreach (array_slice($properties, 0, 3) as $property): ?>
              <?= render_view('front/partials/property-card', ['property' => $property]) ?>
            <?php endforeach; ?>
          </div>
          <p class="sg__label" style="margin-top: 3.5rem">Liste · résultats en vue liste</p>
          <div style="display: grid; gap: 1rem">
            <?php foreach (array_slice($properties, 3, 2) as $property): ?>
              <?= render_view('front/partials/property-card', ['property' => $property, 'variant' => 'row']) ?>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>
