<?php

function cleanfilename( $name,  $bad = '', $replace = '.', $donotlower = true) {
	if ( ! $bad) $bad = '*{}|=/ -_",;:!?()[]&%$# ' . "'" . '\\';
	$name = strcleanup( $name, $bad, $replace);
	for ( $i = 0; $i < 10; $i++) $name = str_replace( $replace . $replace, $replace, $name);
	if ( strpos( $name, '.') === 0) $name = substr( $name, 1);
	if ( ! $donotlower) $name = strtolower( $name);
	return $name;
}

// flatten directory with its subdirectories and return hash{ path: filename}
function flgetall( $dir, $extspick = '', $extsignore = '') { // picks and ignores are dot-delimited
	if ( $extspick) $extspick = ttl( $extspick, '.'); else $extspick = array();
	if ( $extsignore) $extsignore = ttl( $extsignore, '.'); else $extsignore = array();
	$dirs = array( $dir);
	$h = array();
	$limit = 10000; while ( count( $dirs)) {
		$dir = lshift( $dirs);
		$FL = flget( $dir);
		foreach ( $FL as $file) {
			if ( is_dir( "$dir/$file")) { lpush( $dirs, "$dir/$file"); continue; }
			$ext = lpop( ttl( $file, '.'));
			if ( $extspick && lisin( $extspick, $ext)) { $h[ "$dir/$file"] = $file; continue; }
			if ( $extsignore && lisin( $extsignore, $ext)) continue;	// ignore, wrong extension
			if ( ! is_file( "$dir/$file")) continue;
			$h[ "$dir/$file"] = $file;
		}
		
	}
	return $h;
}
// read a file list, enables prefix and generic filters
function flget( $dir, $prefix = '', $string = '', $ending = '', $length = -1, $skipfiles = false, $skipdirs = false) {
	$in = popen( "ls -a $dir", 'r');
	$list = array();
	while ( $in && ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( ! $line) continue;
		if ( $line === '.' || $line === '..') continue;
		if ( is_dir( "$dir/$line") && $skipdirs) continue;
		if ( is_file( "$dir/$line") && $skipfiles) continue;
		if ( $prefix && strpos( $line, $prefix) !== 0) continue;
		if ( $string && ! strpos( $line, $string)) continue;	// string not found anywhere
		if ( $ending && strrpos( $line, $ending) !== strlen( $line) - strlen( $ending)) continue; 
		if ( $length > 0 && strlen( $line) != $length) continue;
		array_push( $list, $line);
	}
	pclose( $in);
	return $list;
}
// pdef format: x.x.*?.x.*?.log (only * is interpreted, ? is pos number)
function flparse( $list, $pdef, $numeric = true, $delimiter2 = null) { // returns multiarray containing filenames
	$plist = array();
	$split = explode( '.', $pdef);
	for ( $i = 0; $i < count( $split); $i++) {
		if ( strpos( $split[ $i], '*') === false) continue;	// not to be parsed
		$pos = $i;
		if ( strlen( str_replace( '*', '', $split[ $i]))) $pos = ( int)str_replace( '*', '', $split[ $i]);
		$plist[ $pos] = $i;
	}
	ksort( $plist, SORT_NUMERIC);
	$plist = array_values( $plist);
	$pcount = count( $split);
	$mlist = array();
	foreach ( $list as $file) {
		$fname = $file;
		if ( $delimiter2) $fname = str_replace( $delimiter2, '.', $fname);
		$split = explode( '.', $fname);
		if ( count( $split) !== $pcount) continue; 	// rogue file
		unset( $ml);
		$ml =& $mlist;
		for ( $i = 0; $i < count( $plist) - 1; $i++) {
			$part = $split[ $plist[ $i]];
			if ( $numeric) $part = ( int)$part;
			if ( ! isset( $ml[ $part])) $ml[ $part] = array();
			unset( $nml);
			$nml =& $ml[ $part];
			unset( $ml);
			$ml =& $nml;
		}
		$part = $split[ $plist[ count( $plist) - 1]];
		if ( $numeric) $part = ( int)$part;
		if ( isset( $ml[ $part]) && is_array( $ml[ $part])) array_push( $ml[ $part], $file);
		else if ( isset( $ml[ $part])) $ml[ $part] = array( $ml[ $part], $file); 
		else $ml[ $part] = $file;
	}
	return $mlist;
}
// debug
function fldebug( $fl) {
	echo "DEBUG FILE LIST\n";
	foreach ( $fl as $k1 => $v1) {
		echo "$k1   $v1\n";
		if ( is_array( $v1)) foreach ( $v1 as $k2 => $v2) {
			echo "   $k2   $v2\n";
			if ( is_array( $v2)) foreach ( $v2 as $k3 => $v3) {
				echo "      $k3   $v3\n";
				if ( is_array( $v3)) foreach ( $v3 as $k4 => $v4) {
					echo "         $k4   $v4\n";
				}
			}
		}
	}
	echo "\n\n";
}

?>