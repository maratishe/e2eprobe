<?php

function procfindlib( $name) { 	// will look either in /usr/local, /APPS or /APPS/research
	$paths = ttl( '/usr/local,/APPS,/APPS/research');
	foreach ( $paths as $path) {
		if ( is_dir( "$path/$name")) return "$path/$name";
	}
	die( "Did not find library [$name] in any of the paths [" . ltt( $paths) . "]\n");
}

// will work only on linux
function procat( $proc, $minutesfromnow = 0) { 
	$time = 'now'; if ( $minutesfromnow) $time .= " + $minutesfromnow minutes";
	$out = popen( "at $time >/dev/null 2>/dev/null 3>/dev/null", 'w');
	fwrite( $out, $proc);
	pclose( $out);
}
function procatwatch( $c, $procidstring, $statusfile, $e = null, $sleep = 2, $timeout = 300) { // c should know/use statusfile
	$startime = tsystem(); if ( ! $e) $e = echoeinit();
	procat( $c); $h = tth( 'progress=?');
	while ( tsystem() - $startime < $timeout) {
		sleep( $sleep);
		if ( ! procpid( $procidstring)) break;	// process finished
		$h2 = jsonload( $statusfile, true, true); if ( ! $h2 && ! isset( $h2[ 'progress'])) continue;
		$h = hm( $h, $h2); echoe( $e, ' ' . $h[ 'progress']);
	}
	echoe( $e, '');	// erase all previous echos
}


function procores() { 	// count the number of cores on this machine
	$file = file( '/proc/cpuinfo');
	$count = 0; foreach ($file as $line) if ( strpos( $line, 'processor') === 0) $count++;
	return $count;
}

// ghostscript command line -- should have gswin32c in PATH
function procgspdf2png( $pdf, $png = '', $r = 300) { // returns TRUE | failed command line    -- judges failure by absence of png file
	if ( ! $png) { $L = ttl( $pdf, '.'); lpop( $L); $png = ltt( $L, '.') . '.png'; }
	if ( is_file( $png)) `rm -Rf $png`;
	$c = "gswin32c -q -sDEVICE=png16m -r$r -sOutputFile=$png -dBATCH -dNOPAUSE $pdf"; echopipee( $c);
	if ( ! is_file( $png)) return $c;
	return true;
}

// ffmpeg command line -- ffmpeg should be in PATH
function procffmpeg( $in = '%06d.png', $out = 'temp.avi', $rate = null) { // returns TRUE | failed command line
	if ( is_file( $out)) `rm -Rf $out`;
	$c = "ffmpeg"; 
	if ( $rate) $c .= " -r $rate";
	$c .= " -i $in $out";
	echopipee( $c); 
	if ( @filesize( $out) == 0) { `rm -Rf $out`; return $c; }	// present but empty file 
	if ( ! is_file( $out)) return $c;
	echopipee( "chmod -R 777 $out");
	return true;
}

// pdftk command line -- pdftk should be in PATH
function procpdftk( $in = 'tempdf*', $out = 'temp.pdf', $donotremove = false) { // returns TRUE | failed command line
	if ( is_file( $out)) `rm -Rf $out`;
	$c = "pdftk $in cat output $out"; echopipee( $c);
	if ( ! is_file( $out)) return $c;
	echopipee( "chmod -R 777 $out");
	if ( ! $donotremove) `rm -Rf $in`;
	return true;
}


function procdf() { 	// runs df -h in terminal and returns hash { mountpoint: { use(string), avail(string), used(string), size(string)}, ...}
	$in = popen( 'df -h', 'r');
	$ks = ttl( trim( fgets( $in)), ' '); lpop( $ks); lpop( $ks); lpop( $ks); lpush( $ks, 'Use'); // Mounted on
	for ( $i = 0; $i < count( $ks); $i++) $ks[ $i] = strtolower( $ks[ $i]);	// lower caps in all keys
	$D = array();
	while ( $in && ! feof( $in)) {
		$line = trim( fgets( $in)); if ( ! $line) continue;
		$vs = ttl( $line, ' '); if ( count( $vs) < 4) continue;	// probably 2-line entry 
		$mount = lpop( $vs); $h = array();
		$ks2 = $ks; while ( count( $ks2) > 1) $h[ lpop( $ks2)] = lpop( $vs);
		$D[ $mount] = $h;
	}
	pclose( $in);
	return $D;
}
function procdu( $dir = null) { 	// runs du -s 
	$cwd = getcwd(); if ( $dir) chdir( $dir); $size = null;
	$in = popen( 'du -s', 'r'); 
	while ( $in && ! feof( $in)) { 
		$line = trim( fgets( $in)); if ( ! $line) continue;
		$size = lshift( ttl( $line, ' '));
	}
	pclose( $in);
	return $size;
}
function procdfuse( $mount) { 	// parser for procdf output, will return int of use on that mount
	$h = procdf();
	if ( ! isset( $h[ $mount])) return null;
	return ( int)( $h[ $mount][ 'use']);
}
function procdfavail( $mount) { 	// will parse 'avail', will return available size in Mb
	$h = procdf();
	if ( ! isset( $h[ $mount])) return null;
	$v = $h[ $mount][ 'avail'];
	if ( strpos( $v, 'G')) return 1000 * ( int)( $v);
	if ( strpos( $v, 'M')) return ( int)$v;
	if ( strpos( $v, 'K') || strpos( $line, 'k')) return 0.001 * ( int)( $v);
}

