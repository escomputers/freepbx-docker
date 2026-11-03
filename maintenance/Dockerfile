FROM escomputers/freepbx-base@sha256:75b3309ff84323aaaeae0da82a5e2ad80c668b2dc7a1fce95bd4703e42a30593

COPY packages/ /opt/freepbx-security/

RUN dpkg --force-confold --install /opt/freepbx-security/debs/*.deb

# Keep the runtime scripts in the repository, rather than relying on copies
# inherited from the pinned base image.
COPY apply-security-updates.sh /usr/local/sbin/apply-security-updates.sh
COPY entrypoint.sh /usr/local/src/entrypoint.sh
