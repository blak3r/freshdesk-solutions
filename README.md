# FreshDesk.com Solutions API Wrapper

Provides methods that are useful for creating articles.

1. Facilitates creating solutions by allowing the use of strings instead of looking up category ids / folder ids / topics ids.
2. When creating an article which has a folder name or category name that doesn't exist, they can be created automatically.

Originally wrote this tool to import all of my KB articles from SugarCRM Knowledgebase.



## Ways To Improve
1. Haven't tried any DELETE methods...
2. Error handling is ok, but could be improved.

## Usage
1. Copy the FreshdeskRest.php into your project.
2. Here's some code.

```
<?php

require_once("FreshdeskRest.php");
$fd = new FreshdeskRest("yoursubdomain.freshdesk.com", "your_username", "your_password");
$fd->setCreateStructureMode('true'); // will create categories and folders if they don't exist.

$fd->createOrUpdateArticle("TEST CATEGORY", "Test_Folder", "Test Article Title", "The <u>HTML</u> of your body goes here!<P>Paragraph 2</P>");
```

![gitimg](https://gitimg.com/blak3r/freshdesk-solutions/README/track)
