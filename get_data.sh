#!/bin/bash

# Run: $ nohup ./get_data.sh > /dev/null 2>&1 &

while true
do
	/usr/bin/php /var/www/steamlug-bot/get_data.php
	sleep 30
done
