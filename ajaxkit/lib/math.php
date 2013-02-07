<?php

// trigonometry
function mrotate( $r, $a, $round = 3) { 	// rotate point ( r, 0) for a degrees (ccw) and return new ( x, y)
	while ( $a > 360) $a -= 360;
	$cos = cos( 2 * 3.14159265 * ( $a / 360));
	$x = round( $r * $cos, $round);
	$y = round( pow( pow( $r, 2) - pow( $x, 2), 0.5), $round);
	if ( ! misvalid( $y)) $y = 0;
	if ( $a > 180) $y = - $y;
	return compact( ttl( 'x,y'));
}

function misvalid( $number) {
	if ( strtolower( "$number") == 'nan') return false;
	if ( strtolower( "$number") == 'na') return false;
	if ( strtolower( "$number") == 'inf') return false;
	if ( strtolower( "$number") == '-inf') return false;
	return true;
}
// mathematics functions
function mr( $length = 10) {	// math random
	$out = '';
	for ( $i = 0; $i < $length; $i++) $out .= mt_rand( 0, 9);
	return $out;
}
function msum( $list) {
	$sum = 0; foreach ( $list as $item) $sum += $item;
	return $sum;
}
function mavg( $list) {
	$sum = 0;
	foreach ( $list as $item) $sum += $item;
	return count( $list) ? $sum / count( $list) : 0;
}
function mmean( $list) { sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.5 * mt_rand( 0, count( $list)))]; }
function mumid( $list) { $h = array(); foreach ( $list as $v) $h[ "$v"] = true; return m50( hk( $h)); }
function m25( $list) { sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.25 * mt_rand( 0, count( $list)))]; }
function m50( $list) { sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.5 * mt_rand( 0, count( $list)))]; }
function m75( $list) { sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.75 * mt_rand( 0, count( $list)))]; }
function mvar( $list) {
	$avg = mavg( $list);
	$sum = 0;
	foreach ( $list as $item) $sum += abs( pow( $item - $avg, 2));
	return count( $list) ? pow( $sum / count( $list), 0.5) : 0;
}
function mmin( $one, $two = NULL) {
	$list = $one;
	if ( $two !== NULL && ! is_array( $one)) $list = array( $one, $two);  
	$min = $list[ 0];
	foreach ( $list as $v) if ( $v < $min) $min = $v;
	return $min;
}
function mmax( $one, $two = NULL) {
	$list = $one;
	if ( $two !== NULL && ! is_array( $one)) $list = array( $one, $two);
	$max = $list[ 0];
	foreach ( $list as $v) if ( $v > $max) $max = $v;
	return $max;
}
function mround( $v, $round) { // difference from traditional math, $round can be negative, will round before decimal points in this case
	if ( $round >= 0) return round( $v, $round);
	// round is a negative value, will round before the decimal point
	$v2 = 1; for ( $i = 0; $i < abs( $round); $i++) $v2 *= 10;
	return $v2 * round( $v / $v2); // first, shrink, then round, then expand again
}
function mhalfround( $v, $round) { // round is multiples of 0.5, same as mround, only semi-decimal, i.e. rounds within closest 0.5 or 5
	$round2 = $round - round( $round); // possible half a decimal
	$round = round( $round);	// decimals
	if ( $round2) $v *= 2;	// make the thing twice as big before rounding
	$v = mround( $v, $round);
	if ( $round2) $v = mround( 0.5 * $v, $round+1);
	return $v;
}
function mratio( $one, $two) {	// one,two cannot be negative
	if ( ! $one || ! $two) return 0;
	if ( $one && $two && $one == $two) return 1;
	$one = abs( $one); $two = abs( $two);
	return mmin( $one, $two) / mmax( $one, $two);
}
function mstats( $list, $precision = 2) { 	// return hash of simple stats: min,max,avg,var
	$min = mmin( $list); $max = mmax( $list); $avg = round( mavg( $list), $precision); $var = round( mvar( $list), $precision);
	$h = tth( "min=$min,max=$max,avg=$avg,var=$var");
	foreach ( $h as $k => $v) $h[ $k] = round( $v, $precision);
	return $h;
}
// logarithmic mapping of an array of samples, $list should be normalized, but if aboveone=true, then non-normalized but positive
function mrel( $list) { // returns list of values relative to the min
	$min = mmin( $list); 
	$list2 = array(); foreach ( $list as $v) lpush( $list2, $v - $min);
	return $list2;
}
function mlog( $list, $min, $realvalues = false, $aboveone = false, $precision = 5) 	{	// takes fraction of 1, boots to min, logs, and puts back to 0..1
	$list1 = array();
	foreach ( $list as $item) array_push( $list1, $min + ( $aboveone ? $item : $item * ( 1.0 - $min)));
	$list2 = array();
	foreach ( $list1 as $item) array_push( $list2, log10( $item));
	$list3 = mnorm( $list2, null, null, $precision);
	if ( ! $realvalues) return $list3;
	return mmap( $list3, mmin( $list), mmax( $list), $precision);
}
function mmap( $list, $min, $max, $precision = 5, $normprecision = 5) {
	$list2 = mnorm( $list, null, null, $normprecision);
	$list3 = array();
	foreach ( $list2 as $v) lpush( $list3, round( $min + $v * ( $max - $min), $precision));
	return $list3;
}
function mnorm( $list, $optmax = NULL, $optmin = NULL, $precision = 5) {	// normalize the list to 0..1 scale
	$out = array();
	$min = mmin( $list);
	if ( $optmin !== NULL) $min = $optmin;
	$max = mmax( $list);
	if ( $optmax !== NULL) $max = $optmax;
	foreach ( $list as $item) array_push( $out, round( mratio( $item - $min, $max - $min), $precision));
	return $out;
}
function mabs( $list, $round = 5) { // returns list with abs() of values
	$out = array(); for ( $i = 0; $i < count( $list); $i++) $out[ $i] = round( abs( $list[ $i]), $round);
	return $out;
}
function mdistance( $list) { 	// returns list of distances between samples
	$out = array();
	for ( $i = 1; $i < count( $list); $i++) array_push( $out, $list[ $i] - $list[ $i - 1]);
	return $out;
}
// direction = up | down | both (down cuts above and selects below), percentile is fraction of 1
function mpercentile( $list, $percentile, $direction) {
	if ( ! count( $list)) return $list;
	sort( $list, SORT_NUMERIC);
	$range = $list[ count( $list) - 1] - $list[ 0];
	$threshold = $list[ 0] + $percentile * $range;
	if ( $direction == 'both') $threshold2 = $list[ 0] + ( 1 - $percentile) * $range;
	$out = array();
	foreach ( $list as $item) {
		if ( $direction == 'both' && $item >= $threshold && $item <= $threshold2) {
			array_push( $out, $item); 
			continue;
		}
		if ( ( $item <= $threshold && $direction == 'down') || ( $item >= $threshold && $direction == 'up')) 
			array_push( $out, $item);
	}
	return $out;
}
// qqplot, two lists, global sum, returns cumulative aggregates normalized on 0..1 scale
function mqqplotbysum( $one, $two, $step = 1, $round = 2) { // returns [ x, y], x=one, y=two, lists have to be the same size
	$sum = 0;
	foreach ( $one as $v) $sum += $v;
	foreach ( $two as $v) $sum += $v;
	$x = array(); $y = array();
	$sum2 = 0;
	for ( $i = 0; $i < count( $one); $i += $step) { 
		for ( $ii = $i; $ii < $i + $step; $ii++) {
			$sum2 += $one[ $ii]; 
			$sum2 += $two[ $ii]; 
		}
		lpush( $x, round( $sum2 / $sum, 2));
		lpush( $y, round( $sum2 / $sum, 2));
	}
	return array( $x, $y);
}
function mqqplotbyvalue( $one, $two, $step = 1, $round = 2) { // returns [ x, y], x=one, y=two, lists have to be the same size
	$max = mmax( array( mmax( $one), mmax( $two)));
	$x = array(); $y = array();
	for ( $i = 0; $i < count( $one); $i += $step) {
		lpush( $x, round( $one[ $i] / $max, 2));
		lpush( $y, round( $two[ $i] / $max, 2));
	}
	return array( $x, $y);
}
// calculates density as moving window, returns hash { bit.center: count}
function mdensity( $list, $min = null, $max = null, $step = 100, $window = 30, $round = 3) { // $step = $window / 3 is advised, both are countable numerals 
	if ( $min === null) $min = mmin( $list);
	if ( $max === null) $max = mmax( $list);
	$step = round( ( $max - $min) / $step, $round); $out = array();
	for ( $v = ( 0.5 * $window) * $step; $v < $max - ( 0.5 * $window) * $step; $v += $step) {
		$count = 0;
		foreach ( $list as $v2) if ( $v2 >= ( $v - 0.5 * $window * $step) && $v2 <= $v + 0.5 * $window * $step) $count++;
		$out[ "$v"] = $count;
	}
	return $out;
}
// returns frequency hash (v => count) in descending order
function mfrequency( $list, $shaper = 1, $round = 0) { // round 0 means interger values
	$out = array();
	foreach ( $list as $v) {
		$v = $shaper * ( round( $v / $shaper, $round));
		if ( ! isset( $out[ "$v"])) $out[ "$v"] = 0;
		$out[ "$v"]++;
	}
	arsort( $out, SORT_NUMERIC);
	return $out;
}
// randomly shifts values a bit around their actual values (used in plots)
function mjitter( $list, $range, $quantizer = 1000) {
	for ( $i = 0; $i < count( $list); $i++) {
		$jitter = ( mt_rand( 0, $quantizer) / $quantizer) * $range;
		$direction = mt_rand( 0, 9);
		if ( $direction < 5) $list[ $i] += $jitter;
		else $list[ $i] -= $jitter;
	}
	return $list;
}

?>