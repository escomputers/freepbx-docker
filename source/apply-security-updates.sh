#!/usr/bin/env bash
set -euo pipefail

package_dir=/opt/freepbx-security
manifest="$package_dir/packages.tsv"

fail() {
  echo "[security] ERROR: $*" >&2
  exit 1
}

[[ -s "$manifest" ]] || fail "Package manifest is missing or empty."

# Use the first two manifest columns; the remaining columns describe the download.
# Check package versions and status before installing anything.
update_required=0

while IFS=$'\t' read -r package expected_version _; do
  [[ -n "$package" && -n "$expected_version" ]] \
    || fail "Invalid entry in packages.tsv."

  package_info=$(dpkg-query -W \
    -f='${Version} ${db:Status-Status} ${db:Status-Eflag}' \
    "$package") || fail "Cannot read package status for $package."

  read -r current_version package_status error_flag <<< "$package_info"

  if [[ -n "$current_version" ]] &&
     dpkg --compare-versions "$current_version" gt "$expected_version"; then
    fail "$package: volumes contain version $current_version, newer than $expected_version."
  fi

  if [[ "$current_version $package_status $error_flag" != "$expected_version installed ok" ]]; then
    printf '[security] %s: %s -> %s\n' \
      "$package" "$package_info" "$expected_version"
    update_required=1
  fi
done < "$manifest"

if [[ "$update_required" == 0 ]]; then
  echo '[security] Packages are already up to date; skipping installation.'
  exit 0
fi

# Install the same package set used during the image build.
shopt -s nullglob
packages=("$package_dir"/debs/*.deb)

(( ${#packages[@]} > 0 )) \
  || fail "No .deb packages found."

[[ -x /usr/sbin/policy-rc.d ]] \
  || fail "policy-rc.d is missing or not executable."

echo '[security] Installing packages into the existing container filesystem.'

export DEBIAN_FRONTEND=noninteractive
dpkg --force-confold --install "${packages[@]}" \
  || fail "Package installation failed; service startup is blocked."

# Verify that every package is correctly installed at the expected version.
while IFS=$'\t' read -r package expected_version _; do
  package_info=$(dpkg-query -W \
    -f='${Version} ${db:Status-Status} ${db:Status-Eflag}' \
    "$package") || fail "Cannot verify $package after installation."

  [[ "$package_info" == "$expected_version installed ok" ]] \
    || fail "$package is not correctly installed at version $expected_version."
done < "$manifest"

echo '[security] Update completed and package versions verified.'
