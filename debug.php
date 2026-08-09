<?php
require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\OciConfig;
use Hitrov\OciApi;

$imageId = 'ocid1.image.oc1.eu-frankfurt-1.aaaaaaaau32lbb2sdrgpxsivv3esw52oepvxq6ef625a5hhml6247cchftka';
$availabilityDomain = 'EU-FRANKFURT-1-AD-1';

try {
    // 8 wymaganych parametrów w poprawnej kolejności:
    // 1: region, 2: userId, 3: keyFingerprint, 4: privateKeyFilename, 5: tenancyId, 6: subnetId, 7: imageId, 8: availabilityDomain
    $config = new OciConfig(
        getenv('OCI_REGION'),
        getenv('OCI_USER_ID'),
        getenv('OCI_KEY_FINGERPRINT'),
        getenv('OCI_PRIVATE_KEY_FILENAME'),
        getenv('OCI_TENANCY_ID'),
        getenv('OCI_SUBNET_ID'),
        $imageId,
        $availabilityDomain
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
    $subnetId = getenv('OCI_SUBNET_ID');
    $region = getenv('OCI_REGION');
    $response = $api->call($config, 'GET', "https://iaas.{$region}.oraclecloud.com/20160918/subnets/" . urlencode($subnetId));
    echo "SUKCES! Podsieć istnieje i odpowiedziała poprawnie.\n";
} catch (\Throwable $e) {
    echo "BŁĄD W TEST 2: " . $e->getMessage() . "\n";
}
