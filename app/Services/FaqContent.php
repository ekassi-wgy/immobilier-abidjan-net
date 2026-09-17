<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Découpe le contenu HTML de la page FAQ en thèmes, questions et réponses.
 *
 * La page reste rédigée depuis le back-office avec une convention simple : un `h2` ouvre un thème,
 * un `h3` est une question, et tout ce qui suit jusqu'au titre suivant en est la réponse. Le site
 * l'affiche en accordéon et en tire les données structurées FAQPage. Un contenu sans aucun `h3`
 * n'est pas une FAQ exploitable : l'appelant retombe alors sur l'affichage d'une page ordinaire.
 */
final class FaqContent
{
    /**
     * @return list<array{id: string, title: ?string, items: list<array{id: string, question: string, answer: string}>}>
     */
    public static function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // En-tête XML : DOMDocument lit sinon le contenu en ISO-8859-1.
        $document->loadHTML('<?xml encoding="UTF-8"><div id="faq-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('faq-root');
        if ($root === null) {
            return [];
        }

        $groups = [];
        $group = null;
        $item = null;
        $flushItem = static function () use (&$group, &$item): void {
            if ($item !== null && $group !== null) {
                $item['answer'] = trim($item['answer']);
                $group['items'][] = $item;
            }
            $item = null;
        };
        $flushGroup = static function () use (&$groups, &$group, $flushItem): void {
            $flushItem();
            if ($group !== null && $group['items'] !== []) {
                $groups[] = $group;
            }
            $group = null;
        };

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && strtolower($node->nodeName) === 'h2') {
                $flushGroup();
                $title = self::text($node);
                $group = ['id' => self::slug($title, count($groups) + 1), 'title' => $title, 'items' => []];
                continue;
            }
            if ($node instanceof DOMElement && strtolower($node->nodeName) === 'h3') {
                $flushItem();
                $group ??= ['id' => 'questions', 'title' => null, 'items' => []];
                // Espace fine insécable avant « ? ! : ; » : la ponctuation ne passe jamais seule à la ligne.
                $question = preg_replace('/\s+([?!:;])/u', "\u{202F}$1", self::text($node)) ?? self::text($node);
                $item = ['id' => $group['id'] . '-' . (count($group['items']) + 1), 'question' => $question, 'answer' => ''];
                continue;
            }
            if ($item !== null) {
                $item['answer'] .= self::html($document, $node);
            }
        }
        $flushGroup();

        return $groups;
    }

    /**
     * Données structurées schema.org FAQPage.
     *
     * @param list<array{items: list<array{question: string, answer: string}>}> $groups
     * @return array<string, mixed>
     */
    public static function schema(array $groups): array
    {
        $entities = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $entities[] = [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(preg_replace('/\s+/u', ' ', strip_tags($item['answer'])) ?? '')],
                ];
            }
        }

        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
    }

    private static function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
    }

    private static function html(DOMDocument $document, DOMNode $node): string
    {
        return (string) $document->saveHTML($node);
    }

    private static function slug(string $title, int $position): string
    {
        $slug = \App\Support\Str::slug($title, 60);

        return $slug !== '' ? $slug : 'theme-' . $position;
    }
}
