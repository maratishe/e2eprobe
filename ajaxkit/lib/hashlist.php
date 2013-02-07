<?php

function hdebug( &$h, $level) {  // converts hash into text with indentation levels
	if ( ! count( $h)) return;
	$key = lshift( hk( $h));
	$v =& $h[ $key];
	for ( $i = 0; $i < $level * 5; $i++) echo ' ';
	echo $key;
	if ( is_array( $v)) { echo "\n"; hdebug( $h[ $key], $level + 1); }
	else echo "   $v\n";
	unset( $h[ $key]);
	hdebug( $h, $level);	// keep doing it until run out of keys
}
function hm( $one, $two, $three = NULL, $four = NULL) {
	if ( ! $one && ! $two) return array();
	$out = $one; if ( ! $out) $out = array();
	if ( is_array( $two)) foreach ( $two as $key => $value) $out[ $key] = $value;
	if ( ! $three) return $out;
	foreach ( $three as $key => $value) $out[ $key] = $value;
	if ( ! $four) return $out;
	foreach ( $four as $key => $value) $out[ $key] = $value;
	return $out;
}
function htouch( &$h, $key, $v = array(), $replaceifsmaller = true, $replaceiflarger = true, $tree = false) { // key can be array, will go deep that many levels
	if ( is_string( $key) && count( ttl( $key)) > 1) $key = ttl( $key);
	if ( ! is_array( $key)) $key = array( $key); $changed = false;
	foreach ( $key as $k) {
		if ( ! isset( $h[ $k])) { $h[ $k] = $v; $changed = true; }
		if ( is_numeric( $v) && is_numeric( $h[ $k]) && $replaceifsmaller && $v < $h[ $k]) { $h[ $k] = $v; $changed = true; }
		if ( is_numeric( $v) && is_numeric( $h[ $k]) && $replaceiflarger && $v > $h[ $k]) { $h[ $k] = $v; $changed = true; }
		if ( $tree) $h =& $h[ $k];	// will go deeper only if 'tree' type is set to true
	}
	return $changed;
}
function hltl( $hl, $key) {	// hash list to list
	$l = array();
	foreach ( $hl as $h) if ( isset( $h[ $key])) array_push( $l, $h[ $key]);
	return $l;
}
function hlf( &$hl, $key = '', $value = '', $remove = false) {	// filters only lines with [ key [=value]]
	$lines = array(); $hl2 = array();
	foreach ( $hl as $h) {
		if ( $key && ! isset( $h[ $key])) continue;
		if ( ( $key && $value) && ( ! isset( $h[ $key]) || $h[ $key] != $value)) { lpush( $hl2, $h); continue; }
		array_push( $lines, $h);
	}
	if ( $remove) $hl = $hl2;	// replace the original hashlist
	return $lines;
}
function hlm( $hl, $purge = '') {	// merging hash list, $purge can be an array
	if ( $purge && ! is_array( $purge)) $purge = explode( ':', $purge);
	$ph = array(); if ( $purge) foreach ( $purge as $key) $ph[ $key] = true;
	$out = array();
	foreach ( $hl as $h) {
		foreach ( $h as $key => $value) { 
			if ( isset( $ph[ $key])) continue;
			$out[ $key] = $value;
		}
	}
	return $out;
}
function hlth( $hl, $kkey, $vkey) { // pass keys for key and value on each line
	$h = array();
	foreach ( $hl as $H) $h[ $H[ $kkey]] = $H[ $vkey];
	return $h;
}
// convert hash of lists (same length) to list of hashes
function holthl( $h) {
	$out = array();
	$keys = array_keys( $h);
	for ( $i = 0; $i < count( $h[ $keys[ 0]]); $i++) {
		$item = array();
		foreach ( $keys as $key) $item[ $key] = $h[ $key][ $i];
		array_push( $out, $item);
	}
	return $out;
}
// adds a new key to each hash in the hash list
function hltag( &$h, $key, $value) {	// does not return anything
	for ( $i = 0; $i < count( $h); $i++) $h[ $i][ $key] = $value;
}
// sort hashlist
function hlsort( &$hl, $key, $how = SORT_NUMERIC, $bigtosmall = false) {
	$h2 = array(); foreach ( $hl as $h) { htouch( $h2, '' . $h[ $key]); lpush( $h2[ '' . $h[ $key]], $h); }
	if ( $bigtosmall) krsort( $h2, $how);
	else ksort( $h2, $how);
	$L = hv( $h2); $hl = array();
	foreach ( $L as $L2) { foreach ( $L2 as $h) lpush( $hl, $h); }
	return $hl;
}
// creates new hash where values in original are keys in resulting hash
function hvak( $h, $overwrite = true, $value = NULL, $numeric = false) {
	$out = array();
	foreach ( $h as $k => $v) {
		if ( ! $overwrite && isset( $out[ $v])) continue;
		$value2 = ( $value === NULL) ? $k : $value;
		$out[ $v] = $numeric ? ( ( int)$value2) : $value2;
	}
	return $out;
}
function htv( $h, $key) { return $h[ $key]; }
// hash and GLOBALS
function htg( $h, $keys = '', $prefix = '', $trim = true) { 
	if ( ! $keys) $keys = array_keys( $h);
	if ( is_string( $keys)) $keys = ttl( $keys, '.');
	foreach ( $keys as $k) $GLOBALS[ $prefix . $k] = $trim ? trim( $h[ $k]) : $h[ $k]; 
}
function hcg( $h) { foreach ( $h as $k => $v) { if ( is_numeric( $k)) unset( $GLOBALS[ $v]); else unset( $GLOBALS[ $k]); }} 
// keys and values
function hk( $h) { return array_keys( $h); }
function hv( $h) { return array_values( $h); }
// hash-like array_ shorthands
// return array(), keys + values, use list( $k, $v) = func() to get returns
function hpop( &$h) { if ( ! count( $h)) return array( null, null); end( $h); $k = key( $h); $v = $h[ $k]; unset( $h[ $k]); return array( $k, $v); }
function hshift( &$h) { if ( ! count( $h)) return array( null, null); reset( $h); $k = key( $h); $v = $h[ $k]; unset( $h[ $k]); return array( $k, $v); }
function hfirst( &$h) { if ( ! count( $h)) return array( null, null); reset( $h); $k = key( $h); return array( $k, $h[ $k]); }
function hlast( &$h) { if ( ! count( $h)) return array( null, null); end( $h); $k = key( $h); return array( $k, $h[ $k]); }
// only for values
function hpopv( &$h) { if ( ! count( $h)) return null; $v = end( $h); $k = key( $h); unset( $h[ $k]); return $v; }
function hshiftv( &$h) { if ( ! count( $h)) return null; $v = reset( $h); $k = key( $h); unset( $h[ $k]); return $v; }
function hfirstv( &$h) { if ( ! count( $h)) return null; return reset( $h); }
function hlastv( &$h) { if ( ! count( $h)) return null; return end( $h); }
// same for keys
function hpopk( &$h) { if ( ! count( $h)) return null; end( $h); $k = key( $h); unset( $h[ $k]); return $k; }
function hshiftk( &$h) { if ( ! count( $h)) return null; reset( $h); $k = key( $h); unset( $h[ $k]); return $k; }
function hfirstk( &$h) { if ( ! count( $h)) return null; reset( $h); return key( $h); }
function hlastk( &$h) { if ( ! count( $h)) return null; end( $h); return key( $h); }


