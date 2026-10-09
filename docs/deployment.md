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
- PHP, rsync, and curl must be installed on the VPS.
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
`main`. Manual runs on other branches do not deploy.

Code is staged outside the web root and checked with the VPS's PHP interpreter
before production files are updated. Deployments run one at a time. The workflow
checks the PHP login entry point after uploading and fails if it returns an HTTP
error, even when the code transfer itself succeeded. A successful health check
does not verify every application feature.

The server's `Config.php`, `.env` files, `storage`, uploads, crawler data,
`public/database`, shared Composer dependencies, and Git metadata are preserved
by `.github/deploy/rsync-excludes`. Obsolete code files outside those exclusions
are removed. Replaced and deleted files are backed up under
`/var/backups/manager_main/<commit>-<run>-<attempt>`.

These backups contain only replaced/deleted files, not a complete snapshot or a
database backup. Recovery requires reviewing the affected files, restoring the
needed backup files, and removing any newly introduced files if necessary.
Deploying a previous commit on `main` is another option. The upload is not an
atomic switch of the entire application, and failed health checks do not trigger
an automatic rollback. `manager_assets` updates require a separate deployment.
