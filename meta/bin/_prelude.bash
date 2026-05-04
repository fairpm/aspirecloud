# This file should be sourced, not run

set -euo pipefail

# These are not exported, but will be visible in the tool's script if they need them
__ORIG_PWD=$PWD
__HERE=$(dirname "$0")

function warn { echo "$@" >&2; }
function die  { warn "$@"; exit 1; }

for file in "$__HERE"/_prelude.d/*.bash; do
    # shellcheck source=/dev/null
    [[ -f $file ]] && source "$file"
done

cd "$__HERE"/../..
