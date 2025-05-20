<?php
namespace App\Database;

use PDO;
use PDOException;
use Exception;

class TableManager
{
    private SQLiteManager $dbManager;
    private PDO $pdo;
    private string $tableName;

    public function __construct(SQLiteManager $dbManager, string $tableName)
    {
        $this->dbManager = $dbManager;
        $pdoInstance = $this->dbManager->getConnection();
        if (!$pdoInstance) {
            throw new Exception("Database connection not available. Ensure database '{$dbManager->getDbName()}' exists and is selected.");
        }
        $this->pdo = $pdoInstance;
        
        // Basic sanitization for table name
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \InvalidArgumentException("Invalid table name '{$tableName}'. Table names must start with a letter or underscore, followed by letters, numbers, or underscores.");
        }
        $this->tableName = $tableName;
    }

    public function createTable(array $columnsDefinition): bool
    {
        if ($this->dbManager->tableExists($this->tableName)) {
            throw new Exception("Table '{$this->tableName}' already exists in database '{$this->dbManager->getDbName()}'.");
        }

        $sqlParts = [];
        foreach ($columnsDefinition as $column) {
            if (empty($column['name']) || empty($column['type'])) {
                throw new \InvalidArgumentException("Column definition must include 'name' and 'type'.");
            }
            // Sanitize column name
            $colName = preg_replace('/[^a-zA-Z_][a-zA-Z0-9_]*/', '', $column['name']);
            if (empty($colName)) throw new \InvalidArgumentException("Invalid column name provided.");
            
            $colDef = "`{$colName}` " . strtoupper($column['type']);
            if (!empty($column['constraints'])) {
                // Basic validation for constraints (more robust validation might be needed)
                if (!preg_match('/^[A-Z0-9_ ]+$/i', $column['constraints'])) {
                     throw new \InvalidArgumentException("Invalid characters in constraints for column '{$colName}'.");
                }
                $colDef .= " " . $column['constraints'];
            }
            $sqlParts[] = $colDef;
        }

        if (empty($sqlParts)) {
            throw new \InvalidArgumentException("No column definitions provided for table '{$this->tableName}'.");
        }

        $sql = "CREATE TABLE `{$this->tableName}` (" . implode(', ', $sqlParts) . ")";
        
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to create table '{$this->tableName}': " . $e->getMessage());
        }
    }

    public function deleteTable(): bool
    {
        if (!$this->dbManager->tableExists($this->tableName)) {
            throw new Exception("Table '{$this->tableName}' does not exist in database '{$this->dbManager->getDbName()}'.");
        }
        $sql = "DROP TABLE `{$this->tableName}`";
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to delete table '{$this->tableName}': " . $e->getMessage());
        }
    }

    public function getTableSchema(): array
    {
        if (!$this->dbManager->tableExists($this->tableName)) {
            // This check might be redundant if called from getAllTablesWithFields
            // but good for direct calls.
            return []; 
        }
        $stmt = $this->pdo->query("PRAGMA table_info(`{$this->tableName}`)");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CRUD Operations
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("No data provided for insert operation.");
        }
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sanitizedColumns = array_map(fn($col) => "`$col`", $columns);

        $sql = "INSERT INTO `{$this->tableName}` (" . implode(', ', $sanitizedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }

    public function select(array $criteria = []): array
    {
        $fields = $criteria['fields'] ?? ['*'];
        if (!is_array($fields)) $fields = explode(',', (string)$fields);
        $fields = array_map(fn($f) => $f === '*' ? '*' : "`".trim($f)."`", $fields);
        $selectClause = implode(', ', $fields);

        $sql = "SELECT {$selectClause} FROM `{$this->tableName}`";
        $params = [];

        // WHERE clause
        if (!empty($criteria['where']) && is_array($criteria['where'])) {
            $whereClauses = [];
            $i = 0;
            foreach ($criteria['where'] as $condition) {
                if (isset($condition['field'], $condition['operator'], $condition['value'])) {
                    // Sanitize field name
                    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $condition['field']);
                    if (empty($field)) continue;

                    // Validate operator against a whitelist
                    $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
                    $operator = strtoupper($condition['operator']);
                    if (!in_array($operator, $validOperators)) {
                        throw new \InvalidArgumentException("Invalid operator: {$operator}");
                    }

                    $placeholder = ":where_{$field}_{$i}";
                    
                    if (in_array($operator, ['IN', 'NOT IN'])) {
                        if (!is_array($condition['value']) || empty($condition['value'])) {
                            throw new \InvalidArgumentException("Value for IN/NOT IN operator must be a non-empty array.");
                        }
                        $inPlaceholders = [];
                        foreach ($condition['value'] as $key => $val) {
                            $inPlaceholder = "{$placeholder}_{$key}";
                            $inPlaceholders[] = $inPlaceholder;
                            $params[$inPlaceholder] = $val;
                        }
                        $whereClauses[] = "`{$field}` {$operator} (" . implode(', ', $inPlaceholders) . ")";
                    } else {
                        $whereClauses[] = "`{$field}` {$operator} {$placeholder}";
                        $params[$placeholder] = $condition['value'];
                    }
                    $i++;
                }
            }
            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(' AND ', $whereClauses); // Default to AND, can be extended
            }
        }
        
        // ORDER BY clause
        if (!empty($criteria['orderBy']) && is_array($criteria['orderBy'])) {
            $orderClauses = [];
            foreach ($criteria['orderBy'] as $order) {
                if (isset($order['field'])) {
                    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $order['field']);
                    if (empty($field)) continue;
                    $direction = (isset($order['direction']) && strtoupper($order['direction']) === 'DESC') ? 'DESC' : 'ASC';
                    $orderClauses[] = "`{$field}` {$direction}";
                }
            }
            if (!empty($orderClauses)) {
                $sql .= " ORDER BY " . implode(', ', $orderClauses);
            }
        }

        // LIMIT and OFFSET
        if (isset($criteria['limit'])) {
            $sql .= " LIMIT " . (int)$criteria['limit'];
            if (isset($criteria['offset'])) {
                $sql .= " OFFSET " . (int)$criteria['offset'];
            }
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Select failed: " . $e->getMessage() . " (SQL: {$sql})");
        }
    }

    public function update(array $data, array $whereConditions): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("No data provided for update operation.");
        }
        if (empty($whereConditions)) {
            throw new \InvalidArgumentException("WHERE clause is mandatory for update operation to prevent accidental full table update.");
        }

        $setClauses = [];
        $params = [];
        $i = 0;
        foreach ($data as $key => $value) {
            // Sanitize column name
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if (empty($col)) continue;

            $placeholder = ":set_{$col}_{$i}";
            $setClauses[] = "`{$col}` = {$placeholder}";
            $params[$placeholder] = $value;
            $i++;
        }
        if (empty($setClauses)) {
            throw new \InvalidArgumentException("No valid fields to update.");
        }

        $sql = "UPDATE `{$this->tableName}` SET " . implode(', ', $setClauses);

        $whereClauses = [];
        $j = 0;
        foreach ($whereConditions as $condition) {
             if (isset($condition['field'], $condition['operator'], $condition['value'])) {
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $condition['field']);
                if (empty($field)) continue;

                $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
                $operator = strtoupper($condition['operator']);
                if (!in_array($operator, $validOperators)) {
                    throw new \InvalidArgumentException("Invalid operator: {$operator}");
                }
                
                $placeholder = ":where_{$field}_{$j}";

                if (in_array($operator, ['IN', 'NOT IN'])) {
                    if (!is_array($condition['value']) || empty($condition['value'])) {
                         throw new \InvalidArgumentException("Value for IN/NOT IN operator must be a non-empty array.");
                    }
                    $inPlaceholders = [];
                    foreach ($condition['value'] as $key_in => $val_in) {
                        $inPlaceholder = "{$placeholder}_{$key_in}";
                        $inPlaceholders[] = $inPlaceholder;
                        $params[$inPlaceholder] = $val_in;
                    }
                    $whereClauses[] = "`{$field}` {$operator} (" . implode(', ', $inPlaceholders) . ")";
                } else {
                    $whereClauses[] = "`{$field}` {$operator} {$placeholder}";
                    $params[$placeholder] = $condition['value'];
                }
                $j++;
            }
        }
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        } else {
             throw new \InvalidArgumentException("WHERE clause resolved to empty, update aborted.");
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }

    public function delete(array $whereConditions): int
    {
        if (empty($whereConditions)) {
            throw new \InvalidArgumentException("WHERE clause is mandatory for delete operation to prevent accidental full table deletion.");
        }
        $sql = "DELETE FROM `{$this->tableName}`";
        $params = [];

        $whereClauses = [];
        $j = 0;
        foreach ($whereConditions as $condition) {
             if (isset($condition['field'], $condition['operator'], $condition['value'])) {
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $condition['field']);
                if (empty($field)) continue;

                $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
                $operator = strtoupper($condition['operator']);
                if (!in_array($operator, $validOperators)) {
                    throw new \InvalidArgumentException("Invalid operator: {$operator}");
                }

                $placeholder = ":where_{$field}_{$j}";
                if (in_array($operator, ['IN', 'NOT IN'])) {
                    if (!is_array($condition['value']) || empty($condition['value'])) {
                         throw new \InvalidArgumentException("Value for IN/NOT IN operator must be a non-empty array.");
                    }
                    $inPlaceholders = [];
                    foreach ($condition['value'] as $key_in => $val_in) {
                        $inPlaceholder = "{$placeholder}_{$key_in}";
                        $inPlaceholders[] = $inPlaceholder;
                        $params[$inPlaceholder] = $val_in;
                    }
                    $whereClauses[] = "`{$field}` {$operator} (" . implode(', ', $inPlaceholders) . ")";
                } else {
                    $whereClauses[] = "`{$field}` {$operator} {$placeholder}";
                    $params[$placeholder] = $condition['value'];
                }
                $j++;
            }
        }
         if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        } else {
             throw new \InvalidArgumentException("WHERE clause resolved to empty, delete aborted.");
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Delete failed: " . $e->getMessage());
        }
    }
}