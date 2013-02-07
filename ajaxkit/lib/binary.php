<?php

// string readers and writers, writer can write multiple bytes into one string (unicode)
function breadbyte( $s) {	// returns interger of one byte or null
	$v = @unpack( 'Cbyte', $s);
	return isset( $v[ 'byte']) ? $v[ 'byte'] : null;
}
function breadbytes( $s, $count = 4) { 	// returns list of bytes, up to four -- if more, do integers or split smaller
	$ks = ttl( 'one,two,three,four');
	$def = ''; for ( $i = 0; $i < $count; $i++) $def .= 'C' . $ks[ $i];
	$v = @unpack( $def, $s); if ( ! $v || ! is_array( $v)) return null;
	return hv( $v);	// return list of values
}
function breadint( $s) { $v = @unpack( 'Inumber', $s); return isset( $v[ 'number']) ? $v[ 'number'] : null; }
// one can be an array, will create tw\o, three, etc. from it, but no more than 6 bytes
function bwritebytes( $one, $two = null, $three = null, $four = null, $five = null, $six = null) {
	if ( is_array( $one)) {	// extract one,two,three,.... from array of one
		$L = ttl( 'one,two,three,four,five,six'); while ( count( $L) > count( $one)) lpop( $L);
		$h = array(); for ( $i = 0; $i < count( $L); $i++) $h[ $L[ $i]] = $one[ $i];
		extract( $h);
	}
	if ( $two === null) return pack( "C", $one);
	if ( $three === null) return pack( "CC", $one, $two);
	if ( $four === null) return pack( "CCC", $one, $two, $three);
	if ( $five === null) return pack( "CCCC", $one, $two, $three, $four);
	if ( $six === null) return pack( "CCCCC", $one, $two, $three, $four, $five);
	return pack( "CCCCCC", $one, $two, $three, $four, $five, $six);
}
function bwriteint( $n) { return pack( 'I', $n); } 	// back 4 bytes of integer into a binary string (also UTF-32)
function bintro( $n) { 	// binary reverse byte order of integer
	return bmask( btail( $n >> 24, 8), 24, 8) + bmask( btail( $n >> 16, 8) << 8, 16, 8) + bmask( btail( $n >> 8, 8) << 16, 8, 8) + bmask( btail( $n, 8) << 24, 0, 8);
}


