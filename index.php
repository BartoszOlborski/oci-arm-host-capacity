```php
<?php
declare(strict_types=1);

$pathPrefix = '';

require "{$pathPrefix}vendor/autoload.php";

use Dotenv\Dotenv;
use Hitrov\Exception\ApiCallException;
use Hitrov\FileCache;
use Hitrov\OciApi;
use Hitrov\OciConfig;
use Hitrov\TooManyRequestsWaiter;

$envFilename = empty($argv[1]) ? '.env' : $argv[1];

$dotenv = Dotenv::createUnsafeImmutable(__DIR__, $envFilename);
$dotenv->safeLoad();

$config = new OciConfig(
    getenv('OCI_REGION'),
    getenv('OCI_USER_ID'),
    getenv('OCI_TENANCY_ID'),
    getenv('OCI_KEY_FINGERPRINT'),
    getenv('OCI_PRIVATE_KEY_FILENAME'),
    getenv('OCI_AVAILABILITY_DOMAIN') ?: null,
    getenv('OCI_SUBNET_ID'),
    getenv('OCI_IMAGE_ID'),
    (int) getenv('OCI_OCPUS'),
    (int) getenv('OCI_MEMORY_IN_GBS')
);

$bootVolumeSizeInGBs = (string) getenv('OCI_BOOT_VOLUME_SIZE_IN_GBS');
$bootVolumeId = (string) getenv('OCI_BOOT_VOLUME_ID');

if ($bootVolumeSizeInGBs) {
    $config->setBootVolumeSizeInGBs($bootVolumeSizeInGBs);
} elseif ($bootVolumeId) {
    $config->setBootVolumeId($bootVolumeId);
}

$api = new OciApi();

if (getenv('CACHE_AVAILABILITY_DOMAINS')) {
    $api->setCache(new FileCache($config));
}

if (getenv('TOO_MANY_REQUESTS_TIME_WAIT')) {
    $api->setWaiter(
        new TooManyRequestsWaiter(
            (int) getenv('TOO_MANY_REQUESTS_TIME_WAIT')
        )
    );
}

$notifier = (function (): \Hitrov\Interfaces\NotifierInterface {
    return new \Hitrov\Notification\Telegram();
})();

$shape = getenv('OCI_SHAPE');

$maxRunningInstancesOfThatShape = 1;

if (getenv('OCI_MAX_INSTANCES') !== false) {
    $maxRunningInstancesOfThatShape = (int) getenv('OCI_MAX_INSTANCES');
}

echo "OCI ARM Host Capacity Bot started.\n";
echo "Shape: {$shape}\n";
echo "Bot will keep trying until successful.\n\n";

while (true) {

    echo "\n=== NOWA RUNDA ===\n";

    $instances = $api->getInstances($config);

    $existingInstances = $api->checkExistingInstances(
        $config,
        $instances,
        $shape,
        $maxRunningInstancesOfThatShape
    );

    if ($existingInstances) {
        echo "$existingInstances\n";
        return;
    }

    if (!empty($config->availabilityDomains)) {
        if (is_array($config->availabilityDomains)) {
            $availabilityDomains = $config->availabilityDomains;
        } else {
            $availabilityDomains = [$config->availabilityDomains];
        }
    } else {
        $availabilityDomains = $api->getAvailabilityDomains($config);
    }

    foreach ($availabilityDomains as $availabilityDomainEntity) {

        $availabilityDomain = is_array($availabilityDomainEntity)
            ? $availabilityDomainEntity['name']
            : $availabilityDomainEntity;

        echo "Próba: {$availabilityDomain}\n";

        try {

            $instanceDetails = $api->createInstance(
                $config,
                $shape,
                getenv('OCI_SSH_PUBLIC_KEY'),
                $availabilityDomain
            );

        } catch (ApiCallException $e) {

            $message = $e->getMessage();

            echo "$message\n";

            if (
                $e->getCode() === 500 &&
                strpos($message, 'InternalError') !== false &&
                strpos($message, 'Out of host capacity') !== false
            ) {

                echo "Brak capacity. Następny AD...\n";

                sleep(16);

                continue;
            }

            echo "Inny błąd OCI. Kończę.\n";

            return;
        }

        $message = json_encode(
            $instanceDetails,
            JSON_PRETTY_PRINT
        );

        echo "=== SUKCES ===\n";
        echo "$message\n";

        if ($notifier->isSupported()) {
            $notifier->notify($message);
        }

        return;
    }

    echo "\nWszystkie AD sprawdzone.\n";
    echo "Brak capacity.\n";
    echo "Czekam 30 sekund i próbuję ponownie.\n";

    sleep(30);
}
    sleep(30);
}
