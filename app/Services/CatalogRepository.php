<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Catalogue : catégories (arborescence), types de transaction, critères dynamiques (EAV) et équipements.
 *
 * Règles de conception (docs/database.md) :
 * - les codes techniques sont fixés à la création (référencés par le code et les imports) ;
 * - une sous-catégorie hérite des critères de sa catégorie racine et peut en ajouter ;
 * - les critères « column » sont stockés dans des colonnes indexées de properties (liste fermée) ;
 * - un élément utilisé par des annonces ne peut pas être supprimé : il se désactive.
 */
final class CatalogRepository
{
    public const INPUT_TYPES = ['integer', 'decimal', 'text', 'boolean', 'select', 'multiselect', 'date', 'year'];
    public const COLUMN_STORAGE = ['living_area', 'land_area', 'rooms', 'bedrooms', 'bathrooms'];
    public const FEATURE_GROUPS = ['comfort', 'security', 'outdoor', 'utilities', 'connectivity'];

    public function __construct(private readonly Database $db)
    {
    }

    // Catégories ------------------------------------------------------------------------------------

    /**
     * Arborescence à deux niveaux : racines triées, chacune avec ses enfants.
     *
     * @return list<array<string, mixed>>
     */
    public function categoryTree(): array
    {
        $rows = $this->db->select(
            'SELECT c.*, co.name AS country_name,
                    (SELECT COUNT(*) FROM properties p WHERE p.category_id = c.id) AS properties_count,
                    (SELECT COUNT(*) FROM category_attributes ca WHERE ca.category_id = c.id) AS attributes_count,
                    (SELECT GROUP_CONCAT(t.name ORDER BY t.sort_order SEPARATOR \', \') FROM category_transaction_types ct
                       JOIN transaction_types t ON t.id = ct.transaction_type_id WHERE ct.category_id = c.id) AS transactions
             FROM property_categories c LEFT JOIN countries co ON co.id = c.country_id
             ORDER BY c.sort_order, c.name'
        );

        $roots = [];
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $roots[(int) $row['id']] = $row + ['children' => []];
            } else {
                $children[] = $row;
            }
        }
        foreach ($children as $child) {
            if (isset($roots[(int) $child['parent_id']])) {
                $roots[(int) $child['parent_id']]['children'][] = $child;
            }
        }

