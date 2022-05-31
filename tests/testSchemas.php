<?php

declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

$eventsDir = __DIR__ . '/events';
$schemaDir = __DIR__ . '/../schema';

$schemaPaths = glob($schemaDir . '/*.json');
$eventPaths = glob($eventsDir . '/*.json');

$missingSchemas = array_diff(
    array_map(fn($path) => basename($path), $schemaPaths),
    array_map(fn($path) => basename($path), $eventPaths)
);

if (count($missingSchemas)) {
    echo sprintf('Some schemas don\'t have an event to test it: ' . PHP_EOL . '  * %s' . PHP_EOL. PHP_EOL, implode(PHP_EOL . '  * ',
    $missingSchemas));
    exit(2);
}

foreach ($eventPaths as $pathname) {
    try {
        $eventData = json_decode(file_get_contents($pathname), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException(
            sprintf(
                'Failed to parse event file "%s": %s',
                $pathname,
                $e->getMessage(),
            ), 0, $e
        );
    }

    $auditLogEventName = $eventData->operation ?? null;
    $sapiEventName = $eventData->event ?? null;

    $eventName = $auditLogEventName ?? $sapiEventName ?? null;

    if ($eventName === null) {
        throw new RuntimeException(sprintf(
            'Event from file "%s" does not seem like an event from Keboola',
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

    try {
        json_decode(file_get_contents($schemaPathname), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException(
            sprintf(
                'Failed to parse schema file %s: %s',
                $schemaPathname,
                $e->getMessage(),
            ), 0, $e
        );
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
