<?php
require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\OciApi;

$config = [
    'region' => getenv('OCI_REGION'),
    'user_id' => getenv('OCI_USER_ID'),
    'tenancy_id' => getenv('OCI_TENANCY_ID'),
    'key_fingerprint' => getenv('OCI_KEY_FINGERPRINT'),
    'private_key_filename' => getenv('OCI_PRIVATE_KEY_FILENAME'),
];

$api = new OciApi($config);

echo "=== TEST 1: Pobieranie domen dostępności (AD) ===\n";
try {
    $ads = $api->getAvailabilityDomains($config['tenancy_id']);
    echo "SUKCES! Znalazłem domeny AD:\n";
    print_r($ads);
} catch (\Throwable $e) {
    echo "BŁĄD W TEST 1 (User/Tenancy/Key/Fingerprint): " . $e->getMessage() . "\n";
}

echo "\n=== TEST 2: Sprawdzanie podsieci (Subnet) ===\n";
$subnetId = getenv('OCI_SUBNET_ID');
try {
    // Zapytanie o szczegóły podsieci
    $response = $api->call('GET', "https://iaas.{$config['region']}.oraclecloud.com/20160918/subnets/" . urlencode($subnetId));
    echo "SUKCES! Podsieć istnieje i jest dostępna:\n";
    print_r($response);
} catch (\Throwable $e) {
    echo "BŁĄD W TEST 2 (Zły OCI_SUBNET_OCID): " . $e->getMessage() . "\n";
}
