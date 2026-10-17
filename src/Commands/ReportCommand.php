<?php

namespace Request\StewardLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Request\StewardLaravel\ReportBuilder;

/**
 * Sends the report, signed with the environment secret: HMAC-SHA256 of
 * "{timestamp}.{body}". Read-only: nothing on the site changes.
 */
class ReportCommand extends Command
{
    protected $signature = 'steward:report';

    protected $description = 'Send installed package versions to Steward by Request';

    public function handle(): int
    {
        $url = config('steward-agent.url');
        $agentId = config('steward-agent.agent_id');
        $secret = config('steward-agent.agent_secret');

        if (blank($agentId) || blank($secret)) {
            $this->components->warn('STEWARD_AGENT_ID and STEWARD_AGENT_SECRET are not set; nothing sent.');

            return self::SUCCESS;
        }

        $body = json_encode((new ReportBuilder(base_path()))->build(), JSON_THROW_ON_ERROR);
        $timestamp = time();

        $response = Http::withBody($body, 'application/json')
            ->acceptJson()
            ->timeout(15)
            ->withHeaders([
                'X-Steward-Agent' => $agentId,
                'X-Steward-Timestamp' => (string) $timestamp,
                'X-Steward-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
            ])
            ->post(rtrim($url, '/').'/api/agent/v1/report');

        if (! $response->successful()) {
            $this->components->error("Steward answered {$response->status()}: ".$response->body());

            return self::FAILURE;
        }

        $this->components->info('Report sent to Steward.');

        return self::SUCCESS;
    }
}
