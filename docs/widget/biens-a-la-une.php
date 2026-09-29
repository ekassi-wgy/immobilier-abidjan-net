<?php
/**
 * Abidjan.net Immobilier : section « Biens à la une » à inclure dans une page d'Abidjan.net.
 *
 * À remettre à l'équipe technique d'Abidjan.net (voir docs/widget.md). Utilisation :
 *
 *     require_once '/chemin/vers/ian-biens-a-la-une.php';
 *     echo ian_biens_a_la_une(3);   // 1 à 6 annonces
 *
 * - Le fragment est gardé 10 minutes dans un fichier local : la page d'Abidjan.net n'appelle
 *   immobilier.abidjan.net qu'une fois toutes les 10 minutes, quel que soit son trafic.
 * - Délai maximal de 2 secondes. Si immobilier.abidjan.net ne répond pas, la dernière version gardée
 *   est affichée ; à défaut, rien (chaîne vide) : la page d'Abidjan.net n'est jamais bloquée.
 * - Pendant qu'une requête rafraîchit le fichier, les autres servent l'ancienne version.
 *
 * Compatible PHP 5.4 à 8.x, avec l'extension cURL ou, à défaut, allow_url_fopen.
 */

if (!function_exists('ian_biens_a_la_une')) {
    function ian_biens_a_la_une($limit = 3)
    {
        $limit = max(1, min(6, (int) $limit));
        $base = defined('IAN_WIDGET_URL') ? IAN_WIDGET_URL : 'https://immobilier.abidjan.net/widget/biens-a-la-une';
        $cache = sys_get_temp_dir() . '/ian-biens-a-la-une-' . md5($base) . '-' . $limit . '.html';
        $ttl = 600;

        $fresh = is_file($cache) && filemtime($cache) > time() - $ttl;
        if ($fresh) {
            return (string) file_get_contents($cache);
        }
        if (is_file($cache)) {
            // Les requêtes suivantes serviront cette version pendant le rafraîchissement
            @touch($cache);
        }

        $html = ian_biens_a_la_une_fetch($base . '?limit=' . $limit);
        if ($html !== null) {
            $tmp = $cache . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $html) !== false) {
                @rename($tmp, $cache);
            }

            return $html;
        }

        return is_file($cache) ? (string) file_get_contents($cache) : '';
    }

    /** Contenu de l'adresse si elle répond 200 en moins de 2 secondes, sinon null. */
    function ian_biens_a_la_une_fetch($url)
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'Abidjan.net (widget Biens a la une)',
            ));
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return ($body !== false && $status === 200) ? (string) $body : null;
        }

        $context = stream_context_create(array('http' => array(
            'timeout' => 2,
            'ignore_errors' => true,
            'header' => "User-Agent: Abidjan.net (widget Biens a la une)\r\n",
        )));
        $body = @file_get_contents($url, false, $context);
        $ok = isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+200\b#', $http_response_header[0]);

        return ($body !== false && $ok) ? (string) $body : null;
    }
}
