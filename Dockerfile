#FROM debian:bullseye-slim
FROM httpd:2.4-bullseye

# Set default Shell
SHELL ["/bin/bash", "-c"]

WORKDIR /usr/src

# Install tools for building
RUN apt-get update && apt-get install curl make lsb-release ca-certificates gnupg2 git wget -y

# Install the SURY Repository Signing Key
RUN curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
# Install the SURY Repository for PHP
RUN echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

# Install Required Dependencies
RUN apt-get update && apt-get -y install \
build-essential linux-headers-$(uname -r) \
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
COPY asterisk-16.30.0 asterisk/
# Build Asterisk
RUN bash asterisk/contrib/scripts/install_prereq install
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
RUN chown -R asterisk. /var/{lib,log,spool}/asterisk
RUN chown -R asterisk. /usr/lib/asterisk && rm -rf /var/www/html

# Download and install FreePBX
COPY freepbx freepbx/
RUN touch /etc/asterisk/{modules,cdr}.conf
#RUN sed -i 's/\$amp_conf\["AMPDBPASS"\] = md5(uniqid());/\$amp_conf\["AMPDBPASS"\] = "freepbxuser";/' freepbx/installlib/installcommand.class.php
RUN cd freepbx/ && ./start_asterisk start 
# && ./install --webroot=/usr/local/apache2/htdocs/ -n --dbhost=172.18.0.2
#RUN sed -i 's/\/var\/www\/html/\/usr\/local\/apache2\/htdocs/g' /etc/apache2/sites-available/000-default.conf
#RUN sed -i 's/\/var\/www\/html/\/usr\/local\/apache2\/htdocs/g' /etc/apache2/sites-available/default-ssl.conf
#RUN a2enmod rewrite
#RUN service apache2 restart

# # Install all Freepbx modules
# RUN fwconsole ma disablerepo commercial
# RUN fwconsole ma installall
# RUN fwconsole ma delete firewall
# RUN fwconsole reload
# RUN fwconsole restart