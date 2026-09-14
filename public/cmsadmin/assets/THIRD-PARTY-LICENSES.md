# Composants tiers — back-office cmsadmin

| Composant | Version | Licence | Emplacement |
|---|---|---|---|
| StarAdmin 2 Free (BootstrapDash) — structure, `style.css`, `off-canvas.js`, `hoverable-collapse.js`, `template.js` (nettoyé) | 2.0.0 | MIT | `css/style.css`, `js/` |
| Bootstrap | 5.3.2 | MIT | `vendors/js/vendor.bundle.base.js`, `css/style.css` |
| jQuery | 3.7.1 | MIT | `vendors/js/vendor.bundle.base.js` |
| Perfect Scrollbar | 1.5.3 | MIT | `vendors/js/vendor.bundle.base.js`, `vendors/css/vendor.bundle.base.css` |
| Material Design Icons | 7.4.47 | Apache 2.0 (icônes) / SIL OFL 1.1 (police) | `vendors/mdi/` |
| Chart.js | 4.x | MIT | `vendors/chart.js/` |
| Select2 | 4.x | MIT | `vendors/select2/` |
| Plus Jakarta Sans | 2.x | SIL OFL 1.1 | `/public/assets/fonts/plus-jakarta-sans/` (voir `OFL.txt`) |

Modifications apportées au template d'origine : suppression de la bannière commerciale, des crédits et liens
BootstrapDash, de la documentation, des pages de démonstration, des jeux d'icônes inutilisés (Feather, Themify,
Font Awesome, Typicons, Simple Line Icons, Flag Icons), des polices Manrope/Nunito/Roboto, de l'import Google Fonts,
des sourcemaps et des formats de police obsolètes (eot/ttf/woff) ; minification de `style.css` avec conservation
des en-têtes de licence.
