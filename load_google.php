<?php
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;

// it seems Google does not want to share the location info
// https://stackoverflow.com/questions/51336701/location-data-google-photos-api

require 'vendor/autoload.php';

session_start();
echo '<pre>';
// print_r($_SESSION);


/**
 * Requests access to the user's Google Photos account, and stores the resulting
 * {@link UserRefreshCredentials} object in the current session, with the key 'credentials'.
 *
 * This function handles the cases before and after the user has been redirected to grant access.
 * The calls are identical, but you must ensure that the $scopes and $redirectURI are consistent.
 *
 * When the user has successfully connected, they will be redirected to the index page.
 *
 * You should request the most restrictive scope that you can, for you user case. See
 * {@link https://developers.google.com/photos/library/guides/authentication-authorization} for more
 * details.
 *
 * The $redirectURI must be authorized for the given client secret in the Google API Console.
 *
 * You should store the refresh token for each user. See
 * {@link https://developers.google.com/identity/protocols/OAuth2WebServer} for more details.
 *
 * @param array $scopes
 * @param string $redirectURI Where the user should be directed to after granting the access
 *      request. Usually the page from where this function is called.
 */
function connectWithGooglePhotos(array $scopes, $redirectURI)
{
    $clientSecretJson = json_decode(
        file_get_contents('./credentials.json'),
        true
    )['web'];
    // print_r($clientSecretJson);
    $clientId = $clientSecretJson['client_id'];
    $clientSecret = $clientSecretJson['client_secret'];
    $oauth2 = new OAuth2([
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
        // Where to return the user to if they accept your request to access their account.
        // You must authorize this URI in the Google API Console.
        'redirectUri' => $redirectURI,
        'tokenCredentialUri' => 'https://www.googleapis.com/oauth2/v4/token',
        'scope' => $scopes,
    ]);
    // The authorization URI will, upon redirecting, return a parameter called code.
    if (!isset($_GET['code'])) {
        $authenticationUrl = $oauth2->buildFullAuthorizationUri(['access_type' => 'offline']);
        header("Location: " . $authenticationUrl);
    } else {
        // With the code returned by the OAuth flow, we can retrieve the refresh token.
        $oauth2->setCode($_GET['code']);
        $authToken = $oauth2->fetchAuthToken();
        $refreshToken = $authToken['access_token'];
        // The UserRefreshCredentials will use the refresh token to 'refresh' the credentials when
        // they expire.
        $_SESSION['credentials'] = new UserRefreshCredentials(
            $scopes,
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken
            ]
        );
        // Return the user to the home page.
        // header("Location: index.php");
    }
}


if (empty($_SESSION['credentials'])) {
  connectWithGooglePhotos(
    ['https://www.googleapis.com/auth/photoslibrary'],
    'http://localhost/endomondo-routes-map/load_google.php'
  );  
}

$photosLibraryClient = new PhotosLibraryClient(['credentials' => $_SESSION['credentials']]);
try {
    $response = $photosLibraryClient->searchMediaItems([
      'mediaTypes' => ['PHOTO'],
      'pageSize' => 3,
    ]);
  // $response = $photosLibraryClient->listAlbums();
  foreach($response->iterateAllElements() as $item) {
    print_r($item->getMediaMetadata()->getWidth());
    echo ' x ';
    print_r($item->getMediaMetadata()->getHeight());
    echo ', ';
    print_r($item->getMediaMetadata()->getMetadata());
    echo '<br/>';
    exit;
  }


} catch (\Google\ApiCore\ApiException $e) {
  echo $templates->render('error', ['exception' => $e]);
}