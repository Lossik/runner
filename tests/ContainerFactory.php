<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\DecoratorExtension;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\Extensions\InjectExtension;

final class ContainerFactory
{
    /** @var array<int, string> */
    private static array $dirs = [];

    public static function create(string $neon, bool $injectExtension = true): Container
    {
        $dir = sys_get_temp_dir() . '/runner-tests-' . uniqid();
        mkdir($dir . '/cache', 0777, true);
        self::$dirs[] = $dir;
        file_put_contents($dir . '/config.neon', $neon);

        $loader = new ContainerLoader($dir . '/cache', true);
        $class = $loader->load(
            static function (Compiler $compiler) use ($dir, $injectExtension): void {
                $compiler->addExtension('extensions', new ExtensionsExtension());
                $compiler->addExtension('decorator', new DecoratorExtension());
                if ($injectExtension) {
                    $compiler->addExtension('inject', new InjectExtension());
                }
                $compiler->loadConfig($dir . '/config.neon');
            },
            basename($dir),
        );

        return new $class();
    }

    public static function cleanup(): void
    {
        foreach (self::$dirs as $dir) {
            self::remove($dir);
        }
        self::$dirs = [];
    }

    private static function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) as $item) {
                if ($item !== '.' && $item !== '..') {
                    self::remove($path . '/' . $item);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
