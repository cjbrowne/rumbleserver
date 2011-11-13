<?php
/******************************************************************************
 * Common startup/init script  --  Darkcanuck's Roborumble Server
 *
 * Copyright 2008-2011 Jerome Lavigne (jerome@darkcanuck.net)
 * Released under GPL version 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 *****************************************************************************/

// error handling
error_reporting(E_ALL);		// applies for errors not caught by error handler
require_once 'classes/ErrorHandler.php';
$err = new ErrorHandler();

if (($_SERVER['REMOTE_ADDR']=='127.0.0.1') || isset($_REQUEST['debug']))
    $err->setDebugMode(true);

// config includes
require_once 'config/config.php';

date_default_timezone_set( isset($default_TZ) ? $default_TZ : 'UTC' );

// class includes
require_once 'classes/MySQL.php';
require_once 'classes/BattleResults.php';
require_once 'classes/BotData.php';
require_once 'classes/GamePairings.php';
require_once 'classes/GameType.php';
require_once 'classes/Participants.php';
require_once 'classes/PriorityBattles.php';
require_once 'classes/RankingsUpdate.php';
require_once 'classes/ServerProperties.php';
require_once 'classes/UploadUsers.php';

// database 'state' field values
define('STATE_NEW',		'0');	// battle result needs to be processed
define('STATE_OK',      '1');	// done pairing processing
define('STATE_RATED',   '2');	// done ratings processing
define('STATE_LOCKED',  'L');	// locked for ratings processing
define('STATE_RETIRED', 'R');	// retired; in results table, requires rebuild if re-activated
define('STATE_RETIRED2','S');   // both bots in pair retired
define('STATE_FLAGGED', 'W');	// possibly bad result, flagged for removal
define('STATE_REMOVED', 'X');	// possibly bad result, needs rebuild if re-activated

// connect to database
$db = new DBlite_MySQL($db_creds);

// check if server in maintenance mode
$properties = new ServerProperties($db);
if ($properties->get('maintenance'))
	trigger_error('Sorry, the server is down for maintenance. Please try again later.', E_USER_ERROR);

?>