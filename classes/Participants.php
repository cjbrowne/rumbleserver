<?php
/******************************************************************************
 * Participants class  --  Darkcanuck's Roborumble Server
 *
 * Copyright 2008-2011 Jerome Lavigne (jerome@darkcanuck.net)
 * Released under GPL version 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 *****************************************************************************/

class Participants {
	
	private $db = null;
	private $game = '';
	
	private $plist = null;
	private $pname = null;
	private $order = '';
	private $limit = 0;
	
	private $is_retired = false;
	
	function __construct($db, $gametype, $order='', $limit=0) {
		$this->db = $db;
		$this->game = $gametype;
		$this->order = $order;
		$this->limit = $limit;
	}
	
	function queryList() {
		$qry = "SELECT p.bot_id, p.battles, p.score_pct, p.score_dmg, p.score_survival,
						p.rating_classic, p.rating_glicko, p.rd_glicko,
						p.rating_glicko2, p.rd_glicko2, p.vol_glicko2,
						p.pairings, p.count_wins, p.timestamp, p.state,
						b.full_name AS name, b.timestamp AS created
				FROM  participants AS p INNER JOIN bot_data AS b ON p.bot_id = b.bot_id
				WHERE p.gametype='" . mysql_escape_string($this->game) . "'
				  AND p.state='" . STATE_OK . "'";
		$valid_orders = array('battles', 'score_pct', 'score_dmg', 'score_survival',
      						'rating_classic', 'rating_glicko', 'rating_glicko2',
      						'pairings', 'count_wins', 'timestamp', 'name', 'created');
		if (($this->order!='') && in_array($this->order, $valid_orders))
			$qry .= " ORDER BY `" . mysql_escape_string($this->order) . "` DESC";
		if ($this->limit>0)
    		$qry .= " LIMIT " . (int)$this->limit;
    	
		$this->db->query($qry);
		foreach($this->db->all() as $rs) {
			$this->plist[ $rs['bot_id'] ] = $rs;
			$this->pname[ $rs['name'] ] =& $this->plist[ $rs['bot_id'] ];
		}
	}
		
	function getList() {
		if ($this->plist==null)
			$this->queryList();
		return $this->plist;
	}
	
	function getBot($id, $retired=false) {
		$this->is_retired = false;
		if ($this->plist==null)
			$this->queryList();
        if (isset($this->plist[ $id ]))
            return $this->plist[ $id ];
        if ($retired) {
            $oldbot = $this->getRetired($id);
            if ($oldbot!=null) {
                $this->is_retired = true;
                return $oldbot;
            }
        }
        trigger_error('Invalid robot id "' . ( (int) $id ) . '"', E_USER_ERROR);
	}
	
	function getByName($name, $retired=false) {
		$this->is_retired = false;
        if ($this->pname==null)
			$this->queryList();
        if (isset($this->pname[ $name ]))
            return $this->pname[ $name ];
        if ($retired) {
        	$bot = new BotData($name);
    		$id = $bot->getID($this->db, false);
            $oldbot = $this->getRetired($id);
            if ($oldbot!=null) {
                $this->is_retired = true;
                return $oldbot;
            }
        }
		trigger_error('Invalid robot name "' . substr($name, 0, 50) . '"', E_USER_ERROR);
	}
	
	function getRetired($id) {
		$qry = "SELECT p.bot_id, p.battles, p.score_pct, p.score_dmg, p.score_survival,
						p.rating_classic, p.rating_glicko, p.rd_glicko,
						p.rating_glicko2, p.rd_glicko2, p.vol_glicko2,
						p.pairings, p.count_wins, p.timestamp, p.state,
						b.full_name AS name, b.timestamp AS created
				FROM  participants AS p INNER JOIN bot_data AS b ON p.bot_id = b.bot_id
				WHERE p.gametype='" . mysql_escape_string($this->game) . "'
				  AND p.state='" . STATE_RETIRED . "'
				  AND p.bot_id='" . mysql_escape_string($id) . "'";
        if ($this->db->query($qry) > 0)
            return $this->db->next();
        else
            return null;
	}
	
	function isRetired() {
	    return $this->is_retired;
	}
	
	function checkNames($botnames) {
		// force input to be an array
		if (!is_array($botnames))
			$bots = array($botnames);
		
		if ($this->plist==null)
			$this->queryList();
		
		$botids = array();
		foreach($botnames as $name) {
			if (!isset($this->pname[$name]))
				$botids[$name] = $this->activateParticipant($name);
			else
				$botids[$name] = $this->pname[$name]['bot_id'];
		}
		return $botids;
	}
	
