<?PHP

function ircexplode($str) {
	$str = rtrim($str, "\r\n");
	$pos = strpos($str, " :");
	if ($pos === false)
		$last = null;
	else {
		$last = substr($str, $pos+2);
		$str = substr($str, 0, $pos);
	}
	$args = explode(" ", $str);
	if ($last !== null)
		$args[] = $last;
	return $args;
}

function ircimplode($args) {
	$last = array_pop($args);
	if (strpos($last, " ") !== false
	or strpos($last, ":") !== false) {
		$last = ":".$last;
	}
	$args[] = $last;
	$str = implode(" ", $args) . "\r\n";
	return $str;
}

function prefixparse($prefix) {
	$npos = $prefix[0] == ":" ? 1 : 0;
	$upos = strpos($prefix, "!", $npos);
	$hpos = strpos($prefix, "@", $upos);
	if ($upos === false or $hpos === false) {
		$nick = null;
		$user = null;
		$host = substr($prefix, $npos);
	} else {
		$nick = substr($prefix, $npos, $upos++-$npos);
		$user = substr($prefix, $upos, $hpos++-$upos);
		$host = substr($prefix, $hpos);
	}
	return array($nick, $user, $host);
}

function ischannel($target) {
	return $target[0] == "#";
}

function nicktolower($nick) {
	$nick = strtolower($nick);
	$nick = strtr($nick, "[]\\", "{}|");
	return $nick;
}

//

function getpts($nick) {
	$nick = nicktolower($nick);
	global $users;
	return (int) $users[$nick]["points"];
}

function isadmin ($source) {
	$nick = nicktolower($nick);
	global $users;
	return (bool) $users[$nick]['admin'];
}

function mysort ($a,$b) {
	if (!isset($a)) { $a = 0; }
	if (!isset($b)) { $b = 0; }
	if ($a == $b) {
		return 0;
	}
	return ($a < $b) ? -1 : 1;
}
function loguser ($source,$reason) {
	global $users;
	if ($users[$source]['vlog'] == true) {
		global $socket;
		fwrite($socket,'NOTICE '.$source.' :'.$reason.'.'."\n");
	}
	$users[$source]['log'][$reason]++;
}
function getstats ($source) {
	global $users;
	foreach ($users[$source]['log'] as $reason => $count) {
		$tmp .= $reason.': '.$count.'.  ';
	}
	return $tmp;
}
function gettop ($bottom = false) {
	global $users;
	foreach ($users as $nick => $data) {
		$tmp[$nick] = $data['points'];
	}
	uasort($tmp,'mysort');
	if ($bottom == false) { $tmp = array_reverse($tmp,true); }
	$i = 0;
	foreach ($tmp as $nick => $pts) {
		$i++;
		$tmp2[$nick] = $pts;
		if ($i >= 3) {
			break;
		}
	}
	if ($bottom == true) { $tmp2 = array_reverse($tmp2,true); }
	return $tmp2;
}
function setignore ($target,$status = true) {
	global $users;
	$users[$target]['ignore'] = $status;
	$users[$target]['points'] = 0;
	unset($users[$target]['log']);
	if ($status == true) { loguser($target,'Ignored =0'); }
	else { loguser($target,'Unignored =0'); }
}
function chgpts ($source,$delta) {
	global $users;
	if ($users[$source]['ignore'] == true) { return; }
	if (($users[$source]['verbose'] == true) and ($users[$source]['vlog'] == false)) {
		global $socket;
		if ($delta > 0) {
			$what = 'gained';
		} else {
			$what = 'lost';
		}
		if (($users[$source]['vdedo'] == false) or ($delta < 0)) {
			fwrite($socket,'NOTICE '.$source.' :You have '.$what.' '.abs($delta).' points.'."\n");
		}
	}
	$users[$source]['points'] += $delta;
	if ($users[$source]['points'] <= -2000) {
		global $target;
		global $config;
		global $socket;
		if ($target != $config['nick']) {
			fwrite($socket,'PRIVMSG '.$target.' :'.$source.' is not clueful!'."\n");
			loguser($source,'User Warned +75');
			chgpts($source,75);
		}
	}
	save_db();
}
function mysqlconn ($user,$pass,$host,$port,$database) {
	global $mysql;
	$mysql = mysql_connect($host.':'.$port,$user,$pass);
	if (!$mysql) {
		die('Can not connect to MySQL!');
	}
	if (!mysql_select_db($database,$mysql)) {
		die('Can not access database!');
	}
}	
function get_db () {
	$ret = unserialize(file_get_contents('cb_users.db'));
//	global $mysql;
//	$ret = array();
//	$res = mysql_query('SELECT * FROM `users`');
//	while ($x = mysql_fetch_array($res)) {
//		$ret[$x['nick']] = array(
//			'ignore' => $x['ignore'],
//			'admin' => $x['admin'],
//			'points' => $x['points'],
//			'verbose' => $x['verbose'],
//			'vdedo' => $x['vdedo'],
//			'vlog' => $x['vlog'],
//			'log' => unserialize($x['log'])
//		);
//	}
	return $ret;
}
function save_db () {
	global $users;
//	global $mysql;
	global $locked;
	if ($locked) { return; }
	file_put_contents('cb_users.db',serialize($users));
//	mysql_query('TRUNCATE `users`');
//	foreach ($users as $nick => $data) {
//		$query  = 'INSERT INTO `users` ';
//
//		$query .= '(`id`,`nick`,`points`,';
//		$query .= '`ignore`,`admin`,`log`,';
//		$query .= '`verbose`,`vdedo`,`vlog`) ';
//
//		$query .= 'VALUES (NULL,\''.mysql_real_escape_string($nick).'\',';
//		$query .= '\''.mysql_real_escape_string($data['points']).'\',';
//		$query .= '\''.mysql_real_escape_string($data['ignore']).'\',';
//		$query .= '\''.mysql_real_escape_string($data['admin']).'\',';
//		$query .= '\''.mysql_real_escape_string(serialize($data['log'])).'\',';
//		$query .= '\''.mysql_real_escape_string($data['verbose']).'\',';
//		$query .= '\''.mysql_real_escape_string($data['vdedo']).'\',';
//		$query .= '\''.mysql_real_escape_string($data['vlog']).'\')';
//
//		mysql_query($query);
//	}
}
?>
