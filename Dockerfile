FROM ubuntu:18.04

USER root
WORKDIR /tmp
ENV DEBIAN_FRONTEND noninteractive

#######################################################################
# Basic installs
#######################################################################
RUN apt-get -y update\
    && apt-get -y install dbus apt-utils locales wget curl tzdata \
        ca-certificates openssl net-tools gnupg cron dialog telnet

#######################################################################
# Time Zone
#######################################################################
RUN ln -sf /usr/share/zoneinfo/UTC /etc/localtime\
    && echo "UTC" > /etc/timezone\
    && dpkg-reconfigure -f noninteractive tzdata

#######################################################################
# Character Set
#######################################################################
RUN locale-gen en_US.UTF-8\
    && update-locale LANG=en_US.UTF-8\
    && update-locale LANGUAGE=en_US:en\
    && update-locale LC_ALL=en_US.UTF-8

ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en
ENV LC_ALL en_US.UTF-8

#######################################################################
# MonetDB
#######################################################################
RUN printf "\
deb https://dev.monetdb.org/downloads/deb/ bionic monetdb\n\
deb-src https://dev.monetdb.org/downloads/deb/ bionic monetdb\n\
" > /etc/apt/sources.list.d/monetdb.list\
\
    && curl 'https://www.monetdb.org/downloads/MonetDB-GPG-KEY' | apt-key add -\
    && apt-get -y update\
    && apt-get -y install monetdb5-sql monetdb-client\
    && chown -R monetdb:monetdb /var/monetdb5/dbfarm\
    && chown -R monetdb:monetdb /var/log/monetdb\
\
    && printf "\
user=monetdb\n\
password=monetdb\n\
language=sql\n\
save_history=true\n\
" > /etc/skel/.monetdb

#######################################################################
# Apache + PHP
#######################################################################
RUN apt-get -y install apache2 php7.2 php7.2-cli php7.2-mbstring php7.2-curl\
    php7.2-gd php7.2-xml zip unzip nano\
    && printf "\n\nServerName localhost\n" >> /etc/apache2/apache2.conf\
    && curl 'https://getcomposer.org/installer' | php\
    && mv composer.phar /usr/bin/composer\
\
    && printf "\
Alias /MonetDB-PHP-Deux /var/MonetDB-PHP-Deux/Examples/WebQuery/www/\n\
\n\
<Directory /var/MonetDB-PHP-Deux/Examples/WebQuery/www>\n\
        Options -Indexes +FollowSymLinks -MultiViews\n\
        AllowOverride all\n\
        Order allow,deny\n\
        allow from all\n\
        Require all granted\n\
</Directory>\n\
" > /etc/apache2/conf-available/monet-browser.conf\
\
    && ln -s /etc/apache2/conf-available/monet-browser.conf \
        /etc/apache2/conf-enabled/monet-browser.conf

#######################################################################
# Creating the startup script
#######################################################################
RUN printf "#!/usr/bin/env bash\n\n\
service dbus start\n\
su monetdb -c '/usr/bin/monetdbd start /var/monetdb5/dbfarm' -s /bin/bash &\n\
service apache2 start\n\
service cron start\n\n\
while true; do sleep infinity; done\n\
" > /var/startup.sh\
\
    && chmod 755 /var/startup.sh

#######################################################################
# Finalizing setup
#######################################################################
EXPOSE 80
VOLUME ["/var/MonetDB-PHP-Deux"]
WORKDIR /var/MonetDB-PHP-Deux
ENV DEBIAN_FRONTEND dialog

CMD ["/var/startup.sh"]