	function activateParticipant($name) {
		// create bot id if necessary
		$bot = new BotData($name);
		$id = $bot->getID($this->db);
		
		// find record in participant table (if retired)
		$qry = "SELECT bot_id, state
			 	FROM   participants
				WHERE  gametype='" . mysql_escape_string($this->game) . "'
				  AND  bot_id='" . mysql_escape_string($id) . "'";
		if ($this->db->query($qry) > 0) {
			// bring out of retirement
			
			/* no longer need to update pairings & battles
			set_time_limit(600);
			
			// TODO -- consider removing this now that battles are no longer retired
			$battles = new BattleResults($this->db);
			$battles->updateState($this->game, $id, STATE_RATED, STATE_RETIRED);
			$battles->updateState($this->game, $id, STATE_RETIRED, STATE_RETIRED2);
			
			// restore pairings if none have an "OK" state
			$pairings = new GamePairings($this->db, $this->game);
			if (!$pairings->checkState($id, STATE_OK)) {
			    $pairings = new GamePairings($this->db, $this->game);
    			$pairings->updateState($id, STATE_OK, STATE_RETIRED);
    			$pairings->updateState($id, STATE_RETIRED, STATE_RETIRED2);
		    } */
		    
			$this->updateParticipant($id);
		} else {
			$this->addParticipant($id);
		}
		return $id;
	}
	
	function addParticipant($id) {
		$qry = "INSERT INTO participants
				SET gametype='" . mysql_escape_string($this->game) . "',
					bot_id='" . mysql_escape_string($id) . "',
					state='" . STATE_OK . "',
					timestamp=NOW()";
		$this->db->query($qry);
		
		// update cached list
		if ($this->plist!=null)
			$this->queryList();
		return true;
	}
	
	function updateParticipant($id, $state=STATE_OK) {
		$qry = "UPDATE participants
				SET    state='" . mysql_escape_string($state) . "'
				WHERE  gametype = '" . mysql_escape_string($this->game) . "'
				  AND  bot_id = '" . mysql_escape_string($id) . "'";
		$this->db->query($qry);
		
		// update cached list
		if ($this->plist!=null)
			$this->queryList();
		return true;
	}
	
	function retireParticipant($name) {
		// find bot id (but don't add to database)
		$bot = new BotData($name);
		$id = $bot->getID($this->db, false);
        
        /* old code when game pairings status was used 
		set_time_limit(600);
        //$this->db->query('START TRANSACTION');
        $this->db->query('SET autocommit=0');
        $this->db->query('LOCK TABLES participants WRITE, participants AS p WRITE,
                                    bot_data WRITE, bot_data AS b WRITE,
                                    game_pairings WRITE, game_pairings AS g WRITE');
        */
        
        /* battles are no longer retired - no real need and it's quite slow
		$battles = new BattleResults($this->db);
		//$battles->updateState($this->game, $id, STATE_RETIRED, STATE_NEW);		// could do all, but want to exclude 'X' state
		//$battles->updateState($this->game, $id, STATE_RETIRED, STATE_OK);
		$battles->updateState($this->game, $id, STATE_RETIRED2, STATE_RETIRED);     // 2nd retire for this pair
		$battles->updateState($this->game, $id, STATE_RETIRED, STATE_RATED);
		*/
		
		/* no longer need to retire pairings
		// retire any remaining pairings with an "OK" state
		$pairings = new GamePairings($this->db, $this->game);
		if ($pairings->checkState($id, STATE_OK)) {
    		//$pairings->updateState($id, STATE_RETIRED, STATE_NEW);
    		$pairings->updateState($id, STATE_RETIRED2, STATE_RETIRED);     // 2nd retire for this pair
    		$pairings->updateState($id, STATE_RETIRED, STATE_OK);		    
		} */
		
		$this->updateParticipant($id, STATE_RETIRED);
		
		//$this->db->query('COMMIT');
		//$this->db->query('UNLOCK TABLES');
		
		return true;
	}
	
	function updateScores($id, $newscores) {
		// update local participant list
		if ($this->plist==null)
			$this->queryList();
		if (!isset($this->plist[$id]))
			return false;
		
		$p =& $this->plist[$id];
		$fields = array('battles', 'score_pct', 'score_dmg', 'score_survival',
		 				'rating_classic', 'rating_glicko', 'rd_glicko',
						'rating_glicko2', 'rd_glicko2', 'vol_glicko2',
						'pairings', 'count_wins');
		foreach($fields as $f) {
			if (isset($newscores[$f]))
				$p[$f] = $newscores[$f];
		}
		$qry = "UPDATE participants
				SET battles='" . mysql_escape_string($p['battles']) . "',
					score_pct='" . mysql_escape_string($p['score_pct']) . "',
					score_dmg='" . mysql_escape_string($p['score_dmg']) . "',
					score_survival='" . mysql_escape_string($p['score_survival']) . "',
					rating_classic='" . mysql_escape_string($p['rating_classic']) . "',
					rating_glicko='" . mysql_escape_string($p['rating_glicko']) . "',
					rd_glicko='" . mysql_escape_string($p['rd_glicko']) . "',
					rating_glicko2='" . mysql_escape_string($p['rating_glicko2']) . "',
					rd_glicko2='" . mysql_escape_string($p['rd_glicko2']) . "',
					vol_glicko2='" . mysql_escape_string($p['vol_glicko2']) . "',
					pairings='" . mysql_escape_string($p['pairings']) . "',
					count_wins='" . mysql_escape_string($p['count_wins']) . "',
					timestamp=NOW(), state='" . STATE_OK . "'
				WHERE gametype = '" . mysql_escape_string($this->game) . "'
				  AND bot_id = '" . mysql_escape_string($id) . "'";
		return($this->db->query($qry) > 0);
	}
}

?>