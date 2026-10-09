#!/usr/bin/env bash
# Runs docs/testing/MODULE_FIX_PROMPT.md for each remaining module, one fresh
# headless Claude session per module, and commits each module separately on the
# auto/module-fixes branch so every run can be reviewed on its own.
#
# Usage:
#   scripts/fix-modules.sh                  # start at module 9 (fleet-compliance)
#   scripts/fix-modules.sh maintenance      # start at a specific slug
#   scripts/fix-modules.sh billing billing  # run only one module (start = stop)
#
# Resumable: a module whose report already exists in docs/testing/reports/ is skipped.
set -uo pipefail

cd "$(dirname "$0")/.."

MODULES=(
  "09 fleet-compliance"
  "10 maintenance"
  "11 fleet-assets"
  "12 otp-sms"
  "13 companies"
  "14 billing"
  "15 subscription-lifecycle"
  "16 subscription-admin"
  "17 driver-hiring"
  "18 compliance-reminders"
  "19 auth"
  "20 dashboard"
  "21 mail-delivery"
)

START="${1:-fleet-compliance}"
STOP="${2:-}"
BRANCH="auto/module-fixes"
LOG_DIR="docs/testing/runs"
PROMPT_FILE="docs/testing/MODULE_FIX_PROMPT.md"

if [ -n "$(git status --porcelain)" ]; then
  echo "Working tree is dirty. Commit or stash your changes first (e.g. the driver-compliance run)." >&2
  exit 1
fi

if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  git checkout "$BRANCH"
else
  git checkout -b "$BRANCH"
fi

mkdir -p "$LOG_DIR"

# The prompt is the ```text block of MODULE_FIX_PROMPT.md.
BASE_PROMPT="$(awk '/^```text$/{f=1;next} /^```$/{if(f)exit} f' "$PROMPT_FILE")"
if [ -z "$BASE_PROMPT" ]; then
  echo "Could not extract the prompt from $PROMPT_FILE" >&2
  exit 1
fi

UNATTENDED_NOTE="You are running unattended: nobody will answer questions. Never ask the user anything. \
Anything that needs a product decision or a human must be marked DEFERRED in the report with the exact question, then move on. \
Never run git commit, git push, git checkout or git reset; the wrapper script commits for you."

started=0
for entry in "${MODULES[@]}"; do
  num="${entry%% *}"
  slug="${entry#* }"

  [ "$slug" = "$START" ] && started=1
  [ "$started" -eq 1 ] || continue

  if [ -f "docs/testing/reports/$slug.md" ]; then
    echo "[$num $slug] report exists, skipping"
  else
    echo "[$num $slug] starting $(date '+%H:%M:%S')"
    prompt="$(printf '%s\n' "$BASE_PROMPT" | sed "s/^MODULE: .*/MODULE: $slug/")"

    claude -p "$prompt" \
      --name "$num-$slug" \
      --permission-mode auto \
      --append-system-prompt "$UNATTENDED_NOTE" \
      --disallowedTools \
        "Bash(git commit:*)" "Bash(git push:*)" "Bash(git checkout:*)" "Bash(git reset:*)" \
        "Bash(php artisan migrate:fresh:*)" "Bash(php artisan db:seed:*)" "Bash(php artisan migrate:*)" \
        "Bash(composer update:*)" "Bash(composer require:*)" \
      > "$LOG_DIR/$num-$slug.log" 2>&1
    status=$?
    echo "[$num $slug] claude exited with $status (log: $LOG_DIR/$num-$slug.log)"

    git add -A
    if git diff --cached --quiet; then
      echo "[$num $slug] no changes"
    else
      git commit -q -m "$slug: automated module fix run (needs review)

Exit status: $status. Report: docs/testing/reports/$slug.md

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
      echo "[$num $slug] committed $(git rev-parse --short HEAD)"
    fi
  fi

  [ -n "$STOP" ] && [ "$slug" = "$STOP" ] && break
done

echo "Done. Review with: git log --oneline main..$BRANCH"
echo "Deferred questions:  grep -n DEFERRED docs/testing/reports/*.md"