// slightly better binary writer
// header: variable number of bytes, bitstring is split into multiple of 8, first 3 bits contain the number of data points (up to 8, 0 is not an option)
//              total byte-length of header is: round( length.of.bitstring / 8, next.full.8)
//              code for each field: 000: null, 011: true, 100: 1 byte, 101: 2 bytes, 110: 3 bytes, 111: 4 bytes
//  h: { key: value, ...}, will look only at values (array===hash), order of keys should be managed by the writer
function bjamwrite( $out, $h, $donotwriteheader = false) { 	// write values from this hash (array is a kind of hash), returns header bytes
	foreach ( $h as $k => $v) if ( is_numeric( $v)) $h[ $k] = ( int)$v;	// make sure all numbers are round\
	$header = bjamheaderwrite( $out, $h, $donotwriteheader);
	//die( '   header:' . json_encode( $header));
	$count = btail( $header[ 0] >> 5, 3); $bs = bjamheader2bitstring( $header); $vs = hv( $h);
	//die( "  bs[$bs]\n");
	for ( $i = 0; $i < $count; $i++) {
		$code = bjamstr2code( substr( $bs, 3 + 3 * $i, 3));
		$count2 = $code - 4 + 1; if ( $count2 < 0) $count2 = 0;
		for ( $ii = $count2 - 1; $ii >= 0; $ii--) bfilewritebyte( $out, btail( $vs[ $i] >> ( 8 * $ii), 8));	// if count2 = 0 (NULL), nothing is written
	}
	return $header;
}
function bjamread( $in, $header = null) { 	// read one set (with header) from the file, return list of values
	if ( ! $header) $header = bjamheaderead( $in);
	//die( " header[" . json_encode( $header) . "]\n");
	$count = btail( $header[ 0] >> 5, 3); $bs = bjamheader2bitstring( $header); $vs = array();
	//echo " count[$count] bs[$bs]";
	for ( $i = 0; $i < $count; $i++) {
		$code = bjamstr2code( substr( $bs, 3 + 3 * $i, 3));
		if ( $code == 0) { lpush( $vs, null); continue; } // no actual data, deduct from flags
		if ( $code == 3) { lpush( $vs, true); continue; }
		$count2 = $code - 4 + 1; if ( $count2 < 0) $count2 = 0; $v = array();
		for ( $ii = 0; $ii < $count2; $ii++) lpush( $v, bfilereadbyte( $in));
		while ( count( $v) < 4) lunshift( $v, 0);
		$v = bhead( $v[ 0] << 24, 8) | bmask( $v[ 1] << 16, 8, 8) | bmask( $v[ 2] << 8, 16, 8) | btail( $v[ 3], 8);
		lpush( $vs, $v);
		//echo "   code[$code] v[$v]";
	}
	//die( "\n");
	return $vs;
}
function bjamheaderwrite( $out, $h, $donotwrite = false) { // returns [ byte1, byte2, byte3, ...] as many bytes as needed	
	$ks = hk( $h); while ( count( $ks) > 7) lpop( $ks);
	$hs = bbitstring( count( $ks), 3); 
	foreach ( $ks as $k) $hs .= bbitstring( bjamint2code( $h[ $k]), 3);
	//die( "   h[" . json_encode( $h) . "] hs[$hs]\n");
	$bytes = array();
	for ( $i = 0; $i < strlen( $hs); $i += 8) {
		$byte = array(); for ( $ii = 0; $ii < 8; $ii++) lpush( $byte, ( $i + $ii < strlen( $hs)) ? ( substr( $hs, $i + $ii, 1) == '0' ? 0 : 1) : 0);
		lpush( $bytes, bwarray2byte( $byte));
	}
	if ( $donotwrite) return $bytes;	// return header bytes without writing to file
	foreach ( $bytes as $byte) bfilewritebyte( $out, $byte);
	return $bytes;
}
function bjamheaderead( $in) { 	// returns [ byte1, byte2, byte3, ...]
	$bytes = array( bfilereadbyte( $in));	// first byte
	$count = btail( $bytes[ 0] >> 5, 3);	// count of items
	$bitcount = 3 + 3 * $count;
	$bytecount = $bitcount / 8; if ( $bytecount > ( int)$bytecount) $bytecount = 1 + ( int)$bytecount;
	$bytecount = round( $bytecount);	// make it round just in case
	for ( $i = 1; $i < $bytecount; $i++) lpush( $bytes, bfilereadbyte( $in));
	return $bytes;
}
function bjamheader2bitstring( $bytes) { // returns '01011...' bitstring of the header, some bits at the end may be unused
	$bs = '';
	foreach ( $bytes as $byte) { $byte = bwbyte2array( $byte); foreach ( $byte as $bit) $bs .= $bit ? '1' : '0'; }
	return $bs;
}
function bjamint2code( $v) { // returns 3-bit binary code for this (int) value
	if ( $v === null || $v === false) return 0;	// 000
	if ( $v === true) return 3;	// 011
	$count = 1; 
	if ( btail( $v >> 8, 8)) $count = 2;
	if ( btail( $v >> 16, 8)) $count = 3;
	if ( btail( $v >> 24, 8)) $count = 4;
	return 4 + ( $count - 1);  // between 100 and 111
}
function bjamstr2code( $s) { // converts 3-char string into a code
	$byte = array(); for ( $i = 0; $i < 5; $i++) lpush( $byte, 0);
	for ( $i = 0; $i < 3; $i++) lpush( $byte, substr( $s, $i, 1) == '0' ? 0 : 1);
	return bwarray2byte( $byte);
}
function bjamcode2count( $code) { return $code >= 4 ? $code - 4 + 1 : 0; }
function bjamcount2code( $count) { return $count > 0 ? 4 + $count - 1 : 0; } 


