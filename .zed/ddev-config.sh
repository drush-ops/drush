#!/bin/sh
# Write .ddev/config.local.yaml with the DDEV project name derived from the
# worktree directory. Worktrees live at …/worktrees/drush/<name>/drush, so
# the parent directory of $PWD contains the name we want.
NAME=$(basename "$(dirname "$PWD")")
printf 'name: drush-%s\n' "$NAME" > .ddev/config.zed-worktree.local.yaml
