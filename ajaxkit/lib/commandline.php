<?php
$CLHELP = array();
// command line functions
function clinit() {
	global $prefix, $BDIR, $CDIR;
	// additional (local) functions and env (if present)
	if ( is_file( "$BDIR/functions.php")) require_once( "$BDIR/functions.php");
	if ( is_file( "$BDIR/env.php")) require_once( "$BDIR/env.php");
	// yet additional env and functions in current directory -- only when CDIR != BDIR
	if ( $CDIR && $BDIR != $CDIR && is_file( "$CDIR/functions.php")) require_once( "$CDIR/functions.php");
	if ( $CDIR && $BDIR != $CDIR && is_file( "$CDIR/env.php")) require_once( "$CDIR/env.php");
}
function clrun( $command, $silent = true, $background = true, $debug = false) {
	if ( $debug) echo "RUN [$command]\n";
	if ( $silent) system( "$command > /dev/null 2>1" . ( $background ? ' &' : ''));
	else system( $command);
}
function clget( $one, $two = '', $three = '', $four = '', $five = '', $six = '', $seven = '', $eight = '', $nine = '', $ten = '', $eleven = '', $twelve = '') {
	global $argc, $argv, $GLOBALS;
	// keys
	if ( count( ttl( $one)) > 1) $ks = ttl( $one);
	else $ks = array( $one, $two, $three, $four, $five, $six, $seven, $eight, $nine, $ten, $eleven, $twelve);
	while ( count( $ks) && ! llast( $ks)) lpop( $ks);
	// values
	$vs = $argv; $progname = lshift( $vs);
	if ( count( $vs) == 1) {	// only one argument, maybe hash
		$h = tth( $vs[ 0]); $ok = true; if ( ! count( $h)) $ok = false;  
		foreach ( $h as $k => $v) if ( ! $k || ! strlen( "$k") || ! $v || ! strlen( "$v")) $ok = false;
		if ( $ok && ltt( hk( $h)) == ltt( $ks)) $vs = hv( $h);	// keys are decleared by themselves, just create values
	}
	if ( count( $vs) && ( $vs[ 0] == '-h' || $vs[ 0] == '--help' || $vs[ 0] == 'help')) { clshowhelp(); die( ''); }
	if ( count( $vs) != count( $ks)) { 
		echo "\n";
		echo "ERROR! clget() wrong command line, see keys/values and help below...\n";
		echo "(expected) keys: " . ltt( $ks, ' ') . "\n";
		echo "(found) values: " . ltt( $vs, ' ') . "\n";
		echo "---\n";
		clshowhelp();
		die( '');
	}
	// merge keys with values
	$h = array(); for ( $i = 0; $i < count( $ks); $i++) $h[ '' . $ks[ $i]] = trim( $vs[ $i]);
	$ks = hk( $h); for ( $i = 1; $i < count( $ks); $i++) if ( $h[ $ks[ $i]] == 'ditto') $h[ $ks[ $i]] = $h[ $ks[ $i - 1]];
	foreach ( $h as $k => $v) echo "  $k=[$v]\n";
	foreach ( $h as $k => $v) $GLOBALS[ $k] = $v;
	return $h;
}
// quiet version, do not output anything, other than errors
function clgetq( $one, $two = '', $three = '', $four = '', $five = '', $six = '', $seven = '', $eight = '', $nine = '', $ten = '', $eleven = '', $twelve = '') {
	global $argc, $argv, $GLOBALS;
	// keys
	if ( count( ttl( $one)) > 1) $ks = ttl( $one);
	else $ks = array( $one, $two, $three, $four, $five, $six, $seven, $eight, $nine, $ten, $eleven, $twelve);
	while ( count( $ks) && ! llast( $ks)) lpop( $ks);
	// values
	$vs = $argv; $progname = lshift( $vs);
	if ( count( $vs) == 1) {	// only one argument, maybe hash
		$h = tth( $vs[ 0]); $ok = true; if ( ! count( $h)) $ok = false;  
		foreach ( $h as $k => $v) if ( ! $k || ! strlen( "$k") || ! $v || ! strlen( "$v")) $ok = false;
		if ( $ok && ltt( hk( $h)) == ltt( $ks)) $vs = hv( $h);	// keys are decleared by themselves, just create values
	}
	if ( count( $vs) && ( $vs[ 0] == '-h' || $vs[ 0] == '--help' || $vs[ 0] == 'help')) { clshowhelp(); die( ''); }
	if ( count( $vs) != count( $ks)) { 
		echo "\n";
		echo "ERROR! clget() wrong command line, see keys/values and help below...\n";
		echo "(expected) keys: " . ltt( $ks, ' ') . "\n";
		echo "(found) values: " . ltt( $vs, ' ') . "\n";
		echo "---\n";
		clshowhelp();
		die( '');
	}
	// merge keys with values
	$h = array(); for ( $i = 0; $i < count( $ks); $i++) $h[ '' . $ks[ $i]] = trim( $vs[ $i]);
	$ks = hk( $h); for ( $i = 1; $i < count( $ks); $i++) if ( $h[ $ks[ $i]] == 'ditto') $h[ $ks[ $i]] = $h[ $ks[ $i - 1]];
	foreach ( $h as $k => $v) //echo "  $k=[$v]\n";
	foreach ( $h as $k => $v) $GLOBALS[ $k] = $v;
	return $h;
}
function clhelp( $msg) { global $CLHELP; lpush( $CLHELP, $msg); }
function clshowhelp() { // show contents of CLHELP 
	global $CLHELP;
	foreach ( $CLHELP as $msg) {
		if ( substr( $msg, strlen( $msg) - 1, 1) != "\n") $msg .= "\n"; 	// no end line in this msg, add one
		echo $msg;
	}
	
}

?>