// for working with binary/hex info
function bfilereadint( $in) {
	$s = fread( $in, 4);
	return breadint( $s);
}
function bfilewriteint( $out, $v) {
	$s = pack( "I", $v); 
	fwrite( $out, $s);
	return $s;
}
function bfilereadbyte( $in) {	// return interger 
	$s = fread( $in, 1);	
	return breadbyte( $s);
}
function bfilewritebyte( $out, $v) {
	fwrite( $out, bwritebytes( $v));
}
// optimial binary, to use only as few bytes as possible
function boptfilereadint( $in, $flags = null) { // return integer, if $flags = null, read byte with flags first
	if ( $flags === null) $flags = bwbyte2array( bfilereadbyte( $in), true);	// as numbers
	$count = 0;
	if ( is_array( $flags)) for ( $i = 0; $i < count( $flags); $i++) $flags[ $i] = $flags[ $i] ? 1 : 0; // make sure those are numbers, not boolean values
	if ( is_array( $flags) && count( $flags) > 2 && $flags[ 0] && $flags[ 1] && $flags[ 2]) $count = 4;
	else if ( is_array( $flags)) $count = $flags[ 0] * 2 + $flags[ 1];
	else $count = $flags;	// number of bytes to read can be passed as integer
	$v = 0;
	if ( $count > 0) $v = bfilereadbyte( $in);
	if ( $count > 1) $v = bmask( bfilereadbyte( $in) << 8, 16, 8) | $v;
	if ( $count > 2) $v = bmask( bfilereadbyte( $in) << 16, 8, 8) | $v;
	if ( $count > 3) $v = bmask( bfilereadbyte( $in) << 24, 0, 8) | $v;
	return $v;
}
function boptfilewriteint( $out, $v, $writeflags = true, $donotwrite = false, $count = null, $maxcount = 4) { // if writeflags=false, will return flags and will not write them
	$flags = array();
	// set flags first
	$flags = array( false, false);
	if ( ! $count) {	// calculate the count
		$count = 0;
		if ( btail( $v, 8) && $maxcount > 0) { $flags = array( false, true); $count = 1; }
		if ( btail( $v >> 8, 8) && $maxcount > 1) { $flags = array( true, false); $count = 2; }
		if ( btail( $v >> 16, 8) && $maxcount > 2) { $flags = array( true, true); $count = 3; }
		if ( btail( $v >> 24, 8) && $maxcount > 3) { $flags = array( true, true, true); $count = 4; }
	}
	while ( count( $flags) < 8) lpush( $flags, false);	// fillter
	if ( $donotwrite) return $flags;	// do not do the actual writing
	if ( $writeflags) bfilewritebyte( $out, bwarray2byte( $flags));
	// now write bytes of the number, do not write anything if zero size
	if ( $count > 0) bfilewritebyte( $out, btail( $v, 8));
	if ( $count > 1) bfilewritebyte( $out, btail( $v >> 8, 8));
	if ( $count > 2) bfilewritebyte( $out, btail( $v >> 16, 8));
	if ( $count > 3) bfilewritebyte( $out, btail( $v >> 24, 8));
	return $flags;
}
// bitwise operations, flags to arrays and back
function bwbyte2array( $v, $asnumbers = false) { // returns array of flags
	$L = array();
	for ( $i = 0; $i < 8; $i++) { 
		lunshift( $L, ( $v & 0x01) ? ( $asnumbers ? 1 : true) : ( $asnumbers ? 0 : false));
		$v = $v >> 1;
	}
	return $L;
}
function bwarray2byte( $flags) { // returns number representing the flags
	$number = 0;
	while ( count( $flags)) {
		$number = $number << 1;
		$flag = lshift( $flags);
		if ( $flag) $number = $number | 0x01;
		else $number = $number | 0x00;
	}
	return $number;
}
// integer variables
function bfullint() { return ( 0xFF << 24) + ( 0xFF << 16) + ( 0xFF << 8) + 0xFF; }
function bemptyint() { return ( 0x00 << 24) + ( 0x00 << 16) + ( 0x00 << 8) + 0x00; }
function b01( $pos, $length) { // return int where bit string has $length bits starting from pos
	$v = 0x01;
	for ( $i = 0; $i < $length - 1; $i++) $v = ( $v << 1) | 0x01;
	for ( $i = 0; $i < ( 32 - $pos - $length); $i++) $v = ( ( $v << 1) | 0x01) ^ 0x01; // sometimes << bit shift in PHP results in 1 at the tail, this weird notation will work with or without this bug
	return $v;
}
function bmask( $v, $pos, $length) { // returns value where only $length bits from $pos are left, and the rest are zero
	$mask = b01( $pos, $length);
	return $v & $mask;
}
function bhead( $v, $bits) { return bmask( $v, 0, $bits); }
function btail( $v, $bits) { return bmask( $v, 32 - $bits, $bits); }
function bbitstring( $number, $length = 32, $separatelength = 0) { 	// from end
	$out = ''; $separator = $separatelength;
	for ( $i = 0; $i < $length; $i++) {
		$number2 = $number & 0x01;
		if ( $number2) $out = "1$out";
		else $out = "0$out";
		$separator--; if ( $separator == 0 && $i < $length - 1) { $out = ".$out"; $separator = $separatelength; }
		$number = $number >> 1;
	}
	return $out;
}

