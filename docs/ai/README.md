# AI agent files

The source of truth for AI-agent guidance lives in this folder so that it is always part of the repository.

| File | Purpose |
| --- | --- |
| [themetoggle-development/SKILL.md](themetoggle-development/SKILL.md) | Step-by-step workflow, recipes and known pitfalls for changing the plugin. |
| [copilot-instructions.md](copilot-instructions.md) | Short pointer for GitHub Copilot. |

`AGENTS.md` and `CLAUDE.md` at the repository root already point here, so agents that read those files find the skill without any setup.

## Optional: auto-loading

The repository has no `.claude/` folder, and `.github/` holds only CI files (workflows, Dependabot): the files above are read through `AGENTS.md` and `CLAUDE.md`. Some tools only auto-load guidance from fixed folders; if you want that, copy the files there and keep this folder as the edited original.

| Tool | Copy to |
| --- | --- |
| Claude Code (project skill) | `.claude/skills/themetoggle-development/SKILL.md` |
| GitHub Copilot | `.github/copilot-instructions.md` (copy as is: it names files by repository-root paths, not links, so it is correct in either location) |

On Windows, from the plugin folder:

```bat
mkdir .claude\skills\themetoggle-development
copy docs\ai\themetoggle-development\SKILL.md .claude\skills\themetoggle-development\SKILL.md
mkdir .github
copy docs\ai\copilot-instructions.md .github\copilot-instructions.md
```

`.github/` is already excluded from release zips. If you create `.claude/`, also add `/.claude export-ignore` to `.gitattributes` and `/.claude` to `.distignore`. After editing the skill here, copy it again so both copies match.
