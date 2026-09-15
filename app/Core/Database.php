<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Accès MySQL / MariaDB via PDO. Connexion ouverte à la première requête.
 *
 * Requêtes préparées systématiques : ne jamais concaténer une valeur dans le SQL.
 * Session SQL en UTC (dates stockées en UTC).
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array{host: string, port: int, database: string, username: string, password: string, charset: string, collation: string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $config = $this->config;
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);

            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $this->pdo->exec(sprintf("SET NAMES %s COLLATE %s, time_zone = '+00:00'", $config['charset'], $config['collation']));
        }

        return $this->pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<int|string, mixed> $params Première colonne de la première ligne */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * INSERT / UPDATE / DELETE : nombre de lignes affectées.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * INSERT sur une table, colonnes issues des clés de $data. Retourne l'identifiant créé.
     * $table et les clés doivent venir du code, jamais d'une saisie utilisateur.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns)),
            implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns))
        );
        $this->run($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Exécute $callback dans une transaction (annulée si une exception est levée).
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<int|string, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(
                is_int($key) ? $key + 1 : ':' . ltrim($key, ':'),
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                }
            );
        }
        $statement->execute();

        return $statement;
    }
}
