<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GenerateCrudStarter extends Command
{
    protected $signature = 'make:crud {name}';
    protected $description = 'Create a new CRUD starter setup for a given entity';

    public function handle(): int
    {
        $name = $this->argument('name');
        $snakeName = Str::snake($name);

        $this->info("Creating CRUD for {$name}");

        $tasks = [
            'migration' => ['make:migration', ['name' => "create_{$snakeName}_table"]],
            'model' => ['make:model', ['name' => $name]],
            'controller' => [
                'make:controller',
                [
                    'name' => "Api/V1/{$name}/{$name}Controller",
                    '--api' => true,
                    '--model' => $name,
                ],
            ],

            // Separate requests
            'insert_request' => ['make:request', ['name' => "{$name}/{$name}InsertRequest"]],
            'update_request' => ['make:request', ['name' => "{$name}/{$name}UpdateRequest"]],

            'resource' => ['make:resource', ['name' => "{$name}/{$name}Resource"]],
            'policy' => ['make:policy', ['name' => "{$name}Policy", '--model' => $name]],
            'repository' => ['make:repo', ['repository' => "{$name}/{$name}Repository"]],
            'service' => ['make:service', ['service' => "{$name}/{$name}Service"]],
            'dto' => ['make:dto', ['dto' => "{$name}/{$name}DTO"]],
            'route' => ['make:route', ['name' => $name]],
            'feature_test' => ['make:test', ['name' => "{$name}FeatureTest"]],
            'unit_test' => ['make:test', ['name' => "{$name}UnitTest", '--unit' => true]],
        ];

        foreach ($tasks as $taskName => [$command, $arguments]) {
            $this->callArtisanCommand($taskName, $command, $arguments);
        }

        $this->info('CRUD starter created successfully!');

        return self::SUCCESS;
    }

    private function callArtisanCommand(string $taskName, string $command, array $arguments): void
    {
        try {
            $exitCode = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());

            if ($output !== '') {
                $this->line($output);
            }

            if ($exitCode !== 0) {
                $this->error("{$taskName} failed with exit code {$exitCode}");
            }
        } catch (\Throwable $e) {
            $this->error("Error creating {$taskName}: {$e->getMessage()}");
        }
    }
}
