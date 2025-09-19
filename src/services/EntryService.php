<?php

namespace goodmorning\craftindexnow\services;

use Craft;
use craft\elements\Entry;
use craft\services\Structures;
use craft\events\MoveElementEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\queue\jobs\UpdateElementSlugsAndUris;
use craft\queue\Queue;
use goodmorning\craftindexnow\jobs\SubmitIndexNow;
use yii\base\Component;
use yii\base\Event;

use yii\queue\ExecEvent;

/**
 * Entry service
 */
class EntryService extends Component
{
  /**
   * Initializes the entry service.
   *
   * @return void
   */
  public function init(): void
  {
    Event::on(
      Entry::class,
      Entry::EVENT_AFTER_SAVE,
      function (ModelEvent $event) {
        $entry = $event->sender;

        if (
          $this->_shouldProcess($entry)
        ) {
          $this->pushSingleEntry($entry);
        }
      }
    );

    Event::on(
      Entry::class,
      Entry::EVENT_AFTER_DELETE,
      function (Event $event) {
        $entry = $event->sender;

        if ($this->_shouldProcess($entry)) {
          $this->pushSingleEntry($entry);
        }
      }
    );

    // Listen for structure move so we can send the old urls
    Event::on(
      Structures::class,
      Structures::EVENT_AFTER_MOVE_ELEMENT,
      function (MoveElementEvent $event) {
        $entry = $event->sender;

        if ($this->_shouldProcess($entry)) {
          $entries = [$entry];

          // find decendents
          $descendants = $entry->getDescendants();
          foreach ($descendants as $descendant) {
            if ($this->_shouldProcess($descendant)) {
              $entries[] = $descendant;
            }
          }

          // find localized versions
          $localized = $entry->localized->all();
          foreach ($localized as $localizedEntry) {
            if ($this->_shouldProcess($localizedEntry)) {
              $entries[] = $localizedEntry;

              // find descendants
              $descendants = $localizedEntry->getDescendants();
              foreach ($descendants as $descendant) {
                if ($this->_shouldProcess($descendant)) {
                  $entries[] = $descendant;
                }
              }
            }
          }

          // send the entries to IndexNow
          $this->pushEntries($entries);
        }
      }
    );

    // Listen for uri queue job completion so we can send the new urls for structure updates
    // This is necessary because the uri update job may not be run immediately after the entry save
    Event::on(
      Queue::class,
      Queue::EVENT_AFTER_EXEC,
      function (ExecEvent $event) {
        $job = $event->job;

        if ($job instanceof UpdateElementSlugsAndUris) {
          $elementId = $job->elementId ?? [];
          $siteId = $job->siteId ?? null;

          $entries = [];

          $entry = Entry::findOne($elementId);
          if ($entry && $this->_shouldProcess($entry)) {
            $entries[] = $entry;

            // push descendants if they are applicable
            $descendants = $entry->getDescendants();
            foreach ($descendants as $descendant) {
              if ($this->_shouldProcess($descendant)) {
                $entries[] = $descendant;
              }
            }
          }

          // find localized versions
          $localized = !empty($entry->localized) ? $entry->localized->all() : [];
          foreach ($localized as $localizedEntry) {
            if ($this->_shouldProcess($localizedEntry)) {
              $entries[] = $localizedEntry;

              // push descendants if they are applicable
              $descendants = $localizedEntry->getDescendants();
              foreach ($descendants as $descendant) {
                if ($this->_shouldProcess($descendant)) {
                  $entries[] = $descendant;
                }
              }
            }
          }

          // Process the entries as needed
          $this->pushEntries($entries);
        }
      }
    );
  }

  /**
   * Pushes a single entry to the queue.
   *
   * @param Entry $entry The entry to push.
   * @return void
   */
  public function pushSingleEntry(Entry $entry): void
  {
    $job = new SubmitIndexNow([
      'urls' => [$entry->url],
      'chunk' => 0,
      'totalChunks' => 1,
    ]);
    Craft::$app->getQueue()->push($job);
  }

  /**
   * Pushes multiple entries to the queue.
   *
   * @param array $entries The entries to push.
   * @return void
   */
  public function pushEntries(array $entries): void
  {
    $urls = [];
    $chunkSize = 1000; // Define the chunk size

    foreach ($entries as $entry) {
      $urls[] = $entry->url;
    }
    // make sure we do not have duplicate urls
    $urls = array_unique($urls);

    // Split URLs into chunks
    $chunks = array_chunk($urls, $chunkSize);

    foreach ($chunks as $index => $chunk) {
      $job = new SubmitIndexNow([
        'urls' => $chunk,
        'chunk' => $index,
        'totalChunks' => count($chunks),
      ]);
      Craft::$app->getQueue()->push($job);
    }
  }

  /**
   * Pushes all applicable entries to the queue.
   *
   * @return void
   */
  public function pushAllEntries(): void
  {
    $sections = Craft::$app->getPlugins()->getPlugin('indexnow')->getSettings()->sections ?? [];
    $sites = Craft::$app->getSites()->getAllSites();
    $siteIds = array_map(fn($site) => $site->id, $sites);

    // get all entries from all sites for the specified sections
    $entries = Entry::find()
      ->section($sections)
      ->siteId($siteIds)
      ->all();

    $this->pushEntries($entries);
  }

  /**
   * Determines if the entry should be processed for IndexNow.
   *
   * @param Entry $entry The entry to check.
   * @return bool True if the entry should be processed, false otherwise.
   */
  private function _shouldProcess($entry): bool
  {
    $sections = Craft::$app->getPlugins()->getPlugin('indexnow')->getSettings()->sections ?? [];
    return !empty($sections) &&
      !empty($entry->section) &&
      in_array($entry->section->handle, $sections) &&
      !ElementHelper::isDraft($entry) &&
      ($entry->enabled && $entry->getEnabledForSite()) &&
      !ElementHelper::rootElement($entry)->isProvisionalDraft &&
      !ElementHelper::isRevision($entry);
  }
}
