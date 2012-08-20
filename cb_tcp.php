#!/usr/bin/env php
<?php

chdir(__DIR__);
require("cb_functions.php");

$users = get_db();

$req = rtrim(fgets(STDIN, 512));
$args = explode(" ", $req);
$cmd = array_shift($args);

switch ($cmd) {
case "points":
	foreach ($args as $nick) {
		$points = user_get_points($nick);
		print "$nick $points\n";
	}
	print "OK\n";
	break;
default:
	print "lolz\n";
}
