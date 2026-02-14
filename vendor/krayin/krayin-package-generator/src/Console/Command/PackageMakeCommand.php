<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make')]
class PackageMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make {package} {--plain}  {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Package';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new package.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->packageGenerator
            ->setPackageGenerator($this)
            ->setConsole($this->components)
            ->setPackage($this->argument('package'))
            ->setPlain($this->option('plain'))
            ->setForce($this->option('force'))
            ->generate();
    }
}
