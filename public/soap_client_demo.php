<?php
// If using Composer:
require_once __DIR__ . '/../vendor/autoload.php'; // For potential client-side classes if any

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define WSDL Cache options - important for production to disable for development
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('soap.wsdl_cache_ttl', '0');

$wsdl_url = 'http://localhost:8000/database_service.wsdl'; // Adjust if your server runs elsewhere
// Or, if running this script on the same server:
// $wsdl_file = __DIR__ . '/database_service.wsdl';


echo "<pre>"; // For better output formatting in browser

try {
    $options = [
        'trace' => 1, // Enable trace to view request/response XML
        'exceptions' => true, // Throw SoapFault exceptions on errors
        'cache_wsdl' => WSDL_CACHE_NONE, // No caching for dev
        'soap_version' => SOAP_1_1,
        // 'classmap' => [...] // If you have client-side classes to map to WSDL types
    ];

    // Create a SoapClient instance
    $client = new SoapClient($wsdl_url, $options); // Use $wsdl_file if accessing locally

    // --- 1. Create Database ---
    $dbName = 'soap_company_db';
    echo "<h3>1. Creating Database '{$dbName}'...</h3>";
    $response = $client->createDatabase($dbName);
    print_r($response); // Expected: object(stdClass)#... ( [message] => Database 'soap_company_db' created successfully. )
    echo "<hr>";

    // --- 2. Create Table ---
    $tableName = 'products';
    echo "<h3>2. Creating Table '{$tableName}' in '{$dbName}'...</h3>";
    $columns = new stdClass();
    $columns->column = [
        (object)['name' => 'id', 'type' => 'INTEGER', 'constraints' => 'PRIMARY KEY AUTOINCREMENT'],
        (object)['name' => 'product_name', 'type' => 'TEXT', 'constraints' => 'NOT NULL'],
        (object)['name' => 'category', 'type' => 'TEXT'],
        (object)['name' => 'price', 'type' => 'REAL']
    ];
    $response = $client->createTable($dbName, $tableName, $columns);
    print_r($response);
    echo "<hr>";

    // --- 3. Insert Record ---
    echo "<h3>3. Inserting Record into '{$tableName}'...</h3>";
    $productData = new stdClass();
    $productData->item = [
        (object)['key' => 'product_name', 'value' => 'Super Widget'],
        (object)['key' => 'category', 'value' => 'Gadgets'],
        (object)['key' => 'price', 'value' => 19.99]
    ];
    $response = $client->insertRecord($dbName, $tableName, $productData);
    print_r($response); // Expected: lastInsertId

    $productData2 = new stdClass();
    $productData2->item = [
        (object)['key' => 'product_name', 'value' => 'Mega Gizmo'],
        (object)['key' => 'category', 'value' => 'Electronics'],
        (object)['key' => 'price', 'value' => 149.50]
    ];
    $client->insertRecord($dbName, $tableName, $productData2);

    $productData3 = new stdClass();
    $productData3->item = [
        (object)['key' => 'product_name', 'value' => 'Basic Thingamajig'],
        (object)['key' => 'category', 'value' => 'Gadgets'],
        (object)['key' => 'price', 'value' => 9.75]
    ];
    $client->insertRecord($dbName, $tableName, $productData3);
    echo "<hr>";


    // --- 4. Select Records ---
    echo "<h3>4. Selecting Records from '{$tableName}' (Gadgets, ordered by price DESC)...</h3>";
    $criteria = new stdClass();
    $criteria->fields = new stdClass();
    $criteria->fields->string = ['id', 'product_name', 'price']; // Array of strings

    $criteria->where = new stdClass();
    $criteria->where->condition = (object)[ // Single condition
        'field' => 'category',
        'operator' => '=',
        'value' => 'Gadgets'
    ];

    $criteria->orderBy = new stdClass();
    $criteria->orderBy->clause = (object)[ // Single order clause
        'field' => 'price',
        'direction' => 'DESC'
    ];
    $criteria->limit = 5;

    $response = $client->selectRecords($dbName, $tableName, $criteria);
    echo "Selected Records:\n";
    print_r($response); // $response->records will be an array of rows
                       // each row will be an object with an 'item' property (array of KeyValue)
    echo "<hr>";

    // --- 5. Update Record ---
    echo "<h3>5. Updating 'Super Widget' price...</h3>";
    $updateData = new stdClass();
    $updateData->item = (object)['key' => 'price', 'value' => 21.50]; // Single item to update

    $updateWhere = new stdClass();
    $updateWhere->condition = (object)[
        'field' => 'product_name',
        'operator' => '=',
        'value' => 'Super Widget'
    ];
    $response = $client->updateRecords($dbName, $tableName, $updateData, $updateWhere);
    print_r($response); // affectedRows
    echo "<hr>";

    // --- 6. List All Tables with Fields ---
    echo "<h3>6. Listing all tables in '{$dbName}'...</h3>";
    $response = $client->listTables($dbName);
    print_r($response); // $response->tables will be array of TableSchema objects
    echo "<hr>";

    // --- 7. Get Table Schema for 'products' ---
    echo "<h3>7. Getting schema for table '{$tableName}'...</h3>";
    $response = $client->getTableSchema($dbName, $tableName);
    print_r($response); // $response->schema will be array of FieldInfo objects
    echo "<hr>";

    // --- 8. Backup Database ---
    echo "<h3>8. Backing up '{$dbName}'...</h3>";
    $response = $client->backupDatabase($dbName);
    print_r($response); // backupPath
    $backupFileName = basename($response->backupPath); // Extract filename for restore
    echo "Backup file: " . $backupFileName . "\n";
    echo "<hr>";

    // --- 9. Delete a Record ---
    echo "<h3>9. Deleting 'Basic Thingamajig'...</h3>";
    $deleteWhere = new stdClass();
    $deleteWhere->condition = (object)[
        'field' => 'product_name',
        'operator' => '=',
        'value' => 'Basic Thingamajig'
    ];
    $response = $client->deleteRecords($dbName, $tableName, $deleteWhere);
    print_r($response); // affectedRows
    echo "<hr>";

    // --- 10. (Demo) Delete the Original Database ---
    echo "<h3>10. Deleting original database '{$dbName}' for restore demo...</h3>";
    $response = $client->deleteDatabase($dbName);
    print_r($response);
    echo "<hr>";

    // --- 11. Restore Database ---
    if (!empty($backupFileName)) {
        echo "<h3>11. Restoring '{$dbName}' from backup '{$backupFileName}'...</h3>";
        $response = $client->restoreDatabase($dbName, $backupFileName);
        print_r($response);
        echo "<hr>";

        echo "<h3>Verifying restored table '{$tableName}'...</h3>";
        $verifyCriteria = new stdClass(); // Empty criteria to select all
        $response = $client->selectRecords($dbName, $tableName, $verifyCriteria);
        print_r($response);
        echo "<hr>";
    } else {
        echo "<h3>Skipping Restore: Backup filename not found.</h3>";
    }


    // --- 12. Delete Table ---
    echo "<h3>12. Deleting Table '{$tableName}'...</h3>";
    $response = $client->deleteTable($dbName, $tableName);
    print_r($response);
    echo "<hr>";

    // --- 13. Delete Database (Cleanup) ---
    echo "<h3>13. Deleting Database '{$dbName}' (Cleanup)...</h3>";
    $response = $client->deleteDatabase($dbName);
    print_r($response);
    echo "<hr>";


    // --- Demo SOAP Fault ---
    echo "<h3>Attempting to use a non-existent database (expecting SOAP Fault)...</h3>";
    try {
        $client->listTables("non_existent_db_for_fault");
    } catch (SoapFault $e) {
        echo "SOAP Fault Caught As Expected!\n";
        echo "Fault Code: " . $e->faultcode . "\n";
        echo "Fault String: " . $e->faultstring . "\n";
        if (isset($e->detail)) {
            echo "Detail: ";
            print_r($e->detail);
        }
        echo "\n--- Last Request Headers ---\n";
        echo htmlentities($client->__getLastRequestHeaders());
        echo "\n--- Last Request XML ---\n";
        echo htmlentities($client->__getLastRequest());
        echo "\n--- Last Response Headers ---\n";
        echo htmlentities($client->__getLastResponseHeaders());
        echo "\n--- Last Response XML ---\n";
        echo htmlentities($client->__getLastResponse());
    }
    echo "<hr>";


} catch (SoapFault $e) {
    echo "<h2>SOAP Fault:</h2>";
    echo "Fault Code: " . $e->faultcode . "<br>";
    echo "Fault String: " . $e->faultstring . "<br>";
    if (isset($e->detail)) {
        echo "Detail: <pre>"; print_r($e->detail); echo "</pre><br>";
    }
    echo "<h3>Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";

    if ($client ?? null) { // Check if client was initialized
        echo "<h3>Last Request Headers:</h3><pre>" . htmlentities($client->__getLastRequestHeaders()) . "</pre>";
        echo "<h3>Last Request XML:</h3><pre>" . htmlentities($client->__getLastRequest()) . "</pre>";
        echo "<h3>Last Response Headers:</h3><pre>" . htmlentities($client->__getLastResponseHeaders()) . "</pre>";
        echo "<h3>Last Response XML:</h3><pre>" . htmlentities($client->__getLastResponse()) . "</pre>";
    }

} catch (Exception $e) {
    echo "<h2>General Exception:</h2>";
    echo $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</pre>";