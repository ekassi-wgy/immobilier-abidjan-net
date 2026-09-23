<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Request;
use App\Core\Response;

/**
 * Guide d'utilisation du back-office (`/cmsadmin/guide`).
 *
 * Page de documentation, sans écriture ni requête en base. Le contenu vit dans
 * `config/cmsadmin-guide.php` (comme le menu) et n'est pas traduit : c'est du contenu éditorial,
 * et la langue du back-office suit `sites.default_locale`.
 *
 * Accessible à tous les rôles connectés — chacun ne voit que les sections de son périmètre, les
 * mêmes que celles de son menu : un partenaire n'a pas à lire comment on valide une annonce.
 */
final class GuideController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $this->user($request)->menuRole();

        return $this->render($request, 'guide/index', [
            'sections' => $this->sections($role),
        ], [
            'title' => __('guide.title'),
            'activeMenu' => 'guide',
        ]);
    }

    /**
     * Sections visibles par ce rôle, blocs compris : un bloc peut restreindre son propre périmètre
     * (les règles de validation, par exemple, ne concernent que l'équipe).
     *
     * @return list<array<string, mixed>>
     */
    private function sections(string $role): array
    {
        $visible = static fn (array $entry): bool => !isset($entry['roles']) || in_array($role, $entry['roles'], true);

        $sections = [];
        foreach (require APP_ROOT . '/config/cmsadmin-guide.php' as $section) {
            if (!$visible($section)) {
                continue;
            }
            $section['blocks'] = array_values(array_filter($section['blocks'] ?? [], $visible));
            if ($section['blocks'] === []) {
                continue;
            }
            $sections[] = $section;
        }

        return $sections;
    }
}
