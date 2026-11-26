# ThemeToggle feature specs

One document per planned feature. Each feature has a **Feature ID** that never changes and is never reused, so its status can be traced from idea to release.

The overview lives in two places that must stay in step:

- [`docs/TODO.md`](../TODO.md), section "Features", is the status list.
- `docs/ThemeToggle feature ideas.xlsx` is the sheet for sorting and planning.

## Feature IDs

- Format: `TTF` followed by three digits, for example `TTF007`. Spec file: `docs/features/feature-TTF007.md`.
- IDs are given in the order features are added. The next free ID is **TTF019**.
- A rejected feature keeps its ID and file; set its status to Rejected and say why under "Decision log".
- Use the ID wherever the work shows up:
    - branch: `feature/TTF007-role-default-theme`
    - commit subject: `TTF007: Add role default theme setting`
    - `CHANGELOG.md` entry: `- Default theme per role (TTF007).`
    - pull request title and e2e check names where it helps: `TTF007 role default: editor gets tt-child`

## Status values

| Status | Meaning |
| --- | --- |
| Idea | Suggested, not yet agreed. |
| Planned | Agreed and scheduled. The spec's open questions are answered. |
| In progress | Being built on a branch. |
| Done | Released. Add the version to the spec's header table. |
| Rejected | Not going to be built. The reason is in the spec. |

When the status changes, update the spec header, `docs/TODO.md` and the sheet together.

## Index

| ID | Feature | Group | Effort | Risk | Depends on |
| --- | --- | --- | --- | --- | --- |
| [TTF001](feature-TTF001.md) | Toolbar menu (theme switcher and feature links) | Quick win | Small | Low | — |
| [TTF002](feature-TTF002.md) | "Why this theme?" admin info | Quick win | Small | Low | TTF001 optional (toolbar menu) |
| [TTF003](feature-TTF003.md) | Usage stats | Quick win | Small | Low | — |
| [TTF004](feature-TTF004.md) | WP-CLI commands | Quick win | Small | Low | TTF005 (export/import format) |
| [TTF005](feature-TTF005.md) | Settings import/export (JSON) | Quick win | Small | Low | — |
| [TTF006](feature-TTF006.md) | Change hooks | Quick win | Small | Low | — |
| [TTF007](feature-TTF007.md) | Default theme per role | Medium | Medium | Medium | — |
| [TTF008](feature-TTF008.md) | Admin-assigned, locked themes | Medium | Medium | Low | — |
| [TTF009](feature-TTF009.md) | Time-limited assignments | Medium | Medium | Low | TTF008 (shared assignment data) |
| [TTF010](feature-TTF010.md) | Cache-plugin compatibility | Medium | Medium | Medium | — |
| [TTF011](feature-TTF011.md) | Audit log | Medium | Medium | Medium | TTF006 |
| [TTF012](feature-TTF012.md) | Rules beyond roles | Medium | Medium | Medium | — |
| [TTF013](feature-TTF013.md) | Preview across pages | Medium | Medium | Medium | — |
| [TTF014](feature-TTF014.md) | Multisite support | Larger | Large | High | — |
| [TTF015](feature-TTF015.md) | Settings for non-active classic themes | Larger | Large | High | — |
| [TTF016](feature-TTF016.md) | Visitor (logged-out) switching | Larger | Large | High | TTF010 |
| [TTF017](feature-TTF017.md) | REST API for preferences | Larger | Medium | Medium | — |
| [TTF018](feature-TTF018.md) | Shareable preview links (no login) | Larger | Large | Medium | TTF010; TTF001 optional (toolbar entry) |

## Spec template

Every spec uses these sections, in this order. Write "None" rather than leaving a section out.

1. Header table: ID, status, group, effort, risk, build order, depends on, related, released in.
2. **Summary**: one paragraph.
3. **Current state, required state, gap**: grounded in the code.
4. **User stories and acceptance criteria**.
5. **Design**: data, settings, flow, UI, hooks, files to change.
6. **Security**: each threat with its prevention.
7. **Caching and headers**.
8. **Data integrity, privacy and leakage**.
9. **Performance**.
10. **Compatibility**: WordPress and PHP versions, other plugins, multisite.
11. **Architecture invariants**: which of the invariants in `docs/ARCHITECTURE.md` are touched and how they stay true.
12. **Tests**: e2e checks to add to `tests/e2e/http-tests.sh`.
13. **Documentation to update**.
14. **Open questions**.
15. **Decision log**: dated decisions and changes to the spec.

The specs are designs, not promises: check them against the code again before building, because the code may have moved on.
