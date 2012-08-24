<?php

date_default_timezone_set('UTC');

function ftrace()
{
    $args = func_get_args();
    $results = array();
    foreach ($args as $arg)
    {
        $results[] = is_scalar($arg) ? $arg : var_export($arg, true);
    }
    $old = ini_set('error_log', 'ftrace.log');
    error_log(implode(' ', $results));
    ini_set('error_log', $old);
}

require_once 'lib/MantisSvnHistoryService.class.php';
require_once 'lib/MantisSvnHistoryXmlRepository.class.php';
require_once 'lib/MantisSvnHistorySqliteRepository.class.php';


$mantis_ticket_number = null;

if (isset($_GET['mantis_ticket_number'], $_GET['svn_repository_url']))
{
    $mantis_ticket_number = intval($_GET['mantis_ticket_number']);
    $svn_repository_url = trim($_GET['svn_repository_url']);
}
else if (isset($argv[1], $argv[2]))
{
    $mantis_ticket_number = intval($argv[1]);
    $svn_repository_url = intval($argv[2]);
}

if (empty($mantis_ticket_number) && empty($svn_repository_url))
{
    die('Missing arguments.');
}

$items = array();

if ($mantis_ticket_number !== null)
{
    $mantis_svn_history_sqlite_repository = new MantisSvnHistorySqliteRepository($svn_repository_url);
    
    $mantis_svn_service = new MantisSvnHistoryService($mantis_svn_history_sqlite_repository);
    $items = $mantis_svn_service->retrieveLogForMantis($mantis_ticket_number);
}


if (isset($_GET['jsoncallback']))
{
   echo $_GET['jsoncallback'] . '(' . json_encode($items) . ')'; 
}
else
{
    echo json_encode($items);  
}