<?php

namespace Lossik\Runner;

use Nette\DI\CompilerExtension;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Schema\Helpers;

class RunnerExtension extends CompilerExtension
{
    /**
     * @var array<string>
     */
    private array $includedFiles = [];

    public function loadConfiguration()
    {
        $container = $this->getContainerBuilder();
        /** @var array<string, mixed> $config no getConfigSchema() override: the default Expect::array() yields an array */
        $config = $this->getConfig();

        $config = $this->expandConfigInclude($config);

        $container->addDefinition($this->prefix('lossik.runner'))
            ->setType(Runner::class)->setFactory(Runner::class, [$config])
            ->addSetup('$di', ['@container']);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function expandConfigInclude(array $config): array
    {
        if (!isset($config['include'])) {
            return $config;
        }
        $include = is_string($config['include']) ? [$config['include']] : $config['include'];
        $incFiles = $this->getFilesFromIncluded($include);
        unset($config['include']);
        $neon = new NeonAdapter();
        foreach ($incFiles as $file) {
            if (!in_array($file, $this->includedFiles)) {
                $this->compiler->addDependencies([$file]);
                $loaded = $neon->load($file);
                // a string include must be a list before the merge, which concatenates only list + list
                if (isset($loaded['include']) && is_string($loaded['include'])) {
                    $loaded['include'] = [$loaded['include']];
                }
                /** @var array<string, mixed> $config merge of two arrays is an array */
                $config = Helpers::merge($loaded, $config);
                $this->includedFiles[] = $file;
            }
        }

        return $this->expandConfigInclude($config);
    }

    /**
     * @param array<string> $included
     * @return array<string>
     */
    protected function getFilesFromIncluded(array $included): array
    {
        $files = [];
        foreach ($included as $include) {
            if (is_dir($include)) {
                $this->compiler->addDependencies([realpath($include)]);
                foreach (scandir($include) as $file) {
                    if ($file == '..' || $file == '.' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) != 'neon') {
                        continue;
                    }
                    $path = rtrim($include, '/\\') . '/' . $file;
                    if (is_file($path)) {
                        $files[] = realpath($path);
                    }
                }
            }
            if (is_file($include) && strtolower(pathinfo($include, PATHINFO_EXTENSION)) == 'neon') {
                $files[] = realpath($include);
            }
        }

        return $files;
    }
}
