<?php
/******************************************************************************
 * GamePairings class  --  Darkcanuck's Roborumble Server
 *
 * Copyright 2008-2011 Jerome Lavigne (jerome@darkcanuck.net)
 * Released under GPL version 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 *****************************************************************************/

class GamePairings {
	
	private $db = null;
	private $gamedef = null;
	
	private $divfields = array('score_pct', 'score_dmg', 'score_survival');
	
	private $pairing = null;
	private $newpair = false;
	
	private $gametype  = '';
	private $id1       = '';
	private $id2       = '';
	
	
	function __construct($db, $gametype, $id1=1, $id2=1) {
		$this->db = $db;
		$this->gamedef = new GameType('1', $gametype);
		$this->gametype = $gametype;

		if (($id1 < 1) || ($id2 < 1))
			trigger_error("Invalid bot pairing data!", E_USER_ERROR);
		$this->id1 = $id1;
		$this->id2 = $id2;
	}
	
	function getPairing() {
		$this->pairing = null;
		
		$qrystring = "SELECT gametype, bot_id, vs_id, battles, score_pct, score_dmg, " .
						" score_survival, count_wins, timestamp, state " .
				        " FROM   game_pairings " .
				        " WHERE  gametype = '%s' AND bot_id=%u AND vs_id=%u " .
				        " FOR UPDATE ";     // write locks rows if inside transaction
		$qry1 = sprintf($qrystring, $this->gametype[0], (int)$this->id1, (int)$this->id2);
		$qry2 = sprintf($qrystring, $this->gametype[0], (int)$this->id2, (int)$this->id1);
		
		if ($this->db->query($qry1) > 0)
		    $this->pairing[ $this->id1 ] = $this->db->next();
        if ($this->db->query($qry2) > 0)
		    $this->pairing[ $this->id2 ] = $this->db->next();
		
		if ( (count($this->pairing) != 0) && (count($this->pairing) != 2) )
		    trigger_error("Corrupted pairing data!!!", E_USER_ERROR);
		
		if (($this->pairing==null) || (count($this->pairing)<2)) {
			$this->newpair = true;
			foreach(array($this->id1, $this->id2) as $id)
				$this->pairing[ $id ] = array(
											'gametype' => $this->gametype,
											'bot_id' => $id,
											'vs_id' => ($id==$this->id1) ? $this->id2 : $this->id1,
											'battles' => 0,
											'score_pct' => 0,
											'score_dmg' => 0,
											'score_survival' => 0,
											'count_wins' => 0,
											'timestamp' => strftime('%Y-%m-%d %T'),
											'state' => STATE_OK
											);
		}
		return true;
	}
	
	function savePairing($keep_time=false) {
		$rows = 0;
		foreach($this->pairing as $id => $pair) {
			$qry = '';
			if ($this->newpair) {
				$qry = "INSERT INTO game_pairings
							SET gametype = '" . $pair['gametype'][0] . "',
								bot_id   = " . ((int)$pair['bot_id']) . ",
								vs_id    = " . ((int)$pair['vs_id']) . ",
								timestamp = NOW(),
								";
			} else {
				$qry = "UPDATE game_pairings
							SET ";
				if (!$keep_time)
				    $qry .=    "timestamp = NOW(), ";
			}
			$qry .=			   "battles   = '" . mysql_escape_string($pair['battles']) . "',
								score_pct = '" . mysql_escape_string($pair['score_pct']) . "',
								score_dmg = '" . mysql_escape_string($pair['score_dmg']) . "',
								score_survival = '" . mysql_escape_string($pair['score_survival']) . "',
								count_wins     = '" . mysql_escape_string($pair['count_wins']) . "',
								state = '" . mysql_escape_string($pair['state']) . "' ";
			if (!$this->newpair) {
				$qry .= " WHERE gametype = '" . $pair['gametype'][0] . "'
							AND bot_id   = " . ((int)$pair['bot_id']) . "
							AND vs_id    = " . ((int)$pair['vs_id']);
			}
			$rows += $this->db->query($qry);
		}
		return ($rows > 1);
	}
	
	function deletePairing() {
		if ($this->newpair)
		    return true;    // nothing to delete
		$rows = 0;
		foreach($this->pairing as $id => $pair) {
			$qry = "DELETE FROM game_pairings
					WHERE gametype = '" . $pair['gametype'][0] . "'
					  AND bot_id   = '" . ((int)$pair['bot_id']) . "'
					  AND vs_id    = '" . ((int)$pair['vs_id']) . "'";
			$rows += $this->db->query($qry);
		}
		return ($rows > 1);
	}
	
