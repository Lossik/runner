# lossik/runner

Runs batch jobs configured in NEON. A job is a processor, an optional source of data and named parameters computed
from that data. The library is a Nette DI extension.

## Installation

```bash
composer require lossik/runner
```

## Supported PHP

| branch | minimum patch version |
|---|---|
| 8.2 | 8.2.35 |
| 8.3 | 8.3.36 |
| 8.4 | 8.4.27 |
| 8.5 | 8.5.12 |

Older patch versions are vulnerable to PHP2Shell (CVE-2026-109618, CVE-2026-109619) and are not supported.
`composer.json` requires only `php: ^8.2`, because distributions backport security fixes without bumping the
version number; the CI enforces the patched minimums above. Projects on older PHP stay on `2.0.x`.

## Quick start

A source returns rows, a parameter turns the rows into one value per row, a processor gets those values:

```php
<?php

namespace App;

use Lossik\Runner\AbstractParameter;
use Lossik\Runner\AbstractProcessor;
use Lossik\Runner\AbstractSource;

final class UserSource extends AbstractSource
{
    public function getSourceData(): array
    {
        return [['id' => 1, 'name' => 'alice'], ['id' => 2, 'name' => 'bob']];
    }
}

final class NameParameter extends AbstractParameter
{
    public function getName(): string
    {
        return 'name';
    }

    public function getParametersItems(array $sourceData): array
    {
        $items = [];
        foreach ($sourceData as $row) {
            $items[$row['id']] = ucfirst($row['name']);
        }

        return $items;
    }
}

final class GreetProcessor extends AbstractProcessor
{
    private string $greeting = 'Hello';

    public function setGreeting(string $greeting): void
    {
        $this->greeting = $greeting;
    }

    public function getParameters(): array
    {
        return ['name'];
    }

    public function getParametersClasses(): array
    {
        return [NameParameter::class];
    }

    public function process(array $parameters): void
    {
        foreach ($parameters as $id => $values) {
            echo $id, ': ', $this->greeting, ', ', $values['name'], "\n";
        }
    }
}
```

Register the extension and configure the job:

```neon
extensions:
    runner: Lossik\Runner\RunnerExtension

runner:
    greet:
        processor:
            class: App\GreetProcessor
            setup:
                - [setGreeting, [Hi]]
        source:
            class: App\UserSource
```

The extension registers an autowired `Lossik\Runner\Runner` service; `$container` is the Nette DI container:

```php
<?php

use Lossik\Runner\Runner;

$runner = $container->getByType(Runner::class);

echo implode(', ', $runner->getProcessors()), "\n"; // greet

$runner->Run('greet');
// 1: Hi, Alice
// 2: Hi, Bob

$runner->RunWidthSourceData('greet', [['id' => 7, 'name' => 'carol']]);
// 7: Hi, Carol
```

## Configuration

The `runner` section maps job names to their settings:

| key | description |
|---|---|
| `processor: {class: …, setup: …}` | the class is created without constructor arguments; `setup` is a list of calls `[method, [arguments]]` made on the new object |
| `processor: {service: …}` | the name of a DI service that implements `IProcessor` |
| `source: {class: …, setup: …}` | optional |
| `include: […]` | files or directories with more jobs, see below; a single path may be given as a string |

A processor defined as a service is set up with its parameters the same way as a processor defined by class:

```neon
services:
    greeter: App\GreetProcessor

runner:
    greetService:
        processor:
            service: greeter
        source:
            class: App\UserSource
```

### Including files

Each item of `include` is a file or a directory. A directory contributes its files with the `.neon` extension
(case-insensitive: `jobs.NEON` is loaded, `jobs.neon.bak` is not); the trailing `/` is optional. An included file has
the same shape as the `runner` section, so it may contain jobs and its own `include`. A path that does not exist is
skipped. A file reached through different paths is loaded only once.

```neon
runner:
    include:
        - %appDir%/config/jobs
    greet:
        processor:
            class: App\GreetProcessor
            setup:
                - [setGreeting, [Hi]]
        source:
            class: App\UserSource
```

`%appDir%/config/jobs/greet.neon`:

```neon
greet:
    processor:
        setup:
            - [setGreeting, [Hello]]

farewell:
    processor:
        class: App\GreetProcessor
        setup:
            - [setGreeting, [Bye]]
    source:
        class: App\UserSource
```

With these two files the runner has the jobs `greet` and `farewell`, and `Run('greet')` prints `1: Hello, Alice` and
`2: Hello, Bob`:

- When an included file defines a job that also exists in the `runner` section, the values from the included file win
  (e.g. its `class`), and lists such as `setup` are concatenated: the calls from the `runner` section first, then
  those from the included file.
- `%parameters%` are expanded only in the main configuration file. Use them there to build absolute paths, such as
  `%appDir%/…` above; in an included file `%name%` stays a literal string.
- In debug mode the container is rebuilt when an included file changes or a `.neon` file is added to an included
  directory.

## API

### `Lossik\Runner\Runner`

| method | description |
|---|---|
| `getProcessors()` | names of the configured jobs |
| `Run(?string $processorName = null)` | runs the job; without a name runs all jobs; an unknown name throws `LogicException` |
| `RunWidthSourceData(string $processorName, array $sourceData)` | runs the job with the given rows instead of its `source`; an unknown name throws `LogicException` |
| `registerParameter(IParameter $parameter)` | registers a parameter under its `getName()` |

The names `Run` and `RunWidthSourceData` do not follow PSR-1 and are kept for backward compatibility until the next
major version.

A job runs in these steps:

1. The processor is created from `class` or `service`.
2. Each class from the processor's `getParametersClasses()` is created, passed to `setupParameter()` and registered.
3. The rows are read from the source (or taken from `RunWidthSourceData()`).
4. For each name from `getParameters()`, the registered parameter's `getParametersItems($rows)` returns values by
   row key; the processor's `process()` gets them as `[rowKey => [parameterName => value, …], …]`.

Parameters are registered in the `Runner` instance, so a job may use a parameter registered by a job run before it.
A name from `getParameters()` that is not registered throws `LogicException`.

### Interfaces

| interface | base class | methods |
|---|---|---|
| `Lossik\Runner\IProcessor` | `AbstractProcessor` | `getParameters()` (parameter names), `getParametersClasses()` (parameter classes to create), `setupParameter(IParameter $parameter)`, `process(array $parameters)` |
| `Lossik\Runner\IParameter` | `AbstractParameter` | `getName()`, `getParametersItems(array $sourceData)` (values by row key) |
| `Lossik\Runner\ISource` | `AbstractSource` | `getSourceData()` (the rows) |

The `Abstract*` base classes implement the interface with default methods, so a subclass overrides only what it
needs.

## Known issues

- A relative path in `include` is resolved against the current working directory of the process, not against the
  configuration file. Build absolute paths with a parameter in the main configuration file (see above).
- A class from `class:` that does not implement the required interface (`IProcessor`, `IParameter`, `ISource`) is
  reported by `LogicException` with an empty message.
- An `include` path that does not exist is skipped; when it is created after the container was compiled, the
  container is not rebuilt in debug mode.
- `Run('')` and `Run('0')` run all jobs, like `Run()`.
- A `processor` without `class` and without `service` ends with `TypeError`.

## License

MIT, see [LICENSE](LICENSE).