// no pipe, just echo with erasure on each update, monitors the time as well
function echoeinit() { // returns handler { last: ( string length), firstime, lastime}
	$h = array(); $h[ 'last'] = 0;
	$h[ 'firstime'] = tsystem(); 
	$h[ 'lastime'] = tsystem();
	return $h;
}
function echoe( &$h, $msg) { // if h[ 'last'] set, will erase old info first, then post current
	if ( $h[ 'last']) for ( $i = 0; $i < $h[ 'last']; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces
	echo $msg; $h[ 'last'] = mb_strlen( $msg);
	$h[ 'lastime'] = tsystem();
}
function echoetime( &$h) { extract( $h); return tshinterval( $firstime, $lastime); }

function procpid( $name, $notpid = null) {  // returns pid or FALSE, if not running
	$in = popen( 'ps ax', 'r');
	$found = false;
	$pid = null;
	while( ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( strpos( $line, $name) !== FALSE) { 
			$split = explode( ' ', $line);
			$pid = trim( $split[ 0]);
			if ( $notpid && $notpid == $pid) { $pid = null; continue; }
			$found = true;
			break;
		}
	}
	pclose( $in);
	if ( $found && is_numeric( $pid)) return $pid;
	return false;
}
function procline( $name) {
	$in = popen( 'ps ax', 'r');
	$found = false;
	$pid = null;
	$pline = '';
	while( ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( strpos( $line, $name) !== FALSE) { 
			$pline = $line;
			break;
		}
	}
	pclose( $in);
	if ( $pline) return $pline;
	return false;
}
function prockill( $pid, $signal = NULL) { // signal 9 is deadly
	if ( ! $pid) return;	 // ignore, if pid is not set
	if ( $signal) `kill -$signal $pid > /dev/null 2> /dev/null`;
	else `kill $pid > /dev/null 2> /dev/null`;
}
function prockillandmakesure( $name, $limit = 20, $signal = NULL) { // signal 9 is deadly
	$rounds = 0;
	while ( $rounds < 20 && $pid = procpid( $name)) { $rounds++; prockill( $pid, $signal); }
	return $rounds;
}
function procispid( $pid) {  // returns false|true, true if pid still exists
	$in = popen( "ps ax", 'r');
	$found = false;
	while ( $in && ! feof( $in)) {
		$pid2 = array_shift( ttl( trim( fgets( $in)), ' '));
		if ( $pid - $pid2 === 0) { pclose( $in); return true; }
	}
	pclose( $in);
	return false;
}
function procpipe( $command, $second = false, $third = false) {	// return output of command
	$c = "$command";
	if ( $second) $c .= ' 2>&1'; else $c .= ' 2>/dev/null';
	if ( $third) $c .= ' 3>&1'; else $c .= ' 3> /dev/null';
	$in = popen( $c, 'r');
	$lines = array();
	while ( $in && ! feof( $in)) array_push( $lines, trim( fgets( $in)));
	return $lines;
}
// different from pipe by directing output to a file, monitoring executable and getting updates from file
function procpipe2( $command, $tempfile, $second = false, $third = false, $echo = false, $pname = '', $usleep = 100000) {
	$c = "$command > $tempfile";
	$tempfile2 = $tempfile . '2';
	if ( $second) $c .= ' 2>&1'; else $c .= ' 2>/dev/null';
	if ( $third) $c .= ' 3>&1'; else $c .= ' 3> /dev/null';
	`$c &`;
	if ( ! $pname) $pname = array_shift( ttl( $command, ' '));
	$pid = procpid( $pname); if ( ! $pid) $pid = -1;
	$lines = array(); $linepos = 0; $lastround = 3;
	while( procispid( $pid) || $lastround) {
		if ( ! procispid( $pid)) $lastround--;
		// get raw lines
		`rm -Rf $tempfile2`;
		`cp $tempfile $tempfile2`;
		$lines2 = array(); $in = fopen( $tempfile2, 'r'); while ( $in && ! feof( $in)) array_push( $lines2, fgets( $in)); fclose( $in);
		`rm -Rf $tempfile2`;
		//echo "found [" . count( $lines2) . "]\n";
		// convert to actual lines by escaping ^m symbol as well
		$cleans = array( 0, 13);
		foreach ( $cleans as $clean) {
			$lines3 = array(); $next = false;
			foreach ( $lines2 as $line) {
				//echo "line length[" . strlen( $line) . "]\n";
				//$lines4 = ttlm( $line, chr( $clean));
				$lines4 = ttl( $line, chr( $clean));
				//echo "line split[" . count( $lines4) . "]\n";
				foreach ( $lines4 as $line2) array_push( $lines3, trim( $line2));
			}
			$lines2 = $lines3;
		}
		for ( $i = 0; $i < $linepos && count( $lines2); $i++) array_shift( $lines2);
		$linepos += count( $lines2);
		foreach ( $lines2 as $line) { array_push( $lines, $line); if ( $echo) echo "pid[$pid][$linepos] $line\n"; }
		usleep( $usleep);
	}
	return $lines;
}
function procwho() { // returns the name of the user
	$in = popen( 'whoami', 'r');
	if ( ! $in) die( 'fialed to know myself');
	$user = trim( fgets( $in));
	fclose( $in);
	return $user;
}
function procwhich( $command) { // returns the path to the command
	$in = popen( 'which $command', 'r');
	$path = ''; if ( $in && ! feof( $in)) $path = trim( fgets( $in));
	fclose( $in);
	return $path;
}
// pipe and echo
function echopipe( $command, $tag = null, $chunksize = 1024) { // returns array( time it took (s), lastline)
	$in = popen( "$command 2>&1 3>&1", 'r');
	$start = tsystem();
	$line = ''; $lastline = '';
	echo $tag ? $tag : '';
	while ( $in && ! feof( $in)) { 
		$stuff = fgets( $in, $chunksize + 1); 
		echo $stuff; $line .= $stuff;
		$tail = substr( $stuff, mb_strlen( $stuff) - 1, 1);
		if ( $tail == "\n") { echo  $tag ? $tag : ''; $lastline = $line; $line = ''; } 
	}
	@fclose( $in);
	return array( tsystem() - $start, $lastline);
}
// with erase -- erases each previous line when outputing the next one (actually, does it symbol by symbol)
function echopipee( $command, $limit = null, $debug = null, $alerts = null, $logfile = null, $newlog = true) {	// returns array( time it took (s), lastline)
	if ( $alerts && is_string( $alerts)) $alerts = ttl( $alerts);
	$start = tsystem();
	$in = popen( "$command 2>&1 3>&1", 'r');
	$count = 0; $line = ''; $lastline = '';
	if ( $debug) fwrite( $debug, "opening command [$command]\n");
	if ( $logfile && $newlog) { $out = fopen( $logfile, 'w'); fclose( $out); }	// empty the log file, only if newlog = true
	if ( $logfile && ! $newlog) { $out = fopen( $logfile, 'a'); fwrite( $out, "NEW ECHOPIPEE for c[$command]\n"); fclose( $out); }
	$endofline = false;
	while ( $in && ! feof( $in)) {
		$stuff = fgetc( $in);
		$line .= $stuff == chr( 13) ? "\n" : $stuff;
		if ( ( ! $limit || ( $limit && mb_strlen( $line) < $limit)) && $stuff != "\n") {
			if ( $endofline) {
				// end of line or chunk (with limit), revert the line back to zero
				if ( $logfile) { $out = fopen( $logfile, 'a'); fwrite( $out, $line); fclose( $out); }
				if ( $debug) fwrite( $debug, $line);
				// hide previous output
				for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces 
				$count = 0; $lastline = $line; $line = ''; // back to zero
				// check for any alert words in output
				if ( $alerts) foreach ( $alerts as $alert) { // if alert word is found, echo the full line and do not erase it
					if ( strpos( strtolower( $line), strtolower( $alert)) != false) { echo "   $line   "; break; }
				}
				$endofline = false;
			}
			echo $stuff; 
			if ( $stuff != chr( 8)) $count++;
			else $count--; if ( $count < 0) $count = 0;
			continue; 
		}
		$endofline = true;
	}
	for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo ' '; echo chr( 8); } // erase current output
	pclose( $in);
	if ( $logfile) { $out = fopen( $logfile, 'a'); fwrite( $out, "\n\n\n\n\n"); fclose( $out); }
	return array( tsystem() - $start, $lastline);
}
function echopipeo( $command) {	// returns array( time it took (s), lastline)
	$start = tsystem();
	$in = popen( "$command 2>&1 3>&1", 'r');
	$endofline = false; $count = 0; $line = ''; $lastline = '';
	while ( $in && ! feof( $in)) {
		$stuff = fgetc( $in);
		$line .= $stuff == chr( 13) ? "\n" : $stuff;
		if ( $endofline) { // none-eol-char but endofline is marked
			for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces 
			$count = 0; $lastline = $line; $line = ''; // back to zero
			$endofline = false;
		}
		while ( $in && ! feof( $in)) {
			$stuff = fgetc( $in);
			$line .= $stuff == chr( 13) ? "\n" : $stuff;
			if ( $stuff == "\n") break;	// end of line break the inner loop
			echo $stuff; 
			if ( $stuff != chr( 8)) $count++;
			else $count--; if ( $count < 0) $count = 0;
		}
		$endofline = true;
	}
	pclose( $in);
	return array( tsystem() - $start, trim( $lastline));
}


?>