<?php

// asynchronous file access
// depends on time
function aslock( $file, $timeout = 1.0, $grain = 0.05) {	// returns [ time, lock]
	global $ASLOCKS, $ASLOCKSTATS, $ASLOCKSTATSON;
	// create a fairly unique lock file based on current time
	$time = tsystem(); $start = ( double)$time; 
	if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=aslock.start,time=$time,file=$file,grain=$grain"));
	$out = null; $count = 0;
	while( $time - $start < $timeout) {
		// create a unique lock filename based on rounded current time
		$time = tsystem(); if ( count( ttl( "$time", '.')) == 1) $time .= '.0';
		$stamp = '' . round( $time);	// times as string
		$L = ttl( "$time", '.'); $stamp .= '.' . lpop( $L);	// add us tail
		$stamp = $grain * ( int)( $stamp / $grain);	// round what's left of time to the nearest grain
		$lock = "$file.$stamp.lock";
		if ( ! is_file( $lock)) { $out = fopen( $lock, 'w'); break; }	// success obtaining the lock
		usleep( mt_rand( round( 0.5 * 1000000 * $grain), round( 1.5 * 1000000 * $grain)));	// between 0.5 and 1.5 of the grain
		$count++;
	}
	if ( ! $out) $out = @fopen( $lock, 'w');
	if ( ! isset( $ASLOCKS[ $lock])) $ASLOCKS[ $lock] = $out;
	if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=aslock.end,time=$time,file=$file,count=$count,status=" . ( $out ? 'ok' : 'failed')));
	return array( $time, $lock);
}
function asunlock( $file, $lockfile = null) { // if lockfile is nul, will try to close the last lock with this prefix
	global $ASLOCKS, $ASLOCKSTATS, $ASLOCKSTATSON;
	$time = tsystem();
	if ( $lockfile) { 
		if ( isset( $ASLOCKS[ $lockfile])) { @fclose( $ASLOCKS[ $lockfile]); @unlink( $lockfile); } 
		unset( $ASLOCKS[ $lockfile]); @unlink( $lockfile);
	}
	else {	// lockfile unknown, try to close the last one with $file as prefix
		$ks = hk( $ASLOCKS);
		while ( count( $ks)) {
			$k = lpop( $ks); 
			if ( strpos( $k, $file) !== 0) continue;
			@fclose( $ASLOCKS[ $k]); @unlink( $ASLOCKS[ $k]); 
			unset( $ASLOCKS[ $k]);
			break;
		}
		
	}
	if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=asunlock,time=$time,file=$file,status=ok"));
}

?>