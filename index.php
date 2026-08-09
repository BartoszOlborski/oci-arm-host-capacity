<?php
declare(strict\_types=1);


// useful when script is being executed by cron user
$pathPrefix = ''; // e.g. /usr/share/nginx/oci-arm-host-capacity/

require "{$pathPrefix}vendor/autoload.php";

use Dotenv\Dotenv;
use Hitrov\Exception\ApiCallException;
use Hitrov\FileCache;
use Hitrov\OciApi;
use Hitrov\OciConfig;
use Hitrov\TooManyRequestsWaiter;

$envFilename = empty($argv[1]) ? '.env' : $argv[1];
$dotenv = Dotenv::createUnsafeImmutable(\_\_DIR\_\_, $envFilename);
$dotenv->safeLoad();

/\*
 \* No need to modify any value in this file anymore!
 \* Copy .env.example to .env and adjust there instead.
 \*
 \* README.md now has all the information.
 \*/
$config = new OciConfig(
    getenv('OCI\_REGION'),
    getenv('OCI\_USER\_ID'),
    getenv('OCI\_TENANCY\_ID'),
    getenv('OCI\_KEY\_FINGERPRINT'),
    getenv('OCI\_PRIVATE\_KEY\_FILENAME'),
    getenv('OCI\_AVAILABILITY\_DOMAIN') ?: null, // null or '' or 'jYtI\:PHX-AD-1' or ['jYtI\:PHX-AD-1','jYtI\:PHX-AD-2']
    getenv('OCI\_SUBNET\_ID'),
    getenv('OCI\_IMAGE\_ID'),
    (int) getenv('OCI\_OCPUS'),
    (int) getenv('OCI\_MEMORY\_IN\_GBS')
);

$bootVolumeSizeInGBs = (string) getenv('OCI\_BOOT\_VOLUME\_SIZE\_IN\_GBS');
$bootVolumeId = (string) getenv('OCI\_BOOT\_VOLUME\_ID');
if ($bootVolumeSizeInGBs) {
    $config->setBootVolumeSizeInGBs($bootVolumeSizeInGBs);
} elseif ($bootVolumeId) {
    $config->setBootVolumeId($bootVolumeId);
}

$api = new OciApi();
if (getenv('CACHE\_AVAILABILITY\_DOMAINS')) {
    $api->setCache(new FileCache($config));
}
if (getenv('TOO\_MANY\_REQUESTS\_TIME\_WAIT')) {
    $api->setWaiter(new TooManyRequestsWaiter((int) getenv('TOO\_MANY\_REQUESTS\_TIME\_WAIT')));
}
$notifier = (function (): \Hitrov\Interfaces\NotifierInterface {
    /\*
     \* if you have own [https://core.telegram.org/bots](https://core.telegram.org/bots)
     \* and set TELEGRAM\_BOT\_API\_KEY and your TELEGRAM\_USER\_ID in .env
     \*
     \* then you can get notified when script will succeed.
     \* otherwise - don't mind OR develop you own NotifierInterface
     \* to e.g. send SMS or email.
     \*/
    return new \Hitrov\Notification\Telegram();
})();

$shape = getenv('OCI\_SHAPE');

$maxRunningInstancesOfThatShape = 1;
if (getenv('OCI\_MAX\_INSTANCES') !== false) {
    $maxRunningInstancesOfThatShape = (int) getenv('OCI\_MAX\_INSTANCES');
}

$instances = $api->getInstances($config);

$existingInstances = $api->checkExistingInstances($config, $instances, $shape, $maxRunningInstancesOfThatShape);
if ($existingInstances) {
    echo "$existingInstances\n";
    return;
}

if (!empty($config->availabilityDomains)) {
    if (is\_array($config->availabilityDomains)) {
        $availabilityDomains = $config->availabilityDomains;
    } else {
        $availabilityDomains = [ $config->availabilityDomains ];
    }
} else {
    $availabilityDomains = $api->getAvailabilityDomains($config);
}

foreach ($availabilityDomains as $availabilityDomainEntity) {
    $availabilityDomain = is\_array($availabilityDomainEntity) ? $availabilityDomainEntity['name'] : $availabilityDomainEntity;
    try {
        $instanceDetails = $api->createInstance($config, $shape, getenv('OCI\_SSH\_PUBLIC\_KEY'), $availabilityDomain);
    } catch(ApiCallException $e) {
        $message = $e->getMessage();
        echo "$message\n";
//            if ($notifier->isSupported()) {
//                $notifier->notify($message);
//            }

        if (
            $e->getCode() === 500 &&
            strpos($message, 'InternalError') !== false &&
            strpos($message, 'Out of host capacity') !== false
        ) {
            // trying next availability domain
            sleep(16);
            continue;
        }

        // current config is broken
        return;
    }

    // success
    $message = json\_encode($instanceDetails, JSON\_PRETTY\_PRINT);
    echo "$message\n";
    if ($notifier->isSupported()) {
        $notifier->notify($message);
    }

    return;
}
