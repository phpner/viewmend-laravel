# Changelog

All notable changes to this project will be documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Laravel 12 and 13 package auto-discovery and configuration publishing.
- Lazy singleton manager with default and named connections.
- Dependency-injection contract, SDK client factory extension point, and optional facade.
- Synchronous `viewmend:deployment` command with stable event IDs and pipeline-safe exit codes.
- Network-free Laravel testing fake backed by the public SDK PSR-18 seam.
- PHPUnit/Testbench coverage, maximum-level Larastan analysis, PSR-12 checks, and CI compatibility matrix.

### Changed

- General SDK clients now require only a connection token; Site Tracker configuration is resolved lazily at the Site Tracker boundary.
- CI now verifies cached configuration in a disposable native Laravel consumer and exercises the lowest compatible dependency sets for Laravel 12 and 13.
