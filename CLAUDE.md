# CLAUDE.md

All project instructions for agents are in AGENTS.md; follow them.

@AGENTS.md

## Claude-specific

- For any code change, follow the `themetoggle-development` skill: read `docs/ai/themetoggle-development/SKILL.md` first. It is not installed as an auto-loading project skill; read it explicitly.
- When this folder is on the user's Windows machine (WAMP) and you work from a sandbox, run PHPCS and tests in the sandbox and copy results back; do not assume the user's machine has PHP, Composer or Node on PATH.
- Report honestly: list commands actually run and their results; say plainly what was not verified.
