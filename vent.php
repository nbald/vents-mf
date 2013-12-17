<?php

$run=$argv[1];
$hour=$argv[2];


date_default_timezone_set('UTC');


$ry=substr($run, 0, 4);
$rm=substr($run, 4, 2);
$rd=substr($run, 6, 2);
$rh=substr($hour, 0, 2);
$run_time=mktime($rh, 0, 0, $rm, $rd, $ry, 0);
$date=date('d-m-Y H:i', $run_time).' UTC';

function check_xy ($x, $y) {
  global $im;
  $rgb=@imagecolorat($im, $x, $y);
  if ($rgb & 0xFFFF != 0) return false;
  if (($rgb & 0x7F000000) >> 24 > 75) return false;
  return true;
}

$imgname="{$run}_{$hour}_ModDdff.png";

$im = imagecreatefrompng($imgname);

$fout=fopen("vent-arome-{$run}_{$hour}.txt", 'w');
$fdiag=fopen("__diag_all_{$run}{$hour}.txt", 'w');

$out="# ====================================
# Vents à 10m AROME Météo-France
# ====================================
#
# Date:\t$date
#
# Fichier original: 
# https://donneespubliques.meteofrance.fr/donnees_libres/Carto/$imgname
#
# Ces valeurs sont issues d'une lecture automatique.
# Nous ne pouvons pas garantir qu'elles soient
# 100% conformes aux mesures de Météo-France.
#
# Vitesse du vent à 10m en Noeuds
# Arrondi à 5 noeuds près.
# 1 noeud = 1.852 km/h
#
# Direction en degrés.
# 0 étant le Nord.
# Rotation sens horaire.
#
lat\tlon\tdir\tvit\n";

fwrite($fout, $out);


$long=23;
$blong=10;

$lines=file('/opt/mf/vent/points.csv');
array_shift($lines);

foreach ($lines as $line) {

  $diag='';

  $pts=explode("\t", $line);

  $start_x=intval($pts[0]);
  $start_y=intval($pts[1]);
  $lat=floatval($pts[2]);
  $lon=floatval($pts[3]);




  // trouve la direction

  $radar=array();
  for ($angle=0; $angle<360; $angle++) {
    $rad=deg2rad($angle);
    $n=0;
    for ($dist=0; $dist<$long; $dist++) {
      $x=$start_x+cos($rad)*$dist;
      $y=$start_y+sin($rad)*$dist;
      if (check_xy($x, $y)) $n++;
    }
    $radar[$angle]=$n;
  }

  $max=max($radar);
  $nmax=0;
  $dir=0;
  for ($angle=0; $angle<360; $angle++) {
    if ($radar[$angle]==$max) {
      $dir+=$angle;
      $nmax++;
    }
  }
  $dir/=$nmax;

  $diag .= "$start_x $start_y $dir\n";

  
  
  // compte les barbules
  $rad=deg2rad($dir);
  $bangle=deg2rad($dir+800);
  
  $moy_a=0;
  $moy_b=0;
  $moy_c=0;
  
  for ($bdist=2; $bdist<$blong; $bdist++) {
    //décale le start
    $bstart_x=$start_x+cos($bangle)*$bdist;
    $bstart_y=$start_y+sin($bangle)*$bdist;
    
    $last_is_trait=false;
    $ntraits=0;
    $npoints=0;
    for ($dist=7; $dist<$long; $dist+=0.5) {
      $x=$bstart_x+cos($rad)*$dist;
      $y=$bstart_y+sin($rad)*$dist;
      $is_trait=check_xy($x, $y);
      if ($is_trait) {
	$npoints++;
	if ($last_is_trait != $is_trait) $ntraits++;
	$diag .= '#';
      } else {
	$diag .= '-';
      }
      $last_is_trait=$is_trait;
    }
    if ($bdist < 6) {
      $moy_a+=$ntraits;
    } else {
      $moy_b+=$ntraits;
    }
    if ($npoints > 0) $npoints /=$ntraits;
    $moy_c+=$npoints;
    $diag .= " $ntraits $npoints\n";
  }
  $moy_a /=4;
  $moy_b /=4;
  $moy_c /=8;
  
  $diag .= "$moy_a $moy_b $moy_c\n";
  
  $moy_a =round($moy_a-0.1);
  $moy_b =round($moy_b-0.1);
  
  if ($moy_a==0) {
    $speed='0';
  } else if ($moy_a==1) {
    if ($moy_b==0) {
      if ($moy_c < 1.1) {
	$speed='???';
      } else {
	$speed='5';
      }
    } else if ($moy_b==1) {
      if ($moy_c < 6) {
	$speed='10';
      } else {
	$speed='50';
      }
    } else {
      $speed = '???';
    }
  } else if ($moy_a==2) {
    if ($moy_b==1) {
      $speed='15';
    } else if ($moy_b==2) {
      $speed='20';
    } else {
      $speed = '???';
    }
  } else if ($moy_a==3) {
    if ($moy_b==2) {
      $speed='25';
    } else if ($moy_b==3) {
      $speed='30';
    } else {
      $speed = '???';
    }
  } else if ($moy_a==4) {
    if ($moy_b==3) {
      $speed='35';
    } else if ($moy_b==4) {
      $speed='40';
    } else {
      $speed = '???';
    }
  } else {
    $speed = '???';
  }
  
  if ($speed == '???') {
    echo "PAS RECONNU :\n";
    echo $diag;
  } else {
    $dir=round(($dir-90+360)%360);
    $out = "$lat\t$lon\t$dir\t$speed\n";
    fwrite($fout, $out);
  }
  
  $diag .= "speed: $speed\n\n";
  fwrite($fdiag, $diag);
}


fclose($fout);
fclose($fdiag);

imagedestroy($im);

