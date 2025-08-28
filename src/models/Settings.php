<?php

namespace goodmorning\craftindexnow\models;

use craft\base\Model;

/**
 * IndexNow settings
 */
class Settings extends Model
{
    /**
     * @var array Sections to include in IndexNow
     */
    public $sections = [];

    /**
     * @var string API key for IndexNow
     */
    public $apiKey = '';

    /**
     * @var string Environment for IndexNow
     */
    public $environment = 'production';

    /**
     * @var bool Whether to perform a dry run (log only, no real HTTP call)
     */
    public $dryRun = false;

    /**
     * @var string|null Optional override for the IndexNow endpoint (useful for RequestBin / Pipedream)
     */
    public $endpointOverride = null;

    /**
     * @var bool Whether to log the payload being sent
     */
    public $logPayload = false;

    /**
     * Define validation rules for the model attributes.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
          [['sections'], 'safe'],
          [['apiKey'], 'string'],
          [['apiKey'], 'required'],
          [['environment'], 'string'],
      [['dryRun', 'logPayload'], 'boolean'],
      [['endpointOverride'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
      ];
    }
}