function hth64( $h, $keys = null) {	// keys can be array or string
	if ( $keys === null) $keys = array_keys( $h);
	if ( $keys && ! is_array( $keys)) $keys = explode( '.', $keys);
	$keys = hvak( $keys, true, true);
	$H = array(); foreach ( $h as $k => $v) $H[ $k] = isset( $keys[ $k]) ? base64_encode( $v) : $v;
	return $H;
}
function h64th( $h, $keys = null) {	// keys can be array or string
	if ( $keys === null) $keys = array_keys( $h);
	if ( $keys && ! is_array( $keys)) $keys = explode( '.', $keys);
	$keys = hvak( $keys, true, true);
	$H = array(); foreach ( $h as $k => $v) $H[ $k] = isset( $keys[ $k]) ? base64_decode( $v) : $v;
	return $H;
}


// hash functions
function tth( $t, $bd = ',', $sd = '=', $base64 = false, $base64keys = null) {	// text to hash
	if ( ! $base64keys) $base64keys = array();
	if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
	if ( $base64) $t = base64_decode( $t);
	$h = array();
	$parts = explode( $bd, trim( $t));
	foreach ( $parts as $part) {
		$split = explode( $sd, $part);
		if ( count( $split) === 1) continue;	// skip this one
		$h[ trim( array_shift( $split))] = trim( implode( $sd, $split));
	}
	foreach ( $base64keys as $k) if ( isset( $h[ $k])) $h[ $k] = base64_decode( $h[ $k]);
	return $h;
}
// processes text body into hash list
function tthl( $text, $ld = '...', $bd = ',', $sd = '=') {
	$lines = explode( '...', base64_decode( $props[ 'search.config']));
	$hl = array();
	foreach ( $lines as $line) {
		$line = trim( $line);
		if ( ! $line || strpos( $line, '#') === 0) continue;
		array_push( $hl, tth( $line, $bd, $sd));
	}
	return $hl;
}
function htt( $h, $sd = '=', $bd = ',', $base64 = false, $base64keys = null) { // hash to text
	// first, process base64
	if ( ! $base64keys) $base64keys = array();
	if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
	foreach ( $base64keys as $k) if ( isset( $h[ $k])) $h[ $k] = base64_encode( $h[ $k]);
	$parts = array();
	foreach ( $h as $key => $value) array_push( $parts, $key . $sd . $value);
	if ( ! $parts) return '';
	if ( $base64) return base64_encode( implode( $bd, $parts));
	return implode( $bd, $parts);
}
function ttl( $t, $d = ',', $cleanup = "\n:\t", $skipempty = true, $base64 = false, $donotrim = false) { // text to list
	if ( ! $cleanup) $cleanup = '';
	if ( $base64) $t = base64_decode( $t);
	$l = explode( ':', $cleanup);
	foreach ( $l as $i) if ( $i != $d) $t = str_replace( $i, ' ', $t);
	$l = array();
	$parts = explode( $d, $t);
	foreach ( $parts as $p) {
		if ( ! $donotrim) $p = trim( $p);
		if ( ! strlen( $p) && $skipempty) continue;	// empty
		array_push( $l, $p);
	}
	return $l;
}
function ttlm( $t, $d = ',', $skipempty = true) { // manual ttl
	$out = array();
	while ( strlen( $t)) {
		$pos = 0;
		for ( $i = 0; $i < strlen( $t); $i++) if ( ord( substr( $t, $i, 1)) == ord( $d)) break;
		if ( $i == strlen( $t)) { array_push( $out, $t); break; }	// end of text
		if ( ! $i) { if ( ! $skipempty) array_push( $out, ''); }
		else array_push( $out, substr( $t, 0, $i));
		$t = substr( $t, $i + 1);
	}
	return $out;
}
function ltt( $l, $d = ',', $base64 = false) {	// list to text 
	if ( ! count( $l)) return '';
	if ( $base64) return base64_encode( implode( $d, $l));
	return implode( $d, $l); 
}
function ldel( $list, $v) {	// delete item from list
	$L = array();
	foreach ( $list as $item) if ( $item != $v) array_push( $L, $item);
	return $L;
}
function ledit( $list, $old, $new) {	// delete item from list
	$L = array();
	foreach ( $list as $item) {
		if ( $item == $old) array_push( $L, $new);
		else array_push( $L, $item);
	}
	return $L;
}
function ltll( $list) { 	// list to list of lists
	$out = array(); foreach ( $list as $v) { lpush( $out, array( $v)); }
	return $out;
}
function lth( $list, $prefix) { // list to hash using prefix[number] as key, if prefix is array, will use those keys directly
	$L = array(); for ( $i = 0; $i < ( is_array( $prefix) ? count( $prefix) : count( $list)); $i++) $L[ $i] = is_array( $prefix) && isset( $prefix[ $i]) ? $prefix[ $i] : "$prefix$i";
	$h = array();
	for ( $i = 0; $i < ( is_array( $prefix) ? count( $prefix) : count( $list)); $i++) $h[ $L[ $i]] = $list[ $i]; 
	return $h;
}
function lr( $list) { return $list[ mt_rand( 0, count( $list) - 1)]; }
function lrv( $list) { return mt_rand( $list[ 0], $list[ 1]); }
function lm( $one, $two) {
	$out = array();
	foreach ( $one as $v) array_push( $out, $v);
	foreach ( $two as $v) array_push( $out, $v);
	return $out;
}
function lisin( $list, $item) { 	// list is in, checks if element is in list
	foreach ( $list as $item2) if ( $item2 == $item) return true;
	return false;
}
// array_ shorthands
function ladd( &$list, $v) { array_push( $list, $v); }
function lpush( &$list, $v) { array_push( $list, $v); }
function lshift( &$list) { if ( ! $list || ! count( $list)) return null; return array_shift( $list); }
function lunshift( &$list, $v) { array_unshift( $list, $v); }
function lpop( &$list) { if ( ! $list || ! count( $list)) return null; return array_pop( $list); }
function lfirst( &$list) { if ( ! $list || ! count( $list)) return null; return reset( $list); }
function llast( &$list) { if ( ! $list || ! count( $list)) return null; return end( $list); }


?>