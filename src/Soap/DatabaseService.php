<?php
namespace App\Soap;

use App\Database\SQLiteManager;
use App\Database\TableManager;
use SoapFault;
use Exception; // Make sure Exception is imported if not in global namespace

class DatabaseService
{
    private function getDbManager(string $dbName): SQLiteManager
    {
        if (empty($dbName)) {
            throw new SoapFault("Client", "Database name (dbName) cannot be empty.");
        }
        $dbManager = new SQLiteManager();
        try {
            $dbManager->setDatabase($dbName);
            if (!$dbManager->getConnection()) {
                 throw new SoapFault("Server", "Database '{$dbName}' not found or connection failed.");
            }
        } catch (Exception $e) {
            throw new SoapFault("Server", "Error setting database '{$dbName}': " . $e->getMessage());
        }
        return $dbManager;
    }

    private function getTableManager(string $dbName, string $tableName): TableManager
    {
        $dbManager = $this->getDbManager($dbName);
        if (empty($tableName)) {
            throw new SoapFault("Client", "Table name (tableName) cannot be empty.");
        }
        try {
            return new TableManager($dbManager, $tableName);
        } catch (Exception $e) {
            throw new SoapFault("Server", "Error initializing table manager for '{$tableName}': " . $e->getMessage());
        }
    }

    // Helper to convert ArrayOfKeyValue to associative array
    private function convertKeyValueToArray($arrayOfKeyValue): array
    {
        $assocArray = [];
        if (isset($arrayOfKeyValue->item) && is_array($arrayOfKeyValue->item)) {
            foreach ($arrayOfKeyValue->item as $kv) {
                $assocArray[$kv->key] = $kv->value;
            }
        } elseif (isset($arrayOfKeyValue->item) && is_object($arrayOfKeyValue->item)) { // Single item
             $assocArray[$arrayOfKeyValue->item->key] = $arrayOfKeyValue->item->value;
        }
        // Handle if $arrayOfKeyValue is already the item itself (SoapServer sometimes does this for single element arrays)
        else if (is_object($arrayOfKeyValue) && isset($arrayOfKeyValue->key)) {
             $assocArray[$arrayOfKeyValue->key] = $arrayOfKeyValue->value;
        }
        return $assocArray;
    }

    // Helper to convert ArrayOfWhereCondition to criteria array
    private function convertWhereConditionsToArray($arrayOfWhereCondition): array
    {
        $conditions = [];
        if (isset($arrayOfWhereCondition->condition) && is_array($arrayOfWhereCondition->condition)) {
            foreach ($arrayOfWhereCondition->condition as $cond) {
                $conditions[] = ['field' => $cond->field, 'operator' => $cond->operator, 'value' => $cond->value];
            }
        } elseif (isset($arrayOfWhereCondition->condition) && is_object($arrayOfWhereCondition->condition)) { // Single condition
            $cond = $arrayOfWhereCondition->condition;
            $conditions[] = ['field' => $cond->field, 'operator' => $cond->operator, 'value' => $cond->value];
        }
        return $conditions;
    }


