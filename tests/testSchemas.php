<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$eventsDir = __DIR__.'/events';
$schemaDir = __DIR__.'/../schema';

foreach (glob($eventsDir.'/*.json') as $pathname) {
    try {
        $eventData = json_decode(file_get_contents($pathname), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException(sprintf(
            'Failed to parse event file %s: %s',
            $pathname,
            $e->getMessage(),
        ), 0, $e);
    }

    $eventName = $eventData->operation ?? null;
    if ($eventName === null) {
        throw new RuntimeException(sprintf(
            'Event from file %s is "operation" property',
            $pathname,
        ));
    }

    $schemaPathname = sprintf('%s/%s.json', $schemaDir, $eventName);

    if (!file_exists($schemaPathname)) {
        throw new RuntimeException(sprintf(
            'Schema for event %s from file %s does not exist',
            $eventName,
            $pathname,
        ));
    }

    $validator = new JsonSchema\Validator;
    $validator->validate($eventData, ['$ref' => sprintf('file://%s', $schemaPathname)]);

    if ($validator->isValid()) {
        echo '.';
        continue;
    }

    echo sprintf('Event %s from file %s is invalid:', $eventName, $pathname).PHP_EOL;
    foreach ($validator->getErrors() as $error) {
        echo sprintf('  %s: %s', $error['pointer'], $error['message']).PHP_EOL;
    }
    exit(1);
}
echo PHP_EOL;

echo 'OK'.PHP_EOL;
