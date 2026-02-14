<?php

namespace Webkul\PackageGenerator\Console\Command;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Webkul\PackageGenerator\Generators\PackageGenerator;

class MakeCommand extends Command implements PromptsForMissingInput
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected Filesystem $filesystem,
        protected PackageGenerator $packageGenerator
    ) {
        parent::__construct();

        $this->filesystem = $filesystem;

        $this->packageGenerator = $packageGenerator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->getSourceFilePath();

        if (! $this->filesystem->isDirectory($dir = dirname($path))) {
            $this->filesystem->makeDirectory($dir, 0777, true);
        }

        $contents = $this->getStubContents();

        if (! $this->filesystem->exists($path)) {
            $this->filesystem->put($path, $contents);
        } else {
            if ($this->option('force')) {
                $this->filesystem->put($path, $contents);
            } else {
                $this->components->error(sprintf('%s [%s] already exists.', $this->type, $path));

                return;
            }
        }

        $this->components->info(sprintf('%s [%s] created successfully.', $this->type, $path));
    }

    /**
     * Get name in studly case.
     */
    public function getStudlyName(): string
    {
        return class_basename($this->argument('package'));
    }

    /**
     * Get name in lower case.
     */
    protected function getLowerName(): string
    {
        return strtolower($this->getStudlyName());
    }

    /**
     * Get the class name.
     */
    protected function getClassName(): string
    {
        return class_basename($this->argument('name'));
    }

    /**
     * Get the class namespace.
     */
    protected function getClassNamespace(string $name): array|string
    {
        return str_replace('/', '\\', $name);
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => [
                'What should the '.strtolower($this->type).' be named?',
                match ($this->type) {
                    'Console command' => 'E.g. SendEmails',
                    'Controller'      => 'E.g. UserController',
                    'Datagrid'        => 'E.g. ProductDatagrid',
                    'Event'           => 'E.g. PodcastProcessed',
                    'Listener'        => 'E.g. SendPodcastNotification',
                    'Mailable'        => 'E.g. OrderShipped',
                    'Middleware'      => 'E.g. EnsureTokenIsValid',
                    'Migration'       => 'E.g. create_flights_table',
                    'Model'           => 'E.g. Flight',
                    'Model Proxy'     => 'E.g. FlightProxy',
                    'Contract'        => 'E.g. Flight',
                    'Module Provider' => 'E.g. ModuleServiceProvider',
                    'Provider'        => 'E.g. ElasticServiceProvider',
                    'Notification'    => 'E.g. InvoicePaid',
                    'Repository'      => 'E.g. UserRepository',
                    'Request'         => 'E.g. StorePodcastRequest',
                    'Route'           => 'E.g. web',
                    'Seeder'          => 'E.g. UserSeeder',
                    default           => '',
                },
            ],
        ];
    }
}