	function updateScores($scores, $do_save=true) {
		// $scores should be an assoc. array with winner & loser ids as array keys
		// data must contain score, bullet dmg. and survival for each bot
		if (!is_array($scores) || (count($scores)<2))
			trigger_error("Invalid score data!", E_USER_ERROR);

		list($this->id1, $this->id2) = array_keys($scores);
		
		if (!$this->newpair && ($this->pairing==null))
			$this->getPairing();
		
		foreach($this->pairing as $id => $p) {
			$vs = ($this->id1==$id) ? $this->id2 : $this->id1;
			$pair =& $this->pairing[$id];
			$bot1 =& $scores[$id];
			$bot2 =& $scores[$vs];
			if (!$this->gamedef->useSurvival()) {
			    $pair['score_pct'] = $this->calcScorePercent($bot1['score'], $bot2['score'], $pair['score_pct'], $pair['battles']);
			    $pair['score_survival'] = $this->calcScorePercent($bot1['survival'], $bot2['survival'], $pair['score_survival'], $pair['battles']);
		    } else {    // survival scoring for twin duel, swap score & survival columns (makes elo/glicko/pl based on survival)
			    $pair['score_survival'] = $this->calcScorePercent($bot1['score'], $bot2['score'], $pair['score_survival'], $pair['battles']);
			    $pair['score_pct'] = $this->calcScorePercent($bot1['survival'], $bot2['survival'], $pair['score_pct'], $pair['battles']);		        
		    }
			$pair['score_dmg'] = $this->calcScorePercent($bot1['bulletdmg'], $bot2['bulletdmg'], $pair['score_dmg'], $pair['battles']);
            $pair['count_wins'] = ($pair['score_pct'] > 50000) ? 1 : 0;
			$pair['battles'] += 1;
		}
		if ($do_save)
		    return $this->savePairing();
		else
		    return true;
	}
	
	function calcScorePercent($score1, $score2, $lastscore, $battles) {
	    $pctscore = (($score1+$score2)>0) ? $score1 / ($score1+$score2) : 0.5;
		return (int) ( (($pctscore * 100 * 1000) + ($lastscore * $battles)) / ($battles+1) );
	}
	
	function recalcScores($id1, $id2) {
		// load pairing data
        $this->id1 = $id1;
        $this->id2 = $id2;
        if (!$this->newpair && ($this->pairing==null))
			$this->getPairing();
        
        // load battle data
		$results = new BattleResults($this->db);
		$battles = $results->getBattleDetails($this->gametype, $this->id1, $this->id2);
		if ($battles==null)
		    return $this->deletePairing();  // delete pair & exit if no other battles left
		if (!is_array($battles) || (count($battles)<1))
			trigger_error("Invalid battle data!", E_USER_ERROR);
        
        // reset pairing scores & battle count
        foreach(array($this->id1, $this->id2) as $id) {
			$this->pairing[$id]['battles'] = 0;
			$this->pairing[$id]['count_wins'] = 0;
			$this->pairing[$id]['score_pct'] = 0;
			$this->pairing[$id]['score_dmg'] = 0;
			$this->pairing[$id]['score_survival'] = 0;
		}
		
        // recalculate scores incrementally (to reuse scoring routine)
        foreach($battles as $b) {
            $scores = array(
    						$b['bot_id'] => array('score' => $b['bot_score'],
    						 					'bulletdmg' => $b['bot_bulletdmg'],
    											'survival' => $b['bot_survival']),
    						$b['vs_id']  => array('score' => $b['vs_score'],
    						 					'bulletdmg' => $b['vs_bulletdmg'],
    											'survival' => $b['vs_survival'])
						    );
		    $this->updateScores($scores, false);
        }
		return $this->savePairing(true);
	}
	
