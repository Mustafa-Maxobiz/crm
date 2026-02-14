<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-model')]
class ModelMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-model {name} {package} {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        parent::handle();

        $this->call('package:make-model-proxy', [
            'name'    => $this->argument('name').'Proxy',
            'package' => $this->argument('package'),
            '--force' => $this->option('force'),
        ]);

        $this->call('package:make-model-contract', [
            'name'    => $this->argument('name'),
            'package' => $this->argument('package'),
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStubContents(): string
    {
        return $this->packageGenerator->getStubContents('model', $this->getStubVariables());
    }

    /**
     * Get the stub variables.
     */
    protected function getStubVariables(): array
    {
        return [
            'PACKAGE'   => $this->getClassNamespace($this->argument('package')),
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
