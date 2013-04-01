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
    private $defaultFolderVisibility = '2'; // Defaults to "Logged In Users"
    private $categoryIdCache = array();
    private $folderIdCache = array();


    /**
     * Constructor
     * @param $domain
     * @param $username String Can be your username or it can be the API Key.
     * @param $password String Optional if you use API Key.
     */
    function __construct($domain, $username, $password = 'X') {
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
     * Sets the default visibility for folders that are created.
     * @param $visibility String 1 = ALL, 2=Logged In Users, 3=Agents, 4=Select Companies
     */
    public function setDefaultFolderVisibility($visibility) {
        $this->defaultFolderVisibility = $visibility;
    }

    /**
     * This method will create or update an article depending on whether one already exists of the same name in the category/folder
     * name provided.  If createStructureMode has been set to true (the default) It will also create the categories and folders
     * if they don't already exist.
     * @param $category String The top level category for this article
     * @param $folder String Category Subfolder
     * @param $topic_name String containing the title of the Article
     * @param $topic_body String containing the article body, supports html.
     * @param $tags String containing the tags for the article OPTIONAL - will set tag to "<categoryname>,<foldername>" (can't remember if passing in an empty string works or not)
     * @param $status 1 = Draft 2 = Published (optional, default value is 1)
     * @param $art_type 1 = Permanent, 2 = Workaround (optional default value is 1)
     * @return String The raw response from the rest call.
     */
    public function createOrUpdateArticle($category, $folder, $topic_name, $topic_body, $tags='default', $status='1', $art_type = '1') {
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

        if( $tags == "default") {
            $tags = "$category,$folder";
        }

        $articleId = $this->getArticleIdUsingIds($categoryId, $folderId, $topic_name);
        if( $articleId != FALSE && !empty($articleId) ) {
            print "Article already exists (#$articleId)... Will Update instead.\n";
            return $this->restCall("/solution/categories/$categoryId/folders/$folderId/articles/$articleId?tags=$tags", "PUT", $payload);
        }
        else {
            //print "<br>ARTICLE PAYLOAD</br><pre>$payload</pre><br>";
            return $this->restCall("/solution/categories/$categoryId/folders/$folderId/articles.xml?tags=$tags", "POST", $payload);
        }
    }

    /**
     * Returns the article ID, this method takes in the IDs for category and folder rather then the names.
     * @param $categoryId
     * @param $folderId
     * @param $topic_name
     * @return bool FALSE if it doesn't exist, the id otherwise.
     */
    public function getArticleIdUsingIds($categoryId, $folderId, $topic_name ) {
        $xml = $this->restCall("/solution/categories/$categoryId/folders/$folderId.xml", "GET", '');

        print "Article XML\n" . $xml;

        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('/solution-folder/articles/solution-article[title="' . $topic_name . '"]/id');
        list(,$theId) = each($xpathresult);


        if( empty($theId) ) {
            print "Article Not Found\n";
            return FALSE;
        }

        print "Article ID: " . $theId;
        return $theId;
    }

    public function getArticleId( $category, $folder, $topic_name ) {
        $categoryId = $this->getCategoryId($category);
        $folderId = $this->getFolderId($category,$folder);
        return $this->getArticleIdUsingIds($categoryId, $folderId, $topic_name);
    }

    public function getCategoryId( $category ) {

        if( !empty($this->categoryIdCache[$category]) ) {
            return $this->categoryIdCache[$category];
        }

        $xml = $this->restCall( "/solution/categories.xml", "GET");

        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('solution-category[name="' . $category . '"]/id');
        list(,$theId) = each($xpathresult);

        if( empty($theId) ) {
            return FALSE;
        }
        else {
            print "Added '$category' to category cache: $theId\n";
            $this->categoryIdCache[$category] = $theId; // add to cache
        }

        //print "Category ID: " . $theId;
        return $theId;
    }

    /**
     * @return array of each top level Category Name.
     */
    public function getCategoryNames() {

        $xml = $this->restCall( "/solution/categories.xml", "GET");

        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('solution-category/name');

        $arr = array();
        foreach( $xpathresult as $cat_name) {
            $arr[] = $cat_name;
        }

        //print count($arr) . " categories found";

        return $arr;
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

        // This is inefficient... could parse response... but this is way easier.
        return $this->getCategoryId($category);
    }

    public function doesFolderExist( $category, $folder ) {
        return getFolderId($category, $folder) != FALSE;
    }

    public function getFolderId( $category, $folder ) {

        if( !empty($this->folderIdCache["$category/$folder"]) ) {
            return $this->folderIdCache["$category/$folder"];
        }

        $categoryId = $this->getCategoryId( $category );
        if( $categoryId == FALSE ) {
            return FALSE;
        }

        $xml = $this->restCall( "/solution/categories/$categoryId.xml", "GET");

        //print "-----[ FIND FOLDER ID XML]----------------------\n";
        //print $xml;
        //print "-----[ FIND FOLDER ^^^^ L]----------------------\n";

        $xml = simplexml_load_string($xml);
        $xpathResult = $xml->xpath('/solution-category/folders/solution-folder[name="' . $folder . '"]/id');
        list(,$theId) = each($xpathResult);

        if( empty($theId) ) {
            return FALSE;
        }
        else {
            print "Adding '$category/$folder' to folder cache\n";
            $this->folderIdCache["$category/$folder"] = $theId;
        }

        // print "Folder ID: " . $theId;
        return $theId;
    }


    /**
     * Create a Folder in the specified Category
     * NOTE: doesn't handle visibility == 4
     * @param $category
     * @param $folder
     * @param $folder_description
     * @param string $visibility - 1 = All, 2 = Logged in Users, 3 = Agents, 4 = Select Companies [need to provide customer_ids for this option]
     */
    public function createFolder($category, $folder, $folder_description = '', $visibility = 'default') {

        if( $visibility == "default") {
            $visibility = $this->defaultFolderVisibility;
        }

        $payload = <<<FOLDER
<solution_folder>
	<name>$folder</name>
	<visibility>$visibility</visibility>
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
     * @param $method - should be either GET, POST, PUT (and theoretically DELETE but that's untested).
     * @param string $postData - only specified if $method == POST or PUT
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
		else if( $method == "PUT" ) {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PUT" );
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $postData);
		}
		else if( $method == "DELETE" ) {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" ); // UNTESTED!
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
        if( !preg_match( '/2\d\d/', $http_status ) ) {
            print "ERROR: HTTP Status Code == " . $http_status . " (302 also isn't an error)\n";
        }

        // print "\n\nREST RESPONSE: " . $returndata . "\n\n";

        return $returndata;
    }

}