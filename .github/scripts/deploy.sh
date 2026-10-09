#!/usr/bin/env bash
set -Eeuo pipefail

# Validate the connection before inserting values into the SSH configuration.
: "${SSH_HOST:?Create the SSH_HOST repository secret.}"
: "${SSH_USER:?Create the SSH_USER repository secret.}"
: "${SSH_PORT:?Create the SSH_PORT repository secret.}"
: "${SSH_PRIVATE_KEY:?Create the SSH_PRIVATE_KEY repository secret.}"
: "${RUNNER_TEMP:?This script runs in GitHub Actions.}"
: "${GITHUB_SHA:?Missing the deployment commit.}"
: "${GITHUB_RUN_ID:?Missing the workflow run identifier.}"
: "${GITHUB_RUN_ATTEMPT:?Missing the workflow run attempt.}"

if [[ "$SSH_HOST" != '179.236.252.5' || "$SSH_USER" != 'root' || "$SSH_PORT" != '22' ]]; then
  echo 'The SSH secrets must match the configured server: root@179.236.252.5, port 22.' >&2
  exit 1
fi

# Keep credentials in a private temporary directory outside the checkout.
umask 077
ssh_directory=$(mktemp -d "$RUNNER_TEMP/manager-main-ssh.XXXXXXXXXX")
remote_stage=''

cleanup() {
  # Remove only the temporary directory allocated for this deployment.
  if [[ "$remote_stage" =~ ^/tmp/manager-main-deploy\.[[:alnum:]]+$ ]]; then
    ssh -F "$ssh_directory/config" manager-production "rm -rf -- '$remote_stage'" || true
  fi
  rm -rf -- "$ssh_directory"
}
trap cleanup EXIT

printf '%s\n' "$SSH_PRIVATE_KEY" | tr -d '\r' > "$ssh_directory/key"
unset SSH_PRIVATE_KEY
cp .github/deploy/known_hosts "$ssh_directory/known_hosts"
cat > "$ssh_directory/config" <<EOF
Host manager-production
    HostName $SSH_HOST
    User $SSH_USER
    Port $SSH_PORT
    IdentityFile "$ssh_directory/key"
    UserKnownHostsFile "$ssh_directory/known_hosts"
    IdentitiesOnly yes
    BatchMode yes
    StrictHostKeyChecking yes
    ConnectTimeout 15
    ServerAliveInterval 15
    ServerAliveCountMax 3
EOF

# Check production dependencies before uploading application files.
remote_stage=$(ssh -F "$ssh_directory/config" manager-production 'bash -s' <<'REMOTE'
set -Eeuo pipefail
command -v php >/dev/null
command -v rsync >/dev/null
command -v curl >/dev/null
test -d /var/www/izzidoro.com.br/public/manager_main
test -f /var/www/izzidoro.com.br/public/manager_main/app/Core/Config.php
test -f /var/www/izzidoro.com.br/public/manager_assets/vendor/autoload.php
umask 077
mktemp -d /tmp/manager-main-deploy.XXXXXXXXXX
REMOTE
)
if [[ ! "$remote_stage" =~ ^/tmp/manager-main-deploy\.[[:alnum:]]+$ ]]; then
  echo 'The server returned an invalid staging directory.' >&2
  exit 1
fi

# Stage code outside the web root while preserving production configuration and data.
printf -v ssh_transport 'ssh -F %q' "$ssh_directory/config"
rsync --recursive --links --times --compress \
  --exclude-from=.github/deploy/rsync-excludes \
  --rsh="$ssh_transport" ./ "manager-production:$remote_stage/"
rsync --rsh="$ssh_transport" .github/deploy/rsync-excludes \
  "manager-production:$remote_stage/.deploy-excludes"

# Use the commit and workflow run to identify the deployment backup.
deployment_id="${GITHUB_SHA:0:12}-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
if [[ ! "$deployment_id" =~ ^[a-f0-9]{12}-[0-9]+-[0-9]+$ ]]; then
  echo 'The deployment identifier is invalid.' >&2
  exit 1
fi
ssh -F "$ssh_directory/config" manager-production \
  "bash -s -- '$remote_stage' '$deployment_id'" < .github/scripts/apply-deploy.sh
