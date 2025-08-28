<?php

namespace goodmorning\craftindexnow\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Key controller
 */
class KeyController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * indexnow/key action
     */
    public function actionIndex(): Response
    {
        $settings = Craft::$app->getPlugins()->getPlugin('indexnow')->getSettings();
        $storedKey = $settings->apiKey ?? '';

        if (!$storedKey) {
            return $this->asErrorJson('API key not set.');
        }

        // return text file with the API key
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain');
        $response->data = $storedKey;
        return $response;
    }
}
