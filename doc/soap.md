```markdown
# SQLite SOAP API Handler - Deployment and Usage Documentation

## Table of Contents

1.  [Introduction](#introduction)
2.  [Project Structure](#project-structure)
3.  [Prerequisites](#prerequisites)
4.  [Deployment Steps](#deployment-steps)
    1.  [Clone/Download Project](#clonedownload-project)
    2.  [Install Dependencies (Composer)](#install-dependencies-composer)
    3.  [Configure WSDL](#configure-wsdl)
    4.  [Set Directory Permissions](#set-directory-permissions)
    5.  [Web Server Configuration](#web-server-configuration)
        *   [Using PHP Built-in Server (Development)](#using-php-built-in-server-development)
        *   [Using Apache (Production/Staging)](#using-apache-productionstaging)
        *   [Using Nginx (Production/Staging)](#using-nginx-productionstaging)
6.  [API Endpoint](#api-endpoint)
7.  [WSDL Location](#wsdl-location)
8.  [API Operations and Usage (Client-Side Examples)](#api-operations-and-usage-client-side-examples)
    1.  [Client Setup](#client-setup)
    2.  [Database Operations](#database-operations)
        *   [Create Database](#create-database)
        *   [Delete Database](#delete-database)
        *   [Backup Database](#backup-database)
        *   [Restore Database](#restore-database)
    3.  [Schema Operations](#schema-operations)
        *   [List All Tables with Fields](#list-all-tables-with-fields)
        *   [Create Table](#create-table)
        *   [Delete Table](#delete-table)
        *   [Get Table Schema](#get-table-schema)
    4.  [Data (CRUD) Operations](#data-crud-operations)
        *   [Insert Record](#insert-record)
        *   [Select Records (Query)](#select-records-query)
        *   [Update Records](#update-records)
        *   [Delete Records](#delete-records)
    5.  [Error Handling](#error-handling)
9.  [Troubleshooting](#troubleshooting)

---

## 1. Introduction

This document provides instructions for deploying and using the SQLite SOAP API Handler. This service allows interaction with SQLite databases through a SOAP (Simple Object Access Protocol) interface. It supports operations such as database creation/deletion, table management, CRUD operations, backups, and restores.

---

## 2. Project Structure

```
sqlite_soap_handler/
├── src/
│   ├── Database/
│   │   ├── SQLiteManager.php
│   │   └── TableManager.php
│   └── Soap/
│       └── DatabaseService.php
├── public/
│   ├── database_service.wsdl
│   ├── soap_server.php         # SOAP Server Endpoint
│   └── soap_client_demo.php    # Example Client
├── databases/                    # Stores .sqlite files (writable)
├── backups/                      # Stores database backups (writable)
└── composer.json
└── README.md
```

*   **`src/`**: Contains the core PHP classes for database management and the SOAP service logic.
*   **`public/`**: Contains the web-accessible files: WSDL, SOAP server script, and a demo client. This directory should be your web server's document root.
*   **`databases/`**: Directory where SQLite database files will be created and stored.
*   **`backups/`**: Directory where database backups will be stored.
*   **`composer.json`**: Defines project dependencies (primarily for PSR-4 autoloading).

---

## 3. Prerequisites

*   PHP 7.4 or higher.
*   PHP `soap` extension enabled. (Check with `php -m | grep soap` or in `php.ini`).
*   PHP `sqlite3` extension enabled. (Check with `php -m | grep sqlite` or in `php.ini`).
*   Composer (for dependency management and autoloading - recommended).
*   A web server (Apache, Nginx, or PHP's built-in server for development).
*   Write permissions for the web server user on the `databases/` and `backups/` directories.

---

## 4. Deployment Steps

### 4.1. Clone/Download Project

Obtain the project files, either by cloning a Git repository or downloading a ZIP archive and extracting it to your server.

```bash
# Example using Git
git clone <repository_url> sqlite_soap_handler
cd sqlite_soap_handler
```

### 4.2. Install Dependencies (Composer)

If you have Composer installed, navigate to the project root directory (`sqlite_soap_handler`) and run:

```bash
composer install
```
This will install any defined dependencies and set up the PSR-4 autoloader specified in `composer.json`, creating a `vendor/autoload.php` file.

If you are not using Composer, you will need to manually include the necessary class files using `require_once` at the beginning of `public/soap_server.php` and `public/soap_client_demo.php`.

### 4.3. Configure WSDL

The WSDL file (`public/database_service.wsdl`) contains the URL of your SOAP server endpoint. This needs to be correctly set.

1.  Open `public/database_service.wsdl`.
2.  Locate the `<service>` tag near the end of the file:
    ```xml
    <service name="DatabaseService">
        <port name="DatabaseServicePort" binding="tns:DatabaseServiceBinding">
            <soap:address location="REPLACE_WITH_YOUR_SOAP_SERVER_URL/soap_server.php"/>
        </port>
    </service>
    ```
3.  Replace `REPLACE_WITH_YOUR_SOAP_SERVER_URL/soap_server.php` with the actual, publicly accessible URL of your `soap_server.php` script.
    *   **Example (PHP built-in server):** `http://localhost:8000/soap_server.php`
    *   **Example (Apache/Nginx):** `http://yourdomain.com/path/to/project/public/soap_server.php` or simply `http://yourdomain.com/soap_server.php` if `public` is the document root.

