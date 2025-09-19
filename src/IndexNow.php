<?php

namespace goodmorning\craftindexnow;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\App;
use craft\services\UserPermissions;
use craft\services\Utilities;

use goodmorning\craftindexnow\models\Settings;

use goodmorning\craftindexnow\services\EntryService;
use goodmorning\craftindexnow\services\SendUrls;
use goodmorning\craftindexnow\services\SettingsService;
use goodmorning\craftindexnow\twigextensions\Settings as SettingsAlias;
use goodmorning\craftindexnow\utilities\IndexNowUtility;
use yii\base\Event;

/**
 * IndexNow plugin
 *
 * @method static IndexNow getInstance()
 * @method Settings getSettings()
 * @author Good Morning <tech@goodmorning.no>
 * @copyright Good Morning
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read EntryService $entry
 * @property-read SendUrls $sendUrls
 * @property-read SettingsService $settingsService
 */
class IndexNow extends Plugin
{
  public string $schemaVersion = '1.0.0';
  public bool $hasCpSettings = true;

  public static function config(): array
  {
    return [
      'components' => [
        'entry' => EntryService::class,
        'settingsService' => SettingsService::class,
        'sendUrls' => SendUrls::class,
      ],
    ];
  }

  public function init(): void
  {
    parent::init();

    $storedKey = $this->getSettings()->apiKey;
    if ($storedKey) {
      // Register custom route using the stored key
      Craft::$app->getUrlManager()->addRules([
        [
          'pattern' => $storedKey . '.txt',
          'route' => 'indexnow/key',
        ],
      ], false);
    }

    $this->attachEventHandlers();

    // Any code that creates an element query or loads Twig should be deferred until
    // after Craft is fully initialized, to avoid conflicts with other plugins/modules
    Craft::$app->onInit(function () {});
    // register twig extensions
    Craft::$app->view->registerTwigExtension(new SettingsAlias());
    // register utilities
    $this->registerUtilities();
  }

  protected function createSettingsModel(): ?Model
  {
    return Craft::createObject(Settings::class);
  }

  protected function settingsHtml(): ?string
  {
    return Craft::$app->view->renderTemplate('indexnow/_settings.twig', [
      'plugin' => $this,
      'settings' => $this->getSettings(),
    ]);
  }

  private function attachEventHandlers(): void
  {
    Event::on(
      UserPermissions::class,
      UserPermissions::EVENT_REGISTER_PERMISSIONS,
      function (RegisterUserPermissionsEvent $event) {
        $event->permissions[] = [
          'heading' => 'IndexNow',
          'permissions' => [
            'indexnow-accessSettings' => [
              'label' => 'Access IndexNow Settings',
            ],
          ],
        ];
      }
    );

    $environment = App::env('CRAFT_ENVIRONMENT');
    $setEnvironment = $this->getSettings()->environment;
    // only run when environment matches the set environment
    if ($environment === $setEnvironment) {
      IndexNow::getInstance()->entry->init();
    }
  }

  private function registerUtilities(): void
  {
    Event::on(
      Utilities::class,
      Utilities::EVENT_REGISTER_UTILITIES,
      function (RegisterComponentTypesEvent $event) {
        $event->types[] = IndexNowUtility::class;
      }
    );
  }
}
