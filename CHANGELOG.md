# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - Unreleased

### Changed

- Requires PHP `^8.2`; supported are only the patched versions 8.2.35+, 8.3.36+, 8.4.27+ and 8.5.12+
  (older ones are vulnerable to PHP2Shell, CVE-2026-109618/109619). Older PHP stays on `2.0.x`.
- New direct requirements `nette/neon ^3.4` and `nette/schema ^1.0`.
  Lowest tested: `nette/di` 3.0.14 (PHP 8.2) … 3.2.5 (PHP 8.5).
- A processor defined as a service now gets `setupParameter()` with its parameters and registers them, replacing a
  parameter registered under the same name. Its `getParametersClasses()` are now instantiated, so a parameter class
  that cannot be created now fails for a service processor too (previously only for a processor defined by class).
- Jobs from an `include` directory given without the trailing `/` are now loaded and run.
- Only files with the `.neon` extension are included; a file such as `aneon` in an included directory or listed
  directly in `include` is no longer loaded.
- `include` given as a single string is now accepted as one path (previously `TypeError`).
- An unknown job and an unregistered parameter now throw `LogicException` instead of `TypeError` / `Error`.

### Fixed

- `Run()` works without `inject: true` for the `Runner` service.
- In debug mode the container is rebuilt when an included file changes or a `.neon` file is added to an included
  directory.
- Docblock types corrected (PHPStan level max).

### Added

- LICENSE (MIT).
- README.
- Tests (PHPUnit).
- CI (GitLab) running on all supported PHP versions with the highest and the lowest allowed dependencies.
- PHPStan and PHP_CodeSniffer (PSR-12) configuration.

### Known behaviours

- `Run('')` and `Run('0')` run all jobs, like `Run()`.
- An `include` path that does not exist is skipped; when it is created after the container was compiled, the
  container is not rebuilt in debug mode.
- `%parameters%` in included files are not expanded and stay literal strings.
- A `processor` without `class` and without `service` ends with `TypeError`.
- When an included file defines a job that also exists in the main configuration, the values from the included file
  win and lists such as `setup` are concatenated (main configuration first).
- A relative path in `include` is resolved against the current working directory of the process.
