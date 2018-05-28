#!/bin/bash
# $1: domain
# $2: admin_user
# $3: admin_email
if [[ $# -gt 0 ]]; then
  DOMAIN="$(echo $1 | sed -e 's/\.[a-z]*$//')" #Domain with TLD removed (i.e. dev.test)
  SMUSH="${1//./_}" #Domain with dots replaced by _ (i.e. dev_test_com)
  if [[ -n "${DOMAIN}" && -n "${SMUSH}" ]]; then
    ROOT="/Users/connorwhite/Sites/${1}"
    ROOT_MYSQL_PASS=""
    URL="localhost/${1}"
    #Ensure directory ($ROOT) doesn't already exist
    if [[ ! -d $ROOT ]]; then
      mkdir -p $ROOT &&
      cd $ROOT &&
      wp core download
      MYSQL_PASS="$(openssl rand -base64 12)"
      mysql -uroot $ROOT_MYSQL_PASS -e "CREATE DATABASE IF NOT EXISTS wp_${SMUSH};
      	GRANT ALL PRIVILEGES ON wp_${SMUSH}.* TO '${SMUSH}'@'127.0.0.1' IDENTIFIED BY '${MYSQL_PASS}';
      	FLUSH PRIVILEGES;"
      wp config create --dbname=wp_$SMUSH --dbuser=$SMUSH --dbpass=$MYSQL_PASS --dbhost=127.0.0.1 --skip-check
      WP_PASS="$(openssl rand -base64 12)"
      wp core install --url=http://$URL --title= --admin_user=$2 --admin_password=$WP_PASS --admin_email=$3
      #Clean up
      wp plugin delete akismet
      wp plugin delete hello
      wp theme delete twentyfifteen
      wp theme delete twentysixteen
      wp theme delete twentyseventeen
    else
      echo "Site already exists"
    fi
  else
    echo "Invalid domain"
  fi
else
  echo "usage: create.sh [environment] [domain] [admin_user] [admin_email]"
fi
