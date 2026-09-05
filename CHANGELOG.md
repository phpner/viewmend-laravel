# Changelog

All notable changes to this project will be documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-09-05

### Added

- Public `cron()` access through the manager contract, facade, fake, and named connections, without Site Tracker configuration.
- Per-connection `api_base_url` and `VIEWMEND_API_BASE_URL` support through the SDK's public client factory.
- Explicit fake API responses, request recording, and request assertions for Cron and Site Tracker dashboard/resource reads.
- Dashboard/resource examples and tests against the published SDK 1.3, plus expanded consumer smoke checks.

### Changed

- Raised the minimum SDK to 1.3 for dashboard/resource reads, the neutral Cron contract, and configurable API URL.
- `assertNothingSent()` now checks every recorded request, including reads. Event response helpers and assertions keep their existing event scope.
- Custom implementations of `ViewMendManagerContract` must implement the new `cron()` accessor. `ClientFactoryContract` is unchanged.

## [1.0.0] - 2026-08-16

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
