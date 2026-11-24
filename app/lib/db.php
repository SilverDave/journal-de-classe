<?php
/** Connexion MySQL (PDO) + raccourcis pour les requêtes préparées. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // vraies requêtes préparées = pas d'injection SQL
        ]);
    }
    return $pdo;
}

/**
 * Exécute une requête préparée. Les valeurs passent TOUJOURS par $params,
 * jamais directement dans la chaîne SQL.
 */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_value(string $sql, array $params = [])
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function last_id(): int
{
    return (int)db()->lastInsertId();
}
