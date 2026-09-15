<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Support\Validator;

/**
 * Profil public de l'agence connectée (lot 1.7).
 *
 * Le responsable (agency_owner) modifie ce que voient les visiteurs : logo, présentation, coordonnées,
 * siège et zones couvertes. L'agent (agency_agent) consulte sans modifier.
 * L'identité légale (nom, adresse publique de la page, RCCM, vérification, statut) reste à l'équipe interne.
 */
final class AgencyProfileController extends Controller
{
    use LogoSupport;

    public function show(Request $request): Response
    {
        [$agency, $isOwner] = $this->agency($request);

        return $this->form($request, $agency, $agency + ['zones' => $this->app->agencies()->zoneIds((int) $agency['id'])], $isOwner);
    }

    public function update(Request $request): Response
    {
        [$agency, $isOwner] = $this->agency($request);
        if (!$isOwner) {
            throw new HttpException(403);
        }

        [$data, $zones, $errors] = $this->validate($request, (int) $agency['country_id']);
        $logoError = $this->checkLogo($request);
        if ($logoError !== null && $logoError !== 'none') {
            $errors['logo'] = __($logoError, ['max' => '2 Mo']);
        }
        if ($errors !== []) {
            return $this->form($request, $agency, $request->all() + ['zones' => []] + $agency, true, $errors, 422);
        }

        $repo = $this->app->agencies();
        $agencyId = (int) $agency['id'];
        $before = $agency + ['zones' => implode(',', $repo->zoneIds($agencyId))];
        $repo->save($agencyId, $data, $zones);

        if ($logoError === null) {
            $this->storeLogo($request, $agencyId, $agency['logo_path']);
        } elseif ($request->input('remove_logo') === '1') {
            $this->removeLogo($agencyId, $agency['logo_path']);
        }

        $changes = $this->diff($before, $data + ['zones' => implode(',', $zones)]);
        $this->log($request, 'agency.profile_updated', 'agency', $agencyId, (string) $agency['name'], $changes);
        $this->notifyStaff($request, $agency, $changes !== null);
        $this->flash('success', __('agency_profile.flash.updated'));

        return $this->redirectToRoute('cmsadmin.agency.profile', status: 303);
    }

    /**
     * Agence du compte connecté (jamais un identifiant passé en paramètre).
     *
     * @return array{0: array<string, mixed>, 1: bool} Agence, et « le compte est responsable »
     */
    private function agency(Request $request): array
    {
        $user = $this->user($request);
        $countryId = site()?->country->id ?? throw new HttpException(403);
        if (!$user->isAgency() || $user->agencyId === null || !$user->canAccessCountry($countryId)) {
            throw new HttpException(403);
        }
        $agency = $this->app->agencies()->find((int) $user->agencyId, $countryId) ?? throw new HttpException(403);

        return [$agency, $user->role === User::AGENCY_OWNER];
    }

    /**
     * @param array<string, mixed>  $agency
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function form(Request $request, array $agency, array $values, bool $isOwner, array $errors = [], int $status = 200): Response
    {
        $countryId = (int) $agency['country_id'];
        $geo = $this->app->geo();

        return $this->render($request, 'agencies/profile', [
            'agency' => $agency,
            'values' => $values,
            'errors' => $errors,
            'isOwner' => $isOwner,
            'cities' => $geo->cityOptions($countryId, true),
            'communes' => $geo->communeOptions($countryId),
            'zoneNames' => $this->zoneNames($geo->communeOptions($countryId), array_map('intval', (array) ($values['zones'] ?? []))),
            'propertiesCount' => $this->app->agencies()->propertiesCount((int) $agency['id']),
            'accounts' => $this->app->users()->forAgency((int) $agency['id']),
        ], [
            'title' => __('agency_profile.title'),
            'activeMenu' => 'agency_profile',
            'plugins' => ['select2'],
        ], $status);
    }

    /**
     * @param array<int, string> $communes
     * @param list<int>          $zones
     * @return list<string>
     */
    private function zoneNames(array $communes, array $zones): array
    {
        return array_values(array_filter(array_map(static fn (int $id): ?string => $communes[$id] ?? null, $zones)));
    }

    /**
     * Champs confiés à l'agence uniquement : le reste de la fiche est repris tel quel.
     *
     * @return array{0: array<string, mixed>, 1: list<int>, 2: array<string, string>}
     */
    private function validate(Request $request, int $countryId): array
    {
        $input = $request->all();
        $website = trim((string) ($input['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $input['website'] = 'https://' . $website;
        }

        $v = new Validator($input);
        $v->maxLength('description', 5000)
            ->maxLength('email', 190)->email('email')
            ->maxLength('phone', 30)->phone('phone')
            ->maxLength('whatsapp', 30)->phone('whatsapp')
            ->maxLength('website', 255)->rule('website', $v->string('website') === '' || filter_var($v->string('website'), FILTER_VALIDATE_URL) !== false, __('validation.url'))
            ->maxLength('address', 255);

        $geo = $this->app->geo();
        $cityId = $v->nullableInt('city_id');
        $v->rule('city_id', $cityId === null || $geo->city($cityId, $countryId) !== null, __('validation.in'));
        $communeId = $v->nullableInt('commune_id');
        $commune = $communeId !== null ? $geo->commune($communeId, $countryId) : null;
        $v->rule('commune_id', $communeId === null || ($commune !== null && ($cityId === null || (int) $commune['city_id'] === $cityId)), __('agencies.commune_not_in_city'));
        if ($commune !== null && $cityId === null) {
            $cityId = (int) $commune['city_id'];
        }

        $allowedZones = $geo->communeOptions($countryId);
        $zones = array_values(array_filter(array_map('intval', $v->list('zones')), static fn (int $id): bool => isset($allowedZones[$id])));

        return [[
            'description' => $v->nullableString('description'),
            'email' => $v->nullableString('email') !== null ? mb_strtolower($v->string('email')) : null,
            'phone' => $v->nullableString('phone'),
            'whatsapp' => $v->nullableString('whatsapp'),
            'website' => $v->nullableString('website'),
            'address' => $v->nullableString('address'),
            'city_id' => $cityId,
            'commune_id' => $communeId,
        ], $zones, $v->errors()];
    }

    /** L'équipe du pays est prévenue dans la cloche (contenu public modifié), sans email. */
    private function notifyStaff(Request $request, array $agency, bool $changed): void
    {
        if (!$changed) {
            return;
        }
        $recipients = $this->app->notifier()->staffRecipients((int) $agency['country_id']);
        $this->app->notifier()->notify(
            $recipients['all'],
            'agency.profile_updated',
            __('agency_profile.notification.title', ['name' => (string) $agency['name']]),
            __('agency_profile.notification.body', ['user' => $this->user($request)->fullName()]),
            '/cmsadmin/agences/' . (int) $agency['id'] . '/modifier',
            site()
        );
    }
}
