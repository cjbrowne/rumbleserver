<?php
/******************************************************************************
 * Remove Bot client request  --  Darkcanuck's Roborumble Server
 *
 * Copyright 2008-2011 Jerome Lavigne (jerome@darkcanuck.net)
 * Released under GPL version 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 *****************************************************************************/

require_once 'classes/common.php';

$err->setClient(true);
ignore_user_abort(true);	// don't stop if client disconnects!

// check for banned users
require_once 'classes/banned.php';

$admin_user = false;
if (isset($_SERVER['REMOTE_ADDR']) &&
    (($_SERVER['REMOTE_ADDR']=='127.0.0.1') || ($_SERVER['REMOTE_ADDR']=='24.85.46.67')) ) {
    $admin_user = true;
    if (isset($_GET['version']))
        $_POST['version'] = $_GET['version'];
    if (isset($_GET['game']))
        $_POST['game'] = $_GET['game'];
    if (isset($_GET['name']))
        $_POST['name'] = $_GET['name'];
}

if ($properties->get('disable_remove') && !$admin_user)
    trigger_error('Function temporarily disabled.  Please try again later.', E_USER_ERROR);


/* check RoboRumble client version */
if (isset($_POST['version'])) {
	$version = trim($_POST['version']);

	switch ($version) {
		
		case "1":
			/* "classic" client, can't determine exact version
			 *
			 *  Supplies the following values:
			 *  	version, game, name, dummy
			 */
			
			// determine game type
			$gametype = new GameType($version, trim($_POST['game']));
			
			// check bot name
			if (!isset($_POST['name']) || empty($_POST['name']))
				trigger_error('No robot specified for removal!', E_USER_ERROR);
			$name = trim(isset($_POST['name']) ? $_POST['name'] : '');
			
			// bot name is from ratings file -- space between name+version replaced by underscore
			$pos = strrpos($name, '_');
			if ($pos!==false)
				$name[$pos] = ' ';
			
			// remove specified bot
			$party = new Participants($db, $gametype->getCode());
			
			// only remove if no new battles in last 4hrs
			//$bot = $party->getByName($name);
			//$ts_bot = strtotime($bot['timestamp']);
			//if ((time()-$ts_bot) < (4*60*60))
			//    trigger_error('Cannot remove ' . substr($name, 0, 70) . ' until at least 4hrs after last battle', E_USER_ERROR);
			
			$priority = new PriorityBattles($properties);
	        $priority->addIgnored($name);
			
			if ($party->retireParticipant($name))
				die('OK.  Removed bot ' . substr($name, 0, 70));
			else
				trigger_error('Failed to remove ' . substr($name, 0, 70), E_USER_ERROR);
			break;
			
		default:
			// unsupported client
			trigger_error('Client version ' . substr($version, 0, 10) . ' is not supported by this server!', E_USER_ERROR);
			break;
	}
	
	// debugging
	if (($_SERVER['REMOTE_ADDR']=='127.0.0.1') || $debug_user)
		echo str_replace(array('[',']','<','>'), '|', $db->debug());
	
} else {
	//missing version parameter
	trigger_error('Missing client version number!', E_USER_ERROR);
}

?>