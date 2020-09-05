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

FROM ubuntu:18.04

USER root
WORKDIR /tmp
ENV DEBIAN_FRONTEND noninteractive

#######################################################################
# Create host user
#######################################################################
ARG ENV_USER_ID
ARG ENV_USER_NAME

RUN printf "\
user=monetdb\n\
password=monetdb\n\
language=sql\n\
save_history=true\n\
" > /etc/skel/.monetdb \
    && useradd -m -s /bin/bash -u $ENV_USER_ID $ENV_USER_NAME

#######################################################################
# Basic installs
#######################################################################
RUN apt-get -y update\
    && apt-get -y install apt-utils locales wget curl tzdata g++ make \
        ca-certificates openssl net-tools gnupg cron dialog supervisor

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
" > /etc/apt/sources.list.d/monetdb.list \
\
    && curl 'https://www.monetdb.org/downloads/MonetDB-GPG-KEY' | apt-key add - \
    && apt-get -y update \
    && apt-get -y install monetdb5-sql monetdb-client \
    && chown -R ${ENV_USER_NAME}:${ENV_USER_NAME} /var/monetdb5/dbfarm \
    && chown -R ${ENV_USER_NAME}:${ENV_USER_NAME} /var/log/monetdb \
    && chown -R ${ENV_USER_NAME}:${ENV_USER_NAME} /run/monetdb \
    && usermod -a -G monetdb $ENV_USER_NAME \
    && su ${ENV_USER_NAME} -c "monetdbd start /var/monetdb5/dbfarm && monetdb create myDatabase" \
    && su ${ENV_USER_NAME} -c "monetdb release myDatabase && monetdb start myDatabase"

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
    && usermod -a -G www-data $ENV_USER_NAME

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
    && chown ${ENV_USER_NAME}:${ENV_USER_NAME} /var/monetdb.sh \
    && chmod 754 /var/monetdb.sh \
\
    && printf "\
[program:monetdbd]\n\
command=/var/monetdb.sh\n\
user="${ENV_USER_NAME}"\n\
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

ENTRYPOINT ["/usr/bin/supervisord", "-n"]
