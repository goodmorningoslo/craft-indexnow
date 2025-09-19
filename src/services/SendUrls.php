<?php

namespace goodmorning\craftindexnow\services;

use Craft;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use yii\base\Component;

/**
 * Send Urls service
 */
class SendUrls extends Component
{
  /**
   * Sends URLs to IndexNow.
   *
   * @param array $urls
   * @return void
   */
  public function sendUrls(array $urls): void
  {
    $settings = Craft::$app->getPlugins()->getPlugin('indexnow')->getSettings();
    $apiKey = $settings->apiKey ?? '';
    $logPayload = $settings->logPayload ?? false;
    $dryRun = $settings->dryRun ?? false;

    if (empty($apiKey)) {
      Craft::error('API key is not set for IndexNow.', __METHOD__);
      return;
    }

    $hostInfo = Craft::$app->getRequest()->getHostInfo();
    $hostOnly = parse_url($hostInfo, PHP_URL_HOST) ?: $hostInfo;

    $keyLocation = $settings->keyLocationOverride ?: rtrim($hostInfo, '/') . '/' . $apiKey . '.txt';

    $payload = [
      'host' => $hostOnly,
      'key' => $apiKey,
      'keyLocation' => $keyLocation,
      'urlList' => array_values(array_unique($urls)),
    ];

    if ($logPayload) {
      Craft::info('IndexNow payload: ' . json_encode($payload), __METHOD__);
    }

    if ($dryRun) {
      Craft::info('IndexNow dry run enabled: not sending HTTP request.', __METHOD__);
      return; // treat as success but skip network
    }

    try {
      $response = $this->_sendRequest($payload);

      if ($response) {
        Craft::info('Successfully sent URLs to IndexNow.', __METHOD__);
      } else {
        Craft::error('Failed to send URLs to IndexNow.', __METHOD__);
      }
    } catch (\Exception $e) {
      Craft::error('Error sending URLs to IndexNow: ' . $e->getMessage(), __METHOD__);
    }
  }

  /**
   * Sends the request to IndexNow.
   *
   * @param array $payload
   * @return bool True if the request was sent successfully (2xx and no error field), false otherwise.
   */
  private function _sendRequest(array $payload): bool
  {
    $settings = Craft::$app->getPlugins()->getPlugin('indexnow')->getSettings();
    $endpointOverride = $settings->endpointOverride ?? '';
    $url = $endpointOverride ?: 'https://api.indexnow.org/indexnow';
    if ($endpointOverride) {
      Craft::info('Using IndexNow endpoint override: ' . $url, __METHOD__);
    }

    // Build a Guzzle client via Craft helper (inherits Craft defaults / proxy etc.)
    $client = Craft::createGuzzleClient([
      'timeout' => 10.0, // seconds
      'headers' => [
        'Accept' => 'application/json, */*',
        'Content-Type' => 'application/json',
        'User-Agent' => 'CraftCMS IndexNow Plugin',
      ],
    ]);

    // Allow a minimal retry for transient network errors (1 extra attempt)
    $attempts = 0;
    $maxAttempts = 2; // initial + 1 retry
    $lastError = null;

    while ($attempts < $maxAttempts) {
      $attempts++;
      try {
        $response = $client->post($url, [
          'json' => $payload,
          // Do not throw on 4xx/5xx so we can log body
          'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        $bodySnippet = substr($body, 0, 500);

        // IndexNow spec typically returns 200 or 202; treat any 2xx as success
        if ($statusCode < 200 || $statusCode >= 300) {
          Craft::error("IndexNow non-success status {$statusCode} body: {$bodySnippet}", __METHOD__);
          return false;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error'])) {
          Craft::error('Error from IndexNow: ' . json_encode($decoded['error']), __METHOD__);
          return false;
        }

        Craft::info('IndexNow response status ' . $statusCode . ' body snippet: ' . $bodySnippet, __METHOD__);
        return true;
      } catch (ConnectException $e) {
        $lastError = $e->getMessage();
        Craft::warning('IndexNow connection issue attempt ' . $attempts . ': ' . $lastError, __METHOD__);
        // brief backoff before retry (non-blocking heavy sleep avoided; short usleep)
        usleep(150000); // 150ms
      } catch (RequestException $e) {
        // Request reached server or failed earlier; no benefit retrying most of these
        $lastError = $e->getMessage();
        Craft::error('IndexNow request exception: ' . $lastError, __METHOD__);
        return false;
      } catch (\Throwable $e) {
        $lastError = $e->getMessage();
        Craft::error('Unexpected error sending to IndexNow: ' . $lastError, __METHOD__);
        return false;
      }
    }

    Craft::error('Failed to send URLs to IndexNow after retries. Last error: ' . $lastError, __METHOD__);
    return false;
  }
}
