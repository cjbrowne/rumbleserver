<?php
/******************************************************************************
 * banned script --  Darkcanuck's Roborumble Server
 *
 *  Suspended users can be manually added here to prevent bad uploads.
 *
 * $HeadURL$
 * $Date$
 * $Revision$
 * $Author$
 *
 * Copyright 2008-2009 Jerome Lavigne (jerome@darkcanuck.net)
 * Released under GPL version 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 *****************************************************************************/

$check_user = (isset($_REQUEST['user'])) ? $_REQUEST['user'] : '';
$check_ip   = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';


/*if ( ($check_user=='username') || ($check_ip=='xx.xx.xx.xx') )
    trigger_error("Account '$check_user' has been suspended!
    Please check your client configuration and the RoboWiki for current issues.
    Post a message on the wiki once you have fixed the problem.", E_USER_ERROR);
*/

?>