### 4.4. Set Directory Permissions

The web server process needs write access to the `databases/` and `backups/` directories.

Navigate to the project root (`sqlite_soap_handler`) and run:

```bash
# Create directories if they don't exist
mkdir -p databases backups

# Set permissions
# Replace www-data:www-data with your web server's user and group if different (e.g., apache:apache)
sudo chown -R www-data:www-data databases backups
sudo chmod -R 775 databases backups
```
If `775` does not work due to strict server configurations, you might temporarily use `777` for testing, but this is not recommended for production.

### 4.5. Web Server Configuration

#### 4.5.1. Using PHP Built-in Server (Development)

For development purposes, PHP's built-in web server is convenient.

1.  Navigate to the project root directory (`sqlite_soap_handler`) in your terminal.
2.  Start the server:
    ```bash
    php -S localhost:8000 -t public
    ```
    *   This makes your application accessible at `http://localhost:8000`.
    *   The `-t public` option sets the `public/` directory as the document root.
    *   Your SOAP server endpoint will be `http://localhost:8000/soap_server.php`.
    *   Your WSDL will be accessible at `http://localhost:8000/database_service.wsdl`.

#### 4.5.2. Using Apache (Production/Staging)

1.  Ensure Apache's `mod_rewrite` is enabled.
2.  Configure a Virtual Host for your application, pointing the `DocumentRoot` to the `public/` directory of your project.

    Example Apache Virtual Host configuration (`/etc/apache2/sites-available/sqlite-soap-api.conf`):
    ```apache
    <VirtualHost *:80>
        ServerName yourdomain.com # Or your server's IP/hostname
        DocumentRoot /var/www/html/sqlite_soap_handler/public

        <Directory /var/www/html/sqlite_soap_handler/public>
            Options Indexes FollowSymLinks
            AllowOverride All # Or at least FileInfo for .htaccess if used
            Require all granted
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/sqlite_soap_api_error.log
        CustomLog ${APACHE_LOG_DIR}/sqlite_soap_api_access.log combined
    </VirtualHost>
    ```
3.  Enable the site and restart Apache:
    ```bash
    sudo a2ensite sqlite-soap-api.conf
    sudo systemctl reload apache2 # or service apache2 reload
    ```

#### 4.5.3. Using Nginx (Production/Staging)

1.  Configure an Nginx server block, setting the `root` to the `public/` directory and ensuring PHP requests are passed to PHP-FPM.

    Example Nginx server block configuration (`/etc/nginx/sites-available/sqlite-soap-api`):
    ```nginx
    server {
        listen 80;
        server_name yourdomain.com; # Or your server's IP/hostname
        root /var/www/html/sqlite_soap_handler/public;

        index soap_server.php index.php index.html index.htm; # soap_server.php not typically an index

        location / {
            try_files $uri $uri/ /soap_server.php?$query_string; # Adjust if not using pretty URLs
        }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Adjust PHP version and FPM socket path
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\.ht {
            deny all;
        }
    }
    ```
