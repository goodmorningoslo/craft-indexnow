<?php

namespace goodmorning\craftindexnow\controllers;

use Craft;
use craft\web\Controller;
use goodmorning\craftindexnow\IndexNow;

use yii\web\Response;

/**
 * Key controller
 */
class IndexNowController extends Controller
{
  public $defaultAction = 'index';
  protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

  /**
   * indexnow/key action
   */
  public function actionIndex(): Response
  {
    IndexNow::getInstance()->entry->pushAllEntries();

    // return to the previous page and set a snackbar message
    Craft::$app->getSession()->setFlash('success', Craft::t('indexnow', 'All URLs have been sent to IndexNow.'));
    return $this->redirect(Craft::$app->getRequest()->getReferrer() ?: Craft::$app->getHomeUrl());
  }
}
