<?php

return [

    /*
    | Steward by Request agent. Credentials come from Steward (admin → application →
    | environments → Agent credentials). Leave them empty and the agent stays silent.
    */

    'url' => env('STEWARD_URL', 'https://steward.request.pt'),
    'agent_id' => env('STEWARD_AGENT_ID'),
    'agent_secret' => env('STEWARD_AGENT_SECRET'),

    // Report every day at this time (server timezone); set to null to schedule it yourself.
    'daily_at' => env('STEWARD_REPORT_AT', '04:15'),

];
