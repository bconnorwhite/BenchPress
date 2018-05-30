#!/bin/bash
# $1: domain
if [[ $# -eq 1 ]]; then
  DOMAIN="$(echo $1 | sed -e 's/\.[a-z]*$//')" #Domain with TLD removed (i.e. dev.test)
  SMUSH="${1//./_}" #Domain with dots replaced by _ (i.e. dev_test_com)
  if [[ -n "${DOMAIN}" && -n "${SMUSH}" ]]; then
    PARENT="/Users/connorwhite/Sites/${1}"
    ROOT_MYSQL_PASS=""
    mysql -uroot $ROOT_MYSQL_PASS -e "DROP USER '${SMUSH}'@'127.0.0.1';
      DROP DATABASE wp_${SMUSH};"
    if [[ -n "${PARENT}" && -d $PARENT ]]; then
      rm -rf $PARENT
    fi
    exit 1
  else
    echo "Invalid domain"
    exit 0
  fi
else
  echo "usage: delete.sh [environment] [domain]"
  exit 0
fi
