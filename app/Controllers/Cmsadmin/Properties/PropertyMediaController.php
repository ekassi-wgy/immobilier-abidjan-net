<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Properties;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Réponses appelées en arrière-plan par le formulaire d'annonce :
 * envoi des photos, critères d'une catégorie, listes géographiques et agents d'une agence.
 */
final class PropertyMediaController extends Controller
{
    use PropertySupport;

    /**
     * Envoi d'une photo : elle est ré-encodée en WebP (3 tailles) dans un dossier temporaire et identifiée
     * par un jeton lié à la session. La photo n'est rattachée à l'annonce qu'à l'enregistrement du formulaire.
     */
    public function uploadPhoto(Request $request): Response
    {
        $site = $this->site();
        $file = $request->file('photo');
        $maxBytes = max(1, (int) settings('listing.max_photo_size_mb', 10)) * 1024 * 1024;

        $error = $this->app->images()->check(is_array($file) ? $file : null, $maxBytes);
        if ($error !== null) {
            return Response::json([
                'error' => __($error === 'none' ? 'upload.failed' : $error, ['max' => settings('listing.max_photo_size_mb', 10) . ' Mo']),
            ], 422);
        }

        try {
            $stored = $this->app->images()->storeVariants((array) $file, strtolower($site->country->iso2) . '/tmp/' . gmdate('Ym'));
        } catch (Throwable $exception) {
            $this->app->logger()->exception($exception, ['action' => 'photo.upload']);

            return Response::json(['error' => __('upload.failed')], 500);
        }

        $token = $this->app->pendingUploads()->add($stored + ['original_name' => (string) ($file['name'] ?? '')]);

        return Response::json([
            'token' => $token,
            'thumb' => $this->imageUrl($stored['path']),
            'name' => mb_substr((string) ($file['name'] ?? ''), 0, 120),
        ]);
    }

    /** Fragment HTML des critères d'une catégorie (rechargé quand la catégorie change). */
    public function criteria(Request $request): Response
    {
        $site = $this->site();
        $categoryId = max(0, (int) $request->query('categorie', 0));
        $schema = $categoryId > 0 ? $this->app->catalog()->formSchema($categoryId, $site->country->id) : null;

        $values = [];
        $reference = (string) $request->query('annonce', '');
        if ($schema !== null && $reference !== '') {
            $property = $this->app->properties()->findByReference($reference, $site->country->id, $this->scopeAgency($request));
            if ($property !== null && (int) $property['category_id'] === $categoryId) {
                $values = $this->app->properties()->attributeValues((int) $property['id'], $schema['attributes'], $property);
            }
        }

        return Response::html(render_view('cmsadmin/pages/properties/criteria', [
            'schema' => $schema,
            'values' => $values,
            'errors' => [],
        ]));
    }

    /** Communes d'une ville, quartiers d'une commune, agents d'une agence (JSON pour les listes liées). */
    public function options(Request $request): Response
    {
        $site = $this->site();
        $countryId = $site->country->id;
        $geo = $this->app->geo();

        $cityId = max(0, (int) $request->query('ville', 0));
        $communeId = max(0, (int) $request->query('commune', 0));
        $agencyId = max(0, (int) $request->query('agence', 0));

        $response = ['communes' => [], 'districts' => [], 'agents' => [], 'center' => null];

        // Transactions autorisées par la catégorie (rechargées quand la catégorie change)
        $categoryId = max(0, (int) $request->query('transactions', 0));
        if ($categoryId > 0) {
            $schema = $this->app->catalog()->formSchema($categoryId, $countryId);
            foreach ($schema['transactions'] ?? [] as $id => $transaction) {
                $response['transactions'][] = ['id' => $id, 'name' => (string) $transaction['name'], 'period' => (string) $transaction['default_price_period']];
            }
        }

        if ($cityId > 0) {
            $city = $geo->city($cityId, $countryId);
            foreach ($geo->communes($countryId, ['ville' => $cityId, 'etat' => 'actifs'], 500, 0)['rows'] as $row) {
                $response['communes'][] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
            $response['center'] = $this->center($city, 11);
        }
        if ($communeId > 0) {
            $commune = $geo->commune($communeId, $countryId);
            foreach ($geo->districts($countryId, ['commune' => $communeId, 'etat' => 'actifs'], 500, 0)['rows'] as $row) {
                $response['districts'][] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
            $response['center'] = $this->center($commune, 13) ?? $response['center'];
        }
        if ($request->query('quartier') !== null) {
            $response['center'] = $this->center($geo->district(max(0, (int) $request->query('quartier', 0)), $countryId), 15) ?? $response['center'];
        }
        if ($agencyId > 0 && $this->user($request)->isStaff() && $this->app->agencies()->find($agencyId, $countryId) !== null) {
            foreach ($this->app->users()->forAgency($agencyId) as $account) {
                if ((int) $account['is_active'] === 1) {
                    $response['agents'][] = ['id' => (int) $account['id'], 'name' => $account['first_name'] . ' ' . $account['last_name']];
                }
            }
        }

        return Response::json($response);
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    private function center(?array $row, int $zoom): ?array
    {
        return $row !== null && $row['latitude'] !== null && $row['longitude'] !== null
            ? ['lat' => (float) $row['latitude'], 'lng' => (float) $row['longitude'], 'zoom' => $zoom]
            : null;
    }
}
