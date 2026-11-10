#!/usr/bin/env python3
"""Validate deployment boundaries before writes and constrain staging cleanup."""
import os
from pathlib import Path
import re
import shutil
import stat
import sys

PROJECTS = {
    'manager': 'manager',
    'manager_main': 'manager-main',
    'manager_assets': 'manager-assets',
}
PUBLIC_ROOT = Path('/var/www/izzidoro.com.br/public')
BACKUP_ROOT = Path('/var/backups')
TEMP_ROOT = Path('/tmp')


class UnsafeDeployment(ValueError):
    """The deployment cannot be confined to the expected directories."""


def directory_chain(path, allow_missing=False):
    """Reject symbolic links and non-directory components, including ancestors."""
    if not path.is_absolute():
        raise UnsafeDeployment('An absolute directory path is required.')
    current = Path(path.anchor)
    for part in path.parts[1:]:
        current /= part
        try:
            info = current.lstat()
        except FileNotFoundError:
            if allow_missing:
                return
            raise UnsafeDeployment('A required directory is missing: ' + str(current))
        if not stat.S_ISDIR(info.st_mode):
            raise UnsafeDeployment('Linked or non-directory path component: ' + str(current))


def validate_stage(project, stage):
    """Require the private, marked mktemp directory created for this repository."""
    prefix = PROJECTS[project] + '-deploy'
    if stage.parent != TEMP_ROOT or not re.fullmatch(re.escape(prefix) + r'\.[A-Za-z0-9]+', stage.name):
        raise UnsafeDeployment('Unexpected staging directory.')
    info = stage.lstat()
    if not stat.S_ISDIR(info.st_mode) or info.st_uid != os.geteuid() or info.st_mode & 0o077:
        raise UnsafeDeployment('Staging must be a private directory owned by the deploy user.')
    # /tmp may be a system alias; the staging directory itself cannot redirect.
    if stage.resolve(strict=True) != TEMP_ROOT.resolve(strict=True) / stage.name:
        raise UnsafeDeployment('Staging directory resolves outside the temporary root.')
    marker = stage / '.deploy-repository'
    if not stat.S_ISREG(marker.lstat().st_mode) or marker.read_text().strip() != project:
        raise UnsafeDeployment('The staging repository marker is missing or invalid.')


def validate_destination(destination, expected):
    """Accept only the literal repository destination, without aliases or links."""
    if destination != expected:
        raise UnsafeDeployment('Destination does not match the repository allowlist.')
    directory_chain(destination)
    if destination.resolve(strict=True) != expected:
        raise UnsafeDeployment('Destination resolves outside its allowlisted path.')
    identity = (destination.stat().st_dev, destination.stat().st_ino)
    for parent in destination.parents:
        if (parent.stat().st_dev, parent.stat().st_ino) == identity:
            raise UnsafeDeployment('Destination aliases an ancestor directory.')


def validate_backup(backup, expected_root):
    """Protect backup paths from redirection and reuse of earlier evidence."""
    if backup.parent != expected_root or not re.fullmatch(r'[a-f0-9]{12}-[0-9]+-[0-9]+', backup.name):
        raise UnsafeDeployment('Unexpected backup directory.')
    directory_chain(expected_root, allow_missing=True)
    if expected_root.resolve(strict=False) != expected_root:
        raise UnsafeDeployment('Backup root redirects to another location.')
    if backup.exists() or backup.is_symlink():
        raise UnsafeDeployment('Refusing to reuse an existing backup directory.')


def validate_transfer(stage, destination):
    """Reject source links and destination collisions before rsync can replace them."""
    for current, directories, files in os.walk(stage, followlinks=False):
        for name in directories + files:
            source = Path(current) / name
            relative = source.relative_to(stage)
            source_info = source.lstat()
            source_is_dir = stat.S_ISDIR(source_info.st_mode)
            if not source_is_dir and not stat.S_ISREG(source_info.st_mode):
                raise UnsafeDeployment('Links and special source files are not allowed: ' + str(relative))
            target = destination / relative
            try:
                target_info = target.lstat()
            except FileNotFoundError:
                continue
            target_is_dir = stat.S_ISDIR(target_info.st_mode)
            if not target_is_dir and not stat.S_ISREG(target_info.st_mode):
                raise UnsafeDeployment('Linked or special destination entry: ' + str(relative))
            if source_is_dir != target_is_dir:
                raise UnsafeDeployment('File/directory collision at destination: ' + str(relative))
            if not target_is_dir and target_info.st_nlink > 1:
                raise UnsafeDeployment('Hard-linked destination file: ' + str(relative))


def validate(project, stage, destination, backup):
    """Validate all boundaries before any production or backup modification."""
    validate_stage(project, stage)
    validate_destination(destination, PUBLIC_ROOT / project)
    validate_backup(backup, BACKUP_ROOT / project)
    required = ['composer.json', 'composer.lock', 'vendor/autoload.php'] if project == 'manager_assets' else ['public/index.php', 'app/Core/App.php']
    for relative in required + ['.deploy-excludes']:
        path = stage / relative
        if not stat.S_ISREG(path.lstat().st_mode) or not path.stat().st_size:
            raise UnsafeDeployment('Staging is incomplete: ' + relative)
    validate_transfer(stage, destination)


def cleanup(project, stage):
    """Remove only the marked private staging tree with symlink-safe traversal."""
    validate_stage(project, stage)
    if not shutil.rmtree.avoids_symlink_attacks:
        raise UnsafeDeployment('This Python runtime cannot safely clean staging directories.')
    shutil.rmtree(stage)


def main(arguments):
    if len(arguments) < 3 or arguments[0] not in ('validate', 'cleanup') or arguments[1] not in PROJECTS:
        raise UnsafeDeployment('Invalid validation command.')
    action, project, stage = arguments[0], arguments[1], Path(arguments[2])
    if action == 'cleanup' and len(arguments) == 3:
        cleanup(project, stage)
        print('Private deployment staging directory removed.')
    elif action == 'validate' and len(arguments) == 5:
        validate(project, stage, Path(arguments[3]), Path(arguments[4]))
        print('Deployment paths and file types validated.')
    else:
        raise UnsafeDeployment('Invalid validation arguments.')


if __name__ == '__main__':
    try:
        main(sys.argv[1:])
    except (UnsafeDeployment, OSError, KeyError) as error:
        print('Deployment stopped: ' + str(error), file=sys.stderr)
        sys.exit(1)
