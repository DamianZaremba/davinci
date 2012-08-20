<?php
	include 'cb_config.php';
	include 'cb_functions.php';

	$users = get_db();
	
	$serversock = stream_socket_server($config['pointsserver'], $errno, $errstr);
	$clients = array();

	while(true) {
		$read = array_merge(array($serversock), $clients);
		$waiting = stream_select($read, $write = NULL, $except = NULL, NULL);

		if($waiting !== FALSE) {
			foreach($read as $key => $sock) {
				// Disconnected clients
				if((!is_resource($sock) or feof($sock)) and in_array($sock, $clients, true)) {
					fclose($sock);
					unset($read[$key]);

					foreach($clients as $k => $client)
						if($sock === $client)
							unset($clients[$k]);
				}

				// New clients
				if($sock === $serversock) {
					$clients[] = stream_socket_accept($sock);

				// Client's we need to get the data from
				} elseif(in_array($sock, $clients, true)) {
					$line = str_replace(array("\r", "\n"), '', fgets($sock, 4096));
					$parts = explode(' ', $line);
					$command = $parts[0];

					switch(strtolower($command)) {
						case 'dump':
							if(isset($parts[1]))
								api_return_entries($sock, 'full', $parts[1]);
							else
								api_return_entries($sock, 'full');
							break;
						case 'points':
							if(isset($parts[1]))
								api_return_entries($sock, 'points', $parts[1]);
							else
								api_return_entries($sock, 'points');
							break;
						case 'shortpoints':
							if(isset($parts[1]))
								api_return_entries($sock, 'shortpoints', $parts[1]);
							else
								fwrite($sock, api_handle_print(NULL, 'usage'));
							break;
						case 'dumpheader':
							fwrite($sock, api_handle_print(NULL, 'header'));
							break;
						default:
							fwrite($sock, api_handle_print(NULL, 'usage'));
							break;
					}

					// Disconnect the client
					foreach($clients as $k => $client)
						if($sock === $client)
							unset($clients[$k]);

					fclose($sock);
					unset($read[$key]);
				}
			}
		}
	}
?>