<?php

// text-based parsers
function tstring2yyyymm( $ym) { // ym should be 'Month YYYY' -- if month is not found, 00 is used
	$L = ttl( $ym, ' '); $m = count( $L) == 2 ? lshift( $L) : ''; $y = lshift( $L);
	if ( $y < 100) $y = ( $y < 20 ? '20' : '19') . $y;
	if ( $m) $m = strtolower( $m);
	foreach ( tth( 'jan=01,feb=02,mar=03,apr=04,may=05,jun=06,jul=07,aug=08,sep=09,oct=10,nov=11,dec=12') as $k => $v) { if ( $m && strpos( $m, $k) !== false) $m = $v; }
	if ( ! $m) $m = 0;
	$ym = round( sprintf( "%04d%02d", $y, $m));
	return $ym;
}
function tyyyymm2year( $ym) { return ( int)substr( $ym, 0, 4); }
function tyyyymm2month( $ym) { return $m = ( int)substr( $ym, 4, 2); }
function tm2string( $m, $short = false) { 
	$one = ttl( '?,January,February,March,April,May,June,July,August,September,October,November,December');
	$two = ttl( '?,Jan.,Feb.,March,April,May,June,July,Aug.,Sep.,Oct.,Nov.,Dec.');
	return $short ? $two[ $m] : $one[ $m];
}

// basic system-based time functions
function tsystem() {	// epoch of system time
	$list = @gettimeofday();
	return ( double)( $list[ 'sec'] + 0.000001 * $list[ 'usec']);
}
function tsystemstamp() {	// epoch of system time
	$list = @gettimeofday();
	return @date( 'Y-m-d H:i:s', $list[ 'sec']) . '.' . sprintf( '%06d', $list[ 'usec']);
}
function tsdate( $stamp) {	// extract date from stamp
	return trim( array_shift( explode( ' ', $stamp)));
}
function tstime( $stamp) {	// time part of stamp
	return trim( array_pop( explode( ' ', $stamp)));
}
function tsdb( $db) {	// Y-m-d H:i:s.us
	return dbsqlgetv( $db, 'time', 'SELECT now() as time');
}
function tsclean( $stamp) {	// cuts us off
	return array_shift( explode( '.', $stamp));
}
function tsets( $epoch) {	// epoch to string
	$epoch = ( double)$epoch;
	return @date( 'Y-m-d H:i:s', ( int)$epoch) . ( count( explode( '.', "$epoch")) === 2 ? '.' . array_pop( explode( '.', "$epoch")) : '');
}
function tsste( $string) {	// string to epoch
	$usplit = explode( '.', $string);
	$split = explode( ' ', $usplit[ 0]);
	$us = ( count( $usplit) == 2) ?  '.' . $usplit[ 1] : '';
	$dsplit = explode( '-', $split[ 0]);
	$tsplit = explode( ':', $split[ 1]);
	return ( double)( 
		@mktime( 
			$tsplit[ 0], 
			$tsplit[ 1], 
			$tsplit[ 2], 
			$dsplit[ 1], 
			$dsplit[ 2], 
			$dsplit[ 0]) . $us
	);
}
// human readible values up until weeks, prefixes: m,h,d,w
function tshinterval( $before, $after = null, $fullnames = false) {	// double values
	$prefix = 'ms';
	$setup = tth( 'ms=milliseconds,s=seconds,m=minutes,h=hours,d=days,w=weeks,mo=months,y=years');
	if ( ! $fullnames) foreach ( $setup as $k => $v) $setup[ $k] = $k;	// key same as value
	extract( $setup);
	if ( ! $after) $interval = abs( $before);
	else $interval = abs( $after - $before);
	$ginterval = $interval;
	if ( $interval < 1) return round( 1000 * $interval) . $ms;
	$interval = round( $interval, 1); if ( $interval <= 10) return $interval . $s; // seconds
	if ( $interval <= 60) return round( $interval) . $s;
	$interval = round( $interval / 60, 1); if ( $interval <= 10) return $interval . $m; // minutes
	if ( $interval <= 60) return round( $interval) . $m;
	$interval = round( $interval / 60, 1); if ( $interval <= 24) return $interval . $h; // hours
	$interval = round( $interval / 24, 1); if ( $interval <= 7) return $interval . $d; // days
	$interval = round( $interval / 7, 1); if ( $interval <= 54) return $interval . $w; // weeks
	$interval = round( $interval / 30.5, 1); if ( $interval <= 54) return $interval . $w; // weeks
	// interpret months from timestamps
	$one = tsets( tsystem()); $two = tsets( tsystem() - $ginterval);
	$L = ttl( $one, '-'); $one = 12 * lshift( $L) + lshift( $L) - 1 + lshift( $L) / 31;
	$L = ttl( $two, '-'); $two = 12 * lshift( $L) + lshift( $L) - 1 + lshift( $L) / 31;
	return round( $one - $two, 1) . $mo;
}
function tshparse( $in) { // parses s|m|h|d|w into seconds
	$out = ( double)$in;
	if ( strpos( $in, 's')) return $out;
	if ( strpos( $in, 'm')) return $out * 60;
	if ( strpos( $in, 'h')) return $out * 60 * 60;
	if ( strpos( $in, 'd')) return $out * 60 * 60 * 24;
	if ( strpos( $in, 'w')) return $out * 60 * 60 * 24 * 7;
	return $in;
}

?>