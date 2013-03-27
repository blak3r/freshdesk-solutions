<?php
/**
 * Implements FreshDesk API methods for Solutions
 * User: blake
 * Date: 3/27/13
 * Time: 1:17 AM
 */

class FreshdeskRest {

    private $domain = '', $username = '', $password = '';
    private $createStructureMode = true; // When true, if you try to create an article and the folder or category doesn't exist it'll create one automatically
    private $lastHttpStatusCode = 200;

    function __construct($domain, $username, $password) {
        $this->domain = $domain;
        $this->password = $password;
        $this->username = $username;
    }

    /**
     * When true, if you try to create an article with a category name or folder name that doesn't exist,
     * it will be created.  Otherwise, it will just fail.
     * @param $mode
     */
    public function setCreateStructureMode($mode) {
        $this->createStructureMode($mode);
    }

    /**
     * This method will create a new article.  It will also create the categories and folders if they don't already exist.
     * @param $category String The top level category for this article
     * @param $folder String Category Subfolder
     * @param $topic_name String containing the title of the Article
     * @param $topic_body String containing the article body, supports html.
     * @param $tags String containing the tags for the article OPTIONAL - will set tag to "default" (can't remember if passing in an empty string works or not)
     * @param $status 1 = Draft 2 = Published (optional, default value is 1)
     * @param $art_type 1 = Permanent, 2 = Workaround (optional default value is 1)
     * @return The raw response from the rest call.
     */
    public function createArticle($category, $folder, $topic_name, $topic_body, $tags='default', $status='1', $art_type = '1') {
        print "In Create Article\n";
        $categoryId = $this->getCategoryId( $category );

        if( !$categoryId && $this->createStructureMode ) {
            $this->createCategory($category);
            $categoryId = $this->getCategoryId( $category );
        }
        if( $categoryId == FALSE ) {
            print "Unknown Category ID: $categoryId";
            return FALSE;
        }

        $folderId = $this->getFolderId( $category, $folder );

        if( !$folderId && $this->createStructureMode ) {
            $this->createFolder( $category, $folder, '' );
            $folderId = $this->getFolderId( $category, $folder );
        }
        if( $folderId == FALSE ) {
            return FALSE;
        }


        $payload = <<<SOLN
<solution_article>
	<title>$topic_name</title>  <!-- Mandatory min >3 characters-->
	<status>$status</status>
	<art_type>$art_type</art_type>
	<description>
	<![CDATA[$topic_body]]>
	</description>
	<folder_id>$folderId</folder_id>  <!-- Mandatory-->
</solution_article>
SOLN;
        //print "<br>ARTICLE PAYLOAD</br><pre>$payload</pre><br>";
        return $this->restCall("/solution/categories/$categoryId/folders/$folderId/articles.xml?tags=default", "POST", $payload);
    }

    public function getCategoryId( $category ) {
        $xml = $this->restCall( "/solution/categories.xml", "GET");

        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('solution-category[name="' . $category . '"]/id');
        list(,$theId) = each($xpathresult);

        if( empty($theId) ) {
            return FALSE;
        }

        //print "Category ID: " . $theId;
        return $theId;
    }

    public function doesCategoryExist( $category ) {
        return getCategoryId($category) != FALSE;
    }

    public function createCategory( $category, $description = '' ) {
        $payload = <<<CAT
<solution_category>
	<name>$category</name>
	<description>$description</description>
</solution_category>
CAT;

        $this->restCall("/solution/categories.xml", "POST", $payload );

        // TODO: this is inefficient, should parse the rest response instead from above instead
        return $this->getCategoryId($category);
    }

    public function doesFolderExist( $category, $folder ) {
        return getFolderId($category, $folder) != FALSE;
    }

    public function getFolderId( $category, $folder ) {

        $categoryId = $this->getCategoryId( $category );
        if( $categoryId == FALSE ) {
            return FALSE;
        }

        $xml = $this->restCall( "/solution/categories/$categoryId.xml", "GET");

        //print $xml;

        $xml = simplexml_load_string($xml);
        $xpathResult = $xml->xpath('/solution-category/folders/solution-folder[name="' . $folder . '"]/id');
        list(,$theId) = each($xpathResult);

        if( empty($theId) ) {
            return FALSE;
        }

        // print "Folder ID: " . $theId;
        return $theId;
    }


    /**
     *
     * NOTE: doesn't handle visibility == 4
     * @param $category
     * @param $folder
     * @param $folder_description
     * @param string $visibility - 1 = All, 2 = Logged in Users, 3 = Agents, 4 = Select Companies [need to provide customer_ids for this option]
     */
    public function createFolder($category, $folder, $folder_description = '', $visibility = '1') {
        $payload = <<<FOLDER
<solution_folder>
	<name>$folder</name>
	<visibility>$visibility</visibility>		<!--- (Mandatory) -->
	<description>$folder_description</description>
</solution_folder>
FOLDER;

        $categoryId = $this->getCategoryId($category);
        if( $categoryId == FALSE && $this->createStructureMode ) {
            $categoryId = $this->createCategory($category);
        }

        $this->restCall("/solution/categories/$categoryId/folders.xml", "POST", $payload);
    }


    /**
     * @param $urlMinusDomain - should start with /... example /solutions/categories.xml
     * @param $method - should be either GET or POST
     * @param string $postData - only specified if $method == POST
     * @return the raw response
     */
    private function restCall($urlMinusDomain, $method, $postData = '') {
        $url = "https://{$this->domain}$urlMinusDomain";

        //print "REST URL: " . $url . "\n";
        $header[] = "Content-type: application/xml";
        $ch = curl_init ($url);

        if( $method == "POST") {
            curl_setopt ($ch, CURLOPT_POST, true);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $postData);
        }
        else {
            curl_setopt ($ch, CURLOPT_POST, false);
        }

        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $returndata = curl_exec ($ch);

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close($http);
        if( preg_match( '/2\d\d/', $http_status ) ) {
            print "ERROR: HTTP Status Code == " . $http_status . "\n";
        }

        // print "\n\nREST RESPONSE: " . $returndata . "\n\n";

        return $returndata;
    }

}