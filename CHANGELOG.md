# Changelog

All notable changes to this project will be documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-09-05

### Added

- Site Tracker dashboard and resource reads through the published `viewmend/sdk ^1.3` dependency.
- Cron access through the facade, concrete manager, fake, and named connections, without Site Tracker configuration.
- Per-connection `api_base_url` and `VIEWMEND_API_BASE_URL` support.
- Explicit fake API responses, request recording, and request assertions, including `assertNoRequestsSent()`.
- Documentation, module tests, and native Laravel consumer checks for the new APIs.

### Compatibility

- Preserved the original three-method `ViewMendManagerContract`; custom 1.0 implementations need no new method. Injected consumers access Cron through `connection()->cron()`.
- Preserved the event-only meaning and error message of `assertNothingSent()`; use `assertNoRequestsSent()` to check every API request.
- Raised the minimum SDK to 1.3. PHP, Laravel, and `ClientFactoryContract` requirements remain unchanged.
- Supersedes the withdrawn 2.0.0 release. Its Git tag is retained for existing references; consumers using `^2.0` should switch to `^1.1`.

## [2.0.0] - 2026-09-05 [WITHDRAWN]

Withdrawn in favor of the compatible 1.1.0 release. The following describes the original 2.0.0 behavior.

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
