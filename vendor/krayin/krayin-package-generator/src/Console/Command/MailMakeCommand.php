<?php

namespace Webkul\PackageGenerator\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'package:make-mail')]
class MailMakeCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:make-mail {name} {package} {--force}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Mailable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new mail.';

    /**
     * Get the stub file for the generator.
     */
    protected function getStubContents(): string
    {
        return $this->packageGenerator->getStubContents('mail', $this->getStubVariables());
    }

    /**
     * Get the stub variables.
     */
    protected function getStubVariables(): array
    {
        return [
            'NAMESPACE'  => $this->getClassNamespace($this->argument('package').'/Mails'),
            'CLASS'      => $this->getClassName(),
            'LOWER_NAME' => $this->getLowerName(),
        ];
    }

    /**
     * Get the source file path.
     */
    protected function getSourceFilePath(): string
    {
        $path = base_path('packages/'.$this->argument('package')).'/src/Mails';

        return "$path/{$this->getClassName()}.php";
    }
}
