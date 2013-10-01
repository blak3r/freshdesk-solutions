<?php
/**
 * Implements FreshDesk API methods in PHP.
 * See README.md
 * User: blake
 * Date: 3/27/13
 * Time: 1:17 AM
 */

class FreshdeskRest {

    // User Role's
    const ROLE_VIEWONLYTHEIRS  = 3;
    const ROLE_VIEWCOMPANIES  = 5;

    // Folder Visibility Constants
    const VIS_ALL=1;
    const VIS_LOGGEDIN = 2;
    const VIS_AGENTS = 3;
    const VIS_SELECTCOMPANIES = 4;

    private $domain = '', $username = '', $password = '';
    private $createStructureMode = true; // When true, if you try to create an article and the folder or category doesn't exist it'll create one automatically
    private $lastHttpStatusCode = 200;
    private $lastHttpResponseText = '';
    private $defaultFolderVisibility = '2'; // Defaults to "Logged In Users"
    private $categoryIdCache = array();
    private $folderIdCache = array();
    private $userIdCache = array();
    private $companyIdCache = array();
    private $proxyServer = "";

    /**
     * Constructor
     * @param $domain - yourname.freshdesk.com - but will also accept http://yourname.freshdesk.com/, etc.
     * @param $username String Can be your username or it can be the API Key.
     * @param $password String Optional if you use API Key.
     */
    function __construct($domain, $username, $password = 'X') {

        $strippedDomain = preg_replace('#^https?://#', '', $domain); // removes http:// or https://
        $strippedDomain = preg_replace('#/#', '', $strippedDomain); // get trailing slash

        $this->domain = $strippedDomain;
        $this->password = $password;
        $this->username = $username;
    }

    /**
     * When true, if you try to create an article with a category name or folder name that doesn't exist,
     * it will be created.  Otherwise, it will just fail.
     * @param bool $mode
     */
    public function setCreateStructureMode($mode) {
        $this->createStructureMode = $mode;
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
        //print "In Create Article\n";
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
        $tags = urlencode($tags);

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

        //print "Article XML\n" . $xml;

        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('/solution-folder/articles/solution-article[title="' . $topic_name . '"]/id');
        list(,$theId) = each($xpathresult);


        if( empty($theId) ) {
            print "Article Not Found\n";
            return FALSE;
        }

        return $theId;
    }

    public function getArticleId( $category, $folder, $topic_name ) {
        $categoryId = $this->getCategoryId($category);
        $folderId = $this->getFolderId($category,$folder);
        return $this->getArticleIdUsingIds($categoryId, $folderId, $topic_name);
    }

    public function getCategoryId( $category ) {

        if( !empty($this->categoryIdCache["$category"]) ) {
            return $this->categoryIdCache["$category"];
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
            $this->categoryIdCache["$category"] = $theId; // add to cache
        }

        //print "Category ID: " . $theId;
        return $theId;
    }

    /**
     * @return array of each all the top level Category Names.
     */
    public function getCategoryNames() {

        $arr = array();
        try {
            $xml = $this->restCall( "/solution/categories.xml", "GET",'',false);
            $xml = simplexml_load_string($xml);
            $xpathResult = $xml->xpath('solution-category/name');

            foreach( $xpathResult as $cat_name) {
                $arr[] = $cat_name;
            }

            //print count($arr) . " categories found";
        }
        catch(Exception $ex) {
            // We return an empty array...
        }
        return $arr;
    }

    /**
     * @param $category is the name of the category (not the id)
     * @return array of each all the folders in the specified category
     */
    public function getFolderNames($category) {

        $categoryId = $this->getCategoryId($category);

        $xml = $this->restCall( "/solution/categories/$categoryId.xml", "GET");

        //print $xml;

        $xml = simplexml_load_string($xml);
        $xpathResult = $xml->xpath('folders/solution-folder/name');

        $arr = array();
        foreach( $xpathResult as $fol_name) {
            $arr[] = $fol_name;
        }

        //print count($arr) . " folders found";

        return $arr;
    }

