<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

$changeNotices = \App\ChangeRecords::get();

foreach ($changeNotices as $changeNotice) {
  $noteText = sprintf(
    '%s %s %s',
    $changeNotice['branch'],
    $changeNotice['title'],
    $changeNotice['url'],
  );
  print $noteText . PHP_EOL;
}
