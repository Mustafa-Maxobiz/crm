<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-migration')]
class MigrationMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-migration {name} {package}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('make:migration', [
            'name'   => $this->argument('name'),
            '--path' => 'packages/'.$this->argument('package').'/src/Database/Migrations',
        ]);
    }
}
