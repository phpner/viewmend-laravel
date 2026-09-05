#!/usr/bin/env bash

set -euo pipefail

package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_dir="${package_root}/tests/Fixtures/consumer"
consumer_dir="$(mktemp -d "${TMPDIR:-/tmp}/viewmend-laravel-consumer.XXXXXX")"

cleanup() {
    status=$?
    trap - EXIT

    if [[ -x "${package_root}/vendor/bin/testbench" ]]; then
        "${package_root}/vendor/bin/testbench" config:clear --no-interaction >/dev/null 2>&1 || true
    fi

    if [[ -n "${consumer_dir}" && "${consumer_dir}" != "/" ]]; then
        rm -rf -- "${consumer_dir}"
    fi

    exit "${status}"
}

trap cleanup EXIT

cp -R "${fixture_dir}/." "${consumer_dir}/"
cp "${package_root}/tests/Fixtures/"site-tracker-*.json "${consumer_dir}/"
mkdir -p \
    "${consumer_dir}/bootstrap/cache" \
    "${consumer_dir}/storage/framework/cache/data" \
    "${consumer_dir}/storage/framework/sessions" \
    "${consumer_dir}/storage/framework/views"

composer --working-dir="${consumer_dir}" config repositories.viewmend path "${package_root}"
COMPOSER_MIRROR_PATH_REPOS=1 composer --working-dir="${consumer_dir}" update \
    --no-dev \
    --no-audit \
    --prefer-dist \
    --no-interaction \
    --no-progress

php "${consumer_dir}/artisan" package:discover --no-interaction

VIEWMEND_API_TOKEN=consumer-smoke-placeholder \
VIEWMEND_API_BASE_URL=http://127.0.0.1:8079/api/v1 \
VIEWMEND_SITE_TRACKER_INTEGRATION_ID=consumer-smoke-integration \
    php "${consumer_dir}/artisan" config:cache --no-interaction

if ! php "${consumer_dir}/artisan" list --raw | grep -q '^viewmend:deployment'; then
    echo 'The ViewMend deployment command was not discovered.' >&2
    exit 1
fi

set +e
command_output="$(php "${consumer_dir}/artisan" viewmend:deployment --no-interaction 2>&1)"
command_status=$?
set -e

if [[ ${command_status} -ne 2 ]]; then
    echo "Expected viewmend:deployment without --event-id to exit 2, got ${command_status}." >&2
    echo "${command_output}" >&2
    exit 1
fi

if [[ "${command_output}" != *"The --event-id option is required."* ]]; then
    echo 'The deployment command did not report the missing stable event ID.' >&2
    echo "${command_output}" >&2
    exit 1
fi

php "${consumer_dir}/verify.php"
php "${consumer_dir}/artisan" config:clear --no-interaction