	function getAllPairings() {
	    //$qrystring = "SELECT bot_id, vs_id, battles, score_pct, score_dmg, " .
		//				" score_survival, count_wins " .
		//		        " FROM   game_pairings " .
		//		        " WHERE  gametype = '%s' AND bot_id=%u " .
		//		        " AND state = '" . STATE_OK . "' ";
		$qrystring = "SELECT g.bot_id, g.vs_id, g.battles, g.score_pct, g.score_dmg, " .
		                " g.score_survival, g.count_wins " .
                        " FROM  game_pairings AS g INNER JOIN participants AS p " .
                        "    ON p.gametype = g.gametype AND p.bot_id = g.vs_id " .
                        " WHERE g.gametype = '%s' AND g.bot_id=%u " .
        		        "   AND p.state = '" . STATE_OK . "' ";
		$qry1 = sprintf($qrystring, $this->gametype[0], (int)$this->id1);
		$qry2 = sprintf($qrystring, $this->gametype[0], (int)$this->id2);
		
		$results = array();
		if ($this->db->query($qry1) > 0)
		    $results = $this->db->all();
        if ($this->db->query($qry2) > 0) {
            foreach ($this->db->all() as $rs)
		        $results[] = $rs;
        }
	    return $results;
	}
	
	/*function checkState($bot_id, $state=STATE_OK) {
		$id = (int)$bot_id;
		$qry = "SELECT vs_id
		        FROM game_pairings
		        WHERE gametype = '" . $this->gametype[0] . "'
				  AND bot_id = $id
				  AND state='" . mysql_escape_string($state) . "'";
		return ($this->db->query($qry) > 0);
	}
	
	function updateState($bot_id, $newstate, $oldstate='') {
		$id = (int)$bot_id;
		$qry = "UPDATE game_pairings SET state='" . mysql_escape_string($newstate) . "'
				WHERE  gametype = '" . $this->gametype[0] . "'
				  AND  (bot_id=$id OR vs_id=$id) ";
		if ($oldstate!='')
			$qry .= " AND state='" . mysql_escape_string($oldstate) . "'";
		return ($this->db->query($qry) > 0);
	}*/
		
	function getBotPairings($game='', $id='', $retired=false, $anystate=false, $order='vs_name') {
		$gametype = ($game!='') ? $game[0] : $this->gametype[0];
		$id1 = (int)(($id!='') ? $id : $this->id1);
        $qry = "SELECT g.gametype AS gametype, g.bot_id AS bot_id,
						g.vs_id AS vs_id, b.full_name AS vs_name,
						g.battles AS battles, g.score_pct AS score_pct,
						g.score_dmg AS score_dmg, g.score_survival AS score_survival,
						g.count_wins AS count_wins, g.timestamp AS timestamp,
						p.state AS state
				FROM game_pairings AS g
				INNER JOIN participants AS p ON p.gametype = g.gametype AND p.bot_id = g.vs_id
				INNER JOIN bot_data AS b ON g.vs_id = b.bot_id
				WHERE g.gametype = '$gametype'
				  AND g.bot_id = '$id1' ";
		if (!$anystate)
			$qry .= " AND p.state = '" . STATE_OK . "' ";
		$qry .= " ORDER BY `" . mysql_escape_string($order) . "` ASC";
		if ($this->db->query($qry)>0)
			return $this->db->all();
		else
			return null;
	}
	
	function getSinglePairing($game='', $id='', $vs='') {
		$gametype = ($game!='') ? $game[0] : $this->gametype[0];
		$id1 = (int)(($id!='') ? $id : $this->id1);
		$id2 = (int)(($vs!='') ? $vs : $this->id2);
        $qry = "SELECT g.gametype AS gametype,
                        g.bot_id AS bot_id, b.full_name AS bot_name,
						g.vs_id AS vs_id, v.full_name AS vs_name,
						g.battles AS battles, g.score_pct AS score_pct,
						g.score_dmg AS score_dmg, g.score_survival AS score_survival,
						g.count_wins AS count_wins, g.timestamp AS timestamp,
						p.state AS state
				FROM game_pairings AS g
				INNER JOIN participants AS p ON p.gametype = g.gametype AND p.bot_id = g.vs_id
                INNER JOIN bot_data AS b ON g.bot_id = b.bot_id
				INNER JOIN bot_data AS v ON g.vs_id = v.bot_id
				WHERE g.gametype = '$gametype'
				  AND g.bot_id = '$id1'
				  AND g.vs_id = '$id2'";
		if ($this->db->query($qry)>0) {
		    $rs = $this->db->next();
		    foreach($this->divfields as $f)
                $rs[$f] = (float)$rs[$f] / 1000.0;
		    return $rs;
		}
		return null;
	}
	
}

?>