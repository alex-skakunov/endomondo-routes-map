<?php

require 'vendor/autoload.php';

define('ENDOMONDO_LOGIN', 'youraddress@gmail.com');
define('ENDOMONDO_PASSWORD', 'password');
$endomondo = new \Fabulator\Endomondo\EndomondoApi();
$endomondo->login(ENDOMONDO_LOGIN, ENDOMONDO_PASSWORD);

$list = [];
$chunkSize = 10;
$offset = 0;
while(true) {
  echo "Loading next chink: $chunkSize \n";
  $workouts = $endomondo->getWorkouts(['limit' => $chunkSize, 'offset' => $offset]);
  if (empty($workouts) || empty(current($workouts))) {
    break;
  }
  $offset += $chunkSize;
  foreach(current($workouts) as $workout) {
    $id = $workout->getId();
    echo "  Processing workout ID $id \n";
    foreach($workout->getPoints() as $point) {
      if (empty($point->getLatitude())) {
        continue;
      }
      $list[$id][] = ['lat' => $point->getLatitude(), 'lng' => $point->getLongitude()];
    }
  }
  echo "  Saving data to file \n";
  file_put_contents('points.js', 'function getPoints() { return ' . json_encode($list) . ';}');
  sleep(2); //Endomondo services are fragile
}