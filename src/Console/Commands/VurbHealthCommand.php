<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Vinkius\Vurb\Services\HealthCheck;

class VurbHealthCommand extends Command
{
    protected $signature = 'vurb:health';

    protected $description = 'Check the health of the Vurb daemon and bridge';

    public function handle(HealthCheck $healthCheck): int
    {
        $status = $healthCheck->status();

        // Daemon
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>Daemon</>',
            $status['daemon']['running'] ? '<fg=green>Running</>' : '<fg=red>Stopped</>'
        );

        if ($status['daemon']['process']) {
            $this->components->twoColumnDetail('  PID', (string) ($status['daemon']['process']['pid'] ?? '-'));
        }

        // Node.js
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>Node.js</>',
            $status['node']['available'] ? '<fg=green>' . $status['node']['version'] . '</>' : '<fg=red>Not found</>'
        );

        // Bridge
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>Bridge</>',
            $status['bridge']['reachable'] ? '<fg=green>Reachable</>' : '<fg=red>Unreachable</>'
        );
        $this->components->twoColumnDetail('  URL', $status['bridge']['base_url'] ?? '-');

        // Tools
        $toolCount = $status['tools']['count'] ?? 0;
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>Tools</>',
            "{$toolCount} registered"
        );

        // Config
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>Config</>',
            ''
        );
        $this->components->twoColumnDetail('  Transport', $status['config']['transport'] ?? '-');
        $this->components->twoColumnDetail('  Token', ($status['config']['token_set'] ?? false) ? '<fg=green>Set</>' : '<fg=red>Not set</>');
        $this->components->twoColumnDetail('  Manifest', $status['config']['manifest_path'] ?? '-');

        // Overall
        $this->newLine();
        $healthy = $healthCheck->check();

        if ($healthy) {
            $this->components->info('All systems operational.');
            return self::SUCCESS;
        }

        $this->components->warn('Some checks failed. See details above.');
        return self::FAILURE;
    }
}
