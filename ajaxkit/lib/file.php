<?php

function fpathparse( $path, $ashash = true) { 	// returns [ (absolute) filepath (no slash), filename, fileroot (without path), filetype (extension)]
	$L = ttl( $path, '/'); $L = ttl( lpop( $L), '.'); 
	$type = llast( $L); if ( count( $L) > 1) lpop( $L); 
	$root = ltt( $L, '.');
	$L = ttl( $path, '/', '', false);
	if ( count( $L) === 1) return $ashash ? lth( array( getcwd(), $path, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( getcwd(), $path, $root, $type);	// plain filename in current directory
	if ( ! strlen( $L[ 0])) { $filename = lpop( $L); return $ashash ? lth( array( ltt( $L, '/'), $filename, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( ltt( $L, '/'), $filename, $root, $type); }	// absolute path
	// relative path
	$cwd = getcwd(); $filename = lpop( $L); $path = ltt( $L, '/');
	chdir( $path);	// should follow relative path as well
	$path = getcwd(); chdir( $cwd);	// read cwd and go back
	return $ashash ? lth( array( $path, $filename, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( $path, $filename, $root, $type);
}

function fbackup( $file, $move = false) { 	// will save a backup copy of this file as file.tsystem()s.random(10)
	$suffix = sprintf( "%d.%d", ( int)tsystem(), mr( 10));
	if ( $move) procpipe( "mv $file $file.$suffix");
	else procpipe( "cp $file $file.$suffix");
}
function fbackups( $file) { 	// will find all backups for this file and return { suffix(times.random): filename}, will retain the path
	$L = ttl( $file, '/', '', false); $file = lpop( $L); $path = ltt( $L, '/'); // if no path will be empty
	$FL = flget( $path, $file); $h = array();
	foreach ( $FL as $file2) {
		if ( $file2 === $file || strlen( $file2) <= strlen( $file)) continue;
		$suffix = str_replace( $file . '.', '', $file2);
		$h[ "$suffix"] = $path ? "$path/$file2" : $file2;
	}
	return $h;
}

function ftempname( $ext = '', $prefix = '', $dir = '') { 	// dir can be '', file in form: [ prefix.] times . random( 10) . ext
	$limit = 10;
	while ( $limit--) {
		$temp = ( $dir ? $dir . '/' : '') . ( $prefix ? $prefix . '.' : '') . ( int)tsystem() . '.' . mr( 10) . ( $ext ? '.' . $ext : '');
		if ( ! is_file( $temp)) return $temp;
	}
	die( " ERROR! ftempname() failed to create a temp name\n"); 
}

// file reading mode with filesize involved
function finopen( $file) { 	// opens( read), reads file size, returns { in: handle, total(bytes),current(bytes),progress(%)}
	$h = array();
	$h[ 'total'] = filesize( $file);
	$h[ 'current'] = 0;	// did not read any
	$h[ 'count'] = 0; // count of lines
	$h[ 'progress'] = '0%'; 
	$h[ 'in'] = fopen( $file, 'r');
	return $h;
}
function finread( &$h, $json = true, $base64 = true, $bzip2 = true) {	// returns array( line | hash | array(), 'x%' | null)
	extract( $h); if ( ! $in || feof( $in)) return array( null, null, null); // empty array and null progress
	$line = fgets( $in); if ( ! trim( $line)) return array( null, null, null); 	// empty line
	$h[ 'count']++;
	$h[ 'current'] += mb_strlen( $line);
	$h[ 'progress'] = round( 100 * ( $h[ 'current'] / $h[ 'total'])) . '%';
	if ( $json) return array( json2h( trim( $line), $base64, null, $bzip2), $h[ 'progress'], $h[ 'count']);
	if ( $base64) $line = base64_decode( trim( $line));
	if ( $bzip2) $line = bzdecompress( $line);
	return array( $line, $h[ 'progress'], $h[ 'count']);
}
function finclose( &$h) { extract( $h); fclose( $in); }
function findone( &$h) { extract( $h); return ( ! $in) | feof( $in); }
// file writing mode with filesize involved
function foutopen( $file, $flag = 'w') { // returns { bytes, progress (easy to read kb,Mb format)}
	$h = array();
	$h[ 'bytes'] = 0; // count of written bytes
	$h[ 'count'] = 0; // count of lines
	$h[ 'progress'] = '0b';	// b, kb, Mb, Gb
	$h[ 'out'] = fopen( $file, $flag);
	return $h;
}
function foutwrite( &$h, $stuff, $json = true, $base64 = true, $bzip2 = true) {	// returns output filesize (b, kb, Mb, etc..)
	if ( is_string( $stuff)) $stuff = tth( $stuff);
	if ( $json) $stuff = h2json( $stuff, $base64, null, null, $bzip2);
	else { // not an object, should be TEXT!, but can still base64 and bzip2 it
		if ( $bzip2) $stuff = bzcompress( $stuff);
		if ( $base64) $stuff = base64_encode( $stuff);
	}
	if ( mb_strlen( $stuff)) $h[ 'bytes'] += mb_strlen( $stuff);
	$tail = ''; $progress = $h[ 'bytes'];
	if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'kb'; }
	if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'Mb'; }
	if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'Gb'; }
	$h[ 'progress'] = $progress . $tail;
	if ( mb_strlen( $stuff)) fwrite( $h[ 'out'], "$stuff\n");
	return $h[ 'progress'];
}
function foutclose( &$h) { extract( $h); fclose( $out); }
// bjam reader, read only, write using bjam* in binary.php -- normally first value is time (inter-record space)
// each parser type is viewed in the order of values, where position in the order is used to select rules and make decisions
function fbjamopen( $file, $firstValueIsNotTime = false) {
	$h = array(); 
	if ( ! $firstValueIsNotTime) $h[ 'time'] = 0;
	$h[ 'in'] = fopen( $file, 'r'); 
	return $h;
}
function fbjamnext( $in, $logic, $filter = array()) {	// returns: hash | null   logic: hash | hash string,   filter: hash | hash string
	if ( is_string( $filter)) $filter = tth( $filter);	// string hash
	if ( is_string( $logic)) $logic = tth( $logic);
	while ( $in[ 'in'] && ! feof( $in[ 'in'])) {
		$L = bjamread( $in[ 'in']); if ( ! $L) return null;
		if ( isset( $in[ 'time'])) $in[ 'time'] += 0.000001 * $L[ 0];	// move time if 'time' key exists
		$h = array(); $good = true;
		for ( $i = 0; $i < count( $logic) && $i < count( $L); $i++) {
			$def = $logic[ $i];
			if ( count( ttl( $def, ':')) === 1) { $h[ $def] = $L[ $i]; continue; }
			// this is supposed to be a { id: string} map now
			$k = lshift( ttl( $def, ':')); $v = lpop( ttl( $def, ':'));
			$map = tth( $v);
			if ( ! isset( $map[ $L[ $i]])) { $good = false; break; } // this record is outside of parsing logic
			$h[ $k] = $map[ $L[ $i]];
		}
		if ( ! $good) continue;	// go to the next
		foreach ( $filter as $k => $v) if ( ! isset( $h[ $k]) || $h[ $k] != $v) $good = false;
		if ( ! $good) continue;
		return $h;	// this data sample is fit, return it
	}
	return null;
}
function fbjamclose( &$h) { fclose( $h[ 'in']); }

?>