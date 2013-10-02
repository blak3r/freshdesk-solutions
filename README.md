# FreshDesk.com PHP API Wrapper


## Highlights

### Provides Methods for Interacting with Tickets and Surveys

1. 

## Ways To Improve
1. Error handling is ok, but could be improved.
2. Not 100% consistent with return types, check method comments.

## Usage
1. Copy the FreshdeskRest.php into your project.
2. Here's some code.

```
<?php

require_once("FreshdeskRest.php");
$fd = new FreshdeskRest("yoursubdomain.freshdesk.com", "your_username", "your_password");
