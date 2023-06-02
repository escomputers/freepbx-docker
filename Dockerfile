#FROM httpd:2.4-bullseye
FROM debian:bullseye-slim

WORKDIR /usr/src

# Install required tools
RUN apt-get update && apt-get install cron curl make lsb-release ca-certificates gnupg2 git wget -y

# Install the SURY Repository Signing Key
RUN curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
# Install the SURY Repository for PHP
RUN echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

# Install Required Dependencies
RUN apt-get update && apt-get -y install \
build-essential linux-headers-$(uname -r) \
  apache2 bison flex php7.4 php7.4-curl php7.4-cli php7.4-common \
  php7.4-mysql php7.4-gd php7.4-mbstring php7.4-intl php7.4-xml php-pear \
  sox libncurses5-dev libssl-dev mpg123 libxml2-dev libnewt-dev \
  pkg-config automake libtool \
  autoconf git uuid uuid-dev libasound2-dev libogg-dev \
  libvorbis-dev libicu-dev libcurl4-openssl-dev libical-dev libneon27-dev libsrtp2-dev \
  libspandsp-dev sudo subversion libtool-bin python-dev \
  dirmngr sendmail-bin sendmail

# Install NodeJS
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash - && apt-get install -y nodejs

# Modifications for Apache
RUN sudo sed -i 's/upload_max_filesize = 20M/upload_max_filesize = 120M/' /etc/php/7.4/apache2/php.ini
RUN sed -i 's/www-data/asterisk/' /etc/apache2/envvars

# Install MySQL Connector ODBC
RUN apt install unixodbc-dev unixodbc dpkg-dev libodbc1 odbcinst1debian2 wget -y
COPY odbc /usr/src/odbc
RUN cd odbc/ && dpkg -i mysql-common_8.0.27-1debian11_amd64.deb
RUN cd odbc/ && dpkg -i mysql-community-client-plugins_8.0.27-1debian11_amd64.deb
RUN cd odbc/ && dpkg -i mysql-community-client-core_8.0.27-1debian11_amd64.deb
RUN cd odbc/ && dpkg -i mysql-community-client_8.0.27-1debian11_amd64.deb
RUN cd odbc/ && dpkg -i mysql-connector-odbc_8.0.33-1debian11_amd64.deb

# Copy FreePBX files
COPY freepbx/installlib/files/odbc.ini /etc/odbc.ini
COPY freepbx/installlib/files/odbcinst.ini /etc/odbcinst.ini

# Download Asterisk source files
COPY asterisk-16.30.0 asterisk/
# Build Asterisk
RUN asterisk/contrib/scripts/install_prereq install
RUN cd asterisk/ && ./configure \
  --with-pjproject-bundled --with-jansson-bundled
RUN cd asterisk/ && make menuselect.makeopts && menuselect/menuselect \
  --enable app_macro menuselect.makeopts
# Install and configure Asterisk
RUN cd asterisk/ && make 
RUN cd asterisk/ && make install 
RUN cd asterisk/ && make config 
RUN cd asterisk/ && ldconfig 
RUN update-rc.d -f asterisk remove
RUN useradd -m asterisk
RUN chown asterisk. /var/run/asterisk
RUN chown -R asterisk. /etc/asterisk
RUN chown -R asterisk. /var/lib/asterisk
RUN chown -R asterisk. /var/log/asterisk
RUN chown -R asterisk. /var/spool/asterisk
RUN chown -R asterisk. /usr/lib/asterisk && rm -rf /var/www/html

# Download and install FreePBX
COPY freepbx freepbx/
RUN touch /etc/asterisk/modules.conf && touch /etc/asterisk/cdr.conf

CMD ["apache2ctl", "-D", "FOREGROUND"]