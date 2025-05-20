<?php
namespace App\Api;

use App\Database\SQLiteManager;
use App\Database\TableManager;
use App\Http\Request;
use App\Http\Response;
use Exception;

class ApiController
{
    private Request $request;
    private ?SQLiteManager $dbManager = null; // Nullable initially

    public function __construct(Request $request)
    {
        $this->request = $request;
        // dbName might not be present for all requests (e.g. list_databases)
        // or could be part of the payload for create_database
        $dbName = $this->request->get('dbName'); 
        if ($dbName) {
            $this->dbManager = new SQLiteManager(); // Initialize without db
            try {
                $this->dbManager->setDatabase($dbName); // Then set it, which connects
            } catch (Exception $e) {
                // If DB doesn't exist, it's fine for actions like 'create_database'
                // For other actions, this will be an issue handled by individual methods.
                // We don't throw an error here globally.
            }
        } else {
             $this->dbManager = new SQLiteManager(); // Initialize without db
        }
    }

    public function handleRequest()
    {
        $action = $this->request->get('action');
        $payload = $this->request->get('payload', []);

        try {
            switch ($action) {
                // Database Operations
                case 'create_database':
                    $dbName = $payload['dbName'] ?? null;
                    if (!$dbName) Response::error("Missing 'dbName' in payload for create_database.");
                    $this->dbManager->createDatabase($dbName);
                    Response::success(null, "Database '{$dbName}' created successfully.");
                    break;

                case 'delete_database':
                    $dbName = $payload['dbName'] ?? null;
                    if (!$dbName) Response::error("Missing 'dbName' in payload for delete_database.");
                    $this->dbManager->deleteDatabase($dbName);
                    Response::success(null, "Database '{$dbName}' deleted successfully.");
                    break;

                case 'backup_database':
                    if (!$this->dbManager || !$this->dbManager->getDbName()) Response::error("Database not selected. Provide 'dbName' in main request or payload.");
                    $backupPath = $this->dbManager->backupDatabase($this->dbManager->getDbName());
                    Response::success(['backup_path' => $backupPath], "Database '{$this->dbManager->getDbName()}' backed up successfully.");
                    break;

                case 'restore_database':
                    $backupFileName = $payload['backupFileName'] ?? null;
                    $dbToRestore = $payload['dbNameToRestore'] ?? ($this->dbManager ? $this->dbManager->getDbName() : null);
                    if (!$dbToRestore) Response::error("Missing 'dbNameToRestore' or current 'dbName'.");
                    if (!$backupFileName) Response::error("Missing 'backupFileName' in payload.");
                    $this->dbManager->restoreDatabase($dbToRestore, $backupFileName);
                    Response::success(null, "Database '{$dbToRestore}' restored successfully from '{$backupFileName}'.");
                    break;

                case 'list_tables':
                    if (!$this->dbManager || !$this->dbManager->getConnection()) Response::error("Database '{$this->request->get('dbName')}' not found or not connected.");
                    $tables = $this->dbManager->getAllTablesWithFields();
                    Response::success($tables);
                    break;

                // Table Operations
                case 'create_table':
                case 'delete_table':
                case 'get_table_schema':
                case 'insert_record':
                case 'select_records':
                case 'update_records':
                case 'delete_records':
                    $this->handleTableOperation($action, $payload);
                    break;

                default:
                    Response::error('Invalid action specified.', null, 404);
            }
        } catch (Exception $e) {
            Response::error($e->getMessage(), ['trace' => $e->getTraceAsString()], 500); // Show trace for debugging
        }
    }

    private function handleTableOperation(string $action, array $payload)
    {
        if (!$this->dbManager || !$this->dbManager->getConnection()) {
            Response::error("Database '{$this->request->get('dbName')}' not found or not connected for table operation.");
        }

        $tableName = $payload['tableName'] ?? null;
        if (!$tableName) Response::error("Missing 'tableName' in payload for table operation '{$action}'.");

        $tableManager = new TableManager($this->dbManager, $tableName);

        switch ($action) {
            case 'create_table':
                $columns = $payload['columns'] ?? null;
                if (!$columns || !is_array($columns)) Response::error("Missing or invalid 'columns' definition in payload.");
                $tableManager->createTable($columns);
                Response::success(null, "Table '{$tableName}' created successfully.");
                break;

            case 'delete_table':
                $tableManager->deleteTable();
                Response::success(null, "Table '{$tableName}' deleted successfully.");
                break;
            
            case 'get_table_schema':
                $schema = $tableManager->getTableSchema();
                 Response::success($schema, "Schema for table '{$tableName}'.");
                break;

            case 'insert_record':
                $data = $payload['data'] ?? null;
                if (!$data || !is_array($data)) Response::error("Missing or invalid 'data' in payload for insert.");
                $lastId = $tableManager->insert($data);
                Response::success(['last_insert_id' => $lastId], "Record inserted into '{$tableName}'.");
                break;

            case 'select_records':
                $criteria = $payload['criteria'] ?? [];
                $records = $tableManager->select($criteria);
                Response::success($records, "Records selected from '{$tableName}'.");
                break;

            case 'update_records':
                $data = $payload['data'] ?? null;
                $where = $payload['where'] ?? null;
                if (!$data || !is_array($data)) Response::error("Missing or invalid 'data' in payload for update.");
                if (!$where || !is_array($where)) Response::error("Missing or invalid 'where' conditions in payload for update.");
                $affectedRows = $tableManager->update($data, $where);
                Response::success(['affected_rows' => $affectedRows], "{$affectedRows} record(s) updated in '{$tableName}'.");
                break;

            case 'delete_records':
                $where = $payload['where'] ?? null;
                if (!$where || !is_array($where)) Response::error("Missing or invalid 'where' conditions in payload for delete.");
                $affectedRows = $tableManager->delete($where);
                Response::success(['affected_rows' => $affectedRows], "{$affectedRows} record(s) deleted from '{$tableName}'.");
                break;
        }
    }
}