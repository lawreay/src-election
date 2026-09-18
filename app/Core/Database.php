<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/../../config/database.php';
        $dbPath = $config['database'];

        $directory = dirname($dbPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $dsn = 'sqlite:' . $dbPath;

        try {
            self::$instance = new PDO($dsn);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA foreign_keys = ON');
            self::initializeSchema(self::$instance);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Unable to connect to database: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$instance;
    }

    private static function initializeSchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');

        if ($schema === false) {
            return;
        }

        $pdo->exec($schema);
    }
}
