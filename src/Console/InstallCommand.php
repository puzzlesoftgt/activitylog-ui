<?php

namespace Puzzlesoft\ActivitylogUi\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'activitylog-ui:install';

    protected $description = 'Publish the Activitylog UI configuration and assets';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'activitylog-ui-config']);
        $this->call('vendor:publish', ['--tag' => 'activitylog-ui-assets', '--force' => true]);

        $this->components->info('Activitylog UI installed. Visit /'.trim(config('activitylog-ui.route.prefix', 'activitylog'), '/'));
        $this->components->warn('Register ActivitylogUi::auth() in your AppServiceProvider to allow access outside the local environment.');

        return self::SUCCESS;
    }
}
