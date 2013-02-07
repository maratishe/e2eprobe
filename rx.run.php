<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to keep TCP server alive by restarting it");
htg( clget( 'port'));

echo "killing old scripts..."; 
while ( procpid( 'rx.php')) { $pid = procpid( 'rx.php'); prockill( $pid); echo " $pid"; }
echo " OK\n";
while ( 1) {
	echo "\n\n"; $before = tsystem();
	$c = "/usr/local/php/bin/php $CDIR/rx.php $port $CDIR"; echo "c[$c]\n"; procat( $c);
	echo "waiting..."; while ( tsystem() - $before < 30 && procpid( 'rx.php')) { echo '.'; usleep( 100000); }
	if ( procpid( 'rx.php')) prockill( procpid( 'rx.php'));
}


?>