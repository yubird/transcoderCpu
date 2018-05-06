#!/bin/sh
MAX=10
CNT=0
TARGET=$1
HD=$2
COMMAND="/usr/bin/php /usr/local/chinachu/transcode/exec.php"
FILES=`ls $TARGET | grep -e "\.ts" -e "\.m2ts"`
for FILE in $FILES
do
	echo $FILE;
	$COMMAND "$TARGET/$FILE" "$HD"
	CNT=`expr $CNT + 1`
	if [ $CNT -ge $MAX ]; then
		break
	fi
done
exit 0
