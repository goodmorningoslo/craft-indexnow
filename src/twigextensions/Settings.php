<?php

namespace goodmorning\craftindexnow\twigextensions;

use goodmorning\craftindexnow\IndexNow;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing helper functions for the IndexNow plugin.
 */
class Settings extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getSectionsWithUrls', [$this, 'getSectionsWithUrls']),
            new TwigFunction('generateKey', [$this, 'generateKey']),
        ];
    }

    /**
     * Returns sections (name + handle) that have URLs.
     */
    public function getSectionsWithUrls(): array
    {
        // settingsService is registered as a component in the plugin config
        $service = IndexNow::getInstance()->settingsService;

        $sectionsWithUrls = $service->getSectionsWithUrls();

        return array_map(static function(array $section): array {
            return [
                'label' => $section['name'],
                'value' => $section['handle'],
            ];
        }, $sectionsWithUrls);
    }

    /**
     * Generate a random API key via the settings service.
     */
    public function generateKey(): string
    {
        return IndexNow::getInstance()->settingsService->generateKey();
    }
}
