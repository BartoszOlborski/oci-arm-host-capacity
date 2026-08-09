<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\OciConfig;
use Hitrov\OciApi;

$region = getenv('OCI_REGION');
$userId = getenv('OCI_USER_ID');
$tenancyId = getenv('OCI_TENANCY_ID');
$fingerprint = getenv('OCI_KEY_FINGERPRINT');
$keyFile = getenv('OCI_PRIVATE_KEY_FILENAME');
$subnetId = getenv('OCI_SUBNET_ID');

$imageId = 'ocid1.image.oc1.eu-frankfurt-1.aaaaaaaau32lbb2sdrgpxsivv3esw52oepvxq6ef625a5hhml6247cchftka';

$availabilityDomain = '';

try {
    $config = new OciConfig(
        $region,
        $userId,
        $tenancyId,
        $fingerprint,
        $keyFile,
        $availabilityDomain,
        $subnetId,
        $imageId
    );
} catch (\Throwable $e) {
    echo "BŁĄD KONFIGURACJI: " . $e->getMessage() . "\n";
    exit(1);
}

$api = new OciApi();

echo "=== DIAGNOSTYKA STREF DOSTĘPNOŚCI ===\n";

try {
    $ads = $api->getAvailabilityDomains($config);

    echo "TWOJE DOKŁADNE NAZWY STREF DOSTĘPNOŚCI:\n";
    print_r($ads);

} catch (\Throwable $e) {
    echo "BŁĄD ODCZYTU AD: " . $e->getMessage() . "\n";
    exit(1);
}
