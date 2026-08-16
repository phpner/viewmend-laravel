# Contributing

Thank you for improving the ViewMend Laravel integration.

## Development setup

Requirements are PHP 8.3 or newer and Composer 2.

```bash
composer install
composer quality
composer consumer:smoke
composer audit --locked --no-dev
```

The test suite must not make real network requests or sleep. Add focused coverage for behavior changes and keep source files strictly typed and PSR-12 compliant.

`composer consumer:smoke` installs the package into a disposable minimal Laravel application. It is the authoritative check for Composer package discovery, native `config:cache`, container resolution, command exit codes, and the network-free facade fake.

Do not use Testbench's `config:cache` command as a consumer cache check. Testbench 11 replaces Laravel's configuration bootstrapper and reloads raw configuration into an empty repository, while Laravel's `mergeConfigFrom()` correctly skips merging when the application reports cached configuration. This can leave later Testbench processes without package defaults even though the generated cache file is valid. If you run that command while investigating Testbench itself, always follow it with:

```bash
vendor/bin/testbench config:clear --no-interaction
```

## Architecture rules

- Use only public APIs from `viewmend/sdk`; never import `ViewMend\Internal`.
- Keep HTTP, authentication, retry, DTO, exception, and event mapping behavior in the SDK.
- Keep production dependencies minimal and explain any addition in `docs/architecture.md`.
- Do not add hidden requests, global listeners, or asynchronous delivery semantics.
- Never include tokens, authorization headers, raw responses, or other secrets in fixtures, output, logs, exceptions, or snapshots.

## Pull requests

Create a focused branch and explain the problem, the chosen behavior, and the verification performed. Update documentation and `CHANGELOG.md` when public behavior changes. Maintainers create releases and tags after review; contributors should not commit generated test artifacts or `vendor/`.
