<?php
function makenv() {	// in web mode, htdocs should be in /web
	global $_SERVER, $prefix, $_SESSION;
	$cdir = getcwd(); @chdir( $prefix); $prefix = getcwd(); chdir( $cdir);
	//$s = explode( '/', $prefix); array_pop( $s); $prefix = implode( '/', $s); // remove / at the end of prefix
	$out = array();
	$addr = '';
	if ( isset( $_SERVER[ 'SERVER_NAME'])) $addr = $_SERVER[ 'SERVER_NAME'];
	if ( isset( $_SERVER[ 'DOCUMENT_ROOT'])) $root = $_SERVER[ 'DOCUMENT_ROOT'];
	if ( ! $addr && is_file( '/sbin/ifconfig')) { 	// probably command line, try to get own IP address from ipconfig
		$in = popen( '/sbin/ifconfig', 'r');
		$L = array(); while ( $in && ! feof( $in)) {
			$line = trim( fgets( $in)); if ( ! $line) continue;
			if ( strpos( $line, 'inet addr') !== 0) continue;
			$L2 = explode( 'inet addr:', $line);
			$L3 = array_pop( $L2);
			$L4 = explode( ' ', $L3);
			$L5 = trim( array_shift( $L4));
			array_push( $L, $L5);
		}
		pclose( $in); $addr = implode( ',', $L);
	}
	if ( ! $root) $root = '/web';
	// find $root depending on web space versus CLI environment
	$split = explode( "$root/", $cdir); $aname = '';
	if ( count( $split) == 2) $aname = @array_shift( explode( '/', $split[ 1]));
	else $aname = '';
	//else { $aname = ''; $root = $prefix ? $prefix : $cdir; } // CLI
	// application session
	$session = array();
	if ( $aname && isset( $_SESSION) && isset( $_SESSION[ $aname])) { // check session, detect ssid changes
		$session = $_SESSION[ $aname];
		$ssid = session_id();
		if ( ! isset( $session[ 'ssid'])) $session[ 'ssid'] = $ssid;
		if ( $session[ 'ssid'] != $ssid) { $session[ 'oldssid'] = $session[ 'ssid']; $session[ 'ssid'] = $ssid; }
	}
	// return result
	$L2 = explode( ',', $addr);
	$out = array(
		'SYSTYPE' => ( isset( $_SERVER) && isset( $_SERVER[ 'SYSTEMDRIVE'])) ? 'cygwin' : 'linux',
		'CDIR' => $cdir,
		'BIP' => $addr ? array_shift( $L2) : '',
		'BIPS' => $addr ? explode( ',', $addr) : array(),
		'SBDIR' => $root,	// server base dir, htdocs for web, ajaxkit root for CLI
		'ABDIR' => $prefix,	// ajaxkit base directory
		'BDIR' => "$root" . ( $aname ? '/' . $aname : ''), // base app dir
		'BURL' => ( $addr ? 'http://' . $addr . ( $aname ? "/$aname" : '') : ''),
		'ABURL' => '', 	// add later
		'ANAME' => $aname ? $aname: 'root',
		'SNAME' => ( isset( $_SERVER) && isset( $_SERVER[ 'SCRIPT_NAME'])) ? $_SERVER[ 'SCRIPT_NAME'] : '?', 
		'DBNAME' => $aname,
		// application session
		'ASESSION' => $session,
		// client (browser) specific
		'RIP' => isset( $_SERVER[ 'REMOTE_ADDR']) ? $_SERVER[ 'REMOTE_ADDR'] : '',
		'RPORT' => isset( $_SERVER[ 'REMOTE_PORT']) ? $_SERVER[ 'REMOTE_PORT'] : '',
		'RAGENT' => isset( $_SERVER[ 'HTTP_USER_AGENT']) ? $_SERVER[ 'HTTP_USER_AGENT'] : ''
	);
	$out[ 'ABURL'] = ( $addr ? "http://$addr" . str_replace( "$root", '', $out[ 'ABDIR']) : '');
	return $out;
}
function jqload( $justdumpjs = false, $mode = 'full') {
	global $BURL, $ABURL, $ABDIR, $JQ, $JQMODE;
	$files = array(); 
	foreach ( $JQ[ 'libs'] as $file) lpush( $files, "jquery.$file" . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $mode == 'full' || $mode == 'short') foreach ( $JQ[ 'basics'] as $file) lpush( $files, $file . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $mode == 'full') foreach ( $JQ[ 'advanced'] as $file) lpush( $files, $file . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $JQMODE == 'debug') {	// separate script tag per file
		foreach ( $files as $file) echo $justdumpjs ? implode( '', file( "$ABDIR/jq/$file")) . "\n" : '<script src="' . $ABURL . "/jq/$file" . '?' . mr( 5) . '"></script>' . "\n";
	}
	if ( $JQMODE == 'source') {	// script type per file with source instead of url pointer
		foreach ( $files as $file) echo ( $justdumpjs ? '' :  "<script>\n") . implode( '', file( "$ABDIR/jq/$file")) . "\n" . ( $justdumpjs ? '' : "</script>\n");
	}
	if ( $JQMODE == 'sourceone') {	// all source inside one tag (no tag if $justdumpjs is true
		if ( ! $justdumpjs) echo "<script>\n\n";
		foreach ( $files as $file) echo implode( '', file( "$ABDIR/jq/$file")) . "\n\n";
		if ( ! $justdumpjs) echo "</script>\n";
	}
	// to fix canvas in IE
	if ( ! $justdumpjs) echo '<!--[if IE]><script type="text/javascript" src="' . $ABURL . '/jq/jquery.excanvas.js"></script><![endif]-->' . "\n";
}
function jqparse( $path, $all = false) {	// minimizes JS and echoes the rest
	$in = fopen( $path, 'r');
	$put = false;
	if ( $all) $put = $all;
	while ( ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( ! $put && strpos( $line, '(function($') !== false) { $put = true; continue; }
		if ( ! $all && strpos( $line, 'jQuery)') !== false) break;	// end of file
		if ( ! strlen( $line) || strpos( $line, '//') === 0) continue;
		if ( strpos( $line, '/*') === 0) {	// multiline comment */
			$limit = 100000;
			while ( $limit--) { 
				// /*
				if ( strpos( $line,  '*/') !== FALSE) break;
				$line = trim( fgets( $in));
			}
			continue;
		}
		if ( $put) echo $line . "\n";
	}
	fclose( $in);
}
function flog( $msg, $echo = true, $timestamp = false, $uselock = false, $path = '') {	// writes the message to file log, no end of line
	global $BDIR, $FLOG;
	if ( is_array( $msg)) $msg = htt( $msg);
	if ( ! $FLOG) $FLOG = $path;
	if ( ! $FLOG) $FLOG = "$BDIR/log.txt"; 
	$out = fopen( $FLOG, 'a');
	if ( $timestamp) fwrite( $out, "time=" . tsystemstamp() . ',');
	fwrite( $out, "$msg\n");
	fclose( $out);
	if ( $echo) echo "$msg\n";
}
function checksession( $usedb = false) { // db calls dbsession()
	global $ASESSION, $DB;
	if ( ! isset( $ASESSION[ 'oldssid'])) return;	// nothing wrong
	$oldssid = $ASESSION[ 'oldssid'];
	$ssid = $ASESSION[ 'ssid'];
	if ( $usedb) dbsession( 'reset', "newssid=$ssid", $oldssid);
	unset( $ASESSION[ 'oldssid']);
}
// will save in BURL/log.base64( uid)    as base64( bzip2( json))  -- no clear from extension, but should remember the format
// $msg can be either string ( will tth())  or hash
// will add     (1) time   (2) uid   (3) took (current time - REQTIME)   (4) reply=JO (if not empty/NULL)
function mylog( $msg, $ouid = null, $noreply = false, $ofile = null) {
	global $uid, $BDIR, $JO, $REQTIME, $_SERVER, $ASLOCKSTATS;
	if ( $ouid === null) $ouid = $uid; 
	if ( $ouid === null) $ouid = 'nobody';
	$h = array();
	$h[ 'time'] = tsystemstamp();
	$h[ 'uid'] = $ouid;
	$h[ 'took'] = tsystem() - $REQTIME;
	$h[ 'script'] = lpop( ttl( $_SERVER[ 'SCRIPT_FILENAME'], '/'));
	$h = hm( $h, is_string( $msg) ? tth( $msg) : $msg);	// merge, but keep time and uid in proper order
	if ( $JO && ! $noreply) $h[ 'reply'] = $JO;
	if ( $ASLOCKSTATS) $h[ 'aslockstats'] = $ASLOCKSTATS;
	$file = sprintf( "%s/log.%s", $BDIR, base64_encode( $ouid)); if ( $ofile) $file = $ofile;
	$out = fopen( $file, 'a'); fwrite( $out, h2json( $h, true, null, null, true) . "\n"); fclose( $out);
}


?>