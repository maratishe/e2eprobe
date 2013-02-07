<?php
// json object library, requires json.php
$JO = array();
function jsonencode( $data, $tab = 1, $linedelimiter = "\n") { switch ( gettype( $data)) {
	case 'boolean': return ( $data ? 'true' : 'false'); 
	case 'NULL': return "null";
	case 'integer': return ( int)$data;
	case 'double': 
	case 'float': return ( float)$data;
	case 'string': {
		$out = '';
		$len = strlen( $data);
		$special = false;
		for ( $i = 0; $i < $len; $i++) {
			$ord = ord( $data{ $i});
			$flag = false;
			switch ( $ord) {
				case 0x08: $out .= '\b'; $flag = true; break;
				case 0x09: $out .= '\t'; $flag = true; break;
				case 0x0A: $out .=  '\n'; $flag = true; break;
				case 0x0C: $out .=  '\f'; $flag = true; break;
				case 0x0D: $out .= '\r'; $flag = true; break;
				case  0x22:
				case 0x2F:
				case 0x5C: $out .= '\\' . $data{ $i}; $flag = true; break;
			}
			if ( $flag) { $special = true; continue; } // switched case
			
			// normal ascii
			if ( $ord >= 0x20 && $ord <= 0x7F) { 
				$out .= $data{ $i}; continue;
			}
			// unicode
			if ( ( $ord & 0xE0) == 0xC0) {
				$char = pack( 'C*', $ord, ord( $data{ $i + 1}));
				$i += 1;
				$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
				$out .= sprintf( '\u%04s', bin2hex( $utf16));
				$special = true;
				continue;
			}
			if ( ( $ord & 0xF0) == 0xE0) {
				$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}));
				$i += 2;
				$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
				$out .= sprintf( '\u%04s', bin2hex($utf16));
				$special = true;
				continue;
			}
			if ( ( $ord & 0xF8) == 0xF0) {
				$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}));
				$i += 3;
				$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
				$out .= sprintf( '\u%04s', bin2hex( $utf16));
				$special = true;
				continue;
			}
			if ( ( $ord & 0xFC) == 0xF8) {
				$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}), ord( $data{ $i + 4}));
				$c += 4;
				$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
				$out .= sprintf( '\u%04s', bin2hex( $utf16));
				$special = true;
				continue;
			}
			if ( ( $ord & 0xFE) == 0xFC) {
				$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}), ord( $data{ $i + 4}), ord( $data{ $i + 5}));
				$c += 5;
				$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
				$out .= sprintf( '\u%04s', bin2hex( $utf16));
				$special = true;
				continue;
			}
		}
		return '"' . $out . '"';
	}
	case 'array': {
		if ( is_array( $data) && count( $data) && ( array_keys( $data) !== range( 0, sizeof( $data) - 1))) {
			$parts = array();
			foreach ( $data as $k => $v) {
				$part = '';
				for ( $i = 0; $i < $tab; $i++) $part .= "\t";
				$part .= '"' . $k . '"' . ': ' . jsonencode( $v, $tab + 1);
				array_push( $parts, $part);
			}
			return "{" . $linedelimiter . implode( ",$linedelimiter", $parts) . '}';
		}
		// not a hash, but an array
		$parts = array();
		foreach ( $data as $v) {
			$part = '';
			for ( $i = 0; $i < $tab; $i++) $part .= "\t";
			array_push( $parts, $part . jsonencode( $v, $tab + 1));
		}
		return "[$linedelimiter" . implode( ",$linedelimiter", $parts) . ']';
	}
	
}}
// JSON functions (class at the end) (requires json class to be imported)
function jsonparse( $text) { return json_decode( $text, true); }
function jsonload( $filename, $ignore = false, $lock = false) {	// load from file and then parse 
 	global $ASLOCKON, $IOSTATSON, $IOSTATS;
 	$lockd = $ignore ? $lock : $ASLOCKON;	// lock decision, when ignore is on, listen to local flag
	$time = null; if ( $lockd) list( $time, $lock) = aslock( $filename);
 	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.aslock,time=$time"));
 	$start = null; if ( $IOSTATSON) $start = tsystem();
 	$body = ''; $in = @fopen( $filename, 'r'); while ( $in && ! feof( $in)) $body .= trim( fgets( $in));
	if ( $in) fclose( $in);
	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.fread,time=" . round( tsystem() - $start, 4)));
	if ( $lockd) asunlock( $filename, $lock);
	$info = $body ? @jsonparse( $body) : null;
	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.done,took=" . round( 1000000 * ( tsystem() - $start)) . ',size=' . ( $body ? strlen( $body) : 0)));
	return $info;
}
function jsondump( $jsono, $filename, $ignore = false, $lock = false) {	// dumps to file, does not use JSON class
	global $ASLOCKON, $IOSTATSON, $IOSTATS;
	$lockd = $ignore ? $lock : $ASLOCKON;	// lock decision, when ignore is on, listen to local flag
	$time = null; if ( $lockd)  list( $time, $lock) = aslock( $filename);
	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.aslock,time=$time"));
	$start = null; if ( $IOSTATSON) $start = tsystem();
	$text = jsonencode( $jsono);
	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.jsonencode,time=" . round( tsystem() - $start, 4)));
	$out = fopen( $filename, 'w'); fwrite( $out, $text); fclose( $out);
	if ( $lockd) asunlock( $filename, $lock);
	if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.done,took=" . round( 1000000 * ( tsystem() - $start)) . ',size=' . strlen( $text)));
}
function jsonsend( $jsono, $header = false) {	// send to browser, do not use JSON class
	if ( $header) header( 'Content-type: text/html');
	echo jsonencode( $jsono);
}
function jsonsendbycallback( $jsono) {	// send to browser, do not use JSON class
	$txt = $jsono === null ? null : base64_encode( json_encode( $jsono));
	echo "eval( callback)( '$txt')\n";
}
function jsonsendbycallbackm( $items, $asjson = false) {	// send to browser, do not use JSON class, send a LIST of items, first aggregating, then calling a callback
	echo "var list = [];\n";
	foreach ( $items as $item) echo "list.push( " . ( $asjson ? json_encode( $item) : $item) . ");\n";
	echo "eval( callback)( list);\n";
}

