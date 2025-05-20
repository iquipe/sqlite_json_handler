<?php
// If using Composer:
require_once __DIR__ . '/../vendor/autoload.php';

// If not using Composer (manual requires):
// Ensure all necessary App\* classes are loaded here
// require_once __DIR__ . '/../src/Database/SQLiteManager.php';
// require_once __DIR__ . '/../src/Database/TableManager.php';
// require_once __DIR__ . '/../src/Soap/DatabaseService.php';


// Define WSDL Cache options - important for production to disable for development
ini_set('soap.wsdl_cache_enabled', '0'); // Disable WSDL caching for development
ini_set('soap.wsdl_cache_ttl', '0');     // Cache TTL to 0

$wsdl_path = __DIR__ . '/database_service.wsdl';

if (!file_exists($wsdl_path)) {
    // This error won't be a SOAP fault as the server hasn't started
    header("Content-Type: text/plain");
    http_response_code(500);
    die("ERROR: WSDL file not found at {$wsdl_path}");
}

try {
    $options = [
        'uri' => 'urn:DatabaseService', // Should match targetNamespace in WSDL
        'soap_version' => SOAP_1_1,    // Or SOAP_1_2 if your WSDL binding uses it
        'cache_wsdl' => WSDL_CACHE_NONE, // Disable WSDL caching for development
        // 'classmap' => [...] // You can define classmaps here if needed for complex types
    ];

    // For a server, you pass null as the first argument if using a WSDL file
    // and provide the WSDL path in the options array.
    // However, PHP's SoapServer expects the WSDL path as the first argument.
    $server = new SoapServer($wsdl_path, $options);
    $server->setClass(\App\Soap\DatabaseService::class);
    $server->handle();

} catch (SoapFault $sf) {
    // This catch might not be hit if SoapServer handles its own faults,
    // but it's good practice for general exceptions during setup.
    header("Content-Type: text/xml"); // Ensure proper content type for SOAP fault
    http_response_code(500); // Internal Server Error for SOAP faults
    error_log("SOAP Server Fault: (faultcode: {$sf->faultcode}, faultstring: {$sf->faultstring})");
    echo $sf->getMessage(); // Or construct a proper SOAP fault XML
} catch (Exception $e) {
    header("Content-Type: text/plain");
    http_response_code(500);
    error_log("SOAP Server Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "An unexpected error occurred on the server.";
}