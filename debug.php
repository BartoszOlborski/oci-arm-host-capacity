<?php
require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\OciConfig;
use Hitrov\OciApi;

$region = getenv('OCI_REGION');
$userId = getenv('OCI_USER_ID');
$fingerprint = getenv('OCI_KEY_FINGERPRINT');
$keyFile = getenv('OCI_PRIVATE_KEY_FILENAME');
$tenancyId = getenv('OCI_TENANCY_ID');
$subnetId = getenv('OCI_SUBNET_ID');
$imageId = 'ocid1.image.oc1.eu-frankfurt-1.aaaaaaaau32lbb2sdrgpxsivv3esw52oepvxq6ef625a5hhml6247cchftka';

$config = new OciConfig(
    $region,
    $userId,
    $fingerprint,
    $keyFile,
    $tenancyId,
    $subnetId,
    $imageId
);

$api = new OciApi();

echo "=== DIAGNOSTYKA STREF DOSTĘPNOŚCI ===\n";
try {
    $ads = $api->getAvailabilityDomains($config);
    echo "TWOJE DOKŁADNE NAZWY STREF DOSTĘPNOŚCI:\n";
    foreach ($ads as $ad) {
        echo " -> " . $ad['name'] . "\n";
    }
} catch (\Throwable $e) {
    echo "BŁĄD: " . $e->getMessage() . "\n";
}