2.  Enable the site and restart Nginx:
    ```bash
    sudo ln -s /etc/nginx/sites-available/sqlite-soap-api /etc/nginx/sites-enabled/
    sudo systemctl reload nginx # or service nginx reload
    ```

---

## 5. API Endpoint

The primary SOAP server endpoint is:
`http://<your_server_address_and_path>/soap_server.php`

Example: `http://localhost:8000/soap_server.php`

---

## 6. WSDL Location

The WSDL (Web Services Description Language) file, which describes the service's operations and data types, is located at:
`http://<your_server_address_and_path>/database_service.wsdl`

Example: `http://localhost:8000/database_service.wsdl`

SOAP clients will use this WSDL URL to understand how to interact with the service.

---

## 7. API Operations and Usage (Client-Side Examples)

The following examples demonstrate how to use a PHP SOAP client to interact with the API. Refer to `public/soap_client_demo.php` for a runnable demo.

### 7.1. Client Setup

```php
<?php
// Ensure autoloader if you have client-side classes or use Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Disable WSDL caching for development
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('soap.wsdl_cache_ttl', '0');

$wsdl_url = 'http://localhost:8000/database_service.wsdl'; // << ADJUST THIS URL

try {
    $client = new SoapClient($wsdl_url, [
        'trace' => 1, // Enable for debugging request/response XML
        'exceptions' => true, // Throw SoapFault exceptions
        'cache_wsdl' => WSDL_CACHE_NONE,
        'soap_version' => SOAP_1_1, // Or SOAP_1_2, ensure it matches server
    ]);

    // ... API calls will go here ...

} catch (SoapFault $e) {
    echo "SOAP Fault: (faultcode: {$e->faultcode}, faultstring: {$e->faultstring})\n";
    if (isset($e->detail)) { print_r($e->detail); }
    // For debugging:
    // echo "Last Request:\n" . htmlentities($client->__getLastRequest()) . "\n";
    // echo "Last Response:\n" . htmlentities($client->__getLastResponse()) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

### 7.2. Database Operations

#### Create Database

*   **Operation:** `createDatabase`
*   **Parameters:**
    *   `dbName` (string): The name of the database to create (e.g., "my_data").
*   **Returns:** Object with a `message` property (string).

```php
$dbName = 'example_db';
$response = $client->createDatabase($dbName);
echo $response->message . "\n";
// Output: Database 'example_db' created successfully.
```

#### Delete Database

*   **Operation:** `deleteDatabase`
*   **Parameters:**
    *   `dbName` (string): The name of the database to delete.
*   **Returns:** Object with a `message` property (string).

```php
$dbName = 'example_db_to_delete';
// $client->createDatabase($dbName); // First create it if it doesn't exist for the demo
$response = $client->deleteDatabase($dbName);
echo $response->message . "\n";
// Output: Database 'example_db_to_delete' deleted successfully.
```

#### Backup Database

*   **Operation:** `backupDatabase`
*   **Parameters:**
    *   `dbName` (string): The name of the database to back up.
*   **Returns:** Object with a `backupPath` property (string) containing the full server path to the backup file.

```php
$dbName = 'example_db'; // Assume this DB exists
// $client->createDatabase($dbName); // Create if needed for demo
$response = $client->backupDatabase($dbName);
echo "Backup created at: " . $response->backupPath . "\n";
$backupFileName = basename($response->backupPath); // e.g., example_db_backup_YYYYMMDDHHMMSS.sqlite
```

#### Restore Database

*   **Operation:** `restoreDatabase`
*   **Parameters:**
    *   `dbNameToRestore` (string): The name of the database to restore (will be created if it doesn't exist, or overwritten).
    *   `backupFileName` (string): The filename of the backup (e.g., `example_db_backup_YYYYMMDDHHMMSS.sqlite`). This file must exist in the `backups/` directory on the server.
*   **Returns:** Object with a `message` property (string).

```php
$dbNameToRestore = 'restored_example_db';
// $backupFileName was obtained from the backupDatabase step
if (isset($backupFileName)) {
    $response = $client->restoreDatabase($dbNameToRestore, $backupFileName);
    echo $response->message . "\n";
    // Output: Database 'restored_example_db' restored successfully from '...'
}
```

### 7.3. Schema Operations

#### List All Tables with Fields

*   **Operation:** `listTables`
*   **Parameters:**
    *   `dbName` (string): The name of the database.
*   **Returns:** Object with a `tables` property. `tables` is an array of `TableSchema` objects. Each `TableSchema` object has:
    *   `tableName` (string)
    *   `fields` (array): An array of `FieldInfo` objects, where each `FieldInfo` has `cid`, `name`, `type`, `notnull`, `dflt_value`, `pk`.

```php
$dbName = 'example_db'; // Assume this DB exists and has tables
$response = $client->listTables($dbName);
if (isset($response->tables) && is_array($response->tables)) {
    foreach ($response->tables as $tableSchema) {
        echo "Table: " . $tableSchema->tableName . "\n";
        if (isset($tableSchema->fields) && is_array($tableSchema->fields)) {
            foreach ($tableSchema->fields as $field) {
                echo "  - {$field->name} ({$field->type})\n";
            }
        } else if (isset($tableSchema->fields) && is_object($tableSchema->fields)) { // Single field case
            echo "  - {$tableSchema->fields->name} ({$tableSchema->fields->type})\n";
        }
    }
} elseif (isset($response->tables) && is_object($response->tables)) { // Single table case
    echo "Table: " . $response->tables->tableName . "\n";
    // ... similar logic for fields ...
}
```

#### Create Table

*   **Operation:** `createTable`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
    *   `columns` (object): Represents `ArrayOfColumnDefinition`. Should be an object with a `column` property, which is an array of `ColumnDefinition` objects. Each `ColumnDefinition` object has:
        *   `name` (string)
        *   `type` (string) (e.g., "INTEGER", "TEXT", "REAL")
        *   `constraints` (string, optional) (e.g., "PRIMARY KEY AUTOINCREMENT", "NOT NULL UNIQUE")
*   **Returns:** Object with a `message` property (string).

```php
$dbName = 'example_db';
$tableName = 'users';
$columnsParam = new stdClass();
$columnsParam->column = [
    (object)['name' => 'id', 'type' => 'INTEGER', 'constraints' => 'PRIMARY KEY AUTOINCREMENT'],
    (object)['name' => 'username', 'type' => 'TEXT', 'constraints' => 'NOT NULL UNIQUE'],
    (object)['name' => 'email', 'type' => 'TEXT']
];
$response = $client->createTable($dbName, $tableName, $columnsParam);
echo $response->message . "\n";
// Output: Table 'users' created successfully.
```

#### Delete Table

*   **Operation:** `deleteTable`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
*   **Returns:** Object with a `message` property (string).

```php
$dbName = 'example_db';
$tableName = 'users_to_delete';
// $client->createTable($dbName, $tableName, $columnsParam); // Create if needed for demo
$response = $client->deleteTable($dbName, $tableName);
echo $response->message . "\n";
// Output: Table 'users_to_delete' deleted successfully.
```

#### Get Table Schema

*   **Operation:** `getTableSchema`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
*   **Returns:** Object with a `schema` property. `schema` is an array of `FieldInfo` objects (see `listTables` for `FieldInfo` structure).

```php
$dbName = 'example_db';
$tableName = 'users'; // Assume this table exists
$response = $client->getTableSchema($dbName, $tableName);
echo "Schema for table '{$tableName}':\n";
if (isset($response->schema) && is_array($response->schema)) {
    foreach ($response->schema as $field) {
        echo "  - {$field->name} ({$field->type}, PK: {$field->pk})\n";
    }
} elseif (isset($response->schema) && is_object($response->schema)) { // Single field case
    $field = $response->schema;
    echo "  - {$field->name} ({$field->type}, PK: {$field->pk})\n";
}
```

### 7.4. Data (CRUD) Operations

#### Insert Record

*   **Operation:** `insertRecord`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
    *   `data` (object): Represents `ArrayOfKeyValue`. An object with an `item` property, which is an array of `KeyValue` objects. Each `KeyValue` object has:
        *   `key` (string): Column name.
        *   `value` (mixed): Value for the column.
*   **Returns:** Object with a `lastInsertId` property (int).

```php
$dbName = 'example_db';
$tableName = 'users';
$userData = new stdClass();
$userData->item = [
    (object)['key' => 'username', 'value' => 'john_doe'],
    (object)['key' => 'email', 'value' => 'john.doe@example.com']
];
$response = $client->insertRecord($dbName, $tableName, $userData);
echo "Inserted record with ID: " . $response->lastInsertId . "\n";
```

#### Select Records (Query)

*   **Operation:** `selectRecords`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
    *   `criteria` (object): Represents `SelectionCriteria`. An object that can have the following optional properties:
        *   `fields` (object): `ArrayOfString`. Object with a `string` property (array of column names). If omitted, selects all (`*`).
        *   `where` (object): `ArrayOfWhereCondition`. Object with a `condition` property (array of `WhereCondition` objects). Each `WhereCondition` has:
            *   `field` (string): Column name.
            *   `operator` (string): e.g., "=", ">", "<", "LIKE", "IN".
            *   `value` (mixed): Value for comparison. For "IN", this should be an array.
        *   `orderBy` (object): `ArrayOfOrderByClause`. Object with a `clause` property (array of `OrderByClause` objects). Each `OrderByClause` has:
            *   `field` (string): Column name.
            *   `direction` (string): "ASC" or "DESC".
        *   `limit` (int, optional)
        *   `offset` (int, optional)
*   **Returns:** Object with a `records` property. `records` is an array of `ArrayOfKeyValue` objects (rows). Each row object has an `item` property which is an array of `KeyValue` objects (columns).

```php
$dbName = 'example_db';
$tableName = 'users';

