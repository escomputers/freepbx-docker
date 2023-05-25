FROM debian:bullseye-slim

# Set default Shell
SHELL ["/bin/bash", "-c"]

# Install tools for building
RUN apt-get update && apt-get install curl make lsb-release ca-certificates gnupg2 git wget -y

# Install the SURY Repository Signing Key
RUN curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
# Install the SURY Repository for PHP
RUN echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

# Install Required Dependencies
RUN apt-get update && apt-get -y install \
build-essential linux-headers-$(uname -r) apache2 \
  mariadb-client bison flex php7.4 php7.4-curl php7.4-cli php7.4-common \
  php7.4-mysql php7.4-gd php7.4-mbstring php7.4-intl php7.4-xml php-pear \
  sox libncurses5-dev libssl-dev mpg123 libxml2-dev libnewt-dev \
  pkg-config automake libtool \
  autoconf git uuid uuid-dev libasound2-dev libogg-dev \
  libvorbis-dev libicu-dev libcurl4-openssl-dev libical-dev libneon27-dev libsrtp2-dev \
  libspandsp-dev sudo subversion libtool-bin python-dev \
  dirmngr sendmail-bin sendmail

# Install NodeJS
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash - && apt-get install -y nodejs

# Download Asterisk source files
RUN wget https://dl.dropboxusercontent.com/s/8i6krh4i8tziv5i/asterisk-16.30.0.tar.gz
RUN tar xvfz asterisk-16.30.0.tar.gz && rm -f asterisk-16.30.0.tar.gz
# Remove unused addons
RUN sed -i 's/libvpb-dev//' asterisk-16.30.0/contrib/scripts/install_prereq
# Build Asterisk
RUN bash asterisk-16.30.0/contrib/scripts/install_prereq install
RUN cd asterisk-16.30.0 && ./configure \
  --with-pjproject-bundled --with-jansson-bundled
RUN cd asterisk-16.30.0 && make menuselect.makeopts && menuselect/menuselect \
  --enable app_macro menuselect.makeopts
# Install and configure Asterisk
RUN cd asterisk-16.30.0 && make 
RUN cd asterisk-16.30.0 && make install 
RUN cd asterisk-16.30.0 && make config 
RUN cd asterisk-16.30.0 && ldconfig 
RUN update-rc.d -f asterisk remove
RUN useradd -m asterisk
RUN chown asterisk. /var/run/asterisk
RUN chown -R asterisk. /etc/asterisk
RUN chown -R asterisk. /var/{lib,log,spool}/asterisk
RUN chown -R asterisk. /usr/lib/asterisk && rm -rf /var/www/html

# Apache config
RUN sed -i 's/\(^upload_max_filesize = \).*/\120M/' /etc/php/7.4/apache2/php.ini
RUN cp /etc/apache2/apache2.conf /etc/apache2/apache2.conf_orig
RUN sed -i 's/^\(User\|Group\).*/\1 asterisk/' /etc/apache2/apache2.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
RUN a2enmod rewrite
RUN service apache2 restart

# Download and install FreePBX
RUN cd /usr/src
RUN wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-16.0-latest.tgz
RUN tar vxfz freepbx-16.0-latest.tgz && rm -f freepbx-16.0-latest.tgz
RUN touch /etc/asterisk/{modules,cdr}.conf
RUN cd freepbx && ./start_asterisk start && ./install -n --dbhost=db

# Install all Freepbx modules
RUN fwconsole ma disablerepo commercial
RUN fwconsole ma installall
RUN fwconsole ma delete firewall
RUN fwconsole reload
RUN fwconsole restart