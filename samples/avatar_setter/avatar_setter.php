<?php
/**
 * File: avatar_setter.php
 * Project: freshdesk-solutions
 * User: blake, http://www.blakerobertson.com
 * Date: 6/13/13
 * Time: 5:02 AM
 *
 * Usage: php avatar_setter.php and it'll set the avatar for each of your users.
 * WARNING: it'll overwrite any user set avatars.
 */
require_once("IconGenerator.php");
require_once("../../FreshdeskRest.php");

// SET THESE
define('FRESHDESK_BASE_API_URL','http://yourdesk.freshdesk.com/');	//With Trailing slashes
define('FRESHDESK_USERNAME','your@email.com');
define('FRESHDESK_PASSWORD','YourPassword');
define("INITIALS_FONT","OpenSans-Regular.ttf"); // If you have it, set this to Future MdCn BT Bold.ttf
                                                // Put the full font path in... or copy the ttf to the same directory

$fd = new FreshdeskRest( FRESHDESK_BASE_API_URL, FRESHDESK_USERNAME, FRESHDESK_PASSWORD );

$fd->initUserCache();
$total = count($fd->getUserCache());
print "\nUser Cache Initialized. Found: " . $total;

$fd->initCompanyCache();
print "\nCompany Cache Initialized. Found: " . count($fd->getCompanyCache()) . "\n\n";

$i=0;
foreach ($fd->getUserCache() as $key => $value) {
    $user = $fd->getUser($key);
    $accountName = $fd->getCompanyName( $user['customer_id'] );

    // Builds an icon with initials of users name, background color is set based on accountName
    $iconFile = createIcon($user['name'],$accountName, "TEMP", INITIALS_FONT );
    //print "ICON FILE: $iconFile";
    $fd->uploadAvatarForUser($user, $iconFile);

    print "[" . $i++ . " of $total] Uploaded Icon for: " . $user['name'] . "\n";
}

print "DONE.\n";