#!/usr/bin/env python3
"""Push this repo to GitHub as ONE commit, using the Git Data API.

The Contents API makes a separate commit per file, which turns a 14-file update
into 14 commits. This builds a tree instead: blobs -> tree -> commit -> move the
branch ref. One commit, clean history, and a diff you can actually read when you
need to work out when a number changed.

    export GITHUB_TOKEN=github_pat_...
    python scripts/gh_push.py --repo slight-oce/wz-customs -m "19 Jul results"
    python scripts/gh_push.py --repo slight-oce/wz-customs --dry-run

Token needs Contents: read and write on that repo only. Revoke it after — a
fine-grained token with a short expiry is the right shape for this.
"""
import argparse, base64, fnmatch, json, os, sys, urllib.request, urllib.error

API = "https://api.github.com"
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
SKIP_DIRS = {".git", "__pycache__", ".venv", "node_modules"}


def gh(method, path, token, body=None):
    req = urllib.request.Request(
        f"{API}{path}", method=method,
        data=json.dumps(body).encode() if body is not None else None,
        headers={"Authorization": f"Bearer {token}",
                 "Accept": "application/vnd.github+json",
                 "X-GitHub-Api-Version": "2022-11-28",
                 "Content-Type": "application/json",
                 "User-Agent": "wz-customs-push"})
    try:
        with urllib.request.urlopen(req) as r:
            return json.loads(r.read() or b"{}")
    except urllib.error.HTTPError as e:
        detail = e.read().decode()[:400]
        if e.code == 401:
            sys.exit("401 — token rejected. Expired, or not scoped to this repo.")
        if e.code == 403 and "rate limit" in detail:
            sys.exit("403 — rate limited. Is GITHUB_TOKEN actually set?")
        if e.code == 404:
            sys.exit(f"404 on {path} — check the repo name and that the token "
                     f"grants Contents access to it.")
        sys.exit(f"{e.code} on {method} {path}\n{detail}")


def ignored(rel, patterns):
    parts = rel.split("/")
    if any(p in SKIP_DIRS for p in parts):
        return True
    for pat in patterns:
        pat = pat.rstrip("/")
        if fnmatch.fnmatch(rel, pat) or any(fnmatch.fnmatch(p, pat) for p in parts):
            return True
    return False


def collect():
    patterns = []
    gi = os.path.join(ROOT, ".gitignore")
    if os.path.exists(gi):
        patterns = [l.strip() for l in open(gi)
                    if l.strip() and not l.startswith("#")]
    out = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            full = os.path.join(dirpath, fn)
            rel = os.path.relpath(full, ROOT).replace(os.sep, "/")
            if not ignored(rel, patterns):
                out.append((rel, full))
    return sorted(out)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--repo", required=True, help="owner/name")
    ap.add_argument("--branch", default="main")
    ap.add_argument("-m", "--message", default="update match data")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    files = collect()
    total = sum(os.path.getsize(f) for _, f in files)
    print(f"{len(files)} files, {total/1024:.1f} KB")
    for rel, full in files:
        print(f"  {os.path.getsize(full):>8,}  {rel}")
    if a.dry_run:
        print("\ndry run — nothing sent")
        return

    token = os.environ.get("GITHUB_TOKEN")
    if not token:
        sys.exit("GITHUB_TOKEN is not set.")

    # existing branch tip, if the repo has one
    base_sha = base_tree = None
    try:
        ref = gh("GET", f"/repos/{a.repo}/git/ref/heads/{a.branch}", token)
        base_sha = ref["object"]["sha"]
        base_tree = gh("GET", f"/repos/{a.repo}/git/commits/{base_sha}", token)["tree"]["sha"]
        print(f"\nbase commit {base_sha[:10]}")
    except SystemExit:
        print(f"\nno {a.branch} branch yet — creating it")

    tree = []
    for rel, full in files:
        blob = gh("POST", f"/repos/{a.repo}/git/blobs", token,
                  {"content": base64.b64encode(open(full, "rb").read()).decode(),
                   "encoding": "base64"})
        tree.append({"path": rel, "mode": "100644", "type": "blob", "sha": blob["sha"]})
        print(f"  blob {blob['sha'][:8]}  {rel}")

    body = {"tree": tree}
    if base_tree:
        body["base_tree"] = base_tree
    new_tree = gh("POST", f"/repos/{a.repo}/git/trees", token, body)

    commit = gh("POST", f"/repos/{a.repo}/git/commits", token,
                {"message": a.message, "tree": new_tree["sha"],
                 "parents": [base_sha] if base_sha else []})

    if base_sha:
        gh("PATCH", f"/repos/{a.repo}/git/refs/heads/{a.branch}", token,
           {"sha": commit["sha"]})
    else:
        gh("POST", f"/repos/{a.repo}/git/refs", token,
           {"ref": f"refs/heads/{a.branch}", "sha": commit["sha"]})

    print(f"\ncommitted {commit['sha'][:10]}  {a.message}")
    print(f"https://github.com/{a.repo}/commit/{commit['sha']}")
    print(f"Pages will rebuild in about a minute: "
          f"https://{a.repo.split('/')[0]}.github.io/{a.repo.split('/')[1]}/")
    print("\nRevoke the token now — it has no further use.")


if __name__ == "__main__":
    main()
