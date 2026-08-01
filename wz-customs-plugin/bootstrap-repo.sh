#!/usr/bin/env bash
#
# Publish this directory as the standalone slight-oce/wz-customs-plugin repo.
#
# This exists because the session that wrote the plugin could not create the
# repository itself: the GitHub App backing Claude Code on the web has no
# repo-creation permission, so the plugin was staged on a branch of wz-customs
# instead. Run this once from a machine that is logged in, and the staging
# arrangement goes away.
#
#   ./bootstrap-repo.sh                 # create private repo and push
#   ./bootstrap-repo.sh --public        # create it public instead
#   ./bootstrap-repo.sh --dry-run       # say what would happen, do nothing
#
# Requires the gh CLI, authenticated (`gh auth login`).

set -euo pipefail

REPO="slight-oce/wz-customs-plugin"
VISIBILITY="--private"
DRY_RUN=0

for arg in "$@"; do
	case "$arg" in
		--public)  VISIBILITY="--public" ;;
		--private) VISIBILITY="--private" ;;
		--dry-run) DRY_RUN=1 ;;
		*) echo "unknown option: $arg" >&2; exit 2 ;;
	esac
done

cd "$(dirname "$0")"

if [ ! -f wz-customs.php ]; then
	echo "error: run this from the plugin directory" >&2
	exit 1
fi

echo "repo:       $REPO"
echo "visibility: ${VISIBILITY#--}"
echo "source:     $(pwd)"

if [ "$DRY_RUN" -eq 1 ]; then
	echo
	echo "--dry-run: would create the repo, commit $(git ls-files -o -c --exclude-standard | wc -l | tr -d ' ') files, and push to main."
	exit 0
fi

if ! command -v gh >/dev/null 2>&1; then
	cat >&2 <<-'MSG'
	error: the gh CLI is not installed.

	Without it, create the repo by hand at https://github.com/new
	(name: wz-customs-plugin, private, do not add a README) and then:

	    git init -b main
	    git add .
	    git commit -m "WZ Customs WordPress plugin"
	    git remote add origin git@github.com:slight-oce/wz-customs-plugin.git
	    git push -u origin main
	MSG
	exit 1
fi

# Verify before creating anything, so a missing login fails here rather than
# halfway through.
gh auth status >/dev/null

if gh repo view "$REPO" >/dev/null 2>&1; then
	echo "repo already exists — pushing to it rather than creating it"
else
	gh repo create "$REPO" "$VISIBILITY" \
		--description "WordPress plugin that renders the WZ Customs rank review from the published data.json."
fi

# A fresh history: this directory was staged inside another repo, and its
# commits there are interleaved with tournament data that does not belong here.
if [ ! -d .git ]; then
	git init -b main
fi

git add .
git commit -m "WZ Customs WordPress plugin

Renders promotions, rank bands, squad standings and per-player breakdowns
from the published data.json. Filters every payload through an allowlist on
ingest so demotions, internal decisions and the band residual cannot reach a
page even when pointed at an admin build." || echo "nothing new to commit"

git remote remove origin 2>/dev/null || true
git remote add origin "https://github.com/$REPO.git"

for attempt in 1 2 3 4; do
	if git push -u origin main; then
		break
	fi
	if [ "$attempt" -eq 4 ]; then
		echo "push failed after 4 attempts" >&2
		exit 1
	fi
	sleep $(( 2 ** attempt ))
done

echo
echo "done — https://github.com/$REPO"
echo "the copy staged under wz-customs can now be deleted."
