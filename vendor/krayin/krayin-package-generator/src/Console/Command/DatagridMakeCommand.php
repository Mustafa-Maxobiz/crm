<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-datagrid')]
class DatagridMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-datagrid {name} {package} {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Datagrid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new datagrid.';

    /**
     * Get the stub file for the generator.
     */
    protected function getStubContents(): string
    {
        return $this->packageGenerator->getStubContents('datagrid', $this->getStubVariables());
    }

    /**
     * Get the stub variables.
     */
    protected function getStubVariables(): array
    {
        return [
            'NAMESPACE' => $this->getClassNamespace($this->argument('package').'/DataGrids'),
            'CLASS'     => $this->getClassName(),
        ];
    }

    /**
     * Get the source file path.
     */
    protected function getSourceFilePath(): string
    {
        $path = base_path('packages/'.$this->argument('package')).'/src/DataGrids';

        return "$path/{$this->getClassName()}.php";
    }
}