    /**\
     * Returns an array of the articles titles in the given folder
     * @param $category This is the name not the id
     * @param $folder This is the name not the id
     * @return array
     */
    public function getArticleNames($category, $folder) {
        $categoryId = $this->getCategoryId($category);
        $folderId = $this->getFolderId($category,$folder);

        $xml = $this->restCall( "/solution/categories/$categoryId/folders/$folderId.xml", "GET");

        //print $xml;

        $xml = simplexml_load_string($xml);
        $xpathResult = $xml->xpath('articles/solution-article/title');

        $arr = array();
        foreach( $xpathResult as $fol_name) {
            $arr[] = $fol_name;
        }

        //print count($arr) . " articles found";

        return $arr;
    }

    function getArticleRawXml($category, $folder, $article ) {
        $categoryId = $this->getCategoryId($category);
        $folderId = $this->getFolderId($category,$folder);
        $articleId = $this->getArticleId($category,$folder, $article);

        $xml = $this->restCall( "/solution/categories/$categoryId/folders/$folderId/articles/$articleId.xml", "GET");
        return $xml;
    }

    function getArticleRawJson($category, $folder, $article ) {
        $categoryId = $this->getCategoryId($category);
        $folderId = $this->getFolderId($category,$folder);
        $articleId = $this->getArticleId($category,$folder, $article);

        $xml = $this->restCall( "/solution/categories/$categoryId/folders/$folderId/articles/$articleId.json", "GET");
        return $xml;
    }

    function getArticle($category, $folder, $article ) {
        $json = $this->getArticleRawJson($category,$folder,$article);
        $article = json_decode($json,true);
        return $article['article'];
    }

    public static function convertArticleToSolr($article, $category, $source ) {
        $xml = "<add><doc>\n";
        $xml .= "<field name='id'>" . $article['folder']['id'] . $article['id'] . "</field>\n";
        $xml .= "<field name='pagetitle'>" . $article['title'] . "</field>\n";
        $xml .= "<field name='wikitext'>" . $article['desc_un_html'] . "</field>\n";
        $xml .= "<field name='resourcename'>" . $source . "</field>\n";
        $xml .= "<field name='category'>" . $category . "</field>\n";
        $xml .= "<field name='includes'>" .    $article['folder']['name'] . "</field>\n";
        $xml .= "<field name='dbkey'>" . "NOT_A_WIKI" . "</field>\n";
        $xml .= "<field name='namespace'>0</field>\n";
        print_r($article);
        $xml .= "<field name='url'>" . "/support/solutions/articles/" . $article['id'] . "</field>\n";
        $xml .= "</doc></add>\n";

        return $xml;
    }



    public function doesCategoryExist( $category ) {
        return $this->getCategoryId($category) != FALSE;
    }

    public function createCategory( $category, $description = '', $optParams=array() ) {
//        $payload = <<<CAT
//<solution_category>
//	<name>$category</name>
//	<description>$description</description>
//</solution_category>
//CAT;
        $reqParams = array(
            "name" => $category,
            "description" => $description
        );

        $payload = $this->merge_and_xml_encode("solution_category", $reqParams, $optParams);

        $this->restCall("/solution/categories.xml", "POST", $payload );

        // This is inefficient... could parse response... but this is way easier.
        return $this->getCategoryId($category);
    }

