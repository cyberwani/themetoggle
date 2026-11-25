# GitHub CI/CD setup

How to put ThemeToggle on GitHub with continuous integration and automatic releases. Steps 1–3 are one-off on your machine; step 4 is repository settings; step 5 is how every release is published.

## What runs

| File | Runs when | What it does |
| --- | --- | --- |
| `.github/workflows/ci.yml` | Every push to `main`, every pull request, or by hand | PHP syntax on PHP 7.4–8.4, PHPCS (problems show as annotations on the pull request), JS/CSS/Markdown lint, translation template up to date (`composer pot:check`), and the end-to-end tests on WordPress 6.7 + PHP 7.4 and latest WordPress + PHP 8.3 with MySQL 8. Logs are attached to the run if the tests fail. |
| `.github/workflows/release.yml` | Pushing a tag such as `v1.0.1` | Runs all of CI, checks the tag matches every version number (`php bin/check-version.php`), builds `themetoggle-1.0.1.zip` from the tagged commit with `git archive`, and publishes a GitHub Release with that zip and generated notes. |
| `.github/dependabot.yml` | Weekly | Grouped update pull requests for the Composer, npm and GitHub Actions dependencies. |

Helpers the workflows call (all in the repository):

| File | Used by | Purpose |
| --- | --- | --- |
| `bin/check-version.php` | `composer version:check`, release workflow | Checks the plugin header, `const VERSION`, `readme.txt` Stable tag and changelog, and `package.json` agree. |
| `bin/check-pot.php` | `composer pot:check`, CI | Fails when `languages/themetoggle.pot` is missing source strings or has stale ones (ignores dates and line numbers). |
| `bin/build-zip.php` | `composer zip:local`, `npm run plugin-zip` | Local zip of the working tree with the same file list as the release zip. Not used by CI. |
| `tests/e2e/ci-install-wordpress.sh` | CI e2e job | Builds the throwaway WordPress test site. |

The `bin/` helpers are PHP scripts, so they run the same on Windows, macOS, Linux and GitHub's runners. `bin/` and `.github/` are excluded from release zips.

## 1. Check the files are in place

From the plugin folder:

```bat
dir .github\workflows
dir bin
```

You should see `ci.yml` and `release.yml` in `.github\workflows\`, `dependabot.yml` in `.github\`, and `build-zip.php`, `check-pot.php`, `check-version.php` in `bin\`. There must be no `.sh` files left in `bin\`.

Open `.github\workflows\release.yml` and check the version step reads:

```yaml
run: php bin/check-version.php "${GITHUB_REF_NAME#v}"
```

If it still says `bash bin/check-version.sh`, replace the file with the current version; that script no longer exists and the release would fail.

## 2. Check locally, then commit

```bat
composer install
npm install
composer check
npm run lint
composer pot:check
composer version:check
```

All of them must pass. If `composer pot:check` fails, run `composer make-pot` and include the updated POT in the commit.

```bat
git init -b main
git add -A
git commit -m "Initial commit"
```

Skip `git init` if the folder is already a repository. `git add -A` also records deleted files. Commit `composer.lock` and `package-lock.json` so CI uses the same tool versions you do. `vendor/`, `node_modules/` and `build/` are ignored by `.gitignore`.

## 3. Create the repository and push

Create an empty repository on GitHub (no README, licence or `.gitignore`), then:

```bat
git remote add origin https://github.com/<you>/themetoggle.git
git push -u origin main
```

Open the **Actions** tab and watch the first CI run.

## 4. Repository settings

- **Settings → Actions → General**
    - Allow actions. If your organisation only allows actions from verified creators, also allow `shivammathur/setup-php`.
    - Leave **Workflow permissions** on read-only. The release job requests write access for itself.
- **Settings → Rules → Rulesets → New branch ruleset** for `main`
    - Require a pull request before merging.
    - Require status checks to pass. After the first CI run, add:
        - `PHP 7.4 syntax`, `PHP 8.0 syntax`, `PHP 8.1 syntax`, `PHP 8.2 syntax`, `PHP 8.3 syntax`, `PHP 8.4 syntax`
        - `PHPCS (WordPress + PHP compatibility)`
        - `JS, CSS and Markdown lint`
        - `Translation template up to date`
        - `E2E (WP 6.7, PHP 7.4)`
        - `E2E (WP latest, PHP 8.3)`
- **Optional: Settings → Rules → Rulesets → New tag ruleset** for `v*`, so only maintainers can create release tags.
- **Settings → Advanced Security**: turn on Dependabot alerts and Dependabot security updates. Version-update pull requests start automatically from `.github/dependabot.yml`.

## 5. Publishing a release

1. Bump the version in `themetoggle.php` (header and `const VERSION`), `readme.txt` (Stable tag and a `= X.Y.Z =` changelog entry) and `package.json`.
2. Check locally:

    ```bat
    composer version:check
    composer check
    npm run lint
    composer pot:check
    ```

3. Merge to `main` with CI green.
4. Tag and push:

    ```bat
    git tag v1.0.1
    git push origin v1.0.1
    ```

5. The Release workflow runs. When it finishes, `themetoggle-1.0.1.zip` is attached to the release under **Releases**.
6. Install that zip on a clean site and run the manual checklist in `docs/TESTING.md`.

If the version check fails, the release is not published: fix the version numbers, delete the tag (`git tag -d v1.0.1` and `git push origin :refs/tags/v1.0.1`), and tag again.

## Not yet verified

These workflows were dry-run outside GitHub; the first real run settles the rest:

- The workflow files parse as valid YAML and were reviewed by hand; they have not run on GitHub, and `actionlint` was not run.
- The MySQL service and the theme downloads in `tests/e2e/ci-install-wordpress.sh` were not tested. Every step after them ran as CI runs it, and all 123 end-to-end checks passed on a fresh site.
- WordPress 6.7 with PHP 7.4 was not tested locally.
- `composer install` from Packagist was not tested in the sandbox; you have since run it successfully on your machine.

If a run fails, open the failed job, copy its log (and the `e2e-logs-…` artifact for e2e failures), and use it to fix the problem.
