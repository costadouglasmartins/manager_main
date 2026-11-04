# Deployment to the existing VPS

The `Deploy manager_main` workflow updates the existing Nginx/PHP installation at
`/var/www/izzidoro.com.br/public/manager_main` on `179.236.252.5`. Docker on the Mac
is the development environment; this workflow uses the existing production PHP
runtime and does not require Docker on the VPS.

## GitHub configuration

In this repository, open **Settings > Secrets and variables > Actions** and add:

| Secret | Value |
| --- | --- |
| `SSH_HOST` | `179.236.252.5` |
| `SSH_USER` | `root` |
| `SSH_PORT` | `22` |
| `SSH_PRIVATE_KEY` | Complete contents of the Mac's `~/.ssh/github_deploy` file |

The corresponding public key must be authorized in `/root/.ssh/authorized_keys`.
The server's public host key is pinned in `.github/deploy/known_hosts`. It is not
a secret. Verify and update it if the server is rebuilt or its host key changes.

## Production prerequisites

- The destination directory and `app/Core/Config.php` must already exist.
- PHP, Python 3, rsync, curl, and flock must be installed on the VPS.
- The sibling `manager_assets/vendor/autoload.php` must exist and its Composer
  dependencies must support the production PHP runtime.
- Production database and chat settings belong in the server's
  `app/Core/Config.php`. A Docker service name such as `mysql` will not normally
  resolve on this VPS, which currently runs MariaDB directly on the host.
- The PHP login page must be able to connect to its production database. This
  workflow does not create databases, import SQL, or change database credentials.

## Deploying

After the workflow is merged into `main`, each push to `main` deploys that commit.
You can also open **Actions > Deploy manager_main > Run workflow**, selecting
`main`. Manual runs on either test branch simulate; other branches do not run.

Code is staged outside the web root and checked with the VPS's PHP interpreter
before production files are updated. All three repository deployments use the
same server lock while applying files and checking health. The workflow
checks the PHP login entry point after uploading and fails if it returns an HTTP
error, even when the code transfer itself succeeded. A successful health check
does not verify every application feature.

The server's `Config.php`, `.env` files, `storage`, uploads, crawler data,
the contents of `public/database` (except the tenant template below), shared
Composer dependencies, and Git metadata are preserved by
`.github/deploy/rsync-excludes`. Server-only files are preserved. Removing obsolete code requires a separate
reviewed operation. Overwritten files are backed up under
`/var/backups/manager_main/<commit>-<run>-<attempt>`.

`public/database/tenant_template.sql` is the only exception: `TenantProvisioner`
runs it to create tenant databases, so every deployment publishes the committed
template. The directory is created if it is missing, and the replaced template
goes to the same backup directory. Dumps, MySQL Workbench models, and operational
SQL scripts in `public/database`, tracked or not, are neither uploaded nor changed
on the server. In `rsync-excludes`, the `+ /public/database/tenant_template.sql`
line must stay before `- /public/database/*`, because rsync applies the first
matching rule. Publishing the template affects only databases provisioned
afterwards; existing tenant databases still need their schema upgrades run
separately.

These backups contain only overwritten files, not a complete snapshot or a
database backup. Recovery requires reviewing the affected files, restoring the
needed backup files, and removing any newly introduced files if necessary.
Deploying a previous commit on `main` is another option. The upload is not an
atomic switch of the entire application, and failed health checks do not trigger
an automatic rollback. `manager_assets` updates require a separate deployment.

## Simulation from the setup branch

A push to `codex/github-deploy` runs the same workflow using the repository SSH
Secrets in dry-run mode. Files are uploaded only to a private temporary staging
directory outside the web root; production rsync uses `--dry-run`, and no backup
directory is created. The staged PHP/dependencies are checked with the production
runtime, and the existing login pages are checked. Temporary staging files are
removed at the end. This validates the Secrets and proposed synchronization
before merging the PRs.

Pushes to `main` publish code. The runner script rejects publication from other
branches and defaults to simulation when no mode is provided. Manual runs on
`main` publish; runs on `codex/github-deploy` simulate. Dry-run HTTP checks examine
the currently deployed applications, not the staged application behavior.

## Destination and cleanup protection

Before opening the shared lock, and again after waiting for it and checking PHP,
the deployment validator checks the exact allowlisted destination for this
repository. Destination path components must be real directories; aliases,
symlinks, ancestor aliases, special entries, hard-linked target files, and
file/directory type conflicts stop publication before production files change.
New backup paths are also validated and existing backup directories are never
reused. Missing application directories are not created automatically.

Staging must be a private directory owned by the deploying user under the fixed
temporary root, with the expected random name and repository marker. Cleanup
uses Python's symlink-safe directory removal after checking that boundary; it
does not accept arbitrary server paths. The marker and validator are excluded
from publication. Python 3 with symlink-safe rmtree support is required.

The synchronization has no delete option. It updates or adds files with backups
of overwritten files and preserves files present only on the server. These
checks reduce the impact of path mistakes; they do not establish the cause of
the earlier incident or replace a complete backup. The SSH account still has
root privileges; account isolation should be addressed separately.

## Verifying this safety correction

Run `python3 tests/test_deploy_safety.py` locally. The workflow also requires the
`safety-checks` job to pass on Ubuntu before the deployment job starts. The tests
use temporary directories only; they do not connect to the VPS.

Push this correction to `codex/guard-deploy-paths` to run the guarded workflow
against the VPS in dry-run mode. This branch can never select publication mode.
Review that simulation and its protected paths before merging into `main`.
Changes to `main` remain real deployments after the prerequisite tests pass.
Do not treat local success as confirmation of the VPS Python/runtime checks.
