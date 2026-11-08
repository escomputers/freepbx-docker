#!/usr/bin/env bash
set -euo pipefail

# Default to the repository package directory; the Dockerfile supplies its own path.
package_dir="${1:-$(dirname -- "${BASH_SOURCE[0]}")/packages}"
cd -- "$package_dir"
mkdir -p debs

# Download the exact archived files, without selecting newer package versions.
while IFS=$'\t' read -r package version filename url checksum; do
  [[ -n "$package" && -n "$version" && -n "$filename" && -n "$url" && -n "$checksum" ]] || {
    echo "Invalid entry in packages.tsv." >&2
    exit 1
  }

  printf 'Downloading %s\n' "$filename"
  curl --proto '=https' -fsSL "$url" -o "debs/$filename"

  # Ubuntu provides sha256sum; macOS provides shasum.
  if command -v sha256sum >/dev/null 2>&1; then
    printf '%s  %s\n' "$checksum" "debs/$filename" | sha256sum --check -
  else
    printf '%s  %s\n' "$checksum" "debs/$filename" | shasum -a 256 --check -
  fi
done < packages.tsv
