#!/usr/bin/env bash
# TE-035: compare the rootfs diff_ids of the scanned local image with those of the pushed image (by digest).
# Usage: compare-diff-ids.sh <local-ref> <registry-image@sha256:digest>
# Exit 0 only when both lists are equal and non-empty. Prints layer digests only (no secrets).
set -euo pipefail
local_ref="${1:?local image ref}"
pushed_ref="${2:?pushed image@digest}"
[[ "$pushed_ref" == *@sha256:* ]] || { echo "::error::pushed ref must be image@sha256:..." >&2; exit 2; }

local_ids="$(docker image inspect --format '{{range .RootFS.Layers}}{{println .}}{{end}}' "$local_ref")"
pushed_ids="$(docker buildx imagetools inspect "$pushed_ref" --format '{{range (index .Image "linux/amd64").RootFS.DiffIDs}}{{println .}}{{end}}' 2>/dev/null \
    || docker buildx imagetools inspect "$pushed_ref" --format '{{range .Image.RootFS.DiffIDs}}{{println .}}{{end}}')"

echo "local  diff_ids ($local_ref):"; echo "$local_ids"
echo "pushed diff_ids ($pushed_ref):"; echo "$pushed_ids"
if [ -z "$local_ids" ] || [ "$local_ids" != "$pushed_ids" ]; then
    echo "::error::diff_ids differ (or are empty): the pushed image is not the scanned image" >&2
    exit 1
fi
echo "diff_ids match"
