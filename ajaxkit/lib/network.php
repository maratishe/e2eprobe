<?php
// library for various networking functions using php

// Wake on LAN
function nwakeonlan( $addr, $mac, $port = '7') { // port 7 seems to be default
	flush();
	$addr_byte = explode(':', $mac);
	$hw_addr = '';
	for ($a=0; $a <6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
	$msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);
	for ($a = 1; $a <= 16; $a++) $msg .= $hw_addr;
	// send it to the broadcast address using UDP
	// SQL_BROADCAST option isn't help!!
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ( $s == false) {
		//echo "Error creating socket!\n";
		//echo "Error code is '".socket_last_error($s)."' - " . socket_strerror(socket_last_error($s));
		return FALSE;
	}
	else {
		// setting a broadcast option to socket:
		$opt_ret = 0;
		$opt_ret = @socket_set_option( $s, 1, 6, TRUE);
		if($opt_ret <0) {
			//echo "setsockopt() failed, error: " . strerror($opt_ret) . "\n";
			return FALSE;
		}
		if( socket_sendto($s, $msg, strlen( $msg), 0, $addr, $port)) {
			//echo "Magic Packet sent successfully!";
			socket_close($s);
			return TRUE;
		}
		else {
			echo "Magic packet failed!";
			return FALSE;
		}
		
	}
	
}

class NTCPClient { 
	public $id;
	public $sock;
	public $lastime;
	public $inbuffer = '';
	public $outbuffer = '';
	public $buffersize;
	// hidden functions -- not part of the interface
	public function __construct() { }
	public function init( $rip = null, $rport = null, $id = null, $sock = null, $buffersize = 2048) {
		$this->id = $id ? $id : uniqid(); 
		if ( $sock) $this->sock = $sock;
		else { 	// create new socket
			$sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP) or die( "ERROR (NTCPClient): could not create a new socket.\n");
			@socket_set_nonblock( $sock); $status = false;
			$limit = 5; while ( $limit--) {
				$status = @socket_connect( $sock, $rip, $rport);
				if ( $status || socket_last_error() == SOCKET_EINPROGRESS) break;
				usleep( 10000);
			}
			if ( ! $status && socket_last_error() != SOCKET_EINPROGRESS) die( "ERROR (NTCPServer): could not connect to the new socket.\n");
			$this->sock = $sock;
		}
		$this->lastime = tsystem(); 
		$this->buffersize = $buffersize;
	}
	public function recv() {
		$buffer = '';
		$status = @socket_recv( $this->sock, $buffer, $this->buffersize, 0);
		//echo "buffer($buffer)\n";
		if ( $status <= 0) return null;
		$this->inbuffer .= substr( $buffer, 0, $status);
		return $this->parse();
	}
	public function parse() {
		$B =& $this->inbuffer;
		//echo "B:$B\n";
		if ( strpos( $B, 'FFFFF') !== 0) return;
		$count = '';
		for ( $pos = 5; $pos < 25 && ( $pos + 5 < strlen( $B)); $pos++) {
			if ( substr( $B, $pos, 5) == 'FFFFF') { $count = substr( $B, 5, $pos - 5); break; }
		}
		if ( ! strlen( $count)) return;	// nothing to parse yet
		if ( strlen( $B) < 5 * 2 + strlen( $count) + $count) return null;	// the data has not been collected yet
		$h = json2h( substr( $B, 5 * 2 + strlen( $count), $count), true, null, true);
		if ( strlen( $B) == 5 * 2 + strlen( $count) + $count) $B = '';
		$B = substr( $B, 5 * 2 + strlen( $count) + $count);
		return $h;
	}
	public function send( $h = null, $persist = false) { 	// will send bz64json( msg)
		$B =& $this->outbuffer;
		//echo "send: $B\n";
		if ( $h !== null && is_string( $h)) $h = tth( $h);
		if ( $h !== null) { $B = h2json( $h, true, null, null, true); $B = 'FFFFF' . strlen( $B) . 'FFFFF' . $B; }
		$status = @socket_write( $this->sock, $B, strlen( $B) > $this->buffersize ? $buffersize : strlen( $B));
		$B = substr( $B, $status);
		if ( $B && $persist) return $this->send( null, true);
		return $status;
	}
	public function isempty() { return $this->outbuffer ? false : true; }
	public function close() { @socket_close( $this->sock); }
}
class NTCPServer { 
	public $port;
	public $sock;
	public $socks = array();
	public $clients = array();
	public $buffersize = 2048;
	public $nonblock = true;
	public $usleep = 10;
	public $timeout;
	public $clientclass;
	public function __construct() {}
	public function start( $port, $nonblock = false, $usleep = 0, $timeout = 300, $clientclass = 'NTCPClient') {
		$this->port = $port;
		$this->nonblock = $nonblock;
		$this->clientclass = $clientclass;
		$this->usleep = $usleep;
		$this->timeout = $timeout;
		$this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP)  or die( "ERROR (NTCPServer): failed to creater new socket.\n");
		socket_set_option( $this->sock, SOL_SOCKET, SO_REUSEADDR, 1) or die( "ERROR (NTCPServer): socket_setopt() filed!\n");
		if ( $nonblock) socket_set_nonblock( $this->sock);
		$status = false; $limit = 5; 
		while ( $limit--) { 
			$status = @socket_bind( $this->sock, '0.0.0.0', $port);
			if ( $status) break;
			usleep( 10000);
		}
		if ( ! $status) die( "ERROR (NTCPServer): cound not bind the socket.\n");
		socket_listen( $this->sock, 20) or die( "ERROR (NTCPServer): could not start listening to the socket.\n");
		$this->socks = array( $this->sock);
		while ( 1) { if ( $this->timetoquit()) break; foreach ( $this->socks as $sock) { 
			if ( $sock == $this->sock) { // main socket, check for new connections
				$client = @socket_accept( $sock);
				if ( $client) {
					//echo "new client $client\n";
					if ( $this->nonblock) @socket_set_nonblock( $client);
					lpush( $this->socks, $client);
					$client = new $this->clientclass();
					$client->init( null, null, uniqid(), $client, $this->buffersize);
					lpush( $this->clients, $client);
					$this->newclient( $client);
				}
				
			}
			else { // existing socket
				$client = null;
				foreach ( $this->clients as $client2) if ( $client2->sock = $sock) $client = $client2;
				if ( tsystem() - $client->lastime > $this->timeout) { 
					$this->clientout( $client);
					@socket_close( $client->sock);
					$this->removeclient( $client);
					continue;
				}
				if ( $client) $this->eachloop( $client);
				if ( $client && strlen( $client->outbuffer)) { if ( $client->send()) $client->lastime = tsystem(); }
				if ( $client) { $h = $client->recv(); if ( $h) { $this->receive( $h, $client); $client->lastime = tsystem(); }}
			}
			//echo "loop sock: $sock\n";
		}; if ( $this->usleep) usleep( $this->usleep); }
		socket_close( $this->sock);
	}
	public function clientout( $client) {
		$L = array(); $L2 = array( $this->sock);
		foreach ( $this->clients as $client2) if ( $client2->sock != $client->sock) { lpush( $L, $client2); lpush( $L2, $client2->sock); } 
		$this->clients = $L;
		$this->socks = $L2;
	}
	// interface, should extend some of the functions, some may be left alone
	public function timetoquit() { return false; }
	public function newclient( $client) { }
	public function removeclient( $client) { }
	public function eachloop( $client) { }
	public function send( $h, $client) { $client->send( $h); }
	public function receive( $h, $client) { }
	
}

?>