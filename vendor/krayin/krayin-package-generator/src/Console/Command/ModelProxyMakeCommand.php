<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-model-proxy')]
class ModelProxyMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-model-proxy {name} {package} {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model Proxy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model proxy.';

    /**
     * Get the stub file for the generator.
     */
    protected function getStubContents(): string
    {
        return $this->packageGenerator->getStubContents('model-proxy', $this->getStubVariables());
    }

    /**
     * Get the stub variables.
     */
    protected function getStubVariables(): array
    {
        return [
            'NAMESPACE' => $this->getClassNamespace($this->argument('package').'/Models'),
            'CLASS'     => $this->getClassName(),
        ];
    }

    /**
     * Get the source file path.
     */
    protected function getSourceFilePath(): string
    {
        $path = base_path('packages/'.$this->argument('package')).'/src/Models';

        return "$path/{$this->getClassName()}.php";
    }
}
