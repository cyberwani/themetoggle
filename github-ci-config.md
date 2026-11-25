I've written the CI/CD setup and dry-run everything I could here, but it hasn't run on GitHub yet. Three workflow files are attached because your machine blocks remote writes to `.github`. Everything else is already in your plugin folder.

**Correction first:** my earlier update that removed the `.claude`/`.github` references hadn't actually reached your disk for five files (`.gitattributes`, `.distignore`, `composer.json`, `CLAUDE.md`, `.gitignore`). I had started the upload before the files were ready, so the old versions went across, and I wrongly told you it had worked. They're fixed now. I compared every file outside `.github` against my copy, and all 58 match.

## What it does
|File|Runs when|What it does|
|--|---|---|
|`.github/workflows/ci.yml`|Every push to `main`, every pull request, or by hand|Checks PHP syntax on PHP 7.4 to 8.4, runs PHPCS (problems show as comments on the PR), lints JS/CSS/Markdown, and checks the translation file has every string. It also runs the end-to-end tests on WordPress 6.7 + PHP 7.4 and on the latest WordPress + PHP 8.3, with MySQL 8. If the tests fail, the logs are attached to the run.|
|`.github/workflows/release.yml`|You push a tag like `v1.0.1`|Runs all of CI, checks the tag matches every version number in the code, builds `themetoggle-1.0.1.zip`, and publishes a GitHub Release with that zip and generated release notes.|
|`.github/dependabot.yml`|Weekly|Opens grouped update pull requests for the Composer, npm and GitHub Actions dependencies.|

New helpers, already in your folder:
-   `bin/check-version.sh` checks that all version numbers agree (`composer version:check`).
-   `bin/check-pot.sh` checks that the translation file is up to date (`composer pot:check`).
-   `tests/e2e/ci-install-wordpress.sh` builds the throwaway test site for CI.

The README, AGENTS, TESTING and skill docs now describe all of this, and neither `bin/` nor `.github/` goes into the release zip.

**What the dry run caught and fixed:**
-   WP-CLI saved the test site's address with an extra folder on the end, which broke every test login. The installer now sets the address itself, and `setup.sh` stops with a clear message if it's wrong.
-   On GitHub the project folder's path contains "themetoggle", so the "no PHP warnings" check would also have caught WordPress core warnings. It now looks only for the plugin's own files.

## Steps on your side
1.  **Place the three attached files.** In the `themetoggle` folder:
    `mkdir .github\workflows`
    
    Move `ci.yml` and `release.yml` into `.github\workflows\`, and `dependabot.yml` into `.github\`.
2.  **Make the first commit.**
    ```
    git init -b main
    composer install
    git add .
    git commit -m "Initial commit"
    ```
    `composer install` creates `composer.lock`. Commit it so CI uses the same tool versions you do.
3.  **Create the GitHub repository** as an empty repo (no README or licence), then:
    ```
    git remote add origin https://github.com/<you>/themetoggle.git
    git push -u origin main
    ```
    Then open the **Actions** tab to watch the first run.
4.  **Change these repository settings:**
    -   **Settings → Actions → General:** allow actions. If your organisation only allows verified creators, also allow `shivammathur/setup-php`. Leave workflow permissions on read-only; the release job asks for write access itself.
    -   **Settings → Rules → Rulesets:** protect `main` (require pull requests and status checks). After the first CI run, add these required checks:
        -   every "PHP … syntax" check
        -   "PHPCS (WordPress + PHP compatibility)"
        -   "JS, CSS and Markdown lint"
        -   "Translation template up to date"
        -   both "E2E …" checks
    -   **Optional:** a tag ruleset on `v*` so only maintainers can trigger a release.
    -   **Settings → Advanced Security:** turn on Dependabot alerts and security updates.
5.  **Release:** `git tag v1.0.0`, then `git push origin v1.0.0`. The zip appears under **Releases**.

## Not yet verified
-   **The workflow files have never run on GitHub.** They parse as valid YAML and I checked them by hand. The sandbox refused to run `actionlint`, a workflow linter I'd downloaded, so that check wasn't done.
-   **MySQL and the theme downloads weren't tested.** No MySQL server was available and wordpress.org was blocked here, so those two install steps are untested. Everything after them ran exactly as CI will run it, and all 123 end-to-end checks passed on a brand-new site.
-   **The WordPress 6.7 + PHP 7.4 combination wasn't tested.** I only had WordPress 7.3-alpha and PHP 8.3.
-   **`composer install` from Packagist (Composer's package server) is untested**, because Packagist was blocked here.

The first CI run will settle all four. If anything fails, send me the run's log or the attached artifact and I'll fix it.

The release pipeline currently stops at a GitHub Release. If you also want it to publish to WordPress.org or deploy to a server, tell me which and I'll add it.
