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
        $config = $this->getConfig();

        $config = $this->expandConfigInclude($config);

        $container->addDefinition($this->prefix('lossik.runner'))
            ->setType(Runner::class)->setFactory(Runner::class, [$config])
            ->addSetup('$di', ['@container']);
    }

    /**
     * @param array $config
     * @return array
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
                $config = Helpers::merge($neon->load($file), $config);
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
