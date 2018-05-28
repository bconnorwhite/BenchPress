#!/bin/bash
# $1: environment
# $2: domain
# $3: admin_user
# $4: admin_email
if [[ $# -gt 1 ]]; then
  DOMAIN="$(echo $2 | sed -e 's/\.[a-z]*$//')" #Domain with TLD removed (i.e. dev.test)
  SMUSH="${2//./_}" #Domain with dots replaced by _ (i.e. dev_test_com)
  if [[ -n "${DOMAIN}" && -n "${SMUSH}" ]]; then
    if [[ "${1}" == "local" ]]; then
      ROOT="/Users/connorwhite/Sites/${2}"
      ROOT_MYSQL_PASS=""
      URL="localhost/${2}"
    elif [[ "${1}" == "prod" ]]; then
      ROOT="/var/www/${2}/html"
      ROOT_MYSQL_PASS="-poPm4pqovMgxYtecchZA="
      URL="${DOMAIN}.essencewebhosting.com"
    fi
    #Ensure directory ($ROOT) doesn't already exist
    if [[ -n "${ROOT}" && -n "$URL" ]]; then
      if [[ ! -d $ROOT ]]; then
        mkdir -p $ROOT &&
        cd $ROOT &&
        wp core download
        if [[ "${1}" == "prod" ]]; then
          ln -s $ROOT /var/www/essencewebhosting.com/html/$DOMAIN
        fi
        MYSQL_PASS="$(openssl rand -base64 12)"
        mysql -uroot $ROOT_MYSQL_PASS -e "CREATE DATABASE IF NOT EXISTS wp_${SMUSH};
        	GRANT ALL PRIVILEGES ON wp_${SMUSH}.* TO '${SMUSH}'@'127.0.0.1' IDENTIFIED BY '${MYSQL_PASS}';
        	FLUSH PRIVILEGES;"
        wp config create --dbname=wp_$SMUSH --dbuser=$SMUSH --dbpass=$MYSQL_PASS --dbhost=127.0.0.1 --skip-check
        WP_PASS="$(openssl rand -base64 12)"
        wp core install --url=http://$URL --title= --admin_user=$3 --admin_password=$WP_PASS --admin_email=$4
        #Clean up
        wp plugin delete akismet
        wp plugin delete hello
        wp theme delete twentyfifteen
        wp theme delete twentysixteen
      else
        echo "Site already exists"
      fi
    else
      echo "Invalid environment"
    fi
  else
    echo "Invalid domain"
  fi
else
  echo "usage: create.sh [environment] [domain] [admin_user] [admin_email]"
fi
