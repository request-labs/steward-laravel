# request-labs/steward-laravel

Steward by Request agent for Laravel sites. Every day it reports the PHP version and the installed version of every package in `composer.lock`, marking those the application requires directly (Steward lists only these, and checks every package for known vulnerabilities), plus any drift from `composer.lock` in the direct ones and, when the application is a git checkout, the deployed commit (Steward records each new commit as a deploy). Read-only: it changes nothing and never updates itself.

## Install

```bash
composer require request-labs/steward-laravel
```

Add to `.env` (values from Steward: admin → application → environments → **Agent credentials**):

```dotenv
STEWARD_URL=https://steward.request.pt
STEWARD_AGENT_ID=...
STEWARD_AGENT_SECRET=...
```

The report runs daily from the Laravel scheduler (04:15 by default, `STEWARD_REPORT_AT` to change, empty to disable). To report right after a deploy, so Steward records it within seconds instead of the next morning, add `php artisan steward:report` to the end of the deploy script.

## Development

The source lives in the Steward repository under `packages/steward-laravel`; this repository is a read-only mirror published with `git subtree`. Releases are tagged here with the same number as `ReportBuilder::VERSION`.
