# Composants tiers — site public

| Composant | Version | Licence | Emplacement |
|---|---|---|---|
| Bootstrap (grille, reboot, utilitaires, modal, offcanvas — compilés dans `css/app.css`) | 5.3.x | MIT | `css/app.css` (sources : `vendor/twbs/bootstrap`) |
| Plus Jakarta Sans | 2.x | SIL OFL 1.1 | `fonts/plus-jakarta-sans/` (voir `OFL.txt`) |
| Phosphor Icons (graisse light) | 2.1.1 | MIT | `img/icons.svg` (généré par `bin/build-icons.php`) |
| Leaflet | 1.9.4 | BSD-2-Clause | `vendors/leaflet/` (voir `LICENSE`) — chargé uniquement sur la vue carte |
| Leaflet.markercluster | 1.5.3 | MIT | `vendors/leaflet/leaflet.markercluster.js` (voir `MARKERCLUSTER-LICENSE`) |
| Photos provisoires de maquette | — | CC BY / CC BY-SA / CC0 / domaine public | `img/placeholder/` (voir `CREDITS.md`) |

Tuiles cartographiques : OpenStreetMap (ODbL), seule ressource externe autorisée.

Outil de build (non déployé côté navigateur) : scssphp 2.x (MIT).
