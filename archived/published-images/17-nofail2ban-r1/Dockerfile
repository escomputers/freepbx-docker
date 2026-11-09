FROM escomputers/freepbx@sha256:34e65b7a0a02ad9da67315c12bcfd0368a89c3f6d185abf498ae8a48687f0daa

COPY pacchetti/ /opt/freepbx-security/

RUN dpkg --force-confold --install /opt/freepbx-security/debs/*.deb

COPY apply-security-updates.sh /usr/local/sbin/apply-security-updates.sh
COPY entrypoint.sh /usr/local/src/entrypoint.sh
