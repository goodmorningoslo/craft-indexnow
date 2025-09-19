<?php

namespace goodmorning\craftindexnow\services;

use Craft;
use craft\base\Component;

class SettingsService extends Component
{
  /**
   * Get sections that have URLs.
   *
   * @return array
   */
  public function getSectionsWithUrls(): array
  {
    $sections = Craft::$app->entries->getAllSections();
    $filteredSections = [];

    foreach ($sections as $section) {
      foreach ($section->siteSettings as $siteSetting) {
        if ($siteSetting->hasUrls) {
          $filteredSections[] = $section->getAttributes(['id', 'name', 'handle']);
          break; // No need to check further site settings for this section
        }
      }
    }

    return $filteredSections;
  }

  public function generateKey(): string
  {
    // Generate a random API key
    return bin2hex(random_bytes(16));
  }
}
