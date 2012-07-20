<?php
// vim: noet

include 'cb_config.php';
include 'cb_functions.php';

const RPL_WELCOME	= '001';
const RPL_ISUPPORT	= '005';
const RPL_ENDOFMOTD	= '376';
const ERR_NOMOTD	= '422';
const ERR_NICKNAMEINUSE	= '433';

function send(/*@args*/) {
	global $socket;

	$args = func_get_args();
	$str = ircimplode($args);
	return fwrite($socket, $str);
}

function on_connect() {
	global $config;

	if (strlen(@$config["irc_pass"]))
		send("PASS", $config["irc_pass"]);

	send("USER", $config["user"], "1", "1", $config["gecos"]);
	send("NICK", $config["nick"]);
}

function on_register() {
	global $config;
	global $mynick;

	if (strlen(@$config["irc_mode"]))
		send("MODE", $mynick, $config["irc_mode"]);

	send("JOIN", implode(",", $config["channels"]));
}

function on_trigger($source, $target, $message) {
	global $config;
	global $users;

	$srcnick = $source->nick;
	$user = &$users[nicktolower($srcnick)];

	$args = explode(" ", $message);
	$cmd = strtolower(substr($args[0], 1));

	$bottom = false;
	$ignore = true;

	switch ($cmd) {
	case 'verbose':
		if ($user['verbose']) {
			$user['verbose'] = false;
			$user['vdedo'] = false;
			$user['vlog'] = false;
			send("NOTICE", $srcnick, "Point change notices disabled.");
		} else {
			$user['verbose'] = true;
			send("NOTICE", $srcnick, "Will notice you of every point change.");
		}
		break;
	case 'vdeductions':
		$user['verbose'] = true;
		if ($user['vlog']) {
			if ($user['vdedo']) {
				$user['vdedo'] = false;
				send("NOTICE", $srcnick, "Will notice you of every point change.");
			} else {
				$user['vdedo'] = true;
				send("NOTICE", $srcnick, "Will notice you only of negative point changes.");
			}
		} else {
			send("NOTICE", $srcnick, "vdeductions is incompatible with vlog (TODO)");
		}
		break;
	case 'vlog':
		$user['verbose'] = true;
		if ($user['vdedo'] == false) {
			if ($user['vlog']) {
				$user['vlog'] = false;
				send("NOTICE", $srcnick, "Will notice you of every point change.");
			} else {
				$user['vlog'] = true;
				send("NOTICE", $srcnick, "Will notice you of log entries relating to you. (TODO: WTF IS THIS)");
			}
		} else {
			send("NOTICE", $srcnick, "vdeductions is incompatible with vlog (TODO)");
		}
		break;
	case 'points':
		$who = $args[1] ? $args[1] : $srcnick;
		$pts = user_get_points($who);
		send("NOTICE", $srcnick, "$who has $pts points.");
		break;
	case 'bottom':
	case 'lamers':
		$bottom = true;
	case 'top':
		$top = gettop($bottom);
		foreach ($top as $who => $pts)
			send("NOTICE", $srcnick, "$who has $pts points.");
		break;
	case 'stats':
		$who = $args[1] ? $args[1] : $srcnick;
		$stats = user_get_stats($who);
		send("NOTICE", $srcnick, "$who's stats:");
		send("NOTICE", $srcnick, $stats);
		break;
	case 'unignore':
		$ignore = false;
	case 'ignore':
		if (user_is_admin($srcnick)) {
			$victim = $args[1];
			if (!isset($victim)) {
				send("NOTICE", $srcnick, "Missing user argument.");
				break;
			}
			user_set_ignored($victim, $ignore);
			if ($ignore)
				send("NOTICE", $srcnick, "$victim is now ignored.");
			else
				send("NOTICE", $srcnick, "$victim is not ignored anymore.");
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'lock':
		if (user_is_admin($srcnick)) {
			if ($locked) {
				$locked = false;
				send("NOTICE", $srcnick, "The database is now in read-write mode.");
			} else {
				$locked = true;
				send("NOTICE", $srcnick, "The database is now in read-only mode.");
			}
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'makeadmin':
		if (user_is_admin($srcnick)) {
			$victim = $args[1];
			if (!isset($victim)) {
				send("NOTICE", $srcnick, "Missing user argument.");
				break;
			}
			user_make_admin($victim);
			send("NOTICE", $srcnick, "$victim is now an admin.");
			send("NOTICE", $victim, "$srcnick just made you an admin.");
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'reload':
		if (user_is_admin($srcnick)) {
			$users = get_db();
			send("NOTICE", $srcnick, "Internal database reloaded according to the MySQL database.");
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'merge':
		if (user_is_admin($srcnick)) {
			$old_user = $args[1];
			$new_user = $args[2];
			if (!isset($old_user) || !isset($new_user)) {
				send("NOTICE", $srcnick, "Usage: .merge old_user new_user");
				break;
			}
			user_merge($old_user, $new_user);
			send("NOTICE", $srcnick, "Merged $old_user into $new_user");
			break;
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'chgpts':
		if (user_is_admin($srcnick)) {
			$victim = $args[1];
			$delta = $args[2];
			if (!isset($victim) or !isset($delta)) {
				send("NOTICE", $srcnick, "Missing user argument.");
				break;
			}
			user_adj_points($victim, $delta, "Administratively changed");
			send("NOTICE", $srcnick, "Points of $victim changed.");
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'reset':
		if (user_is_admin($srcnick)) {
			$victim = $args[1];
			if (!isset($victim)) {
				send("NOTICE", $srcnick, "Missing user argument.");
				break;
			}
			user_reset_points($victim);
			send("NOTICE", $srcnick, "User $victim reset.");
		} else {
			send("NOTICE", $srcnick, "Access denied.");
		}
		break;
	case 'whoami':
		$args[1] = $srcnick;
	case 'whois':
		$who = $args[1];
		if (!isset($who)) {
			send("NOTICE", $srcnick, "Missing user argument.");
			break;
		}

		$pts = user_get_points($who);
		$stats = user_get_stats($who);

		if     ($pts ==  1337)	$rank = 'Clueful 3l33t';
		elseif ($pts >=  1000)	$rank = 'Clueful Elite';
		elseif ($pts >=   500)	$rank = 'Super Clueful';
		elseif ($pts >=   200)	$rank = 'Extremely Clueful';
		elseif ($pts >=    50)	$rank = 'Very Clueful';
		elseif ($pts >=    10)	$rank = 'Clueful';
		elseif ($pts >=   -10)	$rank = 'Neutral';
		elseif ($pts >=  -500)	$rank = 'Needs Work';
		elseif ($pts >= -1000)	$rank = 'Not Clueful';
		elseif ($pts >= -1500)	$rank = 'Lamer';
		else			$rank = 'Idiot';

		send("NOTICE", $srcnick, "$who has $pts points and holds the rank of $rank.");
		send("NOTICE", $srcnick, "$who's stats: $stats");
		if (user_is_admin($who))
			send("NOTICE", $srcnick, "$who is a DaVinci administrator.");
		if (user_is_ignored($who))
			send("NOTICE", $srcnick, "$who is ignored by DaVinci.");
		break;
	}
}

function rate_message($nick, $message) {
	$smilies  = '((>|\})?(:|;|8)(-|\')?(\)|[Dd]|[Pp]|\(|[Oo]|[Xx]|\\|\/)';
	$smilies .= '|(\)|[Dd]|[Pp]|\(|[Oo]|[Xx]|\\|\/)(-|\')?(:|;|8)(>|\})?)';

	if (preg_match('/^'.$smilies.'$/i', $message)
	or preg_match('/^(um+|uh+m*|er+m*|ah+|ok)\.*$/i', $message)
	or preg_match('/^(brb|bbl|lol|rot?fl|heh|wt[fh]|haha?|lmf?ao|bbiab|grr+|hr?m+|gtg|wb)/i')
	or preg_match('!(http|ftp)s?://!')
	or preg_match('/^[^a-z]/i', $message)
	) {
		return;
	}

	if (preg_match('/(^| )[ru]( |$)/i', $message)) {
		user_adj_points($nick, -40, "Use of r, R, u, or U -40");
	}

	if (!preg_match('/[aeiouy]/i', $message)) {
		user_adj_points($nick, -30, "No vowels -30");
	}

	if (preg_match('/\b(cunt|fuck)\b/i', $message)) {
		user_adj_points($nick, -20, "Use of uncreative profanity -20");
	}

	if (preg_match('/^[^a-z]{8,}$/', $message)) {
		user_adj_points($nick, -20, "All caps -20");
	}

	if (preg_match('/(^| )lawl( |$)/', $message)) {
		user_adj_points($nick, -20, "Use of non-clueful variation of \"lol\" -20");
	}

	if (preg_match('/(^| )rawr( |$)/', $message)) {
		user_adj_points($nick, -20, "Use of non-clueful expression -20");
	}

	if (preg_match('/(^| )i( |$)/', $message)) {
		user_adj_points($nick, -5, "Lower-case personal pronoun -5");
	}

	// Shit, I have no idea what this does. Let's assume it works.
	if (preg_match('/^([^ ]+(:|,| -) .|[^a-z]).*(\?|\.(`|\'|")?|!|:|'.$smilies.')( '.$smilies.')?$/',$message)) {
		user_adj_points($nick, +2, "Clueful sentence +2");
	} elseif (preg_match('/^([^ ]+(:|,| -) .|[^a-z]).*$/', $message)) {
		user_adj_points($nick, +1, "Normal sentence +1");
	} else {
		user_adj_points($nick, -1, "Abnormal sentence -1");
	}
}

//mysqlconn($config['mysqluser'],$config['mysqlpass'],$config['mysqlhost'],$config['mysqlport'],$config['mysqldb']);
$locked = false;
$users = get_db();

if (strpos($config["server"], "://") === false)
	$uri = "tcp://{$config["server"]}:{$config["port"]}";
else
	$uri = $config["server"];

$socket = stream_socket_client($uri, $errno, $errstr, 30);
if (!$socket) {
	echo "$errstr ($errno)\n";
	exit();
}

$mynick = $config["nick"];
$nickctr = 0;

on_connect();

while (!feof($socket)) {
	$line = fgets($socket);
	if (!strlen($line))
		continue;
	$params = ircexplode($line);
	if ($params[0][0] == ":")
		$prefix = array_shift($params);
	else
		$prefix = null;
	$source = prefixparse($prefix);
	$srcnick = $source->nick;
	$cmd = strtoupper($params[0]);

	switch ($cmd) {
	case RPL_WELCOME:
		$mynick = $params[1];
		break;
	case RPL_ENDOFMOTD:
	case ERR_NOMOTD:
		on_register();
		break;
	case ERR_NICKNAMEINUSE:
		$newnick = $config["nick"] . ++$nick_ctr;
		send("NICK", $newnick);
		break;
	case "INVITE":
		$target = $params[1];
		$channel = $params[2];
		send("JOIN", $channel);
		send("PRIVMSG", $channel, "\001ACTION waves at $srcnick.\001");
		break;
	case "PING":
		send("PONG", $params[1]);
		break;
	case "PRIVMSG":
		$target = $params[1];
		$message = $params[2];
		if ($message == "\001VERSION\001") {
			send("NOTICE", $srcnick, "\001VERSION DaVinci by Cluenet\001");
		} elseif ($message[0] == $config["trigger"]) {
			on_trigger($source, $target, $message);
		} elseif (ischannel($target)) {
			rate_message($srcnick, $message);
		} else {
			send("NOTICE", $srcnick, "?");
		}
	}
}
?>
