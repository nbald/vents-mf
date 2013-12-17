#!/bin/bash

RUN_DAY=$(date -u +%Y%m%d)

DIR="${RUN_DAY:0:6}/$RUN_DAY"

cd /var/www/labo/mf/arome-vent/

mkdir -p $DIR
cd $DIR


TIME_START=$(date --date="$RUN_DAY UTC" +%s)

for FRAME in {0..30..3}; do
  TIME=$(( TIME_START + FRAME*3600))
  DAY=$(date -u -d@$TIME +%Y%m%d)
  HOUR=$(date -u -d@$TIME +%H00)
  FILE="${DAY}_${HOUR}_ModDdff.png"
  URL=" https://donneespubliques.meteofrance.fr/donnees_libres/Carto/$FILE";
  echo $FILE
  if [ "$FRAME" == "0" ]; then
    for i in {0..20}; do
      wget -q --spider $URL && break
      sleep 60
    done
  fi
  
  wget -q $URL
  if [ "$?" != "0" ]; then
    echo "$FILE wget failed"
    break
  fi
  
  php /opt/mf/vent/vent.php $DAY $HOUR >& "__diag_err_${DAY}${HOUR}.txt"
done
