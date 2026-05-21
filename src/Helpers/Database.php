<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $driver = $_ENV['DB_DRIVER'] ?? 'sqlite';

            try {
                if ($driver === 'sqlite') {
                    $path = ROOT_PATH . '/database/' . ($_ENV['DB_FILE'] ?? 'cofre.sqlite');
                    self::$instance = new PDO("sqlite:$path");
                } else {
                    $dsn = sprintf(
                        '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $driver,
                        $_ENV['DB_HOST'] ?? '127.0.0.1',
                        $_ENV['DB_PORT'] ?? '3306',
                        $_ENV['DB_NAME'] ?? 'cofre_digital'
                    );
                    self::$instance = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '');
                }

                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                self::migrate(self::$instance);
            } catch (PDOException $e) {
                throw new RuntimeException('Falha na conexão com o banco: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                email      TEXT    NOT NULL UNIQUE,
                password   TEXT    NOT NULL,
                created_at TEXT    NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS secrets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                title       TEXT    NOT NULL,
                content     TEXT    NOT NULL,
                type        TEXT    NOT NULL DEFAULT 'note',
                is_favorite INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at  TEXT    NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS tokens (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                token      TEXT    NOT NULL UNIQUE,
                expires_at TEXT    NOT NULL,
                created_at TEXT    NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
    }
}
