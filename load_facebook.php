<?php

require 'vendor/autoload.php';

file_put_contents('points_facebook.log', '');
session_start();
$fb = new Facebook\Facebook([
  'app_id' => '1087543464775563',
  'app_secret' => 'fffb047a8c324cbf7f55ea95c1c30d8a',
  'default_graph_version' => 'v3.2',
  ]);

if (empty($_SESSION['fb_access_token'])) {

  $helper = $fb->getRedirectLoginHelper();

  $permissions = ['user_tagged_places', 'user_photos', 'user_videos', 'user_posts']; // Optional permissions
  $loginUrl = $helper->getLoginUrl('http://localhost/endomondo-routes-map/load_facebook.php', $permissions);

  echo '<a href="' . htmlspecialchars($loginUrl) . '">Log in with Facebook!</a>';

  $accessToken = $helper->getAccessToken();

  if (!isset($accessToken)) {
    if ($helper->getError()) {
      header('HTTP/1.0 401 Unauthorized');
      echo "Error: " . $helper->getError() . "\n";
      echo "Error Code: " . $helper->getErrorCode() . "\n";
      echo "Error Reason: " . $helper->getErrorReason() . "\n";
      echo "Error Description: " . $helper->getErrorDescription() . "\n";
    } else {
      header('HTTP/1.0 400 Bad Request');
      echo 'Bad request';
    }
    exit;
  }

  if (! $accessToken->isLongLived()) {
    // Exchanges a short-lived access token for a long-lived one
    try {
      $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
      echo "<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n";
      exit;
    }

    echo '<h3>Long-lived</h3>';
    var_dump($accessToken->getValue());
  }

  $_SESSION['fb_access_token'] = (string) $accessToken;
}

echo '<pre>';
$fetchUrl = '/me/feed?fields=id,place&limit=50&with=location';
$list = [];
while(true) {
  try {
    // Returns a `Facebook\FacebookResponse` object
    $response = $fb->get(
      $fetchUrl,
      $_SESSION['fb_access_token']
    );
  } catch(Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . $e->getMessage();
    exit;
  } catch(Facebook\Exceptions\FacebookSDKException $e) {
    echo 'Facebook SDK returned an error: ' . $e->getMessage();
    exit;
  }
  $json = $response->getBody();
  $body = json_decode($json, true);
  if (empty($body) || empty($body['data'])) {
    break;
  }
  
  foreach($body['data'] as $record) {
    print_r($record);
    if (empty($record['place']['location']['latitude'])) {
      continue;
    }
    $id = $record['place']['id'];
    $lat = $record['place']['location']['latitude'];
    $lng = $record['place']['location']['longitude'];
    $list[$id] = ['lat' => $lat, 'lng' => $lng];
  }

  $nextUrl = $body['paging']['next'];
  $nextSubUrl = substr($nextUrl, strpos($nextUrl, '/feed'));

  $fetchUrl = '/me' . $nextSubUrl;
  file_put_contents('points_facebook.log', 'processed ' . sizeof($body['data']) . ' records' . chr(10), FILE_APPEND);
  sleep(rand(0, 2));
}
file_put_contents('points_facebook.js', 'function getFBPoints() { return ' . json_encode($list) . ';}');