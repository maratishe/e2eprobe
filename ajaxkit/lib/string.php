<?php

// valid char ranges: { from: to (UTF32 ints), ...} -- valid if terms of containing meaning (symbools and junks are discarded) 
$UTF32GOODCHARS = tth( "65345=65370,65296=65305,64256=64260,19968=40847,12354=12585,11922=12183,1072=1105,235=235,48=57,97=122,44=46"); // UTF-32 INTS!
$UTF32TRACK = array(); 	// to track decisions for specific chars
function utf32isgood( $n) { 	// n: 32-bit integer representation of a char (small endian)
	global $UTF32GOODCHARS, $UTF32TRACK; if ( count( $UTF32TRACK) > 50000) $UTF32TRACK = array();	// if too big, reset
	if ( isset( $UTF32TRACK[ $n])) return $UTF32TRACK[ $n];	// true | false
	$good = false; 
	foreach ( $UTF32GOODCHARS as $low => $high) if ( $n >= $low && $n <= $high) $good = true;
	$UTF32TRACK[ $n] = $good; return $good;
}
function utf32fix( $n, $checkgoodness = true) { 	// returns same number OR 32 (space) if bad symbol
	if ( $checkgoodness) if ( ! utf32isgood( $n)) return 32;	// return space
	if ( $n >= 65345 && $n <= 65370) $n = 97 + ( $n - 65345);	// convert Romaji to single-byte ASCII
	return $n;
}
function utf32ispdfglyph( $n) { return ( $n >= 64256 && $n <= 64260); }
function utf32fixpdf( $n) { // returns UTF-32 string
	$L = ttl( 'ff,fi,fl,ffi,ffl'); if ( $n >= 64256 && $n <= 64260) return mb_convert_encoding( $L[ $n - 64256], 'UTF-32', 'ASCII');	// replacement string
	return bwriteint( bintro( $n)); // string of the current char, no change
}
function utf32clean( $body, $e = null) {	// returns new body
	$body3 = ''; if ( ! mb_strlen( $body)) return $body3;
	$body = mb_strtolower( $body); 
	$body2 = @mb_convert_encoding( $body, 'UTF-32', 'UTF-8'); if ( ! $body2) return '';	// nothing in body
	$count = mb_strlen( $body2, 'UTF-32');
	//echoe( $e, " cleanfilebody($count)");
	for ( $i = 0; $i < $count; $i++) {
		if ( $e && $i == 5000 * ( int)( $i / 5000)) echoe( $e, " cleanfilebody(" . round( 100 * ( $i / $count)) . '%)');
		$char = @mb_substr( $body2, $i, 1, 'UTF-32'); if ( ! $char) continue; 
		$n = bintro( breadint( $char));
		$n2 = utf32fix( $n, true);	// fix range (32 when bad), fix PDF, convert back to string
		if ( $n == $n2 && ! utf32ispdfglyph( $n)) $body3 .= $char;
		else $body3 .= utf32fixpdf( $n2); 
	}
	// get rid of double spaces
	$body2 = trim( @mb_convert_encoding( $body3, 'UTF-8', 'UTF-32')); if ( ! mb_strlen( $body2)) return '';	// nothing left in string 
	$before = mb_strlen( $body2);
	$limit = 1000; while ( $limit--) {
		$body2 = str_replace( '  ', ' ', $body2);
		$after = mb_strlen( $body2); if ( $after == $before) break;	// no more change
		$before = $after;
	}
	//echoe( $e, '');
	if ( $e) { echoe( $e, " cleanfilebody(" . mb_substr( $body2, 0, 50) . '...)'); sleep( 1); }
	return $body2;
}

function sfixpdfglyphs( $s) { 	// fix pdf glyphs like ffi,ff, etc.
	$body2 = @mb_convert_encoding( $s, 'UTF-32', 'UTF-8'); if ( ! $body2) return $s;	// nothing in body
	$body = ''; $count = mb_strlen( $body2, 'UTF-32');
	for ( $i = 0; $i < $count; $i++) {
		$char = @mb_substr( $body2, $i, 1, 'UTF-32'); if ( ! $char) continue; 
		$n = bintro( breadint( $char));
		if ( $n == 8211) $char = mb_convert_encoding( '--', 'UTF-32', 'ASCII');
		//echo  "  $n:" . substr( $s, $i, 1) . "\n";
		if ( ! utf32ispdfglyph( $n)) { $body .= $char; continue; }
		$body .= utf32fixpdf( $n); 
	}
	return trim( @mb_convert_encoding( $body, 'UTF-8', 'UTF-32'));
}

// email processors
function strmailto( $email, $subject, $body) { 	// returns encoded mailto URL -- make sure it is smaller than 10?? bytes
	$text = "$email?subject=$subject&body=$body";
	$setup = array( '://'=> '%3A%2F%2F', '/'=> '%2F', ':'=> '%3A', ' '=> '%20', ','=> '%2C', "\n"=> '%0A', '='=> '%3D', '&'=> '%26', '#'=> '%23', '"'=> '%22');
	foreach ( $setup as $k => $v) $text = str_replace( $k, $v, $text);
	return $text;
}
// base64
function s2s64( $txt) { return base64_encode( $txt); }
function s642s( $txt) { return base64_decode( $txt); }
// string library
function strisalphanumeric( $string, $allowspace = true) {
	$ok = true;
	$alphanumeric = ". a b c d e f g h i j k l m n o p q r s t u v w x y z A B C D E F G H I J K L M N O P Q R S T U V W X Y Z 0 1 2 3 4 5 6 7 8 9 ";
	if ( ! $allowspace) $alphanumeric = str_replace( ' ', '', $alphanumeric);
	for ( $i = 0; $i < strlen( $string); $i++) {
		$letter = substr( $string, $i, 1);
		if ( ! is_numeric( strpos( $alphanumeric, $letter))) { $ok = false; break; }
	}
	return $ok;
}
function strcleanup( $text, $badsymbols, $replace = '') {
	for ( $i = 0; $i < strlen( $badsymbols); $i++) {
		$text = str_replace( substr( $badsymbols, $i, 1), $replace, $text);
	}
	return $text;
}
function strtosqlilike( $text) {	// replaces whitespace with %
	$split = explode( ' ', $text);
	$split2 = array();
	foreach ( $split as $part) {
		$part = trim( $part);
		if ( ! $part) continue;
		array_push( $split2, strtolower( $part));
	}
	return '%' . implode( '%', $split2) . '%';
}
function strdblquote( $text) { return '"' . $text . '"'; }
function strquote( $text) { return "'$text'"; }
?>