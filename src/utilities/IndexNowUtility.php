<?php

namespace goodmorning\craftindexnow\utilities;

use Craft;
use craft\base\Utility;

class IndexNowUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('indexnow', 'IndexNow Utility');
    }

    public static function id(): string
    {
        return 'indexnow-utility';
    }

    public static function iconPath(): ?string
    {
        return Craft::getAlias('@goodmorning/craftindexnow/icon.svg');
    }

    public static function contentHtml(): string
    {
        return Craft::$app->view->renderTemplate('indexnow/utility/index');
    }
}
