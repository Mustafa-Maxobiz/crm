<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-route')]
class RouteMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-route {package} {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Route';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new route file.';

    /**
     * Get the stub file for the generator.
     */
    protected function getStubContents(): string
    {
        return $this->packageGenerator->getStubContents('routes', $this->getStubVariables());
    }

    /**
     * Get the stub variables.
     */
    protected function getStubVariables(): array
    {
        return [
            'CONTROLLER_CLASS_NAME' => $this->getClassNamespace($this->argument('package').'/Http/Controllers/'.$this->getStudlyName().'Controller'),
            'LOWER_NAME'            => $this->getLowerName(),
            'CLASS_NAME'            => "{$this->getStudlyName()}Controller",
        ];
    }

    /**
     * Get the source file path.
     */
    protected function getSourceFilePath(): string
    {
        $path = base_path('packages/'.$this->argument('package')).'/src/Routes';

        return "$path/web.php";
    }
}
