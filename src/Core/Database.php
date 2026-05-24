<?php
declare(strict_types=1);

namespace Twitkey\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $driver;

    /**
     * Create the PDO connection and install the schema on first boot.
     */
    private function __construct()
    {
        $this->driver = strtolower((string)($_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'sqlite'));
        $this->ensureDataDirectories();

        if ($this->driver === 'mysql') {
            $host = (string)($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'mysql');
            $port = (string)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
            $name = (string)($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'twitkey');
            $user = (string)($_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'twitkey');
            $pass = (string)($_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $user, $pass, $this->options());
        } else {
            $path = (string)($_ENV['DB_PATH'] ?? getenv('DB_PATH') ?: $this->defaultSqlitePath());
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $this->pdo = new PDO('sqlite:' . $path, null, null, $this->options());
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }

        if (!$this->hasTable('users')) {
            $this->installSchema();
            $installed = $this->dataDir() . '/.installed';
            if (!is_file($installed)) {
                file_put_contents($installed, date(DATE_ATOM));
            }
        }
    }

    /**
     * Return the singleton database wrapper.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Return the underlying PDO connection.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * True when the active connection is MySQL.
     */
    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * Fetch one row from a prepared query.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $this->bind($stmt, $key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch all rows from a prepared query.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $this->bind($stmt, $key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Execute a prepared mutation query.
     *
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $this->bind($stmt, $key, $value);
        }
        return $stmt->execute();
    }

    /**
     * Bind a value with an explicit PDO type.
     */
    private function bind(\PDOStatement $stmt, int|string $key, mixed $value): void
    {
        $param = is_int($key) ? $key + 1 : ':' . ltrim((string)$key, ':');
        $type = match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
        $stmt->bindValue($param, $value, $type);
    }

    /**
     * Return the last inserted primary key.
     */
    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Execute a callback inside a transaction.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Return the writable data directory.
     */
    public function dataDir(): string
    {
        $path = (string)($_ENV['DATA_DIR'] ?? getenv('DATA_DIR') ?: '/data');
        if (!is_dir($path) && defined('TWITKEY_ROOT')) {
            $fallback = TWITKEY_ROOT . '/data';
            if (is_dir($fallback) || mkdir($fallback, 0777, true)) {
                return $fallback;
            }
        }
        return $path;
    }

    /**
     * Ensure expected persistent folders exist.
     */
    private function ensureDataDirectories(): void
    {
        foreach ([$this->dataDir(), $this->dataDir() . '/avatars', $this->dataDir() . '/uploads', $this->dataDir() . '/cache'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    /**
     * Return conservative PDO options for production use.
     *
     * @return array<int, mixed>
     */
    private function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => $this->driver === 'mysql',
        ];
    }

    /**
     * Resolve the default SQLite path.
     */
    private function defaultSqlitePath(): string
    {
        return $this->dataDir() . '/twitkey.db';
    }

    /**
     * Check whether a table already exists.
     */
    private function hasTable(string $table): bool
    {
        if ($this->isMysql()) {
            $row = $this->one('SHOW TABLES LIKE :table', ['table' => $table]);
            return $row !== null;
        }
        $row = $this->one("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table", ['table' => $table]);
        return $row !== null;
    }

    /**
     * Install the schema from schema.sql, with light type adaptation for MySQL.
     */
    private function installSchema(): void
    {
        $schemaPath = TWITKEY_ROOT . '/schema.sql';
        $sql = (string)file_get_contents($schemaPath);
        if ($this->isMysql()) {
            $sql = $this->mysqlSchema($sql);
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $this->pdo->exec($trimmed);
            }
        }
    }

    /**
     * Convert the SQLite-first schema into a MySQL 8 compatible variant.
     */
    private function mysqlSchema(string $sql): string
    {
        $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
        $sql = preg_replace("/TEXT DEFAULT \\(datetime\\('now'\\)\\)/", 'DATETIME DEFAULT CURRENT_TIMESTAMP', $sql) ?? $sql;
        $sql = str_replace('TEXT DEFAULT NULL', 'VARCHAR(255) DEFAULT NULL', $sql);
        $sql = str_replace("TEXT DEFAULT ''", "VARCHAR(255) DEFAULT ''", $sql);
        $sql = str_replace('TEXT NOT NULL UNIQUE', 'VARCHAR(190) NOT NULL UNIQUE', $sql);
        $sql = str_replace('tag  TEXT NOT NULL UNIQUE', 'tag  VARCHAR(190) NOT NULL UNIQUE', $sql);
        $sql = str_replace('role         TEXT DEFAULT', 'role         VARCHAR(20) DEFAULT', $sql);
        $sql = str_replace('status       TEXT DEFAULT', 'status       VARCHAR(20) DEFAULT', $sql);
        $sql = str_replace('status          TEXT DEFAULT', 'status          VARCHAR(20) DEFAULT', $sql);
        $sql = str_replace('vote     TEXT NOT NULL', 'vote     VARCHAR(20) NOT NULL', $sql);
        $sql = str_replace('type         TEXT NOT NULL', 'type         VARCHAR(40) NOT NULL', $sql);
        $sql = str_replace('target_type TEXT', 'target_type VARCHAR(40)', $sql);
        $sql = str_replace('action     TEXT NOT NULL', 'action     VARCHAR(120) NOT NULL', $sql);
        return $sql;
    }

    /**
     * Split SQL statements while respecting simple string literals.
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = !$inString;
            }
            if ($char === ';' && !$inString) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }
        return $statements;
    }
}
