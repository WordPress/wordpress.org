FROM mysql:5.7

# Set default password
ENV MYSQL_ROOT_PASSWORD=mysql
ENV MYSQL_DATABASE=wordcamp_dev

ADD wordcamp_dev.sql /docker-entrypoint-initdb.d/data.sql

RUN apt-get install nano -y

EXPOSE 3306
