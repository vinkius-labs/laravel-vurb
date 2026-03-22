<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Vinkius\Vurb\Governance\LockfileGenerator;

class VurbLockCommand extends Command
{
    protected $signature = 'vurb:lock
        {--check : Verify the lockfile matches current state (CI gate)}
        {--path= : Custom lockfile path}';

    protected $description = 'Generate or verify the vurb.lock governance file';

    public function handle(LockfileGenerator $generator): int
    {
        $path = $this->option('path');

        if ($this->option('check')) {
            return $this->checkLockfile($generator, $path);
        }

        return $this->generateLockfile($generator, $path);
    }

    protected function generateLockfile(LockfileGenerator $generator, ?string $path): int
    {
        $writtenPath = $generator->generateAndWrite($path);
        $this->components->info("Lockfile generated: {$writtenPath}");

        return self::SUCCESS;
    }

    protected function checkLockfile(LockfileGenerator $generator, ?string $path): int
    {
        $lockfilePath = $path ?? base_path('vurb.lock');

        if (! file_exists($lockfilePath)) {
            $this->components->error("Lockfile not found: {$lockfilePath}");
            $this->components->info('Run <comment>php artisan vurb:lock</comment> to generate one.');
            return self::FAILURE;
        }

        $matches = $generator->check($path);

        if ($matches) {
            $this->components->info('Lockfile is up to date.');
            return self::SUCCESS;
        }

        $this->components->error('Lockfile mismatch! The tool surface has changed since the last lock.');
        $this->components->info('Run <comment>php artisan vurb:lock</comment> to update, then commit vurb.lock.');

        return self::FAILURE;
    }
}
