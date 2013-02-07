<?php
mb_internal_encoding( "UTF-8");
// search and index
$LUCENEDIR = '/ntfs/lucene'; 
$LUCENECODEDIR = '/code/lucene2';
$CONTENTDIR = '/ntfs/content';
// perform aslock() for each file (for now, only JSON)
$ASLOCKON = false;	// locks files before all file operations
$IOSTATSON = false; // when true, will collect statistics about file file write/reads (with locks)
// collect IO stats globally (can be used by a logger) (only JSON implements it for now)
$IOSTATS = array();  // stats is in [ {type,time,[size]}, ...] 
// file locks
$ASLOCKS = array();	$ASLOCKSTATS = array(); $ASLOCKSTATSON = false; // filename => lock
$JQMODE = 'sourceone';	// debug|source|sourceone (debug is SCRIPT tag per file, sourceone is stuff put into one file)
$JQ = array(		// {{{ all JQ files (jquery.*.js)
	'libs' => array( 	// those that cannot be changed
		'1.6.4', 'base64', 'form', 'json.2.3', 'Storage', 'svg', 'timers' //, 'lzw-async'
	),
	'basics' => array( 'ioutils', 'iobase'),
	'advanced' => array(
		'iodraw',
		// ioatoms
		'ioatoms',
		'ioatoms.input', 'ioatoms.containers', 
		'ioatoms.output', 'ioatoms.gui', 'ioatoms.gridgui'
	)
); // }}}
$env = makenv(); // CDIR,BIP,SBDIR,ABDIR,BDIR,BURL,ANAME,DBNAME,ASESSION,RIP,RPORT,RAGENT
//var_dump( $env);
foreach ( $env as $k => $v) $$k = $v;
$DB = null; $DBNAME = $ANAME;	// db same as ANAME
$MAUTHDIR = '/code/mauth';
$MFETCHDIR = '/code/mfetch';
// library loader
if ( ! isset( $LIBCASES)) $LIBCASES = array( 'commandline', 'csv', 'filelist', 'hashlist', 'hcsv', 'json', 
	'json', 'math', 'string', 'time', 'db', 'proc', 'async', 'plot', 
	'ngraph', 'objects', 'chart', 'r', 'mauth', 'matrixfile', 'matrixmath',
	'binary', 'curl', 'mfetch', 'network', 'remote', 'lucene', 'pdf', 'crypt', 'file', 'dll', 'hashing', 'queue',
	'optimization', 'websocket'
);
foreach ( $LIBCASES as $lib) if ( is_file( "$ABDIR/lib/$lib.php")) require_once( "$ABDIR/lib/$lib.php");
?>
