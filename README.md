# request/steward-laravel

Steward by Request agent for Laravel sites. Every day it reports the PHP version and the installed version of each package the application requires directly, plus any drift from `composer.lock`. Read-only: it changes nothing and never updates itself.

## Install

```bash
composer require request/steward-laravel
```

Add to `.env` (values from Steward: admin → application → environments → **Agent credentials**):

```dotenv
STEWARD_URL=https://steward.request.pt
STEWARD_AGENT_ID=...
STEWARD_AGENT_SECRET=...
```

The report runs daily from the Laravel scheduler (04:15 by default, `STEWARD_REPORT_AT` to change, empty to disable). To report right after a deploy, add `php artisan steward:report` to the deploy script.
