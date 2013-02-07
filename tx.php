<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to run a TCP socket sender  -- make tasks and send them to server");
clhelp( "[tag] tag for all records");
htg( clgetq( 'rip,rport,tag,timeout,run'));
$port2 = $rport + 1;

$C = new NTCPClient();
$C->init( $rip, $rport);
echo "sleep 500ms..."; usleep( 500000); echo " OK\n";


// global setup 
$psize = 100 * mt_rand( 1, 14);
$probesize = 50 * mt_rand( 1, 10);
echo "[START]\n";
$msg = "action=restart"; $C->send( $msg); echo "> $msg\n";
sleep( 1);
@socket_close( $C->sock); unset( $C); 
$C = new NTCPClient();
$C->init( $rip, $rport);
echo "sleep 500ms..."; usleep( 500000); echo " OK\n";


// my method
echo "[PROPOSED]\n";
$lastime = tsystem(); $before = tsystem(); $thru = null;
$msg = "tag=$tag,run=$run,method=proposed,psize=$psize,probesize=$probesize"; $C->send( $msg);
echo "> $msg\n"; unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
$c = "$CDIR/probe.udp.proposed.tx $rip $port2 $psize $probesize";
echo "c[$c]\n"; procat( $c);
$lastime = tsystem(); unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
echo "OK, took " . tshinterval( $before, tsystem()) . "\n";
if ( $thru == 0) die( "\n");


// pathchirp method
echo "[PATHCHIRP]\n";
$smallest = 0.5 * $thru;
$biggest = 5 * $thru;
echo "picking alpha (smallest=$smallest,biggest=$biggest):";
for ( $alpha = 1; $alpha < 5; $alpha += 0.01) {
	$L = array(); $pspace = round( 1000000 * ( ( $psize * 8) / ( 1000 * $smallest)));
	while ( count( $L) < $probesize) {
		lpush( $L, $pspace);
		$pspace = round( $pspace / $alpha);
	}
	$thru2 = round( 0.001 * ( ( $psize * 8) / ( llast( $L) * 0.000001)));
	echo " $alpha/$pspace/$thru2";
	if ( $thru2 > $biggest) break;
}
echo " STOP!  apha($alpha)\n";
$pspace = round( 1000000 * ( ( $psize * 8) / ( 1000 * $smallest)));
$lastime = tsystem(); $before = tsystem();
$msg = "tag=$tag,run=$run,method=pathchirp,psize=$psize,probesize=$probesize,pspace=$pspace,alpha=$alpha"; $C->send( $msg);
echo "> $msg\n"; unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
$c = "$CDIR/probe.udp.pathchirp.tx $rip $port2 $psize $probesize $pspace $alpha";
echo "c[$c]\n"; procat( $c);
$lastime = tsystem(); unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
echo "OK, took " . tshinterval( $before, tsystem()) . "\n";


// igi method
echo "[IGI]\n";
$smallest = 0.5 * $thru; $pspace1 = round( 1000000 * ( ( $psize * 8) / ( 1000 * $smallest)));
$biggest = 1.5 * $thru; $pspace2 = round( 1000000 * ( ( $psize * 8) / ( 1000 * $biggest)));
$step = round( ( $pspace1 - $pspace2) / 5);
$lastime = tsystem(); $before = tsystem();
$msg = "tag=$tag,run=$run,method=igi,psize=$psize,probesize=$probesize,pspacestart=$pspace1,pspacestep=$step,pspaceend=$pspace2"; $C->send( $msg);
echo "> $msg\n"; unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
$probesize2 = $probesize * round( ( $pspace1 - $pspace2) / $step);
$c = "$CDIR/probe.udp.igi.tx $rip $port2 $psize $probesize2 $pspace1 $step $pspace2";
echo "c[$c]\n"; procat( $c);
$lastime = tsystem(); unset( $status);
while ( tsystem() - $lastime < $timeout) {
	$msg = $C->recv(); if ( ! $msg) { usleep( 300000); continue; }
	echo '< ' . json_encode( $msg) . "\n";
	extract( $msg); break;
}
if ( ! isset( $status)) die( "\n");
echo "OK, took " . tshinterval( $before, tsystem()) . "\n";


?>