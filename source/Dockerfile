FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

WORKDIR /usr/src

# Pinned sources versions (override with --build-arg for reproducible builds)
ARG ASTERISK_VERSION=21.10.2
ARG ASTERISK_VERSION_TAG=21.10.2

ARG FREEPBX_VERSION=17.0.21
ARG FREEPBX_VERSION_TAG=17.0.2

# Install requirements
RUN apt-get update && apt-get -y install \
  build-essential pkg-config cron curl wget \
  bison flex libnewt-dev libssl-dev libncurses5-dev \
  subversion libsqlite3-dev libjansson-dev \
  libxml2-dev uuid uuid-dev default-libmysqlclient-dev \
  lame ffmpeg mpg123 expect \
  # PHP 8.2 Installation
  && echo "postfix postfix/mailname string freepbx.localdomain" | debconf-set-selections \
  && echo "postfix postfix/main_mailer_type string 'Internet Site'" | debconf-set-selections \
  && apt-get install -y apache2 mariadb-client \
  php8.2 php8.2-curl php8.2-cli \
  php8.2-common php8.2-mysql php8.2-gd php8.2-mbstring php8.2-intl \
  php8.2-xml php-pear \
  sox sqlite3 automake libtool autoconf unixodbc-dev \
  libasound2-dev libogg-dev libvorbis-dev libicu-dev libcurl4-openssl-dev \
  odbc-mariadb unixodbc \
  libical-dev libneon27-dev libsrtp2-dev libspandsp-dev libtool-bin \
  nodejs npm ipset iptables fail2ban php-soap \
  postfix libsasl2-modules mailutils \
  certbot python3-certbot-apache logrotate

# Download and build Asterisk directly in this stage
RUN set -eux \
  && AST_URL="https://github.com/escomputers/freepbx-docker/releases/download/v${ASTERISK_VERSION_TAG}/asterisk-${ASTERISK_VERSION}.tar.gz" \
  && curl -fsSL "$AST_URL" -o asterisk.tar.gz \
  && tar -xzf asterisk.tar.gz \
  && cd "asterisk-${ASTERISK_VERSION}" \
  && contrib/scripts/get_mp3_source.sh \
  && contrib/scripts/install_prereq install \
  && ./configure --libdir=/usr/lib64 --with-pjproject-bundled --with-jansson-bundled \
  && make menuselect.makeopts \
  && menuselect/menuselect --disable BUILD_NATIVE --enable format_mp3 menuselect.makeopts \
  && make -j"$(nproc)" \
  && make install \
  && make samples \
  && ldconfig \
  # Create asterisk user and give permissions
  && groupadd asterisk \
  && useradd -r -d /var/lib/asterisk -g asterisk asterisk \
  && usermod -aG audio,dialout asterisk \
  && chown -R asterisk:asterisk /etc/asterisk \
  && chown -R asterisk:asterisk /var/lib/asterisk /var/log/asterisk /var/spool/asterisk \
  && chown -R asterisk:asterisk /usr/lib64/asterisk \
  && sed -i 's|;runuser|runuser|' /etc/asterisk/asterisk.conf \
  && sed -i 's|;rungroup|rungroup|' /etc/asterisk/asterisk.conf \
  && echo "/usr/lib64" >> /etc/ld.so.conf.d/x86_64-linux-gnu.conf \
  && ldconfig \
  # Configure Apache web server
  && sed -i 's/\(^upload_max_filesize = \).*/\120M/' /etc/php/8.2/apache2/php.ini \
  && sed -i 's/\(^memory_limit = \).*/\1256M/' /etc/php/8.2/apache2/php.ini \
  && sed -i 's/^\(User\|Group\).*/\1 asterisk/' /etc/apache2/apache2.conf \
  && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
  && a2enmod rewrite \
  && apache2ctl -t \
  && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
  && rm -rf /usr/src/asterisk-${ASTERISK_VERSION} asterisk.tar.gz /var/www/html/index.html \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /usr/local/src

# FreePBX source
RUN set -eux \
  && FREEPBX_URL="https://github.com/escomputers/freepbx-docker/releases/download/v${FREEPBX_VERSION_TAG}/freepbx-${FREEPBX_VERSION}.tgz" \
  && curl -fsSL "$FREEPBX_URL" -o freepbx.tgz \
  && tar -xzf freepbx.tgz \
  && rm freepbx.tgz

COPY odbc/odbcinst.ini /etc/odbcinst.ini
COPY odbc/odbc.ini /etc/odbc.ini
COPY fail2ban/jail.local /etc/fail2ban/jail.local
COPY fail2ban/filter.d/asterisk.conf /etc/fail2ban/filter.d/asterisk.conf
COPY postfix/main.cf /etc/postfix/main.cf
COPY logrotate/asterisk /etc/logrotate.d/asterisk
COPY entrypoint.sh entrypoint.sh

RUN sed -i "s|^smtp_sasl_password_maps.*|smtp_sasl_password_maps = texthash:/run/secrets/postfix_sasl_passwd|" /etc/postfix/main.cf \
  && mkdir -p /var/log/asterisk \
  && touch /var/log/asterisk/full \
  && chown -R asterisk:asterisk /var/log/asterisk

CMD ["/usr/bin/env", "bash", "/usr/local/src/entrypoint.sh"]
