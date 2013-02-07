<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
sforeach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to run a TCP socket receiver -- receives and executes tasks");
clhelp( "NOTE: uses the next port for C/C++ server");
htg( clget( 'port,wdir'));
$port2 = $port + 1;

echo "\n\n"; $lastime = tsystem(); $setup = null;
class MyServer extends NTCPServer { 
	public function eachloop( $client) { 
		global $lastime, $setup, $wdir;
		if ( tsystem() - $lastime < 1) return; $lastime = tsystem(); 
		if ( ! $setup) return; extract( $setup); // psize, probesize
		if ( procpid( 'probe.udp')) return;	// still running
		if ( ! is_file( "$wdir/temp.hcsv")) { $setup = null; $msg = 'msg=ok,status=done,thru=0'; echo "> $msg\n"; return $client->send( $msg); }
		$h = $setup; $h[ 'probe'] = array();
		$lines = file( "$wdir/temp.hcsv");
		foreach ( $lines as $line)  {
			$line = trim( $line); if ( ! $line) continue;
			$h2 = tth( $line); if ( ! isset( $h2[ 'pos'])) continue;
			extract( $h2); 	// pos, pspace
			$h[ 'probe'][ $pos] = $pspace;
		}
		ksort( $h[ 'probe']); $h[ 'probe'] = hv( $h[ 'probe']);
		$h2 = $h; $h2[ 'probe'] = '(' . count( $h2[ 'probe']) . ') samples'; echo json_encode( $h2) . "\n";
		$out = foutopen( "$wdir/$tag.bz64jsonl", 'a'); foutwrite( $out, $h); foutclose( $out);
		// stats
		$vs = $h[ 'probe']; $vss = array(); $vs2 = array();
		foreach ( $vs as $v) { 
			if ( $v != -1) lpush( $vs2, $v); 
			else { if ( count( $vs2)) lpush( $vss, $vs2); $vs2 = array(); }
		}
		if ( count( $vss)) {
			$vsh = array(); foreach ( $vss as $vs) $vsh[ '' . count( $vs)] = $vs;
			krsort( $vsh, SORT_NUMERIC);
			list( $count, $vs2) = hfirst( $vsh);
		}
		//echo "good probe: " . json_encode( $vs2) . "\n";
		$thru = 0; if ( count( $vs2) && msum( $vs2) > 0) $thru = round( 0.001 * ( ( $psize * 8 * count( $vs2)) / ( 0.000001 * msum( $vs2))));
		$msg = "msg=ok,status=done,thru=$thru"; echo "> $msg\n"; $client->send( $msg);
		$setup = null; `rm -Rf $wdir/temp.hcsv`;
	}
	public function receive( $h, $client) { 
		global $port2, $wdir, $setup, $lastime;
		echo "\n\n"; 
		echo "< " . json_encode( $h) . "\n";
		$setup = $h; extract( $h);	 // tag, method, psize, probesize,   run, pspace, alpha, 
		if ( isset( $action) && $action == 'restart') die( "\n");
		if ( procpid( 'probe.udp')) prockill( procpid( 'probe.udp'));
		$c = "$wdir/probe.udp.$method.rx $port2 $wdir/temp.hcsv $psize $probesize";
		echo "c[$c]\n"; `rm -Rf $wdir/temp.hcsv`; procat( $c); $lastime = tsystem(); 
		$client->send( 'msg=ok,status=done');
	}
	
}
$S = new MyServer();
$S->start( $port, true, 10000, 30);


?>