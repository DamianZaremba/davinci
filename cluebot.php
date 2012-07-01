<?PHP
include 'cb_config.php';
include 'cb_functions.php';

const ERR_NOMOTD	= '422';
const RPL_ENDOFMOTD	= '376';

function send(/*@args*/) {
	$args = func_get_args();
	$str = ircimplode($args);

	global $socket;
	return fwrite($socket, $str);
}

//mysqlconn($config['mysqluser'],$config['mysqlpass'],$config['mysqlhost'],$config['mysqlport'],$config['mysqldb']);
$locked = false;
$users = get_db();

$socket = stream_socket_client('tcp://'.$config['server'].':'.$config['port'],$errno,$errstr,30);

if (!$socket) {
	echo "$errstr ($errno)\n";
} else {
	fwrite($socket,'USER '.$config['user'].' "1" "1" :'.$config['gecos']."\n");
	fwrite($socket,'NICK '.$config['nick']."\n");

	while (!feof($socket)) {
		$line = fgets($socket);
		$tmp = ircexplode($line);
		if ($tmp[0][0] == ":") {
			$prefix = array_shift($tmp);
			list($nick, $user, $host) = prefixparse($prefix);
		} else {
			$prefix = $nick = $user = $host = null;
		}
		$cmd = strtoupper($tmp[0]);

		switch ($cmd) {
		case "PING":
			send("PONG", $tmp[1]);
			break;
		case RPL_ENDOFMOTD:
		case ERR_NOMOTD:
			send("JOIN", implode(",", $config["channels"]));
			break;
		case "PRIVMSG":
			$source = $nick;
			$target = $tmp[1];
			$message = $tmp[2];
			if (!ischannel($target))
				break;
			if ($message[0] == $config["trigger"]) {
				$tmp0 = explode(' ',$message);
				$cmd = strtolower(substr($tmp0[0],1));
				$bottom = false;
				$ignore = true;

				$u = &$users[$source];

				switch ($cmd) {
					case 'verbose':
						if ($u['verbose']) {
							$u['verbose'] = false;
							$u['vdedo'] = false;
							$u['vlog'] = false;
							send("NOTICE", $source, "Point change notices disabled.");
						} else {
							$u['verbose'] = true;
							send("NOTICE", $source, "Will notice you of every point change.");
						}
						break;
					case 'vdeductions':
						$u['verbose'] = true;
						if ($u['vlog']) {
							if ($u['vdedo']) {
								$u['vdedo'] = false;
								send("NOTICE", $source, "Will notice you of every point change.");
							} else {
								$u['vdedo'] = true;
								send("NOTICE", $source, "Will notice you only of negative point changes.");
							}
						} else {
							send("NOTICE", $source, "vdeductions is incompatible with vlog (TODO)");
						}
						break;
					case 'vlog':
						$u['verbose'] = true;
						if ($u['vdedo'] == false) {
							if ($u['vlog']) {
								$u['vlog'] = false;
								send("NOTICE", $source, "Will notice you of every point change.");
							} else {
								$u['vlog'] = true;
								send("NOTICE", $source, "Will notice you of log entries relating to you. (TODO: WTF IS THIS)");
							}
						} else {
							send("NOTICE", $source, "vdeductions is incompatible with vlog (TODO)");
						}
						break;
					case 'points':
						$who = $tmp0[1] ? $tmp0[1] : $source;
						$pts = getpts($who);
						send("NOTICE", $source, "$who has $pts points.");
						break;
					case 'bottom':
					case 'lamers':
						$bottom = true;
					case 'top':
						$top = gettop($bottom);
						foreach ($top as $who => $pts)
							send("NOTICE", $source, "$who has $pts points.");
						break;
					case 'stats':
						$who = $tmp0[1] ? $tmp0[1] : $source;
						$stats = getstats($who);
						send("NOTICE", $source, "$who's stats:");
						send("NOTICE", $source, $stats);
						break;
					case 'unignore':
						$ignore = false;
					case 'ignore':
						if (isadmin($source)) {
							setignore($victim, $ignore);
							if ($ignore)
								send("NOTICE", $source, "$victim is now ignored.");
							else
								send("NOTICE", $source, "$victim is not ignored anymore.");
						} else {
							send("NOTICE", $source, "Access denied.");
						}
						break;
					case 'lock':
						if (isadmin($source)) {
							if ($locked)
								$locked = false;
								send("NOTICE", $source, "The database is now in read-write mode.");
							} else {
								$locked = true;
								send("NOTICE", $source, "The database is now in read-only mode.");
							}
						} else {
							send("NOTICE", $source, "Access denied.");
						}
						break;
					case 'reload':
						if (isadmin($source)) {
							$users = get_db();
							send("NOTICE", $source, "Internal database reloaded according to the MySQL database.");
						} else {
							send("NOTICE", $source, "Access denied.");
						}
						break;
					case 'chgpts':
						if (isadmin($source)) {
							$victim = $tmp0[1];
							$points = $tmp0[2];
							loguser($victim, "Administratively changed");
							chgpts($victim, $points);
							send("NOTICE", $source, "Points of $victim changed.");
						} else {
							send("NOTICE", $source, "Access denied.");
						}
						break;
					case 'reset':
						if (isadmin($source)) {
							$victim = $tmp0[1];
							unset($users[$victim]);
							send("NOTICE", $source, "User $victim reset.");
						} else {
							send("NOTICE", $source, "Access denied.");
						}
						break;
					case 'whoami':
						$tmp0[1] = $source;
					case 'whois':
						$who = $tmp0[1];
						$pts = getpts($who);
						$stats = getstats($who);

						if ($pts < -1500)	$rating = 'Lamer';
						elseif ($pts < -1000)	$rating = 'Not clueful';
						elseif ($pts < -500)	$rating = 'Needs alot of work';
						elseif ($pts < -10)	$rating = 'Needs work';
						elseif ($pts < 10)	$rating = 'Neutral';
						elseif ($pts < 30)	$rating = 'Clueful';
						elseif ($pts < 60)	$rating = 'Very clueful';
						elseif ($pts < 100)	$rating = 'Extremely clueful';
						elseif ($pts < 500)	$rating = 'Super clueful';
						else			$rating = 'Clueful elite';

						send("NOTICE", $source, "$who has $pts points and holds the rank of $rating.");
						send("NOTICE", $source, "$who's stats: $stats");
						if (isadmin($who))
							send("NOTICE", $source, "$who is a DaVinci administrator.");
						if ($users[$who]['ignore'])
							send("NOTICE", $source, "$who is ignored by DaVinci.");
						break;
				}
			} else {
				$tmppts = 0;
				$smilies = '(>|\})?(:|;|8)(-|\')?(\)|[Dd]|[Pp]|\(|[Oo]|[Xx]|\\|\/)';
				if ((!preg_match('/^'.$smilies.'$/i',$message))
					and (!preg_match('/^(uh+|um+|uhm+|er+|ok|ah+|er+m+)(\.+)?$/i',$message))
					and (!preg_match('/^[^A-Za-z].*$/',$message))
					and (!preg_match('/^s(.).+\1.+\1i?g?$/',$message))
					and (!preg_match('/(brb|bbl|lol|rofl|heh|wt[hf]|hah|lmao|bbiab|grr|hmm|hrm|http:|grep|\||vtun|ifconfig|\$|mm|gtg|wb)/i',$message))
				) {
					if (preg_match('/^([^ ]+(:|,| -) .|[^a-z]).*(\?|\.|!|:|'.$smilies.')( '.$smilies.')?$/',$message)) {
						loguser($source,'Normal sentence +1');
						$tmppts++;
					} else {
						loguser($source,'Abnormal sentence -1');
						$tmppts--;
					}
					if (preg_match('/^[^a-z]{8,}$/',$message)) {
						loguser($source,'All caps -20');
						$tmppts -= 20;
					}
					if (preg_match('/^[^aeiouy]*$/i',$message)) {
						loguser($source,'No vowels -30');
						$tmppts -= 30;
					}
					if (preg_match('/(^| )[rRuU]( |$)/',$message)) {
						loguser($source,'Use of r, R, u, or U -40');
						$tmppts -= 40;
					}
					chgpts($source,$tmppts);
				}
			}
		}
	}
}
?>
