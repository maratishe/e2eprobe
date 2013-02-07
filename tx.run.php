<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to keep the TX socket alive");
htg( clget( 'rip,rport,tag,timeout'));

$run = 1;
while ( 1) {
	echo "\n\n";
	echo "RUN $run   sleep 1s..."; sleep( 1); echo " OK\n";
	system( "php tx.php $rip $rport $tag $timeout $run");
	$run++;
}


?>