<?php

require 'vendor/autoload.php';

define('ENDOMONDO_LOGIN', 'i1t2b3@gmail.com');
define('ENDOMONDO_PASSWORD', 'drowssap');
$endomondo = new \Fabulator\Endomondo\EndomondoApi();
$endomondo->login(ENDOMONDO_LOGIN, ENDOMONDO_PASSWORD);


// load recent 10 workouts and write to the same file

$dirtyJson = file_get_contents('points.js');
$json = substr($dirtyJson, strpos($dirtyJson, 'return ')+strlen('return '), -2);
$pointsData = json_decode($json, 1);
echo "Loaded ".sizeof($pointsData)." records\n";

$workouts = $endomondo->getWorkouts(['limit' => 10]);
if (empty($workouts) || empty(current($workouts))) {
  exit('No data');
}
foreach(current($workouts) as $workout) {
  $id = $workout->getId();
  echo "  Processing workout ID $id \n";
  if (!empty($pointsData[$id])) {
    echo "  \tSkipped\n";
    continue; // processed already
  }
  foreach($workout->getPoints() as $point) {
    if (empty($point->getLatitude())) {
      echo "  \tSkipped - no coordinates\n";
      continue;
    }
    if (empty($pointsData[$id])) {
      $pointsData[$id] = array();
    }
    $pointsData[$id][] = ['lat' => $point->getLatitude(), 'lng' => $point->getLongitude()];
  }
}
echo chr(10);

file_put_contents('points.js', 'function getPoints() { return ' . json_encode($pointsData) . ';}');
exit;


// full reload
$list = [];
$chunkSize = 20;
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
    // if (!in_array($id, array('828342970', '828341575'))) {
    //     continue;
    // }
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
  sleep(2);
}