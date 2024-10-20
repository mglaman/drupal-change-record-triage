<?php declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Utils;

final class ChangeRecords
{
  public static function get(): array {
    $client = new Client([
      'headers' => [
        'User-Agent' => 'mglaman change record scraper '.Utils::defaultUserAgent(),
        'Accept' => 'application/json',
      ],
      'handler' => HandlerStack::create(),
    ]);

    $query = http_build_query([
      'type' => 'changenotice',
      'field_project' => 3060,
      'field_change_to_branch' => [
        'value' => [
          '10.4.x',
          '10.3.x',
          '10.2.x',
          '10.1.x',
          '10.0.x',
          '10.x',
        ],
      ],
      'field_change_record_status' => 1,
      'status' => 1,
      'sort' => 'changed',
      'direction' => 'DESC',
    ]);
    $url = "https://www.drupal.org/api-d7/node?$query";

    $results = [];
    for (; ;) {
      if (getenv('DEBUG')) {
        print $url.PHP_EOL;
      }
      $changeRecords = $client->get($url);
      $changeRecords = \json_decode((string) $changeRecords->getBody(),
        false, 512, JSON_THROW_ON_ERROR);
      $results[] = $changeRecords->list;
      if (!isset($changeRecords->next)) {
        break;
      }
      $url = $changeRecords->next;
    }
    $results = array_merge(...$results);
    //  api does not support >= filtering
    $results = array_filter($results, static fn (object $item) => (int) $item->changed > 1650050083);
    return array_map(static fn(object $changeRecord) => [
      'branch' => $changeRecord->field_change_to_branch,
      'release' => $changeRecord->field_change_to,
      'url' => $changeRecord->url,
      'title' => $changeRecord->title,
      'body' => $changeRecord->field_description->value
    ], $results);
  }
}