    public function doesFolderExist( $category, $folder ) {
        return $this->getFolderId($category, $folder) != FALSE;
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
            //print "Adding '$category/$folder' to folder cache\n";
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

    // --------------[ Forum Methods ]------------- //


    /**
     *
     * @param $categoryId
     * @param $forumId
     * @param $topicId
     * @return string - response which will be null... the HTTP Error Code for this method is also not 200.
     */
    public function setMonitorStatusForTopic( $topicId, $userId, $status ) {
        return $this->restCall("/support/discussions/topics/$topicId/monitor.json?user_id=$userId&status=$status", "PUT", '', false);
    }

    public function isTopicMonitored($topicId, $userId) {
        $json =  $this->restCall("/support/discussions/topics/$topicId/check_monitor.json?user_id=$userId", "GET", '', false);
        $data = json_decode($json, true);
        return $data['monitorship']['active'];
    }

    public function getAllMonitoredTopicsAsJSON( $userId ) {
        return $this->restCall("/support/discussions/user_monitored.json?user_id=$userId", "GET", "", false);
    }



    /**
     * Useful for determining the top level category id... can't get this by looking at urls
     * WARNING: must be agent to run this... trying to run as a "user" will redirect
     * @return string... todo convert this over to json eventually..
     */
    public function forumListCategories() {
        return $this->restCall("/categories.xml", "GET");  // gets redirected to /support/discussions
    }

    public function forumListForums($categoryId) {
        return $this->restCall("/categories/$categoryId.xml","GET");
    }

    public function forumListTopics($categoryId, $forumId) {
        return $this->restCall("/categories/$categoryId/forums/$forumId.xml","GET");
    }


    // ------------[ User Related Methods ]-------------------//

    /**
     * I created this originally... because there was no documented way to get a user from email address.
     * Since I originally wrote this, I learned of a better way.  This is still an interesting approach if you
     * need to do something performant, so i left it in the code base.  But, checkout getUserByEmailQuery.
     *
     * This method basically gets every single contact in your system... page by page and caches the ids in
     * an array. The only time this would need to be called is if users are added or data is old... like people got added on the website.
     *
     * Developer FYI: this utilizes an undocumented (as of May 2013) 'page' get param to get all contacts.
     */
    public function initUserCache() {
        $this->userIdCache = array();
        $page = 1;

        do {
            $prevCacheSize = count($this->userIdCache);
            $this->initUserCacheGetPage($page);
            $page++;
            //print "Cache has: " . count($this->userIdCache) . " before it was $prevCacheSize\n";
        }while( count($this->userIdCache) > $prevCacheSize );
    }

    private function initUserCacheGetPage($pageNum = 1, $additionalParams = "state=all" ) {
        $xml = $this->restCall( "/contacts.xml?page=$pageNum&$additionalParams", "GET");
        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('/users/user');
        while(list( ,$node) = each($xpathresult) ) {
            $email = (string) $node->email;
            $id = (string) $node->id;
            $this->userIdCache[$email] = $id;
        }
    }

    private function initUserCacheIfNeeded() {
        if( count($this->userIdCache) <= 0 ) {
            //print "Initializing cache";
            $this->initUserCache();
            //print "Final Cache has: " . count($this->userIdCache);

        }
    }

    /**
     * @param $email
     * @return mixed
     */
    public function getUserIdUsingCache($email)
    {
        $this->initUserCacheIfNeeded();
        return $this->userIdCache[$email];
    }

    /**
     * returns the user object as a php array for the provided email address
     * @param $email
     * @return mixed|null mixed is an php array
     */
    public function getUserByEmailUsingCache($email) {
        $this->initUserCacheIfNeeded();
        return $this->getUserById( $this->userIdCache[$email] );
    }


    /**
     * See also getUserByEmail()
     * @param $userId
     * @return mixed|null mixed type is a php array
     */
    public function getUserById($userId) {
        // TODO throw an error if user isn't in the cache.
        if( empty($userId) ) {
            return NULL;
        }

        $json = $this->restCall("/contacts/$userId.json", "GET", null, false );
        $arr = json_decode($json,true);
        return $arr['user'];
    }


    /**
     * Takes in an email address and returns an array of user properties.
     * For example you could do getUser($email)['id']
     * @param $email
     * @return mixed an array of all the elements, for example you could do $returnObj['phone'] to get the phone.
     */
    public function getUser($email) {
        $json = $this->restCall( "/contacts.json?state=all&" . urlencode("query=email is $email"), "GET", null, false );
        //print $json;
        $decoded = json_decode($json,true);

        if( isset($decoded[0]['user']) )
            return $decoded[0]['user'];
        return null;
    }

    public function getUserId($email) {
        $temp = $this->getUser($email);
        return ($temp != null ? $temp['id'] : null);
    }

    public function getUserRole($email) {
        $temp =  $this->getUser($email);
        return $temp['user_role'];
    }

    public function getUserName($email) {
        $temp =  $this->getUser($email);
        return $temp['name'];
    }

    public function getUserCustomerId($email) {
        $temp =  $this->getUser($email);
        return $temp['customer_id'];
    }



    /**
     * Sets user-role = 5, this is the user-role that allows them to see all Tickets for the Company their associated with.
     * @param $email the email address of the user.
     * @param string $companyId only needs to be specified if user isn't already associated with that company.
     * @return undefined.
     */
    public function setUserCanViewAllTicketsFromCompany($email,$companyId='') {
        $userObj = $this->getUserByEmail($email);
        return $this->setUserRoleHelper($userObj, 5, $companyId);
    }

    private function setUserRoleHelper($user, $userRole='3', $customerId='') {

        $userId = $user['id'];
        if( empty($customerId) ) {
            $customerId = $user['customer_id'];
        }

        if( empty($customerId) && $userRole == '5' ) {
            //print "ERROR: you must set a customer-id if you want to set the user role.";
            //var_dump($user);
            return null;
        }

        $payload = <<<FOLDER
<user>
	<name>{$user['name']}</name>
	<email>{$user['email']}</email>
	<user-role>$userRole</user-role>
	<customer-id>$customerId</customer-id>
</user>
FOLDER;

        //print $payload;

        return $this->restCall("/contacts/$userId.xml", "PUT", $payload, false );
    }


    /**
     * Creates a user if they don't already exists... if they do exist it updates the roles.
     * See also uploadAvatarForUser to set the avatar.
     * @param $name
     * @param $email
     * @param string $customerId
     * @param int $userRole
     * @param array $optParams an associative php array, use this if you want to specify the other attributes like job_title, description, etc.
     * @return the user id of the object created or null
     */
    public function createUser($name, $email,$customerId='', $userRole=FreshdeskRest::ROLE_VIEWONLYTHEIRS, $optParams=array() ) {

        $checkIfExistsId = $this->getUserId($email);
        if( !empty($checkIfExistsId) ) {
            //print "\n\nUSER ALREADY EXISTS!, will call update instead\n";
            $this->setUserRoleHelper($this->getUserById($checkIfExistsId), $userRole, $customerId);
            return $checkIfExistsId;
        }

        if( $userRole == FreshdeskRest::ROLE_VIEWCOMPANIES && empty($customerId) ) {
            //print "ERROR: you must set a customer-id if you want to set the user role to 5.";
            return null;
        }

        $reqParams = array (
            "name" => $name,
            "email" => $email,
            "user-role" => $userRole,
            "customer-id" => $customerId
        );
        $payload = $this->merge_and_xml_encode("user", $reqParams,$optParams);
        //print "\n\njson payload... " . $payload . "\n";

        $json = $this->restCall("/contacts.json", "POST", $payload, false);
        $decoded = json_decode($json,true);
        $theId = $decoded['user']['id'];
        return $theId;
    }

    /**
     * Uploads an Image.
     *
     * Developer FYI: This is non-documented at time of writing... uses form multipart submission
     * Note: The reason for customer_id and user_role being params is they have to be set
     *       or else they'll get cleared on save. (June 10, 2013)
     * @param $user is an array like the one your get from getUser(..), it MUST have an id, name, customer_id, and user_role set.
     * @param $imagePath
     * @param $userRole is the role to set, either ROLE_VIEWCOMPANIES or ROLE_VIEWTHEIRS, user_role isn't part of User object returned from freshdesk
     * @param bool $debugMode
     * @return mixed
     */
    public function uploadAvatarForUser($user, $imagePath, $userRole=FreshdeskRest::ROLE_VIEWCOMPANIES, $debugMode=false) {
        //error_log("USER IN UPLOAD: " . print_r($user,true) );
        $userId = $user['id'];
        //$userRole = $user['user_role']; // For whatever reason, user_role isn't provided in getUser...
        $companyId = $user['customer_id'];

        $urlMinusDomain = "/contacts/$userId.json";
        $url = "https://{$this->domain}$urlMinusDomain";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0 );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT" );

        $post = array(
//            "user[name]" => $name,
//            "user[email]" => $email,
              "user[customer_id]" => $companyId,
              "user[user_role]" => $userRole,
              "user[avatar_attributes[content]]"=>"@$imagePath;type=image/png",  // Note: tested with jpg and it works...
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        if( !empty($this->proxyServer) ) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyServer ); // Use with Fiddler to debug
        }

