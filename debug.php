<?php
require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\OciConfig;
use Hitrov\OciApi;

try {
    $config = new OciConfig(
        getenv('OCI_USER_ID'),
        getenv('OCI_KEY_FINGERPRINT'),
        getenv('OCI_PRIVATE_KEY_FILENAME'),
        getenv('OCI_TENANCY_ID'),
        getenv('OCI_REGION'),
        getenv('OCI_TENANCY_ID'), // compartmentId
        getenv('OCI_SUBNET_ID')
    );
} catch (\Throwable $e) {
    echo "BŁĄD TWORZENIA KONFIGURACJI: " . $e->getMessage() . "\n";
    exit(1);
}

$api = new OciApi();

echo "=== TEST 1: Pobieranie domen dostępności (AD) ===\n";
try {
    $ads = $api->getAvailabilityDomains($config);
    echo "SUKCES! Pobrano strefy dostępności:\n";
    print_r($ads);
} catch (\Throwable $e) {
    echo "BŁĄD W TEST 1: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 2: Sprawdzanie podsieci (Subnet) ===\n";
try {
    $response = $api->call($config, 'GET', "https://iaas.{$config->getRegion()}.oraclecloud.com/20160918/subnets/" . urlencode($config->getSubnetId()));
    echo "SUKCES! Podsieć istnieje i odpowiedziała poprawnie.\n";
} catch (\Throwable $e) {
    echo "BŁĄD W TEST 2: " . $e->getMessage() . "\n";
}