// converters
function bint2hex( $number) { return sprintf( "%X", $number); } // only integer types 
function bint2bytestring( $number) { 	// returns string containing byte sequence from integer (from head to tail bits)
	return bwritebytes( bmask( $number >> 24, 24, 8), bmask( $number >> 16, 24, 8), bmask( $number >> 8, 24, 8), bmask( $number, 24, 8));
}
function bbytestring2int( $s) {
	$v = @unpack( 'Cone/Ctwo/Cthree/Cfour', $s);
	extract( $v);
	return bmask( $one << 24, 0, 8) | bmask( $two << 16, 8, 8) | bmask( $three << 8, 16, 8) | bmask( $four, 24, 8);
}
function bint2bytelist( $number, $count = 4) { $L = array(); for ( $i = 0; $i < $count; $i++) lunshift( $L, btail( $number >> ( 8 * $i), 8)); return $L; }


/** packets: specific binary format for writing packet trace information compactly  2012/03/31 moved to fin/fout calls
* the main idea: use boptfile but collect and store all flag bits separately (do not allow boptfile read/write bits from file)
* flags are collected into 2 first bytes in the following structure:
*   BYTE 0: (1) protocol, (7) length of the record
*   BYTE 1: (2) pspace, (2) sport, (2) dport, (2) psize
*  *** sip and dip are written in fixed 4 bytes and do not require flags
*/
function bpacketsinit( $filename) { return fopen( $filename, 'w'); } // noththing to do, just open the new file
function bpacketsopen( $filename) { return fopen( $filename, 'r'); } // binary safe
function bpacketsclose( $handle) { fclose( $handle); }
function bpacketswrite( $out, $h) { // h { pspace, sip, sport, dip, dport, psize, protocol}
	$L = ttl( 'pspace,sip,sport,dip,dport,psize'); foreach ( $L as $k) $h[ $k] = ( int)$h[ $k]; // force values to integers 
	extract( $h);
	$flags = array( 0, 0);
	$flags[ 0] = $protocol == 'udp' ? 0x00 : bmask( 0xff, 24, 1);
	// first, do the flag run
	$size = 4;
	$f = boptfilewriteint( null, $pspace, true, true, null, 3); // pspace  (max 3 bytes = 2 flag bits)
	$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | $v;
	$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
	$size += 4;	// sip
	$f = boptfilewriteint( null, $sport, true, true, null, 3); // sport
	$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 2);
	$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
	$size += 4;	// dip
	$f = boptfilewriteint( null, $dport, true, true, null, 3); // dport
	$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 4);
	$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
	$f = boptfilewriteint( null, $psize, true, true, null, 3); // psize
	$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 6);
	$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
	// remember the length of the line
	$flags[ 0] = $flags[ 0] | $size;
	// now, write the actual data
	bfilewritebyte( $out, $flags[ 0]);
	bfilewritebyte( $out, $flags[ 1]);
	boptfilewriteint( $out, $pspace, false, false, null, 3); // pspace
	boptfilewriteint( $out, $sip, false, false, 4); // sip
	boptfilewriteint( $out, $sport, false, false, null, 3); // sport
	boptfilewriteint( $out, $dip, false, false, 4); // dip
	boptfilewriteint( $out, $dport, false, false, null, 3); // dport
	boptfilewriteint( $out, $psize, false, false, null, 3); // psize
}
function bpacketsread( $in) { // returns { pspace, sip, sport, dip, dport, psize, protocol}
	if ( ! $in || feof( $in)) return null; // no data
	$v = bfilereadbyte( $in); $f = bwbyte2array( $v, true);
	$protocol = $f[ 0] ? 'tcp' : 'udp';	// protocol
	$f[ 0] = 0;
	$linelength = bwarray2byte( $f);	// line length
	if ( ! $linelength) return null;	// no data
	$h = array();
	$h[ 'protocol'] = $protocol;
	$v = bfilereadbyte( $in); $f = bwbyte2array( $v, true);
	$h[ 'pspace'] = boptfilereadint( $in, array( $f[ 0], $f[ 1], 0, 0, 0, 0, 0, 0));
	$h[ 'sip'] = boptfilereadint( $in, array( 1, 1, 1, 0, 0, 0, 0, 0));
	$h[ 'sport'] = boptfilereadint( $in, array( $f[ 2], $f[ 3], 0, 0, 0, 0, 0));
	$h[ 'dip'] = boptfilereadint( $in, array( 1, 1, 1, 0, 0, 0, 0, 0));
	$h[ 'dport'] = boptfilereadint( $in, array( $f[ 4], $f[ 5], 0, 0, 0, 0, 0, 0));
	$h[ 'psize'] = boptfilereadint( $in, array( $f[ 6], $f[ 7], 0, 0, 0, 0, 0, 0));
	return $h;
}

