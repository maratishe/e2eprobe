<?php
// library for handling hash-CSV files
// * hash csv is when each line is in format key1=value1,key2=value2,... etc

// line-by-line reading of hcsv files
function hcsvopen( $filename, $critical = false) {	// returns filehandler
	$in = @fopen( $filename, 'r');
	if ( $critical && ! $in) die( "could not open [$filename]");
	return $in;
}
function hcsvnext( $in, $key = '', $value = '', $notvalue = '') { 	// returns line hash, next by key or value is possible
	if ( ! $in) return null;
	while ( $in && ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( ! $line || strpos( $line, '#') === 0) continue;
		$hash = tth( $line);
		if ( ! $hash || ! count( array_keys( $hash))) continue;
		if ( $key) {
			if ( ! isset( $hash[ $key])) continue;
			if ( $value && $hash[ $key] != $value) continue;
			if ( $notvalue && $hash[ $key] == $value) continue;
			return $hash;
		}
		else return $hash;
	}
	return null;
}
function hcsvclose( $in) { @fclose( $in); }

// one-liners, read entire hcsv files
function hcsvread( $filename, $key = '', $value = '') {	 // returns hash list, can filter by [ key [= value]]
	$lines = array();
	$hcsv = hcsvopen( $filename);
	while ( 1) {
		$h = hcsvnext( $hcsv, $key, $value);
		if ( ! $h) break;
		array_push( $lines, $h);
	}
	hcsvclose( $hcsv);
	return $lines;
}


?>