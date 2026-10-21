"""Exercise deployment boundaries and real rsync behavior in temporary directories."""
import importlib.util
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True
REPO = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('deploy_guard', REPO / '.github/scripts/validate-deploy.py')
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)


class DeploySafetyTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='deploy-safety-')
        self.addCleanup(self.temporary.cleanup)
        self.base = Path(self.temporary.name).resolve()
        # Overrides exist only in this imported test module, never in the CLI.
        guard.TEMP_ROOT = self.base / 'staging'
        guard.PUBLIC_ROOT = self.base / 'public'
        guard.BACKUP_ROOT = self.base / 'backups'
        self.project = REPO.name
        self.stage = guard.TEMP_ROOT / (guard.PROJECTS[self.project] + '-deploy.abc123')
        self.stage.mkdir(parents=True, mode=0o700)
        (self.stage / '.deploy-repository').write_text(self.project)
        shutil.copyfile(REPO / '.github/deploy/rsync-excludes', self.stage / '.deploy-excludes')
        self.destination = guard.PUBLIC_ROOT / self.project
        self.destination.mkdir(parents=True)
        self.backup = guard.BACKUP_ROOT / self.project / 'aaaaaaaaaaaa-1-1'
        self.write(self.stage, 'public/index.php', '<?php // staged entry point\n')
        self.write(self.stage, 'app/Core/App.php', '<?php // staged application\n')
        if self.project == 'manager_assets':
            self.write(self.stage, 'composer.json', '{"name":"manager/shared-assets"}')
            self.write(self.stage, 'composer.lock', '{}')
            self.write(self.stage, 'vendor/autoload.php', '<?php // staged dependencies\n')

    def write(self, root, relative, content):
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)
        return path

    def validate(self, destination=None):
        guard.validate(self.project, self.stage, destination or self.destination, self.backup)

    def test_valid_repository_destination(self):
        self.validate()
        self.assertFalse(self.backup.exists())

    def test_parent_destination_rejected(self):
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate(guard.PUBLIC_ROOT)

    def test_var_www_destination_rejected(self):
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate(Path('/var/www'))

    def test_destination_symlink_rejected(self):
        other = self.base / 'other'
        other.mkdir()
        self.destination.rmdir()
        self.destination.symlink_to(other, target_is_directory=True)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_ancestor_symlink_rejected(self):
        other = self.base / 'other'
        guard.PUBLIC_ROOT.rename(other)
        guard.PUBLIC_ROOT.symlink_to(other, target_is_directory=True)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_missing_destination_rejected_without_creation(self):
        self.destination.rmdir()
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()
        self.assertFalse(self.destination.exists())

    def test_source_symlink_rejected(self):
        outside = self.write(self.base, 'outside.txt', 'untouched')
        (self.stage / 'linked.txt').symlink_to(outside)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()
        self.assertEqual(outside.read_text(), 'untouched')

    def test_linked_target_subdirectory_rejected(self):
        outside = self.base / 'outside'
        outside.mkdir()
        (self.destination / 'public').symlink_to(outside, target_is_directory=True)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_target_file_replacing_directory_rejected(self):
        self.write(self.stage, 'collision', 'new file')
        saved = self.write(self.destination, 'collision/data.txt', 'saved data')
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()
        self.assertEqual(saved.read_text(), 'saved data')

    def test_target_directory_replacing_file_rejected(self):
        self.write(self.stage, 'collision/data.txt', 'new data')
        saved = self.write(self.destination, 'collision', 'saved file')
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()
        self.assertEqual(saved.read_text(), 'saved file')

    def test_linked_backup_root_rejected(self):
        guard.BACKUP_ROOT.mkdir()
        (guard.BACKUP_ROOT / self.project).symlink_to(self.destination, target_is_directory=True)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_existing_backup_rejected(self):
        self.backup.mkdir(parents=True)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_staging_marker_must_match_repository(self):
        (self.stage / '.deploy-repository').write_text('different-repository')
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_public_staging_permissions_rejected(self):
        self.stage.chmod(0o755)
        with self.assertRaises(guard.UnsafeDeployment):
            self.validate()

    def test_incomplete_staging_rejected(self):
        (self.stage / '.deploy-excludes').unlink()
        with self.assertRaises((guard.UnsafeDeployment, OSError)):
            self.validate()

    def test_cleanup_refuses_production_directory(self):
        with self.assertRaises(guard.UnsafeDeployment):
            guard.cleanup(self.project, self.destination)
        self.assertTrue(self.destination.is_dir())

    def test_cleanup_does_not_follow_source_symlinks(self):
        outside = self.write(self.base, 'outside/data.txt', 'saved data')
        (self.stage / 'link').symlink_to(outside.parent, target_is_directory=True)
        guard.cleanup(self.project, self.stage)
        self.assertFalse(self.stage.exists())
        self.assertEqual(outside.read_text(), 'saved data')

    def test_rsync_preserves_server_only_data_and_backs_up_overwrites(self):
        server_only = self.write(self.destination, 'server-only/document.txt', 'saved document')
        self.write(self.destination, 'public/index.php', '<?php // old code\n')
        protected = ['.env', 'storage/state.json', 'uploads/file.txt', 'tmp/session.txt']
        if self.project != 'manager_assets':
            protected += ['app/Core/Config.php', 'public/tenant_files/customer/file.txt', 'public/database/data.sql']
        for relative in protected:
            self.write(self.stage, relative, 'repository value')
            self.write(self.destination, relative, 'production value')
        self.validate()
        self.backup.mkdir(parents=True)
        script = (REPO / '.github/scripts/apply-deploy.sh').read_text()
        options = re.search(r'^rsync_options=\(\n.*?\n\)', script, re.M | re.S).group(0)
        shell = '''set -Eeuo pipefail
stage=$1
destination=$2
backup_directory=$3
''' + options + '''
rsync_options+=(--backup --backup-dir="$backup_directory")
rsync "${rsync_options[@]}" "$stage/" "$destination/"
'''
        result = subprocess.run(['bash', '-c', shell, 'fixture', str(self.stage), str(self.destination), str(self.backup)], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(server_only.read_text(), 'saved document')
        self.assertEqual((self.destination / 'public/index.php').read_text(), '<?php // staged entry point\n')
        self.assertEqual((self.backup / 'public/index.php').read_text(), '<?php // old code\n')
        for relative in protected:
            self.assertEqual((self.destination / relative).read_text(), 'production value')
        self.assertFalse((self.destination / '.deploy-repository').exists())
        self.assertFalse((self.destination / '.deploy-excludes').exists())

    def test_no_pruning_or_unbounded_cleanup_options_in_scripts(self):
        apply = (REPO / '.github/scripts/apply-deploy.sh').read_text()
        deploy = (REPO / '.github/scripts/deploy.sh').read_text()
        self.assertNotRegex(apply, r'--(?:del(?:ete[^\s)]*)?|force|inplace|remove-source-files)(?:\s|$)')
        self.assertNotIn('rm -rf', deploy)

    def test_publication_rejected_from_safety_branch_before_ssh(self):
        credentials = self.base / 'runner-temp'
        credentials.mkdir()
        environment = dict(os.environ, SSH_HOST='179.236.252.5', SSH_USER='root', SSH_PORT='22', SSH_PRIVATE_KEY='dummy-test-key', RUNNER_TEMP=str(credentials), GITHUB_SHA='a' * 40, GITHUB_RUN_ID='1', GITHUB_RUN_ATTEMPT='1', GITHUB_REF='refs/heads/codex/guard-deploy-paths', DEPLOY_MODE='apply')
        result = subprocess.run(['bash', '.github/scripts/deploy.sh'], cwd=REPO, env=environment, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Publication is allowed only from the main branch', result.stderr)
        self.assertEqual(list(credentials.iterdir()), [])


if __name__ == '__main__':
    unittest.main()