    /**
     * Creates a new SQLite database.
     * @param string $dbName The name of the database to create.
     * @return object An object with a 'message' property.
     * @throws SoapFault
     */
    public function createDatabase(string $dbName): object
    {
        try {
            $dbManager = new SQLiteManager(); // Don't connect immediately
            $dbManager->createDatabase($dbName);
            return (object)['message' => "Database '{$dbName}' created successfully."];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Deletes an SQLite database.
     * @param string $dbName The name of the database to delete.
     * @return object An object with a 'message' property.
     * @throws SoapFault
     */
    public function deleteDatabase(string $dbName): object
    {
        try {
            $dbManager = new SQLiteManager();
            $dbManager->deleteDatabase($dbName);
            return (object)['message' => "Database '{$dbName}' deleted successfully."];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Backs up an SQLite database.
     * @param string $dbName The name of the database to backup.
     * @return object An object with a 'backupPath' property.
     * @throws SoapFault
     */
    public function backupDatabase(string $dbName): object
    {
        try {
            $dbManager = $this->getDbManager($dbName);
            $path = $dbManager->backupDatabase($dbName);
            return (object)['backupPath' => $path];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Restores an SQLite database from a backup.
     * @param string $dbNameToRestore The name of the database to restore/overwrite.
     * @param string $backupFileName The name of the backup file.
     * @return object An object with a 'message' property.
     * @throws SoapFault
     */
    public function restoreDatabase(string $dbNameToRestore, string $backupFileName): object
    {
        try {
            $dbManager = new SQLiteManager(); // Don't connect initially
            $dbManager->restoreDatabase($dbNameToRestore, $backupFileName);
            return (object)['message' => "Database '{$dbNameToRestore}' restored successfully from '{$backupFileName}'."];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Lists all tables in the database with their fields.
     * @param string $dbName The name of the database.
     * @return object An object containing an 'tables' property (ArrayOfTableSchema).
     * @throws SoapFault
     */
    public function listTables(string $dbName): object
    {
        try {
            $dbManager = $this->getDbManager($dbName);
            $schemaArray = $dbManager->getAllTablesWithFields(); // This returns [tableName => [fields...]]

            $tablesForSoap = [];
            foreach ($schemaArray as $tableName => $fields) {
                $tableSchema = new \stdClass();
                $tableSchema->tableName = $tableName;
                $tableSchema->fields =  $fields; // Fields are already in the correct format from TableManager
                $tablesForSoap[] = $tableSchema;
            }
            return (object)['tables' => $tablesForSoap];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Creates a new table in the specified database.
     * @param string $dbName
     * @param string $tableName
     * @param object $columns (Represents ArrayOfColumnDefinition from WSDL)
     * @return object An object with a 'message' property.
     * @throws SoapFault
     */
    public function createTable(string $dbName, string $tableName, object $columns): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $colsDefinition = [];
            // $columns might be an object with a 'column' property which is an array, or just a single 'column' object
            $actualColumns = [];
            if (isset($columns->column)) {
                $actualColumns = is_array($columns->column) ? $columns->column : [$columns->column];
            }

            foreach ($actualColumns as $col) {
                $colsDefinition[] = [
                    'name' => $col->name,
                    'type' => $col->type,
                    'constraints' => $col->constraints ?? ''
                ];
            }
            $tableManager->createTable($colsDefinition);
            return (object)['message' => "Table '{$tableName}' created successfully."];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Deletes a table from the database.
     * @param string $dbName
     * @param string $tableName
     * @return object An object with a 'message' property.
     * @throws SoapFault
     */
    public function deleteTable(string $dbName, string $tableName): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $tableManager->deleteTable();
            return (object)['message' => "Table '{$tableName}' deleted successfully."];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Gets the schema (field definitions) for a specific table.
     * @param string $dbName
     * @param string $tableName
     * @return object An object with a 'schema' property (ArrayOfFieldInfo).
     * @throws SoapFault
     */
    public function getTableSchema(string $dbName, string $tableName): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $schema = $tableManager->getTableSchema();
            return (object)['schema' => $schema];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }


    /**
     * Inserts a new record into the specified table.
     * @param string $dbName
     * @param string $tableName
     * @param object $data (Represents ArrayOfKeyValue from WSDL)
     * @return object An object with a 'lastInsertId' property.
     * @throws SoapFault
     */
    public function insertRecord(string $dbName, string $tableName, object $data): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $assocData = $this->convertKeyValueToArray($data);
            if (empty($assocData)) {
                 throw new SoapFault("Client", "No data provided for insert operation.");
            }
            $lastId = $tableManager->insert($assocData);
            return (object)['lastInsertId' => $lastId];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Selects records from a table based on criteria.
     * @param string $dbName
     * @param string $tableName
     * @param object $criteria (Represents SelectionCriteria from WSDL)
     * @return object An object with a 'records' property (ArrayOfArrayOfKeyValue).
     * @throws SoapFault
     */
    public function selectRecords(string $dbName, string $tableName, object $criteria): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $critArray = [];

            if (isset($criteria->fields->string)) {
                $critArray['fields'] = is_array($criteria->fields->string) ? $criteria->fields->string : [$criteria->fields->string];
            }
            if (isset($criteria->where)) {
                $critArray['where'] = $this->convertWhereConditionsToArray($criteria->where);
            }
            if (isset($criteria->orderBy->clause)) {
                $orderByClauses = is_array($criteria->orderBy->clause) ? $criteria->orderBy->clause : [$criteria->orderBy->clause];
                foreach ($orderByClauses as $clause) {
                    $critArray['orderBy'][] = ['field' => $clause->field, 'direction' => $clause->direction];
                }
            }
            if (isset($criteria->limit)) {
                $critArray['limit'] = (int)$criteria->limit;
            }
            if (isset($criteria->offset)) {
                $critArray['offset'] = (int)$criteria->offset;
            }

            $results = $tableManager->select($critArray);

            // Convert results to ArrayOfArrayOfKeyValue
            $soapResults = [];
            foreach ($results as $row) {
                $soapRowItems = [];
                foreach ($row as $key => $value) {
                    $kv = new \stdClass();
                    $kv->key = $key;
                    $kv->value = $value;
                    $soapRowItems[] = $kv;
                }
                // WSDL expects <row><item>...</item><item>...</item></row>
                // So we need an object that has an 'item' property which is an array of KeyValue
                $soapRow = new \stdClass();
                $soapRow->item = $soapRowItems;
                $soapResults[] = $soapRow;
            }

            return (object)['records' => $soapResults];
        } catch (Exception $e) {
            throw new SoapFault("Server", "Select Error: " . $e->getMessage());
        }
    }

    /**
     * Updates records in a table.
     * @param string $dbName
     * @param string $tableName
     * @param object $data (ArrayOfKeyValue)
     * @param object $where (ArrayOfWhereCondition)
     * @return object An object with an 'affectedRows' property.
     * @throws SoapFault
     */
    public function updateRecords(string $dbName, string $tableName, object $data, object $where): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $assocData = $this->convertKeyValueToArray($data);
            $whereConditions = $this->convertWhereConditionsToArray($where);

            if (empty($assocData)) throw new SoapFault("Client", "No data provided for update.");
            if (empty($whereConditions)) throw new SoapFault("Client", "WHERE clause is mandatory for update.");

            $affected = $tableManager->update($assocData, $whereConditions);
            return (object)['affectedRows' => $affected];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }

    /**
     * Deletes records from a table.
     * @param string $dbName
     * @param string $tableName
     * @param object $where (ArrayOfWhereCondition)
     * @return object An object with an 'affectedRows' property.
     * @throws SoapFault
     */
    public function deleteRecords(string $dbName, string $tableName, object $where): object
    {
        try {
            $tableManager = $this->getTableManager($dbName, $tableName);
            $whereConditions = $this->convertWhereConditionsToArray($where);
            if (empty($whereConditions)) throw new SoapFault("Client", "WHERE clause is mandatory for delete.");

            $affected = $tableManager->delete($whereConditions);
            return (object)['affectedRows' => $affected];
        } catch (Exception $e) {
            throw new SoapFault("Server", $e->getMessage());
        }
    }
}