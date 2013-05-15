<?php
/**
 * File: example.php
 * Project: freshdesk-solutions
 * User: blake, http://www.blakerobertson.com
 * Date: 5/15/13
 * Time: 7:07 PM
 */

require_once("FreshdeskRest.php");

// SET THE FOLLOWING
$URL = "changeme.freshdesk.com";
$API_USER = "youremail@changeme.com";
$API_PASS = "yourpasswordOrAPIToken@changeme.com";

$CATEGORY_NAME = "General";
$FOLDER_NAME = "Installation";


$fd = new FreshdeskRest($URL, $API_USER, $API_PASS);


// Uncomment this and it'll create TEST CATEGORY, put a folder under it called Test_Folder, and then create an article (if it doesn't exist... or update otherwise)
// print "\n\n==== SOLUTIONS EXAMPLES =======\n");
//$fd->createOrUpdateArticle("TEST CATEGORY", "Test_Folder", "Test Article Title", "The <u>HTML</u> has been changed AGAIN!<P>Paragraph 2</P>");
//$fd->getCategoryId("TEST CATEGORY");
//$fd->getFolderId($, "Test_Folder");


print "\n\n===== USER EXAMPLES =======\n";
$USER_EMAIL = "someone@somedomain.com";
$userArray = $fd->getUserByEmail($USER_EMAIL);
print "User Object: \n" . print_r($userArray, true);
print "Here's how you'd get the user_role from the array: " . $userArray['user_role'] . "\n";
print "User ID: " . $fd->getUserId($USER_EMAIL);;


// The following can be used to determine the forum category id's and and such which can be passed in to monitor topic.

//print $fd->forumListCategories();

// You'll need to put your id's in.
//print $fd->forumListForums("33267");
//print "\n\n TOPICS \n\n";
//print $fd->forumListTopics("33267", "131622");
//print $fd->monitorTopicById("33267", "131622", "18878");