$criteria = new stdClass();

// Specifying fields
$criteria->fields = new stdClass();
$criteria->fields->string = ['id', 'username'];

// Adding a WHERE condition
$criteria->where = new stdClass();
$criteria->where->condition = (object)[ // Single condition
    'field' => 'username',
    'operator' => 'LIKE',
    'value' => 'john%'
];
// For multiple WHERE conditions (ANDed together):
// $criteria->where->condition = [
//     (object)['field' => 'username', 'operator' => '=', 'value' => 'john_doe'],
//     (object)['field' => 'status', 'operator' => '=', 'value' => 'active']
// ];

// Adding ORDER BY
$criteria->orderBy = new stdClass();
$criteria->orderBy->clause = (object)[ // Single order clause
    'field' => 'id',
    'direction' => 'DESC'
];

$criteria->limit = 10;

$response = $client->selectRecords($dbName, $tableName, $criteria);

echo "Selected Records:\n";
if (isset($response->records) && (is_array($response->records) || is_object($response->records))) {
    $recordsToIterate = is_array($response->records) ? $response->records : [$response->records];
    foreach ($recordsToIterate as $rowObj) {
        $rowData = [];
        if (isset($rowObj->item) && (is_array($rowObj->item) || is_object($rowObj->item))) {
             $itemsToIterate = is_array($rowObj->item) ? $rowObj->item : [$rowObj->item];
            foreach ($itemsToIterate as $kv) {
                $rowData[$kv->key] = $kv->value;
            }
            print_r($rowData);
        }
    }
}
```

#### Update Records

*   **Operation:** `updateRecords`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
    *   `data` (object): `ArrayOfKeyValue` (see `insertRecord`) for columns to update.
    *   `where` (object): `ArrayOfWhereCondition` (see `selectRecords`) to specify which rows to update.
*   **Returns:** Object with an `affectedRows` property (int).

```php
$dbName = 'example_db';
$tableName = 'users';

