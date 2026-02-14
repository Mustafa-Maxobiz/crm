<?php

namespace Webkul\PackageGenerator\Generators;

use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Webkul\PackageGenerator\Package;

class PackageGenerator
{
    /**
     * The package vendor namespace.
     *
     * @var string
     */
    protected $vendorNamespace;

    /**
     * The package name.
     *
     * @var string
     */
    protected $packageName;

    /**
     * The plain agument.
     *
     * @var bool
     */
    protected $plain;

    /**
     * The argument that make force the overrides.
     *
     * @var bool
     */
    protected $force;

    /**
     * Define the type of package.
     *
     * @var string
     */
    protected $type = 'package';

    /**
     * Contains console instance
     *
     * @var \Illuminate\Console\Command
     */
    protected $console;

    /**
     * Contains generator instance
     *
     * @var \Webkul\PackageGenerator\Console\Command
     */
    protected $generator;

    /**
     * Contains subs files information
     *
     * @var array
     */
    protected $stubFiles = [
        'package'  => [
            'views/components/layouts/style'             => 'Resources/views/components/layouts/style.blade.php',
            'views/index'                                => 'Resources/views/index.blade.php',
            'scaffold/menu'                              => 'Config/menu.php',
            'scaffold/acl'                               => 'Config/acl.php',
            'assets/js/app'                              => 'Resources/assets/js/app.js',
            'assets/css/app'                             => 'Resources/assets/css/app.css',
            'assets/images/Icon-Temp'                    => 'Resources/assets/images/Icon-Temp.svg',
            'assets/images/Icon-Temp-Active'             => 'Resources/assets/images/Icon-Temp-Active.svg',
            'package'                                    => '../package.json',
            'vite'                                       => '../vite.config.js',
            'tailwind'                                   => '../tailwind.config.js',
            'postcss'                                    => '../postcss.config.js',
            '.gitignore'                                 => '../.gitignore',
            'composer'                                   => '../composer.json',
        ],
    ];

    /**
     * Contains package file paths for creation
     *
     * @var array
     */
    protected $paths = [
        'package'  => [
            'config'     => 'Config',
            'command'    => 'Console/Commands',
            'migration'  => 'Database/Migrations',
            'seeder'     => 'Database/Seeders',
            'contracts'  => 'Contracts',
            'model'      => 'Models',
            'routes'     => 'Http',
            'controller' => 'Http/Controllers',
            'filter'     => 'Http/Middleware',
            'request'    => 'Http/Requests',
            'provider'   => 'Providers',
            'repository' => 'Repositories',
            'event'      => 'Events',
            'listener'   => 'Listeners',
            'emails'     => 'Mail',
            'assets'     => 'Resources/assets',
            'lang'       => 'Resources/lang',
            'views'      => 'Resources/views',
        ],
    ];

    /**
     * Create a new generator instance.
     *
     * @return void
     */
    public function __construct(
        protected Config $config,
        protected Filesystem $filesystem,
        protected Package $package
    ) {}

    /**
     * Set generator
     */
    public function setPackageGenerator(mixed $generator): self
    {
        $this->generator = $generator;

        return $this;
    }

    /**
     * Set console
     */
    public function setConsole(mixed $console): self
    {
        $this->console = $console;

        return $this;
    }

    /**
     * Set package.
     */
    public function setPackage(mixed $packageName): self
    {
        $this->packageName = $packageName;

        return $this;
    }

    /**
     * Set package plain.
     */
    public function setPlain(mixed $plain): self
    {
        $this->plain = $plain;

        return $this;
    }

    /**
     * Set force status.
     */
    public function setForce(mixed $force): self
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Set type status.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Generate package
     */
    public function generate(): void
    {
        if ($this->package->has($this->packageName)) {
            if ($this->force) {
                $this->package->delete($this->packageName);
            } else {
                $this->console->error(sprintf('Package %s already exist !', $this->packageName));

                return;
            }
        }

        $this->createFolders();

        if (! $this->plain) {
            $this->createFiles();

            $this->createClasses();
        }

        $this->console->info(sprintf('Package %s created successfully.', $this->packageName));
    }

    /**
     * Generate package folders
     */
    public function createFolders(): void
    {
        foreach ($this->paths[$this->type] as $key => $folder) {
            $path = base_path('packages/'.$this->packageName.'/src').'/'.$folder;

            $this->filesystem->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Generate package files
     */
    public function createFiles(): void
    {
        $variables = $this->getStubVariables();

        foreach ($this->stubFiles[$this->type] as $stub => $file) {
            $path = base_path('packages/'.$this->packageName.'/src').'/'.$file;

            if (! $this->filesystem->isDirectory($dir = dirname($path))) {
                $this->filesystem->makeDirectory($dir, 0775, true);
            }

            $this->filesystem->put($path, $this->getStubContents($stub, $variables));

            $this->console->info("Created file : {$path}");
        }
    }

    /**
     * Generate package classes
     */
    public function createClasses(): void
    {
        if ($this->type == 'package') {
            $this->generator->call('package:make-provider', [
                'name'    => $this->packageName.'ServiceProvider',
                'package' => $this->packageName,
            ]);

            $this->generator->call('package:make-module-provider', [
                'name'    => 'ModuleServiceProvider',
                'package' => $this->packageName,
            ]);

            $this->generator->call('package:make-controller', [
                'name'    => $this->packageName.'Controller',
                'package' => $this->packageName,
            ]);

            $this->generator->call('package:make-route', [
                'package' => $this->packageName,
            ]);
        }
    }

    /**
     * Get the variables for the stub file.
     */
    protected function getStubVariables(): array
    {
        return [
            'LOWER_NAME'      => $this->getLowerName(),
            'CAPITALIZE_NAME' => $this->getCapitalizeName(),
            'PACKAGE'         => $this->getClassNamespace($this->packageName),
            'CLASS'           => $this->getClassName(),
        ];
    }

    /**
     * Get the class name of the package.
     */
    protected function getClassName(): string
    {
        return class_basename($this->packageName);
    }

    /**
     * Get the class namespace of the package.
     */
    protected function getClassNamespace($name): array|string
    {
        return str_replace('/', '\\', $name);
    }

    /**
     * Returns content of stub file
     */
    public function getStubContents(string $stub, array $variables = []): mixed
    {
        $path = __DIR__.'/../stubs/'.$stub.'.stub';

        $contents = file_get_contents($path);

        foreach ($variables as $search => $replace) {
            $contents = str_replace('$'.strtoupper($search).'$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * Get the capitalize name of the package.
     */
    protected function getCapitalizeName(): string
    {
        return ucwords(class_basename($this->packageName));
    }

    /**
     * Get the lower name of the package.
     */
    protected function getLowerName(): string
    {
        return strtolower(class_basename($this->packageName));
    }
}
