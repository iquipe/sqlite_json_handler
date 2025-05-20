<?php
namespace App\Database;

use PDO;
use PDOException;
use Exception;

class SQLiteManager
{
    private ?PDO $pdo = null;
    private string $dbPath;
    private string $dbName;
    private const DATABASES_DIR = __DIR__ . '/../../databases/';
    private const BACKUPS_DIR = __DIR__ . '/../../backups/';

    public function __construct(string $dbName = '')
    {
        if (!is_dir(self::DATABASES_DIR)) {
            mkdir(self::DATABASES_DIR, 0775, true);
        }
        if (!is_dir(self::BACKUPS_DIR)) {
            mkdir(self::BACKUPS_DIR, 0775, true);
        }

        if (!empty($dbName)) {
            $this->setDatabase($dbName);
        }
    }

    public function setDatabase(string $dbName): void
    {
        if (empty(trim($dbName))) {
            throw new \InvalidArgumentException("Database name cannot be empty.");
        }
        // Sanitize dbName to prevent directory traversal or invalid characters
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbName);
        if (empty($dbName)) {
            throw new \InvalidArgumentException("Database name contains invalid characters.");
        }

        $this->dbName = $dbName . ".sqlite";
        $this->dbPath = self::DATABASES_DIR . $this->dbName;
        $this->connect();
    }
    
    public function getDbName(): ?string
    {
        return isset($this->dbName) ? str_replace('.sqlite', '', $this->dbName) : null;
    }

    private function connect(): void
    {
        if (!isset($this->dbPath)) {
             // Allow creation of manager without immediate connection for createDatabase
            return;
        }
        if (!file_exists($this->dbPath) && $this->pdo === null) {
            // Don't auto-create here, let createDatabase handle it.
            // This prevents errors if trying to operate on a non-existent DB.
            return;
        }
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public function getConnection(): ?PDO
    {
        if ($this->pdo === null && isset($this->dbPath) && file_exists($this->dbPath)) {
            $this->connect(); // Attempt to connect if not already
        }
        return $this->pdo;
    }

    public function closeConnection(): void
    {
        $this->pdo = null;
    }

    public function createDatabase(string $dbName): bool
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbName);
        if (empty($dbName)) {
            throw new \InvalidArgumentException("Database name contains invalid characters or is empty.");
        }
        $newDbPath = self::DATABASES_DIR . $dbName . ".sqlite";
        if (file_exists($newDbPath)) {
            throw new Exception("Database '{$dbName}' already exists.");
        }
        try {
            $tempPdo = new PDO('sqlite:' . $newDbPath); // Creates the file
            $tempPdo = null; // Close connection
            $this->setDatabase($dbName); // Set as current DB and connect
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to create database '{$dbName}': " . $e->getMessage());
        }
    }

    public function deleteDatabase(string $dbName): bool
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbName);
        $targetDbPath = self::DATABASES_DIR . $dbName . ".sqlite";

        if (!file_exists($targetDbPath)) {
            throw new Exception("Database '{$dbName}' does not exist.");
        }

        // If current connection is to the DB being deleted, close it
        if ($this->pdo && $this->dbPath === $targetDbPath) {
            $this->closeConnection();
            $this->dbPath = '';
            $this->dbName = '';
        }

        if (unlink($targetDbPath)) {
            return true;
        }
        throw new Exception("Failed to delete database '{$dbName}'. Check permissions.");
    }

    public function backupDatabase(string $dbName): string
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbName);
        $sourceDbPath = self::DATABASES_DIR . $dbName . ".sqlite";

        if (!file_exists($sourceDbPath)) {
            throw new Exception("Database '{$dbName}' does not exist for backup.");
        }

        $backupFileName = $dbName . '_backup_' . date('YmdHis') . '.sqlite';
        $backupFilePath = self::BACKUPS_DIR . $backupFileName;

        // Ensure current connection is closed if it's the one being backed up to avoid locking
        $wasConnectedToSource = ($this->pdo && $this->dbPath === $sourceDbPath);
        if ($wasConnectedToSource) {
            $this->closeConnection();
        }

        if (copy($sourceDbPath, $backupFilePath)) {
            // Re-establish connection if it was closed
            if ($wasConnectedToSource) {
                $this->setDatabase($dbName);
            }
            return $backupFilePath;
        }
        
        // Re-establish connection if it was closed and backup failed
        if ($wasConnectedToSource) {
            $this->setDatabase($dbName);
        }
        throw new Exception("Failed to backup database '{$dbName}'.");
    }

    public function restoreDatabase(string $dbName, string $backupFileName): bool
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbName);
        // Sanitize backupFileName as well, though it's less critical if it only reads from backup dir
        $backupFileName = basename($backupFileName); // Basic sanitization
        $backupFilePath = self::BACKUPS_DIR . $backupFileName;
        $targetDbPath = self::DATABASES_DIR . $dbName . ".sqlite";

        if (!file_exists($backupFilePath)) {
            throw new Exception("Backup file '{$backupFileName}' does not exist.");
        }

        // If current connection is to the DB being restored, close it
        if ($this->pdo && $this->dbPath === $targetDbPath) {
            $this->closeConnection();
        }

        if (copy($backupFilePath, $targetDbPath)) {
            $this->setDatabase($dbName); // Set and connect to the restored DB
            return true;
        }
        
        // Attempt to reconnect to original if restore failed and it existed
        if (file_exists($targetDbPath)) {
             $this->setDatabase($dbName);
        }
        throw new Exception("Failed to restore database '{$dbName}' from '{$backupFileName}'.");
    }

    public function getAllTablesWithFields(): array
    {
        if (!$this->pdo) {
            throw new Exception("Not connected to any database. Select or create a database first.");
        }
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $schema = [];

        foreach ($tables as $tableName) {
            $tableManager = new TableManager($this, $tableName);
            $schema[$tableName] = $tableManager->getTableSchema();
        }
        return $schema;
    }
    
    public function tableExists(string $tableName): bool
    {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() !== false;
    }
}