        return array_values($roots);
    }

    /** @return array<string, mixed>|null */
    public function category(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM property_categories WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int, string> Catégories racines [id => nom] */
    public function rootCategoryOptions(?int $exceptId = null): array
    {
        $options = [];
        foreach ($this->db->select('SELECT id, name FROM property_categories WHERE parent_id IS NULL AND id <> :except ORDER BY sort_order, name', ['except' => $exceptId ?? 0]) as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }

    public function hasChildren(int $categoryId): bool
    {
        return $this->db->scalar('SELECT 1 FROM property_categories WHERE parent_id = :id LIMIT 1', ['id' => $categoryId]) !== null;
    }

    public function valueExists(string $table, string $column, string $value, ?int $exceptId = null): bool
    {
        $allowed = [
            'property_categories' => ['code', 'slug'],
            'property_attributes' => ['code'],
            'features' => ['code'],
        ];
        if (!in_array($column, $allowed[$table] ?? [], true)) {
            throw new \InvalidArgumentException("Contrôle d'unicité non prévu : {$table}.{$column}");
        }

        return $this->db->scalar("SELECT 1 FROM `{$table}` WHERE `{$column}` = :value AND id <> :except LIMIT 1", ['value' => $value, 'except' => $exceptId ?? 0]) !== null;
    }

    /** @return list<int> */
    public function categoryTransactionIds(int $categoryId): array
    {
        return array_map('intval', array_column($this->db->select('SELECT transaction_type_id FROM category_transaction_types WHERE category_id = :id', ['id' => $categoryId]), 'transaction_type_id'));
    }

    /**
     * Critères rattachés : [attribute_id => ['is_required' => bool, 'sort_order' => int]].
     *
     * @return array<int, array{is_required: bool, sort_order: int}>
     */
    public function categoryAttributes(int $categoryId): array
    {
        $links = [];
        foreach ($this->db->select('SELECT attribute_id, is_required, sort_order FROM category_attributes WHERE category_id = :id', ['id' => $categoryId]) as $row) {
            $links[(int) $row['attribute_id']] = ['is_required' => (bool) $row['is_required'], 'sort_order' => (int) $row['sort_order']];
        }

        return $links;
    }

    /**
     * @param array<string, mixed>                                        $data
     * @param list<int>                                                   $transactionIds
     * @param array<int, array{is_required: bool, sort_order: int}>       $attributes
     */
    public function saveCategory(?int $id, array $data, array $transactionIds, array $attributes): int
    {
        return $this->db->transaction(function (Database $db) use ($id, $data, $transactionIds, $attributes): int {
            if ($id === null) {
                $id = $db->insert('property_categories', $data);
            } else {
                $this->update('property_categories', $id, $data);
                $db->execute('DELETE FROM category_transaction_types WHERE category_id = :id', ['id' => $id]);
                $db->execute('DELETE FROM category_attributes WHERE category_id = :id', ['id' => $id]);
            }

            foreach (array_unique($transactionIds) as $transactionId) {
                $db->execute('INSERT INTO category_transaction_types (category_id, transaction_type_id) VALUES (:c, :t)', ['c' => $id, 't' => $transactionId]);
            }
            foreach ($attributes as $attributeId => $link) {
                $db->execute(
                    'INSERT INTO category_attributes (category_id, attribute_id, is_required, sort_order) VALUES (:c, :a, :r, :s)',
                    ['c' => $id, 'a' => $attributeId, 'r' => $link['is_required'], 's' => $link['sort_order']]
                );
            }

            return $id;
        });
    }

    /** @return array<string, int> [clé de traduction => nombre] */
    public function categoryUsage(int $id): array
    {
        return array_filter([
            'catalog.usage.subcategories' => (int) $this->db->scalar('SELECT COUNT(*) FROM property_categories WHERE parent_id = :id', ['id' => $id]),
            'catalog.usage.properties' => (int) $this->db->scalar('SELECT COUNT(*) FROM properties WHERE category_id = :id', ['id' => $id]),
        ]);
    }

    // Types de transaction ---------------------------------------------------------------------------

    /** @return array<int, string> [id => nom] */
    public function transactionOptions(): array
    {
        $options = [];
        foreach ($this->db->select('SELECT id, name FROM transaction_types ORDER BY sort_order, name') as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }

    // Critères ---------------------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> [id => groupe] */
    public function attributeGroups(): array
    {
        $groups = [];
        foreach ($this->db->select('SELECT * FROM attribute_groups ORDER BY sort_order, name') as $row) {
            $groups[(int) $row['id']] = $row;
        }

        return $groups;
    }

    /**
     * @param array{q?: string, groupe?: int, etat?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function attributes(array $filters, int $limit, int $offset): array
    {
        $where = '1 = 1';
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (a.name LIKE :q OR a.code LIKE :q2)';
            $params['q'] = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            $params['q2'] = $params['q'];
        }
        if (!empty($filters['groupe'])) {
            $where .= ' AND a.group_id = :group';
            $params['group'] = (int) $filters['groupe'];
        }
        if (($filters['etat'] ?? '') === 'actifs') {
            $where .= ' AND a.is_active = 1';
        } elseif (($filters['etat'] ?? '') === 'inactifs') {
            $where .= ' AND a.is_active = 0';
        }

        $from = "FROM property_attributes a JOIN attribute_groups g ON g.id = a.group_id WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT a.*, g.name AS group_name,
                        (SELECT COUNT(*) FROM property_attribute_options o WHERE o.attribute_id = a.id) AS options_count,
                        (SELECT COUNT(*) FROM category_attributes ca WHERE ca.attribute_id = a.id) AS categories_count
                 {$from} ORDER BY g.sort_order, a.sort_order, a.name LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /**
     * Tous les critères actifs, groupés, pour l'affectation aux catégories.
     *
     * @return array<string, list<array<string, mixed>>> [nom du groupe => critères]
     */
    public function attributesByGroup(): array
    {
        $grouped = [];
        foreach ($this->db->select(
            'SELECT a.id, a.code, a.name, a.input_type, a.unit, a.is_active, g.name AS group_name
             FROM property_attributes a JOIN attribute_groups g ON g.id = a.group_id
             ORDER BY g.sort_order, a.sort_order, a.name'
        ) as $row) {
            $grouped[(string) $row['group_name']][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    public function attribute(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM property_attributes WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function attributeOptions(int $attributeId): array
    {
        return $this->db->select(
            'SELECT o.*, (SELECT COUNT(*) FROM property_attribute_values v WHERE v.value_option_id = o.id) AS usage_count
             FROM property_attribute_options o WHERE o.attribute_id = :id ORDER BY o.sort_order, o.label',
            ['id' => $attributeId]
        );
    }

    public function attributeValuesCount(int $attributeId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM property_attribute_values WHERE attribute_id = :id', ['id' => $attributeId]);
    }

    /**
     * Enregistre un critère et ses options (tableau complet : les options absentes et inutilisées sont supprimées).
     *
     * @param array<string, mixed>              $data
     * @param list<array<string, mixed>>        $options ['id' => ?int, 'code', 'label', 'label_translations', 'sort_order', 'is_active']
     */
    public function saveAttribute(?int $id, array $data, array $options): int
    {
        return $this->db->transaction(function (Database $db) use ($id, $data, $options): int {
            if ($id === null) {
                $id = $db->insert('property_attributes', $data);
            } else {
                $this->update('property_attributes', $id, $data);
            }

            $kept = [];
            foreach ($options as $option) {
                $optionId = $option['id'] ?? null;
                unset($option['id']);
                if ($optionId !== null) {
                    $db->execute(
                        'UPDATE property_attribute_options SET code = :code, label = :label, label_translations = :tr, sort_order = :sort, is_active = :active
                         WHERE id = :id AND attribute_id = :attribute',
                        ['code' => $option['code'], 'label' => $option['label'], 'tr' => $option['label_translations'], 'sort' => $option['sort_order'], 'active' => $option['is_active'], 'id' => $optionId, 'attribute' => $id]
                    );
                    $kept[] = (int) $optionId;
                } else {
                    $kept[] = $db->insert('property_attribute_options', ['attribute_id' => $id] + $option);
                }
            }

            // Options retirées du formulaire : supprimées si aucune annonce ne les utilise, sinon désactivées
            foreach ($this->attributeOptions($id) as $existing) {
                if (in_array((int) $existing['id'], $kept, true)) {
                    continue;
                }
                if ((int) $existing['usage_count'] > 0) {
                    $db->execute('UPDATE property_attribute_options SET is_active = 0 WHERE id = :id', ['id' => $existing['id']]);
                } else {
                    $db->execute('DELETE FROM property_attribute_options WHERE id = :id', ['id' => $existing['id']]);
                }
            }

            return $id;
        });
    }

    /** @return array<string, int> */
    public function attributeUsage(int $id): array
    {
        return array_filter([
            'catalog.usage.properties' => (int) $this->db->scalar('SELECT COUNT(DISTINCT property_id) FROM property_attribute_values WHERE attribute_id = :id', ['id' => $id]),
        ]);
    }

    // Équipements ------------------------------------------------------------------------------------

    /**
     * @param array{q?: string, groupe?: string, etat?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function features(array $filters): array
    {
        $where = '1 = 1';
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (f.name LIKE :q OR f.code LIKE :q2)';
            $params['q'] = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            $params['q2'] = $params['q'];
        }
        if (in_array($filters['groupe'] ?? '', self::FEATURE_GROUPS, true)) {
            $where .= ' AND f.feature_group = :group';
            $params['group'] = $filters['groupe'];
        }
        if (($filters['etat'] ?? '') === 'actifs') {
            $where .= ' AND f.is_active = 1';
        } elseif (($filters['etat'] ?? '') === 'inactifs') {
            $where .= ' AND f.is_active = 0';
        }

        return $this->db->select(
            "SELECT f.*, (SELECT COUNT(*) FROM property_features pf WHERE pf.feature_id = f.id) AS properties_count
             FROM features f WHERE {$where}
             ORDER BY FIELD(f.feature_group, 'comfort', 'security', 'outdoor', 'utilities', 'connectivity'), f.sort_order, f.name",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function feature(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM features WHERE id = :id', ['id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public function saveFeature(?int $id, array $data): int
    {
        if ($id === null) {
            return $this->db->insert('features', $data);
        }
        $this->update('features', $id, $data);

        return $id;
    }

    /** @return array<string, int> */
    public function featureUsage(int $id): array
    {
        return array_filter([
            'catalog.usage.properties' => (int) $this->db->scalar('SELECT COUNT(*) FROM property_features WHERE feature_id = :id', ['id' => $id]),
        ]);
    }

    // Commun -----------------------------------------------------------------------------------------

    public function setActive(string $table, int $id, bool $active): void
    {
        $this->assertTable($table);
        $this->db->execute("UPDATE `{$table}` SET is_active = :active WHERE id = :id", ['active' => $active, 'id' => $id]);
    }

    public function delete(string $table, int $id): void
    {
        $this->assertTable($table);
        $this->db->execute("DELETE FROM `{$table}` WHERE id = :id", ['id' => $id]);
    }

    /** @param array<string, mixed> $data */
    private function update(string $table, int $id, array $data): void
    {
        $this->assertTable($table);
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE `{$table}` SET {$sets} WHERE id = :id", $data + ['id' => $id]);
    }

    private function assertTable(string $table): void
    {
        if (!in_array($table, ['property_categories', 'property_attributes', 'features'], true)) {
            throw new \InvalidArgumentException("Table non gérée par le catalogue : {$table}");
        }
    }
}
