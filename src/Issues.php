<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

final class Issues
{
  protected static $issueCache = [];

  protected static function loadIssues(): void
  {
    $client = self::getClient();
    $page = 1;
    $perPage = 100;
    $issues = [];
    do {
      $response = $client->get('/repos/mglaman/drupal-change-record-triage/issues', [
        'query' => [
          'per_page' => $perPage,
          'page' => $page,
        ],
      ]);
      $data = \json_decode(
        (string)$response->getBody(),
        false,
        512,
        JSON_THROW_ON_ERROR
      );
      $issues = array_merge($issues, $data);
      $page++;
    } while (count($data) === $perPage);

    foreach ($issues as $issue) {
      self::$issueCache[$issue->title] = $issue;
    }
  }

  public static function exists(string $title): bool
  {
    if (empty(self::$issueCache)) {
      self::loadIssues();
    }

    if (isset(self::$issueCache[$title])) {
      return true;
    }

    $client = self::getClient();
    $query = 'repo:mglaman/drupal-change-record-triage/issues is:issue ' . $title;

    try {
      $search = $client->get('/search/issues?q=' . urlencode($query));
      $data = \json_decode(
        (string)$search->getBody(),
        false,
        512,
        JSON_THROW_ON_ERROR
      );
      return ($data->total_count ?? 0) > 0;
    }
    catch (ClientException $exception) {
      $response = $exception->getResponse();
      if ($response->hasHeader('Retry-After')) {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        print PHP_EOL . "Hit Retry-After for $retryAfter" . PHP_EOL;
        if ($retryAfter < 90) {
          sleep($retryAfter);
          return self::exists($title);
        }

        throw new \RuntimeException((string) $response->getBody());
      }

      if ($response->hasHeader('x-ratelimit-reset')) {
        $rateLimitReset = (int) $response->getHeaderLine('x-ratelimit-reset');
        $wait = $rateLimitReset - time();
        if ($wait > 90) {
          throw new \RuntimeException("Rate limit reset over 90: $wait");
        }
        print PHP_EOL . "Waiting $wait for rate limit reset" . PHP_EOL;
        // weird bug where it was -1
        if ($wait > 0) {
          sleep($wait);
        }
        return self::exists($title);
      }

      throw new \RuntimeException((string) $response->getBody());
    }
  }

  public static function create(array $changeRecord): void
  {
    $client = self::getClient();

    $body = <<<BODY
{$changeRecord['url']}

Introduced in branch/version: {$changeRecord['branch']} / {$changeRecord['release']}

{$changeRecord['body']}
BODY;

    try {
      $client->post('/repos/mglaman/drupal-change-record-triage/issues', [
        'json' => [
          'title' => $changeRecord['title'],
          'body' => $body,
          'labels' => array_filter([
            $changeRecord['branch'],
          ])
        ],
      ]);
      sleep(1);
    }
    catch (ClientException $exception) {
      $response = $exception->getResponse();
      if ($response->hasHeader('Retry-After')) {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        print PHP_EOL . "Hit Retry-After for $retryAfter" . PHP_EOL;
        if ($retryAfter < 90) {
          sleep($retryAfter);
          self::create($changeRecord);
        } else {
          throw new \RuntimeException((string) $response->getBody());
        }
      } else if ($response->hasHeader('x-ratelimit-reset')) {
        $rateLimitReset = (int) $response->getHeaderLine('x-ratelimit-reset');
        $wait = $rateLimitReset - time();
        print PHP_EOL . "Waiting $wait for rate limit reset" . PHP_EOL;
        if ($wait > 90) {
          throw new \RuntimeException("Rate limit reset over 90: $wait" . PHP_EOL . $response->getBody());
        }
        // weird bug where it was -1
        if ($wait > 0) {
          sleep($wait);
        }
        self::create($changeRecord);
      }
      else {
        throw new \RuntimeException((string) $response->getBody());
      }
    }

  }

  private static function getClient(): Client
  {
    return new Client([
      'base_uri' => 'https://api.github.com',
      'headers' => [
        'Authorization' => 'Bearer ' . getenv('GITHUB_TOKEN'),
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28',
      ],
    ]);
  }

}