/** flows: specific binary format for storing binary information about packet flows
* main idea: to use boptfile* optimizers but without writing flags with information, instead, flags are aggregated into structure below
*  BYTE 0: (1) protocol  (2) sport, (2) dport, (3) bytes
*  BYTE 1: (1) startimeus invert (if 1, 1000000 - value) (3) length of startimeus (1) durationus invert (3) length of durationus   000 means no value = BYTE 2 flags not set == value not written into file  
*  BYTE 2: (2) packets, (2) startimeus (optional) (2) duration(s) (2) duration(us) (optional)  -- optionals depend on lengths in BYTE1
*  ** sip, dip, and startime(s) are written in 4 bytes and do not require flags (not compressed)
*/
function bflowsinit( $timeoutms, $filename) { // create new file, write timeout(ms) as first 2 bytes (65s max)s, return file handle
	$out = fopen( $filename, 'w');
	$timeout = ( int)$timeoutms;	// should not be biggeer than 65565s
	bfilewritebyte( $out, btail( $timeout >> 8, 8));
	bfilewritebyte( $out, btail( $timeout, 8));
	return $out;
}
function bflowsopen( $filename) { 	// returns [ handler, timeout (ms)]
	$in = fopen( $filename, 'r');
	$timeout = bmask( bfilereadbyte( $in) << 8, 16, 8) + bfilereadbyte( $in);
	return array( $in, $timeout);
}
function bflowsclose( $handle) { fclose( $handle); }
function bflowswrite( $out, $h, $debug = false) { // needs { sip, sport, dip, dport, bytes, packets, startime, lastime, protocol}
	extract( $h); if ( ! isset( $protocol)) $protocol = 'tcp';
	if ( $debug) echo "\n";
	$flags = array( 0, 0, 0);	// flags
	$flags[ 0] = $protocol == 'udp' ? 0x00 : bmask( 0xff, 24, 1); 
	$startime = round( $startime, 6);	// not more than 6 digits
	$startimes = ( int)$startime;	// startimes 
	$startimeus = round( 1000000 * ( $startime - ( int)$startime)); if ( $startimeus > 999999) $startimeus = 999999; 
	while ( strlen( "$startimeus") < 6) $startimeus = "0$startimeus";
	while ( strlen( "$startimeus") && substr( "$startimeus", strlen( $startimeus) - 1, 1) == '0') $startimeus = substr( $startimeus, 0, strlen( $startimeus) - 1);
	$duration = round( $lastime - $startime, 6);
	$durations = ( int)$duration; 	// durations
	$durationus = round( 1000000 * ( $duration - ( int)$duration)); if ( $durationus > 999999) $durationus = 999999; 
	while ( strlen( "$durationus") < 6) $durationus = "0$durationus";
	while ( strlen( "$durationus") && substr( "$durationus", strlen( $durationus) - 1, 1) == '0') $durationus = substr( $durationus, 0, strlen( $durationus) - 1);
	if ( $debug) echo "bflowswrite() : setup : startimes[$startimes] startimeus[$startimeus]   durations[$durations] durationus[$durationus]\n";
	// first, do the flag run
	$f = boptfilewriteint( null, $sport, true, true, null, 3); // sport  (max 3 bytes = 2 flag bits)
	$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 1);
	$f = boptfilewriteint( null, $dport, true, true, null, 3); // dport
	$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 3);
	$f = boptfilewriteint( null, $bytes, true, true); // bytes -- this one can actually be 4 bytes = 3 flag bits
	$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 5);
	$f = boptfilewriteint( null, $packets, true, true, null, 3); // packets
	$v = bwarray2byte( $f); $flags[ 2] = $flags[ 2] | $v;
	if ( $debug) echo "bflowswrite() : startimeus : ";
	$startimeus2 = null; if ( strlen( $startimeus)) {	// store us of startime (check which one is shorter) 
		$v = null; $v1 = ( int)$startimeus; $v2 = ( int)( 999999 - $v1); 
		if ( $debug) echo " v1[$v1] v2[$v2]";
		if ( strlen( "$v1") <= strlen( "$v2")) $v = $v1;	// v1 is shorter, do not invert
		else { $flags[ 1] = $flags[ 1] | bmask( 0xff, 24, 1); $v = $v2; }
		$flags[ 1] = $flags[ 1] | bmask( strlen( $startimeus) << 4, 25, 3); // read length of value
		if ( $debug) echo " v.before.write[$v]"; 
		$f = boptfilewriteint( null, $v, true, true, null, 3); $flags[ 2] = $flags[ 2] | ( bwarray2byte( $f) >> 2);  
		$startimeus2 = $v;
		if ( $debug) echo "  f[" . bbitstring( bwarray2byte( $f), 8) . "]   flags1[" . bbitstring( $flags[ 1], 8) . "] flags2[" . bbitstring( $flags[ 2], 8) . "]\n";
	}
	$f = boptfilewriteint( null, $durations, true, true, null, 3); // durations
	$v = bwarray2byte( $f); $flags[ 2] = $flags[ 2] | ( $v >> 4);
	$durationus2 = null; if ( strlen( $durationus)) {	// store duration 
		$v = null; $v1 = ( int)$durationus; $v2 = ( int)( 999999 - $v1); 
		if ( strlen( "$v1") <= strlen( "$v2")) $v = $v1;	// v1 is shorter, do not invert
		else { $flags[ 1] = $flags[ 1] | ( bmask( 0xff, 24, 1) >> 4); $v = $v2; }
		$flags[ 1] = $flags[ 1] | btail( strlen( $durationus), 3);
		$f = boptfilewriteint( null, $v, true, true, null, 3); $flags[ 2] = $flags[ 2] | ( bwarray2byte( $f) >> 6);  
		$durationus2 = $v;
		if ( $debug) echo "bflowswrite() : durationus : v1[$v1] v2[$v2] v[$v]   flags1[" . bbitstring( $flags[ 1], 8) . "] flags2[" . bbitstring( $flags[ 2], 8) . "]\n";
	}
	// now, write the actual data
	bfilewritebyte( $out, $flags[ 0]);
	bfilewritebyte( $out, $flags[ 1]);
	bfilewritebyte( $out, $flags[ 2]);
	if ( $debug) echo "bflowswrite() : flags : b1[" . bbitstring( $flags[ 0], 8) . "] b2[" . bbitstring( $flags[ 1], 8) . "] b3[" . bbitstring( $flags[ 2], 8) . "]\n";
	boptfilewriteint( $out, $sip, false, false, 4);
	boptfilewriteint( $out, $sport, false, false, null, 3);
	boptfilewriteint( $out, $dip, false, false, 4);
	boptfilewriteint( $out, $dport, false, false, null, 3);
	boptfilewriteint( $out, $bytes, false);	// do not limit, allow 4 bytes of data
	boptfilewriteint( $out, $packets, false, false, null, 3);
	boptfilewriteint( $out, $startimes, false, false, 4);
	if ( strlen( $startimeus)) boptfilewriteint( $out, $startimeus2, false, false, null, 3); // only if this is a none-zero string
	boptfilewriteint( $out, $durations, false, false, null, 3);
	if ( strlen( $durationus)) boptfilewriteint( $out, $durationus2, false, false, null, 3);
}
function bflowsread( $in, $debug = false) { // returns { sip,sport,dip,dport,bytes,packets,startime,lastime,protocol,duration}
	if ( $debug) echo "\n\n";
	if ( ! $in || feof( $in)) return null; // no data
	$b1 = bfilereadbyte( $in); $f1 = bwbyte2array( $b1, true); // first byte of flags
	$b2 = bfilereadbyte( $in); $f2 = bwbyte2array( $b2, true);	// second byte of flags
	$b3 = bfilereadbyte( $in); $f3 = bwbyte2array( $b3, true);	// third byte of flags
	if ( $debug) echo "bflowsread() : setup :   B1 " . bbitstring( $b1, 8) . "   B2 " . bbitstring( $b2, 8) . "   B3 " . bbitstring( $b3, 8) . "\n";
	$h = tth( 'sip=?,sport=?,dip=?,dport=?,bytes=?,packets=?,startime=?,lastime=?,protocol=?,duration=?');	// empty at first
	$h[ 'protocol'] = btail( $b1 >> 7, 1) ? 'tcp': 'udp';
	$h[ 'sip'] = boptfilereadint( $in, 4);
	$h[ 'sport'] = boptfilereadint( $in, btail( $b1 >> 5, 2));
	$h[ 'dip'] = boptfilereadint( $in, 4);
	$h[ 'dport'] = boptfilereadint( $in, btail( $b1 >> 3, 2));
	$h[ 'bytes'] = boptfilereadint( $in, bwbyte2array( $b1 << 5));
	$h[ 'packets'] = boptfilereadint( $in, btail( $b3 >> 6, 2));
	// startime -- complex parsing logic
	if ( $debug) echo "bflowsread() : startime : ";
	$v = boptfilereadint( $in, 4); $v2 = btail( $b2 >> 4, 4); $v3 = '';
	if ( $debug) echo " v2[$v2]";
	if ( $v2) { // parse stuff after decimal point 
		$v3 = boptfilereadint( $in, btail( $b3 >> 4, 2)); 
		if ( $debug) echo " v3[$v3]";
		if ( btail( $v2 >> 3, 1)) $v3 = 999999 - $v3; // invert
		if ( $debug) echo " v3[$v3]";
		$v2 = btail( $v2, 3);
		if ( $debug) echo " v2[$v2]"; 
		while ( strlen( "$v3") < $v2) $v3 = "0$v3";
		if ( $debug) echo " v3[$v3]";
	}
	if ( $debug) echo "   b2[" . bbitstring( $b2, 8) . "] b3[" . bbitstring( $b3, 8) . "]\n";
	$h[ 'startime'] = ( double)( $v . ( $v3 ? ".$v3" : ''));
	// duration us -- complex logic
	if ( $debug) echo "bflowsread() : duration : ";
	$v = boptfilereadint( $in, btail( $b3 >> 2, 2)); $v2 = btail( $b2, 4); $v3 = '';
	if ( $debug) echo " v[$v] v2[$v2] v3[$v3]";
	if ( $v2) { // parse stuff after decimal point 
		$v3 = boptfilereadint( $in, btail( $b3, 2)); 
		if ( $debug) echo " v3[$v3]";
		if ( btail( $v2 >> 3, 1)) $v3 = 999999 - $v3; // invert
		if ( $debug) echo " v3[$v3]";
		$v2 = btail( $v2, 3); while ( strlen( "$v3") < $v2) $v3 = "0$v3";
		if ( $debug) echo " v3[$v3]";
	}
	if ( $debug) echo " v3[$v3]\n";
	$h[ 'duration'] = ( double)( $v . ( $v3 ? ".$v3" : ''));
	$h[ 'lastime'] = $h[ 'startime'] + $h[ 'duration'];
	if ( $debug) echo "bflowsread() : finals : duration[" . $h[ 'duration'] . "] lastime[" . $h[ 'lastime'] . "]\n";
	return $h;
}

?>