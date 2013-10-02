<?php
/**
 * File: example.php
 * Project: freshdesk-solutions
 */

//Require the main configuration
require_once('inc/config.php');

//Require the FreshDesk API Library
require_once("FreshdeskRest.php");

//Create New FreshDesk API Object
$fd = new FreshdeskRest(FD_URL, FD_API_USER, FD_API_PASS);

//$fd->getSingleTicket(31701);

//$json = $fd->getTicketSurvey(31701);
$json = $fd->getTicketSurvey(32882);

print_r($json);