// json2h and back translations
function h2json( $h, $base64 = false, $base64keys = '', $singlequotestrings = false, $bzip = false) {
	if ( ! $base64keys) $base64keys = array();
	if ( $base64keys && is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
	foreach ( $base64keys as $k) $h[ $k] = base64_encode( $h[ $k]);
	if ( $singlequotestrings) foreach ( $h as $k => $v) if ( is_string( $v)) $h[ $k] = "'$v'";
	$json = jsonencode( $h);
	if ( $bzip) $json = bzcompress( $json);
	if ( $base64) $json = base64_encode( $json);
	return $json;
}
function json2h( $json, $base64 = false, $base64keys = '', $bzip = false) {
	if ( ! $base64keys) $base64keys = array();
	if ( $base64keys && is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
	if ( $base64) $json = base64_decode( $json);
	if ( $bzip) $json = bzdecompress( $json);
	$h = @jsonparse( $json);
	if ( $h) foreach ( $base64keys as $k) $h[ $k] = base64_decode( $h[ $k]);
	return $h;
}

// read entire json64 files
function b64jsonload( $file, $json = true, $base64 = true, $bzip = false) {
	$in = finopen( $file); $HL = array();
	while ( ! findone( $in)) { 
		list( $h, $progress) = finread( $in, $json, $base64, $bzip); if ( ! $h) continue;
		lpush( $HL, $h);
	}
	finclose( $in); return $HL;
}
function b64jsonldump( $HL, $file, $json = true, $base64 = true, $bzip = false) {
	$out = foutopen( $file, 'w'); foreach ( $HL as $h) foutwrite( $out, $h, $json, $base64, $bzip); foutclose( $out);
}


// json object functions, all return $JO (for shorthand)
function jsonerr( $err) { 
	global $JO;
	if ( ! isset( $JO[ 'errs'])) $JO[ 'errs'] = array();
	array_push( $JO[ 'errs'], $err);
	return $JO;
}
function jsonmsg( $msg) {
	global $JO;
	if ( ! isset( $JO[ 'msgs'])) $JO[ 'msgs'] = array();
	array_push( $JO[ 'msgs'], $msg);
	return $JO;
}
function jsondbg( $msg) {
	global $JO;
	if ( ! isset( $JO[ 'dbgs'])) $JO[ 'dbgs'] = array();
	array_push( $JO[ 'dbgs'], $msg);
	return $JO;
}


?>