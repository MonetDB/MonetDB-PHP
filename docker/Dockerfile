#######################################################################
# Copyright 2020 Tamas Bolner
# 
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#######################################################################

FROM debian:11.6

USER root
ENV DEBIAN_FRONTEND noninteractive
ENV TZ="Etc/UTC"

#######################################################################
# Basic installs and setup
#######################################################################
RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y locales apt-utils wget curl g++ make libssl-dev \
        net-tools gnupg dialog supervisor lsb-release nano vim mc \
        apt-transport-https software-properties-common gnupg2 zip unzip \
    && sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
    && locale-gen

ENV LANG en_US.UTF-8  
ENV LANGUAGE en_US:en  
ENV LC_ALL en_US.UTF-8 

#######################################################################
# Create user
#######################################################################
RUN printf "\
user=monetdb\n\
password=monetdb\n\
language=sql\n\
save_history=true\n\
" > /etc/skel/.monetdb

#######################################################################
# MonetDB
#######################################################################
RUN useradd -m -s /bin/bash mdb \
    && printf "\
deb https://dev.monetdb.org/downloads/deb/ bullseye monetdb\n\
deb-src https://dev.monetdb.org/downloads/deb/ bullseye monetdb\n\
" > /etc/apt/sources.list.d/monetdb.list \
    && wget -O /etc/apt/trusted.gpg.d/monetdb.gpg https://dev.monetdb.org/downloads/MonetDB-GPG-KEY.gpg \
    && apt-get update \
    && apt-get -y install monetdb5-sql monetdb-client \
    && chown -R mdb:mdb /var/monetdb5/dbfarm \
    && chown -R mdb:mdb /var/log/monetdb \
    && chown -R mdb:mdb /run/monetdb \
    && usermod -a -G monetdb mdb \
    && su mdb -c "monetdbd start /var/monetdb5/dbfarm && monetdb create myDatabase" \
    && su mdb -c "monetdb release myDatabase && monetdb start myDatabase"

#######################################################################
# Apache + PHP
#######################################################################
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/sury-php.list \
    && curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-keyring.gpg \
    && apt-get update \
    && apt-get -y install apache2 php8.1 php8.1-cli php8.1-mbstring php8.1-curl php8.1-gd php8.1-xml \
    && printf "\n\nServerName localhost\n" >> /etc/apache2/apache2.conf \
    && curl 'https://getcomposer.org/installer' | php \
    && mv composer.phar /usr/bin/composer \
\
    && printf "\
Alias /MonetDB-PHP /var/MonetDB-PHP/Examples/WebQuery/www/\n\
\n\
<Directory /var/MonetDB-PHP/Examples/WebQuery/www>\n\
        Options -Indexes +FollowSymLinks -MultiViews\n\
        AllowOverride all\n\
        Order allow,deny\n\
        allow from all\n\
        Require all granted\n\
</Directory>\n\
" > /etc/apache2/conf-available/monet-browser.conf\
\
    && ln -s /etc/apache2/conf-available/monet-browser.conf \
        /etc/apache2/conf-enabled/monet-browser.conf \
    && usermod -a -G www-data mdb

#################################################################
# Supervisor
#################################################################
RUN printf "\
[program:apache2]\n\
command=/bin/bash -c 'source /etc/apache2/envvars && /usr/sbin/apache2 -D FOREGROUND'\n\
user=root\n\
numprocs=1\n\
autostart=true\n\
autorestart=true\n\
" > /etc/supervisor/conf.d/apache2.conf \
\
    && printf "#!/usr/bin/env bash\n\
\n\
trap stop_function SIGINT SIGTERM\n\
\n\
function stop_function() {\n\
    monetdbd stop /var/monetdb5/dbfarm\n\
    exit\n\
}\n\
\n\
monetdbd start /var/monetdb5/dbfarm\n\
\n\
while [ -f /run/monetdb/merovingian.pid ] && kill -0 $(cat /run/monetdb/merovingian.pid) ; do\n\
    sleep 0.2\n\
done\n\
" > /var/monetdb.sh \
\
    && chown mdb:mdb /var/monetdb.sh \
    && chmod 754 /var/monetdb.sh \
\
    && printf "\
[program:monetdbd]\n\
command=/var/monetdb.sh\n\
user=mdb\n\
numprocs=1\n\
autostart=true\n\
autorestart=true\n\
" > /etc/supervisor/conf.d/monetdbd.conf

#######################################################################
# Finalizing setup
#######################################################################
EXPOSE 80
VOLUME ["/var/MonetDB-PHP"]
WORKDIR /var/MonetDB-PHP
ENV DEBIAN_FRONTEND dialog

# ENTRYPOINT ["tail", "-f", "/dev/null"]
ENTRYPOINT ["/usr/bin/supervisord", "-n"]