$updateData = new stdClass();
$updateData->item = (object)['key' => 'email', 'value' => 'john.doe.updated@example.com'];

$updateWhere = new stdClass();
$updateWhere->condition = (object)[
    'field' => 'username',
    'operator' => '=',
    'value' => 'john_doe'
];

$response = $client->updateRecords($dbName, $tableName, $updateData, $updateWhere);
echo "Rows updated: " . $response->affectedRows . "\n";
```

#### Delete Records

*   **Operation:** `deleteRecords`
*   **Parameters:**
    *   `dbName` (string)
    *   `tableName` (string)
    *   `where` (object): `ArrayOfWhereCondition` (see `selectRecords`) to specify which rows to delete.
*   **Returns:** Object with an `affectedRows` property (int).

```php
$dbName = 'example_db';
$tableName = 'users';

$deleteWhere = new stdClass();
$deleteWhere->condition = (object)[
    'field' => 'username',
    'operator' => '=',
    'value' => 'user_to_delete'
];
// $client->insertRecord($dbName, $tableName, ...); // Insert 'user_to_delete' first for demo

$response = $client->deleteRecords($dbName, $tableName, $deleteWhere);
echo "Rows deleted: " . $response->affectedRows . "\n";
```

### 7.5. Error Handling

SOAP errors will be thrown as `SoapFault` exceptions if the `exceptions` option is true in the `SoapClient` constructor.

```php
try {
    // Attempt an operation that might fail, e.g., on a non-existent database
    $response = $client->listTables("non_existent_db");
} catch (SoapFault $e) {
    echo "SOAP Fault Occurred!\n";
    echo "Code: " . $e->faultcode . "\n";
    echo "Message: " . $e->faultstring . "\n";
    if (isset($e->detail)) {
        echo "Details: ";
        print_r($e->detail); // Can contain more specific error info from the server
    }
    // For debugging, if trace was enabled on SoapClient:
    // echo "Last Request Headers:\n" . $client->__getLastRequestHeaders() . "\n";
    // echo "Last Request XML:\n" . htmlentities($client->__getLastRequest()) . "\n";
    // echo "Last Response Headers:\n" . $client->__getLastResponseHeaders() . "\n";
    // echo "Last Response XML:\n" . htmlentities($client->__getLastResponse()) . "\n";
}
```

---

## 9. Troubleshooting

*   **"Could not connect to host" / "failed to load external entity":**
    *   Ensure the WSDL URL in your `SoapClient` and the `<soap:address location="...">` in `database_service.wsdl` are correct and accessible.
    *   Check if the PHP development server or your web server (Apache/Nginx) is running.
    *   Firewall issues might be blocking access to the server port.
*   **"Class 'App\\Soap\\DatabaseService' not found":**
    *   If using Composer, ensure `composer install` was run and `vendor/autoload.php` is included in `public/soap_server.php`.
    *   If not using Composer, check your manual `require_once` statements.
    *   Verify namespace and class names match exactly.
*   **"Procedure '...' not present":**
    *   The method name called by the client does not match a public method in `src/Soap/DatabaseService.php` or is not defined in the WSDL.
    *   Ensure WSDL caching is disabled during development (`ini_set('soap.wsdl_cache_enabled', '0');` in both server and client).
*   **Permissions Errors on `databases/` or `backups/`:**
    *   The SOAP server (running as the web server user) will log errors, but the client might receive a generic server error. Check your web server's error logs (e.g., `/var/log/apache2/error.log` or `/var/log/nginx/error.log`).
    *   Re-verify permissions as per [Section 4.4](#set-directory-permissions).
*   **SOAP Faults with vague messages:**
    *   Enable tracing in the `SoapClient` (`'trace' => 1`).
    *   Inspect `__getLastRequest()` and `__getLastResponse()` to see the raw XML messages, which can provide clues.
    *   Check server-side PHP error logs and web server error logs for more detailed error messages from the `DatabaseService.php` or underlying managers.
*   **Type Mismatches / "Could not find type":**
    *   Ensure your WSDL (`<types>` section) correctly defines all complex types used by your operations.
    *   When calling client methods, ensure the structure of your PHP objects/arrays matches what the WSDL expects (e.g., for `ArrayOfKeyValue`, `SelectionCriteria`).
*   **WSDL Caching:** During development, WSDL caching can cause issues if you modify the WSDL and PHP doesn't pick up the changes. Ensure it's disabled:
    ```php
    ini_set('soap.wsdl_cache_enabled', '0');
    ini_set('soap.wsdl_cache_ttl', '0');
    // For SoapClient options:
    'cache_wsdl' => WSDL_CACHE_NONE,
    ```

---
This comprehensive documentation should guide users through deploying and utilizing your SQLite SOAP API.
```