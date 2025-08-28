# IndexNow for Craft CMS

IndexNow integration for Craft CMS.  
This plugin automatically (or programmatically) notifies participating search engines when content on your site changes, so they can crawl and update search results faster.

## What is IndexNow?

[IndexNow](https://www.indexnow.org/) is an open protocol originally backed by Microsoft Bing and Yandex.  
Instead of waiting for search engines to discover changed content via periodic crawling, your site **pushes** a small JSON payload listing new / updated / deleted URLs. Benefits:

- Faster reflection of fresh or removed content
- Reduced unnecessary crawl load
- Lightweight (simple POST with JSON)
- Privacy-friendly (you choose what to send)

The core endpoint used (by default) is: `https://api.indexnow.org/indexnow`, which proxies to participating engines.

## Features

- 🔑 Automatic key verification route: `https://your-site/<apiKey>.txt`
- 🛰️ URL submission service with retry & logging
- 🧪 Dry run mode for safe testing (no external HTTP requests)
- 🌐 Environment gating (only activate on selected environment)
- 🧩 Twig extension (exposes settings for templates)
- 🛠️ Control panel utility (`Utilities > IndexNow`)
- 👤 Permission: restrict access to plugin settings (`indexnow-accessSettings`)
- 🧾 Optional request payload logging (for audits / debugging)
- 🔌 Endpoint override (point to a mock server or future protocol changes)

## Requirements

- Craft CMS 5.7.0+
- PHP 8.2+
- OpenSSL / cURL enabled (default Craft HTTP stack requirements)
- Outbound HTTPS allowed to `api.indexnow.org` (or your override)


## Installation

### From the plugin store (recommended)

1. In the control panel, go to plugin store → search for “indexnow”.
2. Click install.

### Via Composer

```bash
# From your Craft project root
composer require goodmorning/craft-indexnow
php craft plugin/install craft-indexnow
```

## Configuration & settings

You can configure settings in the control panel (Settings → Plugins → IndexNow) or optionally via a config file override (`config/indexnow.php`).

### Current settings (based on code)

| Setting            | Type    | Default     | Description |
|--------------------|---------|-------------|-------------|
| `apiKey`           | string  | (none)      | Secret key used both for verification file route and payload authentication. Required for submissions. |
| `environment`      | string  | `production`| Environment name in which the plugin should actively initialize URL submission logic. |
| `logPayload`       | bool    | `false`     | If true, logs the full JSON payload prior to sending (use with caution in production). |
| `dryRun`           | bool    | `false`     | If true, skips the outbound HTTP request but logs as if it would submit (useful for testing). |
| `endpointOverride` | string  | (empty)     | Alternate IndexNow endpoint (e.g. staging / mock server). If set, plugin logs the override. |

### Config file example (`config/indexnow.php`)

Create this file if you’d like to lock or override settings from code (these values will typically override CP-edited values depending on how your Settings model is implemented):

```php
<?php
return [
    'apiKey' => App::env('INDEXNOW_API_KEY'),
    'environment' => 'staging,production',
    'logPayload' => false,
    'dryRun' => false,
    'endpointOverride' => null,
];
```

Make sure to add `INDEXNOW_API_KEY` to your `.env`:

```
INDEXNOW_API_KEY=your-generated-key
```

## Key verification file

Once the `apiKey` is saved, the plugin dynamically registers a route:

```
https://your-domain/<apiKey>.txt
```

This route should return the key content — search engines request it to verify ownership.

Test it after saving the key:

```bash
curl -i https://example.com/YOURKEYVALUE.txt
```

Expect: `HTTP/1.1 200 OK` and body containing the key.

## Usage

### Automatic Submission

This plugin listens for element events and collects URLs to send. Ensure only public, canonical URLs are submitted (avoids drafts / revisions).

### Manual submission (programmatic)

From custom module / template / console command:

```php
use goodmorning\craftindexnow\IndexNow;

/** @var \goodmorning\craftindexnow\services\SendUrls $sender */
$sender = IndexNow::getInstance()->sendUrls;

$sender->sendUrls([
    'https://example.com/articles/new-thing',
    'https://example.com/articles/another-thing',
]);
```

The service will:
1. Build payload (`host`, `key`, `urlList`)
2. Optionally log payload
3. POST JSON to endpoint (with retry on transient connect issues)
4. Log success or failure

## Dry run mode

Enable `dryRun` to validate integration without network side effects.  
The plugin:

- Builds & logs payload
- Skips HTTP request
- Logs: “IndexNow dry run enabled: not sending HTTP request.”

Use this in early staging or while finalizing API key ownership.

## Endpoint override

Useful for:

- Mock servers
- Future protocol changes
- Internal relay / firewall

Set `endpointOverride` to a full URL. Logged on use:

```
Using IndexNow endpoint override: https://internal-mock/index
```

## Troubleshooting

| Problem | Possible Cause | Resolution |
|---------|----------------|-----------|
| Verification file 404 | API key not saved, route cache not cleared | Re-save settings, flush caches |
| “API key is not set” error | Missing key | Add key in settings or config file |
| Repeated failures sending | Network firewall / blocked endpoint | Test with `curl`, whitelist domain |
| Payload logs missing | `logPayload` is false | Enable in settings temporarily |
| Works in dev, not in prod | Environment mismatch | Check `CRAFT_ENVIRONMENT` and `environment` setting |
| Duplicate submissions | Entry events fired multiple times (e.g., drafts) | Add guards in `EntryService` (publish status, duplicates suppression) |

## Security considerations

- Avoid enabling `logPayload` long-term in production; logs may reveal content timing.
- Treat `endpointOverride` carefully — don’t point to untrusted hosts.

## Roadmap / ideas

- Configurable batch size and debounce window
- Console command: `php craft indexnow/send <url>`
- Resend last N changed entries from Utility
- Multiple environment support
- Metrics panel (success vs failure counts)

(Open issues / PRs are welcome.)

## Changelog

See [`CHANGELOG.md`](./CHANGELOG.md) for release history (add entries there before tagging).

## License

Distributed under the Craft license (or update this if you choose another).  
See [`LICENSE.md`](./LICENSE.md).

## Support

- Email: tech@goodmorning.no
- Issues: (add GitHub issues URL once public)

## Quick start

1. Install plugin
2. Set API key
3. Hit `https://site/<apiKey>.txt` to verify
4. Ensure environment matches (`CRAFT_ENVIRONMENT`)
5. Save / update content
6. Check logs for “Successfully sent URLs to IndexNow.”

Happy indexing!