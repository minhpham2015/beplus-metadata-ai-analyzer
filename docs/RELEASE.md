# Release Procedure — Beplus Metadata AI Analyzer

GitHub `main` is the single source of truth. WordPress.org SVN is always
regenerated FROM `main` — never hand-edited.

## 1. Land the fix/feature

- Branch off `main`: `fix/<short-name>` or `feature/<short-name>`.
- Open a PR. CI must pass (`.github/workflows/ci.yml`): PHP syntax (7.4–8.3),
  WPCS, and the version-consistency check.
- Merge to `main` with `--no-ff` (or GitHub's "Create a merge commit") so the
  fix/feature history stays visible — don't squash away the reasoning.

## 2. Bump version (before or as part of the PR)

Both of these MUST match, and must match `Stable tag` in step 4:

- `beplus-metadata-ai-analyzer.php` → `Version:` header AND `SSO_VERSION` constant
- `readme.txt` → `Stable tag:`

Add a `= X.Y.Z =` entry under `== Changelog ==` and `== Upgrade Notice ==` in
`readme.txt` (WordPress.org renders these — this is the user-facing log).
Also add an entry to `CHANGELOG.md` at repo root (dev-facing, Keep a
Changelog format, includes internal/security notes readme.txt wouldn't).

## 3. Sync to WordPress.org SVN

```bash
# Fresh clone of main — never reuse a stale working copy
git clone https://github.com/minhpham2015/beplus-metadata-ai-analyzer.git /tmp/release-src

# Checkout SVN (first time: full checkout; after: svn up --set-depth infinity trunk)
svn co --depth immediates https://plugins.svn.wordpress.org/beplus-metadata-ai-analyzer/ /tmp/release-svn
cd /tmp/release-svn && svn up --set-depth infinity trunk

# Mirror git -> svn trunk (this deletes anything in trunk not in the git repo)
rsync -av --exclude='.git' --exclude='.svn' /tmp/release-src/ trunk/ --delete-excluded

# Stage any renamed/removed files svn doesn't know about yet
svn status
svn add <new files>
svn rm --keep-local <files removed upstream>
```

## 4. Verify before committing

- `grep -i "^Stable tag" trunk/readme.txt` and `grep "Version:" trunk/*.php`
  must show the SAME number.
- `php -l` every touched `.php` file (or run the file through a container:
  `docker run --rm -v $(pwd)/trunk:/code php:8.1-cli sh -c "find /code -name '*.php' -exec php -l {} \;"`
  and grep for anything that isn't "No syntax errors").
- If the fix touched a live endpoint (sitemap.xml, robots.txt, llms.txt,
  meta tags on a real post), re-verify on a throwaway WordPress+Docker site
  BEFORE committing to SVN, not after.

## 5. Commit trunk, then tag

```bash
svn commit trunk -m "Release X.Y.Z: <one-line summary>" --username minhphamit
svn up trunk
svn copy trunk tags/X.Y.Z
svn commit tags/X.Y.Z -m "Tag X.Y.Z release" --username minhphamit
```

Both commits are required — committing trunk alone does NOT publish a new
version to users; WordPress.org watches `tags/`.

## 6. Clean up

- Delete `/tmp/release-src` and `/tmp/release-svn`.
- Delete the merged feature/fix branch on GitHub if it wasn't auto-deleted.
- Confirm on https://wordpress.org/plugins/beplus-metadata-ai-analyzer/
  that the "Version" shown matches (allow a few minutes for cache).

## Credentials

SVN commits need a WordPress.org account password (Application Password,
not the login password, if 2FA is on). This is never stored on disk long-term
— provided per-session when a release is run, and the account owner should
rotate it after any session where it was typed into a chat/terminal that
isn't a personal, already-authenticated shell.
