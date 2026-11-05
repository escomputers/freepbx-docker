# Source archive verification

## Asterisk

| Archive | SHA-256 | Upstream | 
| --- | --- | --- |
| [asterisk-21.10.2.tar.gz](https://github.com/escomputers/freepbx-docker/releases/download/v21.10.2/asterisk-21.10.2.tar.gz) | `9624e807a1138cabed14431a326c9870e53fc132738dbbd61314abc027785d94` | [asterisk-21.10.2.sha256](https://downloads.asterisk.org/pub/telephony/asterisk/releases/asterisk-21.10.2.sha256) |

The archive’s GPG signature was verified using the [detached signature published by Asterisk](https://downloads.asterisk.org/pub/telephony/asterisk/releases/asterisk-21.10.2.tar.gz.asc):
- Signer: `Asterisk Development Team <asteriskteam@digium.com>`.
- Full signing-key fingerprint: `F2FC93DB7587BD1FB49E045A5D984BE337191CE7`.
- Signature date: 2025-08-28.

The public key was retrieved from the [Ubuntu keyserver by its full fingerprint](https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xF2FC93DB7587BD1FB49E045A5D984BE337191CE7).\
Its identity was cross-checked against the [Debian announcement identifying this upstream signing key](https://lists.debian.org/debian-devel-changes/2020/08/msg03590.html) and the [Asterisk developer's key-distribution guidance](https://community.asterisk.org/t/gpg-key-for-downloads-asterisk-org/87164/2).

## FreePBX

| Archive | SHA-256 |
| --- | --- |
| [freepbx-17.0.21.tgz](https://github.com/escomputers/freepbx-docker/releases/download/v17.0.2/freepbx-17.0.21.tgz) | `4d37f8547038db2621a54dfc016b8116a485be7d1685d35ce9624d223cbe8d2f` |

| Component | SHA-256 | Signature date |
| --- | --- | --- |
| [framework-17.0.21.tgz](https://mirror.freepbx.org/modules/packages/framework/framework-17.0.21.tgz) | `22e2680c6ab9f3d66769dbb7704f1221d083663651ad248a9b5c9d5247f1cff9` | 2025-09-15 |
| [core-17.0.18.34.tgz](https://mirror.freepbx.org/modules/packages/core/core-17.0.18.34.tgz) | `8a7a05582582cc436e77ac4b308dc8b409e31f7ebd95a851beae77a530a675c4` | 2025-08-14 |
| [cdr-17.0.4.38.tgz](https://mirror.freepbx.org/modules/packages/cdr/cdr-17.0.4.38.tgz) | `9dc9adc691d50df8eec680c69a0d4ecd125234dbc0e6f75f145c16bf26a2b3ec` | 2025-04-28 |
| [dashboard-17.0.4.6.tgz](https://mirror.freepbx.org/modules/packages/dashboard/dashboard-17.0.4.6.tgz) | `b6983226b744d767c593c8536bf547547b0d28614b87165976ff424558588438` | 2024-10-13 |
| [customappsreg-17.0.1.tgz](https://mirror.freepbx.org/modules/packages/customappsreg/customappsreg-17.0.1.tgz) | `5d84789bddd87992db90e8bb60555a3a5a72b22c6651105c3f4349b0036c812b` | 2023-09-26 |
| [certman-17.0.3.13.tgz](https://mirror.freepbx.org/modules/packages/certman/certman-17.0.3.13.tgz) | `9dd4604d5bcd87ad0bb9da9e752ef6280444fc31d0981af5685b1f527c290f85` | 2024-08-13 |
| [voicemail-17.0.5.34.tgz](https://mirror.freepbx.org/modules/packages/voicemail/voicemail-17.0.5.34.tgz) | `3a1a8954edbd8e88688e8fea7bc1cdb10d0bbec1ab30e0a91c8330a3b5bd850b` | 2025-09-11 |
| [music-17.0.5.tgz](https://mirror.freepbx.org/modules/packages/music/music-17.0.5.tgz) | `5ee3b18efc738ce2ddae0ce322e37fefcee60c5119a5aa8ce56fb1fbac226086` | 2024-07-26 |
| [featurecodeadmin-17.0.2.tgz](https://mirror.freepbx.org/modules/packages/featurecodeadmin/featurecodeadmin-17.0.2.tgz) | `1a2d3578d7fb7406ae7b29e35ef86f7e83da6b067e7200d84b1ecbc3c6dbf338` | 2023-09-13 |
| [infoservices-17.0.1.1.tgz](https://mirror.freepbx.org/modules/packages/infoservices/infoservices-17.0.1.1.tgz) | `250b3c44592aa0ee22e0e00bad889065ebcfbb997503bbdc05dea90f472898ab` | 2024-07-17 |
| [sipsettings-17.0.6.10.tgz](https://mirror.freepbx.org/modules/packages/sipsettings/sipsettings-17.0.6.10.tgz) | `f898c20f41e0767cb5b535193523f0d7969c4f02f535e2a4a2175ae70af5a04d` | 2025-07-11 |
| [logfiles-17.0.3.3.tgz](https://mirror.freepbx.org/modules/packages/logfiles/logfiles-17.0.3.3.tgz) | `5c99d5d5c8e9a176d7d3e7e9d2fef55740546ce85ee9281832312ebc3ad7495b` | 2024-06-06 |
| [callrecording-17.0.3.8.tgz](https://mirror.freepbx.org/modules/packages/callrecording/callrecording-17.0.3.8.tgz) | `50c0bd6b6295aa952220a57faa0c58b34ef528155cf9925e17743a9b77656ac0` | 2024-10-10 |
| [recordings-17.0.2.14.tgz](https://mirror.freepbx.org/modules/packages/recordings/recordings-17.0.2.14.tgz) | `1ff2e7960ad06d669bd75072b32ecfb7cb981e96fd3499cfec9ddc2b8dc60075` | 2025-09-10 |
| [conferences-17.0.3.2.tgz](https://mirror.freepbx.org/modules/packages/conferences/conferences-17.0.3.2.tgz) | `332442ac86ef61436b2ce93b91d5aca7dc7b492abc4eb19fa404685a1cda029d` | 2024-05-23 |
| [pm2-17.0.3.4.tgz](https://mirror.freepbx.org/modules/packages/pm2/pm2-17.0.3.4.tgz) | `accee0878685cc1b1e4451b4318b5382392a1b57368fe157266c567ae634c346` | 2025-02-27 |

For each archive, the top-level directory name was removed from the paths before comparison. Bundled module directories were compared separately from framework. Regular files were compared using SHA-256; symbolic and hard links were compared by type and target. All 9592 file/link entries matched, with no missing, additional, or differing entries.

The GPG signatures of all official packages were verified using their corresponding `.tgz.gpg` files ([framework example](https://mirror.freepbx.org/modules/packages/framework/framework-17.0.21.tgz.gpg)):
- Signer: `FreePBX Mirror Servers <security@freepbx.org>`.
- Full signing-key fingerprint: `456D051E9204C27C37D4811BB53D215A755231A3`.
- Signing key's certification verified using the FreePBX v2 master key: `0BDE0BFA09946D732091E26E1588A7366BD35B34`

Both public keys were retrieved from the Ubuntu keyserver by their full fingerprints ([mirror signing key](https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x456D051E9204C27C37D4811BB53D215A755231A3), [master key](https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x0BDE0BFA09946D732091E26E1588A7366BD35B34)).\
Their fingerprints were cross-checked against the [official framework 17.0.21 GPG implementation](https://github.com/FreePBX/framework/blob/release/17.0.21/amp_conf/htdocs/admin/libraries/BMO/GPG.class.php), which lists both keys.
