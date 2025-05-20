
# SQLite JSON/REST API Handler - Deployment and Usage Documentation

## Table of Contents

1.  [Introduction](#introduction)
2.  [Project Structure](#project-structure)
3.  [Prerequisites](#prerequisites)
4.  [Deployment Steps](#deployment-steps)
    1.  [Clone/Download Project](#clonedownload-project)
    2.  [Install Dependencies (Composer)](#install-dependencies-composer)
    3.  [Set Directory Permissions](#set-directory-permissions)
    4.  [Web Server Configuration](#web-server-configuration)
        *   [Using PHP Built-in Server (Development)](#using-php-built-in-server-development)
        *   [Using Apache (Production/Staging)](#using-apache-productionstaging)
        *   [Using Nginx (Production/Staging)](#using-nginx-productionstaging)
5.  [API Endpoint](#api-endpoint)
6.  [Request Format](#request-format)
7.  [Response Format](#response-format)
8.  [API Operations and Usage (Request/Response Samples)](#api-operations-and-usage-requestresponse-samples)
    1.  [Common Request Structure](#common-request-structure)
    2.  [Database Operations](#database-operations)
        *   [Create Database](#create-database)
        *   [Delete Database](#delete-database)
        *   [Backup Database](#backup-database)
        *   [Restore Database](#restore-database)
    3.  [Schema Operations](#schema-operations)
        *   [List All Tables with Fields](#list-all-tables-with-fields)
        *   [Create Table](#create-table)
        *   [Delete Table](#delete-table)
        *   [Get Table Schema (Individual)](#get-table-schema-individual)
    4.  [Data (CRUD) Operations](#data-crud-operations)
        *   [Insert Record](#insert-record)
        *   [Select Records (Query)](#select-records-query)
        *   [Update Records](#update-records)
        *   [Delete Records](#delete-records)
    5.  [Error Handling](#error-handling)
9.  [Troubleshooting](#troubleshooting)

---

## 1. Introduction

This document provides instructions for deploying and using the SQLite JSON/REST API Handler. This service allows interaction with SQLite databases through a simple JSON-based RESTful API. It supports operations such as database creation/deletion, table management, CRUD operations, backups, and restores.

---

## 2. Project Structure

```
sqlite_json_handler/
├── src/
│   ├── Database/
│   │   ├── SQLiteManager.php
│   │   └── TableManager.php
│   ├── Http/
│   │   ├── Request.php
│   │   └── Response.php
│   └── Api/
│       └── ApiController.php
├── public/
│   └── api.php                 # API Entry Point
├── databases/                    # Stores .sqlite files (writable)
├── backups/                      # Stores database backups (writable)
└── composer.json
└── README.md
```

*   **`src/`**: Contains the core PHP classes for database management, HTTP request/response handling, and the API controller logic.
*   **`public/`**: Contains the web-accessible API entry point script (`api.php`). This directory should be your web server's document root.
*   **`databases/`**: Directory where SQLite database files will be created and stored.
*   **`backups/`**: Directory where database backups will be stored.
*   **`composer.json`**: Defines project dependencies (primarily for PSR-4 autoloading).

---

## 3. Prerequisites

*   PHP 7.4 or higher.
*   PHP `sqlite3` extension enabled. (Check with `php -m | grep sqlite` or in `php.ini`).
*   PHP `json` extension enabled (usually enabled by default).
*   Composer (for dependency management and autoloading - recommended).
*   A web server (Apache, Nginx, or PHP's built-in server for development).
*   Write permissions for the web server user on the `databases/` and `backups/` directories.
*   A tool to make HTTP POST requests with JSON bodies (e.g., `curl`, Postman, Insomnia).

---

## 4. Deployment Steps

### 4.1. Clone/Download Project

Obtain the project files, either by cloning a Git repository or downloading a ZIP archive and extracting it to your server.

```bash
# Example using Git
git clone <repository_url> sqlite_json_handler
cd sqlite_json_handler
```

### 4.2. Install Dependencies (Composer)

If you have Composer installed, navigate to the project root directory (`sqlite_json_handler`) and run:

```bash
composer install
```
This will install any defined dependencies and set up the PSR-4 autoloader specified in `composer.json`, creating a `vendor/autoload.php` file.

If you are not using Composer, you will need to manually include the necessary class files using `require_once` at the beginning of `public/api.php`.

### 4.3. Set Directory Permissions

The web server process needs write access to the `databases/` and `backups/` directories.

Navigate to the project root (`sqlite_json_handler`) and run:

```bash
# Create directories if they don't exist
mkdir -p databases backups

# Set permissions
# Replace www-data:www-data with your web server's user and group if different (e.g., apache:apache)
sudo chown -R www-data:www-data databases backups
sudo chmod -R 775 databases backups
```
If `775` does not work due to strict server configurations, you might temporarily use `777` for testing, but this is not recommended for production.

### 4.4. Web Server Configuration

#### 4.4.1. Using PHP Built-in Server (Development)

For development purposes, PHP's built-in web server is convenient.

1.  Navigate to the project root directory (`sqlite_json_handler`) in your terminal.
2.  Start the server:
    ```bash
    php -S localhost:8000 -t public
    ```
    *   This makes your application accessible at `http://localhost:8000`.
    *   The `-t public` option sets the `public/` directory as the document root.
    *   Your API endpoint will be `http://localhost:8000/api.php`.

#### 4.4.2. Using Apache (Production/Staging)

1.  Ensure Apache's `mod_rewrite` is enabled (optional, but useful if you want prettier URLs later).
2.  Configure a Virtual Host for your application, pointing the `DocumentRoot` to the `public/` directory of your project.

    Example Apache Virtual Host configuration (`/etc/apache2/sites-available/sqlite-json-api.conf`):
    ```apache
    <VirtualHost *:80>
        ServerName your.api.domain.com # Or your server's IP/hostname
        DocumentRoot /var/www/html/sqlite_json_handler/public

        <Directory /var/www/html/sqlite_json_handler/public>
            Options Indexes FollowSymLinks
            AllowOverride All # Or at least FileInfo for .htaccess if used for rewrites
            Require all granted
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/sqlite_json_api_error.log
        CustomLog ${APACHE_LOG_DIR}/sqlite_json_api_access.log combined
    </VirtualHost>
    ```
3.  Enable the site and restart Apache:
    ```bash
    sudo a2ensite sqlite-json-api.conf
    sudo systemctl reload apache2 # or service apache2 reload
    ```

#### 4.4.3. Using Nginx (Production/Staging)

1.  Configure an Nginx server block, setting the `root` to the `public/` directory and ensuring PHP requests are passed to PHP-FPM.

    Example Nginx server block configuration (`/etc/nginx/sites-available/sqlite-json-api`):
    ```nginx
    server {
        listen 80;
        server_name your.api.domain.com; # Or your server's IP/hostname
        root /var/www/html/sqlite_json_handler/public;

        index api.php index.php index.html index.htm; # api.php is the main entry

        location / {
            try_files $uri $uri/ /api.php?$query_string; # If you want api.php to handle all non-file requests
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
    sudo ln -s /etc/nginx/sites-available/sqlite-json-api /etc/nginx/sites-enabled/
    sudo systemctl reload nginx # or service nginx reload
    ```

---

## 5. API Endpoint

All API requests are made via HTTP POST to a single endpoint:
`http://<your_server_address_and_path>/api.php`

Example: `http://localhost:8000/api.php`

---

## 6. Request Format

*   **Method:** `POST`
*   **Content-Type:** `application/json`
*   **Body:** A JSON object containing the action to perform and its associated payload.

```json
{
    "dbName": "optional_for_some_actions_required_for_most_table_ops",
    "action": "action_name",
    "payload": {
        // Action-specific parameters
    }
}
```

*   `dbName` (string, optional/required): The name of the target database (without `.sqlite` extension).
    *   Not required for `create_database`.
    *   Required for most other operations to specify which database to act upon.
*   `action` (string, required): The specific operation to perform (e.g., `create_table`, `insert_record`).
*   `payload` (object, required): An object containing parameters specific to the chosen `action`.

---

## 7. Response Format

All API responses are in JSON format.

**Successful Response:**
```json
{
    "status": "success",
    "message": "Descriptive message of the outcome",
    "data": {
        // Action-specific data, or null
    }
}
```
HTTP Status Code: `200 OK` (or `201 Created` for resource creation, though this example uses `200` for simplicity).

**Error Response:**
```json
{
    "status": "error",
    "message": "Error description",
    "errors": { // Optional: more detailed errors or validation messages
        // ...
    }
}
```
HTTP Status Code: `400 Bad Request`, `404 Not Found`, `500 Internal Server Error`, etc. (The example primarily uses `400` and `500`).

---

## 8. API Operations and Usage (Request/Response Samples)

All examples use `curl`. Replace `http://localhost:8000/api.php` with your actual API endpoint.

### 8.1. Common Request Structure

Remember the base structure for all requests:
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -d '{
         "dbName": "your_db",  # If applicable
         "action": "your_action",
         "payload": { /* ...action specific payload... */ }
     }' \
     http://localhost:8000/api.php
```

### 8.2. Database Operations

#### Create Database

*   **Action:** `create_database`
*   **Payload:**
    *   `dbName` (string, required): Name of the database to create.

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "action": "create_database",
    "payload": {
        "dbName": "company_data"
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Database 'company_data' created successfully.",
    "data": null
}
```

#### Delete Database

*   **Action:** `delete_database`
*   **Payload:**
    *   `dbName` (string, required): Name of the database to delete.

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "action": "delete_database",
    "payload": {
        "dbName": "company_data"
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Database 'company_data' deleted successfully.",
    "data": null
}
```

#### Backup Database

*   **Action:** `backup_database`
*   **`dbName` (top-level, required):** Name of the database to back up.
*   **Payload:** (empty object `{}`)

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "backup_database",
    "payload": {}
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Database 'company_data' backed up successfully.",
    "data": {
        "backup_path": ".../sqlite_json_handler/backups/company_data_backup_YYYYMMDDHHMMSS.sqlite"
    }
}
```

#### Restore Database

*   **Action:** `restore_database`
*   **Payload:**
    *   `dbNameToRestore` (string, required): Name of the database to restore to (will be created/overwritten).
    *   `backupFileName` (string, required): Filename of the backup (must exist in `backups/` on server).

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "action": "restore_database",
    "payload": {
        "dbNameToRestore": "company_data_restored",
        "backupFileName": "company_data_backup_YYYYMMDDHHMMSS.sqlite"
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Database 'company_data_restored' restored successfully from 'company_data_backup_YYYYMMDDHHMMSS.sqlite'.",
    "data": null
}
```

### 8.3. Schema Operations

For these operations, `dbName` at the top level of the request JSON is required.

#### List All Tables with Fields

*   **Action:** `list_tables`
*   **`dbName` (top-level, required):** Name of the database.
*   **Payload:** (empty object `{}`)

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "list_tables",
    "payload": {}
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Operation successful",
    "data": {
        "employees": [
            {"cid":0,"name":"id","type":"INTEGER","notnull":0,"dflt_value":null,"pk":1},
            {"cid":1,"name":"name","type":"TEXT","notnull":1,"dflt_value":null,"pk":0}
            // ... other fields ...
        ],
        "departments": [ /* ... fields ... */ ]
    }
}
```
(The `data` object is a map where keys are table names, and values are arrays of field schema objects.)

#### Create Table

*   **Action:** `create_table`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required): Name of the table to create.
    *   `columns` (array, required): Array of column definition objects. Each object:
        *   `name` (string, required): Column name.
        *   `type` (string, required): SQLite data type (e.g., "INTEGER", "TEXT", "REAL", "BLOB").
        *   `constraints` (string, optional): e.g., "PRIMARY KEY AUTOINCREMENT", "NOT NULL UNIQUE".

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "create_table",
    "payload": {
        "tableName": "employees",
        "columns": [
            { "name": "id", "type": "INTEGER", "constraints": "PRIMARY KEY AUTOINCREMENT" },
            { "name": "name", "type": "TEXT", "constraints": "NOT NULL" },
            { "name": "email", "type": "TEXT", "constraints": "UNIQUE" },
            { "name": "department_id", "type": "INTEGER" }
        ]
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Table 'employees' created successfully.",
    "data": null
}
```

#### Delete Table

*   **Action:** `delete_table`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required): Name of the table to delete.

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "delete_table",
    "payload": {
        "tableName": "old_logs"
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Table 'old_logs' deleted successfully.",
    "data": null
}
```

#### Get Table Schema (Individual)

*   **Action:** `get_table_schema`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required): Name of the table to get schema for.

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "get_table_schema",
    "payload": {
        "tableName": "employees"
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Schema for table 'employees'.",
    "data": [
        {"cid":0,"name":"id","type":"INTEGER","notnull":0,"dflt_value":null,"pk":1},
        {"cid":1,"name":"name","type":"TEXT","notnull":1,"dflt_value":null,"pk":0},
        {"cid":2,"name":"email","type":"TEXT","notnull":0,"dflt_value":null,"pk":0},
        {"cid":3,"name":"department_id","type":"INTEGER","notnull":0,"dflt_value":null,"pk":0}
    ]
}
```

### 8.4. Data (CRUD) Operations

For these operations, `dbName` at the top level of the request JSON is required.

#### Insert Record

*   **Action:** `insert_record`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required)
    *   `data` (object, required): Key-value pairs where keys are column names and values are the data to insert.

**Request:**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "insert_record",
    "payload": {
        "tableName": "employees",
        "data": {
            "name": "Jane Doe",
            "email": "jane.doe@example.com",
            "department_id": 1
        }
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Record inserted into 'employees'.",
    "data": {
        "last_insert_id": 1
    }
}
```

#### Select Records (Query)

*   **Action:** `select_records`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required)
    *   `criteria` (object, optional): Defines filtering, sorting, etc.
        *   `fields` (array of strings, optional): Columns to select (e.g., `["id", "name"]`). Defaults to `*`.
        *   `where` (array of objects, optional): Conditions for the WHERE clause. Each object:
            *   `field` (string, required): Column name.
            *   `operator` (string, required): e.g., "=", "!=", ">", "<", ">=", "<=", "LIKE", "NOT LIKE", "IN", "NOT IN".
            *   `value` (mixed, required): Value for comparison. For "IN" or "NOT IN", this should be an array.
        *   `orderBy` (array of objects, optional): Sorting order. Each object:
            *   `field` (string, required): Column name.
            *   `direction` (string, optional): "ASC" (default) or "DESC".
        *   `limit` (integer, optional): Maximum number of records to return.
        *   `offset` (integer, optional): Number of records to skip (for pagination).

**Request (Example: Get all employees in department 1, ordered by name):**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "select_records",
    "payload": {
        "tableName": "employees",
        "criteria": {
            "fields": ["id", "name", "email"],
            "where": [
                { "field": "department_id", "operator": "=", "value": 1 },
                { "field": "name", "operator": "LIKE", "value": "J%" }
            ],
            "orderBy": [
                { "field": "name", "direction": "ASC" },
                { "field": "id", "direction": "DESC" }
            ],
            "limit": 10,
            "offset": 0
        }
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "Records selected from 'employees'.",
    "data": [
        { "id": 1, "name": "Jane Doe", "email": "jane.doe@example.com" }
        // ... other matching records ...
    ]
}
```

#### Update Records

*   **Action:** `update_records`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required)
    *   `data` (object, required): Key-value pairs of columns to update.
    *   `where` (array of objects, required): Conditions for the WHERE clause (see `selectRecords`). *Mandatory to prevent accidental full table updates.*

**Request (Example: Update Jane Doe's email):**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "update_records",
    "payload": {
        "tableName": "employees",
        "data": {
            "email": "jane.d.updated@example.com"
        },
        "where": [
            { "field": "id", "operator": "=", "value": 1 }
        ]
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "1 record(s) updated in 'employees'.",
    "data": {
        "affected_rows": 1
    }
}
```

#### Delete Records

*   **Action:** `delete_records`
*   **`dbName` (top-level, required)**
*   **Payload:**
    *   `tableName` (string, required)
    *   `where` (array of objects, required): Conditions for the WHERE clause (see `selectRecords`). *Mandatory to prevent accidental full table deletions.*

**Request (Example: Delete employee with ID 2):**
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{
    "dbName": "company_data",
    "action": "delete_records",
    "payload": {
        "tableName": "employees",
        "where": [
            { "field": "id", "operator": "=", "value": 2 }
        ]
    }
}' http://localhost:8000/api.php
```
**Successful Response (200 OK):**
```json
{
    "status": "success",
    "message": "1 record(s) deleted from 'employees'.",
    "data": {
        "affected_rows": 1
    }
}
```

### 8.5. Error Handling

If an error occurs, the API will respond with an appropriate HTTP status code (e.g., 400, 404, 500) and a JSON body like this:

**Example Error Response (400 Bad Request):**
```json
{
    "status": "error",
    "message": "Missing 'tableName' in payload for table operation 'create_table'."
}
```
**Example Error Response (500 Internal Server Error):**
```json
{
    "status": "error",
    "message": "Failed to create table 'employees': SQLSTATE[HY000]: General error: 1 table employees already exists",
    "errors": { // "errors" key might be named "trace" in some configurations
        "trace": "..." // Long trace string, useful for debugging
    }
}
```

---

## 9. Troubleshooting

*   **"Invalid JSON input" error:**
    *   Ensure your request body is valid JSON and the `Content-Type` header is `application/json`.
*   **"Action not specified" or "Invalid action specified":**
    *   Check that the `action` field in your JSON request body is correct and matches one of the supported actions.
*   **"Database '...' not found or not connected":**
    *   Ensure the `dbName` you provided exists or that you've run `create_database` first.
    *   Check for typos in `dbName`.
*   **Permissions Errors on `databases/` or `backups/`:**
    *   The API will likely return a 500 error with a message like "Failed to create database" or "Failed to write...".
    *   Check your web server's error logs for more specific permission denied messages.
    *   Re-verify permissions as per [Section 4.3](#set-directory-permissions).
*   **SQL Syntax Errors (often 500 Internal Server Error):**
    *   The error message in the JSON response should contain details from the SQLite driver.
    *   Double-check your column names, data types, and query criteria for correctness.
*   **General 500 Internal Server Errors:**
    *   Check your web server's PHP error log. The `ApiController` includes a trace in the error response for debugging, but more details might be in the server logs.
*   **API endpoint not found (404):**
    *   Verify your web server configuration (`DocumentRoot`, rewrite rules if any).
    *   Ensure the path to `api.php` in your request URL is correct.

---
This comprehensive documentation should guide users through deploying and utilizing your SQLite JSON/REST API.
```