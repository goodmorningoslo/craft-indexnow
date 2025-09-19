<?php

namespace goodmorning\craftindexnow\jobs;

use Craft;
use craft\queue\BaseJob;

use goodmorning\craftindexnow\IndexNow;

/**
 * Submit Index Now queue job
 */
class SubmitIndexNow extends BaseJob
{
  /**
   * @var int
   */
  public int $chunk = 0;
  public int $totalChunks = 0;

  /**
   * @var int
   */
  public int $siteId = 0;

  /**
   * @var array
   */
  public array $urls = [];

  /**
   * @inheritdoc
   */
  public function getTtr(): int
  {
    return 1000;
  }

  /**
   * @inheritdoc
   */
  public function canRetry($attempt, $error): bool
  {
    return $attempt < 3;
  }

  public function execute($queue): void
  {
    $urls = [];

    // make sure all of the urls are valid and unique
    foreach ($this->urls as $url) {
      $url = trim($url);
      if (filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $urls)) {
        $urls[] = $url;
      }
    }

    $this->setProgress(
      $queue,
      1,
      Craft::t(
        'indexnow',
        'Submitting {count} URLs. ({chunk, number} / {totalChunks, number})',
        [
          'count' => count($urls),
          'chunk' => $this->chunk + 1,
          'totalChunks' => $this->totalChunks,
        ]
      )
    );

    IndexNow::getInstance()->sendUrls->sendUrls($urls, $this->siteId);
  }

  protected function defaultDescription(): ?string
  {
    return Craft::t('indexnow', 'Submitting IndexNow entries');
  }
}
