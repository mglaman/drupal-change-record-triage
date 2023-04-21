<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

$createdCount = 0;
$changeRecords = \App\ChangeRecords::get();
foreach ($changeRecords as $changeRecord) {
  print sprintf(
      '[%s] %s',
      $changeRecord['branch'],
      $changeRecord['title'],
    ) . PHP_EOL;

  try {
    if (!\App\Issues::exists($changeRecord['title'])) {
      \App\Issues::create($changeRecord);
      $createdCount++;
    }
  } catch (\Exception $exception) {
    print "Stopped at exception: {$exception->getMessage()}" . PHP_EOL;
    break;
  }
  usleep( 500000 );
}

print PHP_EOL . "Created $createdCount issues" . PHP_EOL;
