FROM escomputers/freepbx@sha256:094a74c0bc67b6ced2b0f710223260e9e8b7e6e75f6179489f56bf1f4aadc123

COPY entrypoint.sh /usr/local/src/entrypoint.sh

RUN set -eux \
  && test -f /usr/local/sbin/apply-security-updates.sh \
  && bash -n /usr/local/src/entrypoint.sh \
  && freepbx_installer="/usr/local/src/freepbx/installlib/installcommand.class.php" \
  && sed -i '/You have successfully installed FreePBX/{n;s/return 1;/return 0;/;}' "$freepbx_installer" \
  && test "$(sed -n '/You have successfully installed FreePBX/{n;p;}' "$freepbx_installer" | tr -d '[:space:]')" = 'return0;' \
  && php -l "$freepbx_installer"