        $verbose = ''; // set later...
        if( $debugMode ) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $httpResponse = curl_exec ($ch);

        if( $debugMode ) {
            !rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            print $verboseLog;
        }

        return $httpResponse;
    }

    /**
     * Creates a company, see also CreateCompanyIfDoesntExist
     * @param string $name the company name
     * @param array $optFields is an optional array in case you want to set other attributes besides name.
     * @return the id of the company created.
     */
    public function createCompany($name, $optFields=array()) {
        $reqFields = array (
            "name" => $name
        );

        $payload = $this->merge_and_xml_encode("customer", $reqFields, $optFields);
        try {
            $json = $this->restCall("/customers.json", "POST", $payload, false );
            $decoded = json_decode($json,true);
            $theId = $decoded['customer']['id'];
            $this->companyIdCache[$name] = $theId; // Adds the new company
        }
        catch(Exception $ex) {
            // Woops something went wrong!
            $theId = NULL;
        }
        return $theId;

    }

    /**
     * Creates a new
     * @param $name
     * @return mixed|the
     */
    public function createCompanyIfDoesntExist($name) {
        $theId = $this->getCompanyId($name);
        if( empty($theId) ) {
            $theId = $this->createCompany($name);
        }
        return $theId;
    }

    /**
     * At the time of writing there is no way to see if a company exists by searching for their name.
     * You have to iterate through all of them until you find a match!
     *
     * This method basically gets every single customer in your system... page by page and caches the ids in
     * an array. The only time this would need to be called is if users are added or data is old... like people got added on the website.
     *
     * Developer FYI: this utilizes an undocumented (as of May 2013) 'page' get param to get all companies.
     */
    public function initCompanyCache() {
        $this->companyIdCache = array();
        $page = 1;

        do {
            $prevCacheSize = count($this->companyIdCache);
            $this->initCompanyCacheGetPage($page);
            $page++;
            //print "Cache has: " . count($this->companyIdCache) . " before it was $prevCacheSize\n";
        }while( count($this->companyIdCache) > $prevCacheSize );
    }

    private function initCompanyCacheGetPage($pageNum = 1, $additionalParams = "state=all" ) {
        $xml = $this->restCall( "/customers.xml?page=$pageNum&$additionalParams", "GET");
        $xml = simplexml_load_string($xml);
        $xpathresult = $xml->xpath('/customers/customer');
        while(list( ,$node) = each($xpathresult) ) {
            $name = (string) $node->name;
            $id = (string) $node->id;
            $this->companyIdCache[$name] = $id;
        }
    }

    private function initCompanyCacheIfNeeded() {
        if( count($this->companyIdCache) <= 0 ) {
            $this->initCompanyCache();
            //print "Final Cache has: " . count($this->companyIdCache);
        }
    }

    /**
     * @param $name - company name
     * @return mixed
     */
    public function getCompanyId($name) {
        $this->initCompanyCacheIfNeeded();
        return $this->companyIdCache[$name];
    }

    /**
     * Get the company name from an ID
     * @param $id
     * @return mixed (returns boolean FALSE if not found)... otheriwse returns the name
     */
    public function getCompanyName($id) {
        $this->initCompanyCacheIfNeeded();
        return array_search( $id, $this->getCompanyCache() );
    }

    public function getCompany($name) {
        $id = $this->getCompanyId($name);
        $json = $this->restCall("/customers/$id.json", "GET", null, false );
        $decoded = json_decode($json,true);
        return $decoded['customer'];
    }

    public function deleteCompany($name) {
        $id = $this->getCompanyId($name);
        $this->restCall("/customers/$id.json", "DELETE", null, false );
        unset($this->companyIdCache[ $name ]);
    }

    public function getCompanyCache() {
        return $this->companyIdCache;
    }

    public function getUserCache() {
        return $this->userIdCache;
    }

    /**
     * @param $urlMinusDomain - should start with /... example /solutions/categories.xml
     * @param $method - should be either GET, POST, PUT (and theoretically DELETE but that's untested).
     * @param string $postData - only specified if $method == POST or PUT
     * @param $debugMode {bool} optional - prints the request and response with headers
     * @return the raw response
     */
    private function restCall($urlMinusDomain, $method, $postData = '',$debugMode=false) {
        $url = "https://{$this->domain}$urlMinusDomain";

        //print "REST URL: " . $url . "\n";
        $header[] = "Content-type: application/xml";
        $ch = curl_init ($url);

        if( $method == "POST") {
            if( empty($postData) ){
                $header[] = "Content-length: 0"; // <-- seems to be unneccessary to specify this... curl does it automatically
            }
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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if( !empty($this->proxyServer) ) {
            curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        }

        $verbose = ''; // set later...
        if( $debugMode ) {
            // CURLOPT_VERBOSE: TRUE to output verbose information. Writes output to STDERR,
            // or the file specified using CURLOPT_STDERR.
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $httpResponse = curl_exec ($ch);

        if( $debugMode ) {
            !rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            print $verboseLog;
        }


        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($http);
        if( !preg_match( '/2\d\d/', $http_status ) ) {
            //print "ERROR: HTTP Status Code == " . $http_status . " (302 also isn't an error)\n";
        }


        if( $httpResponse == "You have exceeded the limit of requests per hour" ) {
            error_log("ERROR: Rate limit exceeded.");
        }

        // print "\n\nREST RESPONSE: " . $httpResponse . "\n\n";
        $this->lastHttpResponseText = $httpResponse;

        return $httpResponse;
    }

    /**
     * Returns the HTTP status code of the last call, useful for error checking.
     * @return int
     */
    public function getLastHttpStatus() {
        return $this->lastHttpStatusCode;
    }

    /**
     * Returns the HTTP Response Text of the last curl call, useful for error checking.
     * @return int
     */
    public function getLastHttpResponseText() {
        return $this->lastHttpResponseText;
    }

    /**
     * Will force cURL requests to use the proxy.  Can be useful to debug requests and responses
     * using Fiddler2 or WireShark.
     * curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888'); // Use with Fiddler to debug
     * @param $proxyServer - example for fiddler2 default: '127.0.0.1:8888'
     */
    public function setProxyServer($proxyServer)
    {
        $this->proxyServer = $proxyServer;
    }

    /**
     * Merges the two arrays and generates the flat xml payload
     * @param $rootTag
     * @param $reqParams
     * @param $optParams
     * @return mixed
     */
    private function merge_and_xml_encode($rootTag, $reqParams, $optParams ) {
        $merged = array_merge($reqParams,$optParams);
        return $this->xml_encode($rootTag,$merged);
    }

    /**
     * Kinda similar to json_encode in that it takes a php array and creates a flat xml
     * object.  This will only work on simple flat arrays (no nested arrays).
     * @param $rootTag
     * @param $tagArray
     * @return string
     */
    private function xml_encode($rootTag, $tagArray) {
        $xml_doc = new DomDocument('1.0');
        $root_element = $xml_doc->appendChild($xml_doc->createElement($rootTag));

        foreach ($tagArray as $key => $value) {
            $the_tag = $root_element->appendChild($xml_doc->createElement($key));
            $the_tag->appendChild($xml_doc->createTextNode($value));
        }
        return $xml_doc->saveXML();
    }


}