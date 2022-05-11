#!/bin/sh

CONFIG_FILE_PATH="config.php"

cat > /etc/ssmtp/ssmtp.conf <<EOM
root=postmaster
mailhub=$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_SERVER".{3}(.+)"/,a) {print a[1]}'):$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_SERVER_SMTPPORT".{3}(.+)"/,a) {print a[1]}')
FromLineOverride=NO
rewriteDomain=$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_USER".+@(.+)"/,a) {print a[1]}')
hostname=$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_USER".+@(.+)"/,a) {print a[1]}')
AuthUser=$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_USER".{3}(.+)"/,a) {print a[1]}')
AuthPass=$(cat "$CONFIG_FILE_PATH" | gawk 'match($0, /"MAIL_PASSWORD".{3}(.+)"/,a) {print a[1]}')
UseSTARTTLS=YES
EOM