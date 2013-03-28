# FreshDesk.com Solutions API Wrapper
Provides methods that are useful for creating articles.
1. Facilitates creating api's by allowing the use of strings instead of looking up category ids / folder ids.
2. When creating an article which has a folder name or category name that doesn't exist, they can be created automatically.

Originally wrote this tool to import all of my KB articles from SugarCRM Knowledgebase.



## Ways To Improve
1. Performance could be improved by caching category id's and folder id's that have already been looked up.
2. Only GET and POST methods are implemented.  Currently there are no delete or update methods.
3. Error handling is ok, but could be improved.

## Usage

1. Copy the FreshdeskRest.php into your project.
2. Here's some code.

```
<?php
/**
 * How to use the freshdesk api.
 */
require_once("FreshdeskRest.php");
$fd = new FreshdeskRest("yoursubdomain.freshdesk.com", "your_username", "your_password");
$fd-

$fd->getCategoryId("Alert Beacon");
$fd->getFolderId("Alert Beacon", "Installation");
$fd->createArticle("TEST CATEGORY", "Test_Folder", "Test Article Title", "The <u>HTML</u> of your body goes here!<P>Paragraph 2</P>");
```

![gitimg](https://gitimg.com/blak3r/freshdesk-solutions/README/track)
