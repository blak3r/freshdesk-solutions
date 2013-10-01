<?php
/**
 * File: fd_solution_export_plus_metrics.php
 * Project: my.alertus.portal
 * User: blake, http://www.blakerobertson.com
 * Date: 9/30/13
 * Time: 9:21 PM
 *
 * This file gets all your solutions and exports them into a subdirectory named "solutions" (must be created manually).
 * Then gives a summary report of which articles have been updated / created since the $dateSince variable
 *
 * WARNING: The hourly api limit is 1000, this will use at least 1 call per solution.  So, don't run it more then once in an hour!
 */

require_once "../FreshdeskRest.php";

if( file_exists("../../config/freshdesk_config.php") ) {
    require_once "../../config/freshdesk_config.php"; // Used by author... so i don't accidently commit my credentials.
}else {
    define('FRESHDESK_BASE_API_URL','http://yourdesk.freshdesk.com/');	//With Trailing slashes
    define('FRESHDESK_USERNAME','your@email.com');
    define('FRESHDESK_PASSWORD','YourPassword');
}
define("DATE_SINCE", '2013-04-02');

$fd = new FreshdeskRest( FRESHDESK_BASE_API_URL, FRESHDESK_USERNAME, FRESHDESK_PASSWORD );

$dateSince = DATE_SINCE; // This will generate metrics for how many articles have been updated/created since this date.
print "Starting...";
$categories = $fd->getCategoryNames();

$i=0; $updatedCnt=0; $notUpdatedCnt=0; $createdCnt=0;
$updatedList = "";
$createdList = "";
foreach($categories as $category ) {
    $folders = $fd->getFolderNames($category);

    foreach($folders as $folder) {
        $articles = $fd->getArticleNames($category,$folder);
        //print "\nArticles in $category/$folder: \n";

        foreach($articles as $article ) {
            $articleObj = $fd->getArticle($category,$folder,$article);

            $epochUpdated = parseDateToEpoch($articleObj['updated_at']);
            $epochCreated = parseDateToEpoch($articleObj['created_at']);

            if( $epochUpdated <= parseDateToEpoch($dateSince) ) {
                $notUpdatedCnt++;
            }
            else {
                $updateList .= $articleObj['updated_at'] . " " .  $articleObj['created_at'] .  " " . $articleObj['title'] . "\n";
                $updatedCnt++;
            }

            if( $epochCreated > parseDateToEpoch($dateSince) ) {
                $createdList .= $articleObj['updated_at'] . " " .  $articleObj['created_at'] .  " " . $articleObj['title'] . "\n";
                $createdCnt++;
            }


            // This section writes the files to a subdirectory "solutions"... Note: you have to create the subdirectory
            // manually right now.  The files are in an XML format suitable for loading into Apache SOLR for indexing.

            print "folder.id: " . $articleObj['folder']['id'] . "\n";
            print "folder.name: " . $articleObj['folder']['name'] . "\n";
            print "id: " . $articleObj['id'] . "\n";
            print "updated_at: " . $articleObj['updated_at'] . "\n";

            if( $articleObj['folder']['name'] != "Drafts" ) {
                $solrXml = $fd->convertArticleToSolr($articleObj, $category, "Knowledgebase");
                print $solrXml;
                $catNoSpaces = preg_replace('/ /', '', $category);
                //   $xml = $fd->getArticleRawXml($category,$folder,$article);
                // TODO: Create directory if it doesn't exist... Just create it manually.
                file_put_contents("solutions/$catNoSpaces-{$articleObj['id']}.xml", $solrXml);
            }
        }
    }
}

print "These articles were UPDATED at least once since 2013-04-02:\n\n";
print $updateList . "\n\n";
print "Summary:\n\tUpdated: $updatedCnt\n\tNotUpdated: $notUpdatedCnt\n";

print "The following articles were CREATED since: $dateSince\n\n";
print $createdList . "\n\n";
print "Summary: \n\tCreated: $createdCnt\n";


function parseDateToEpoch($dateStr) {
    $parsedDate = date_parse($dateStr);
    $epochUpdated = strtotime( $dateStr);
    return $epochUpdated;
}