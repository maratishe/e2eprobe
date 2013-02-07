<?php
// eveyrthing that has to do with network graphs
class IDGen { 
	public $id = 0;
	public $used = array();
	public function next() { $this->id++; return $this->id; }
	public function random( $digits) { 
		$limit = 1000; 
		while ( $limit--) {	// generate a new key 
			$k = mr( $digits); if ( isset( $this->used[ "$k"])) continue;
			return $k;
		}
		die( " ERROR! IDGen() : cannot generate a random key after 1000 attemps, [" . count( $this->used) . "] key have already been used!\n\n");
	}
	public function reset() { $this->id = 0; $this->used = array(); }
}
class Location {
	public $coordinates;
	public function __construct( $one = 0, $two = 0, $three = '') {
		if ( is_array( $one)) return $this->coordinates = $one;
		if ( count( explode( ':', $one)) > 1) return $this->coordinates = explode( ':', $one);
		$this->coordinates = array( $one);
		if ( $two !== '') array_push( $this->coordinates, $two);
		if ( $three !== '') array_push( $this->coordinates, $three);
	}
	public function isEmpty() { 
		if ( count( $this->coordinates) == 1 && ! $this->coordinates[ 0]) return true;
		return false;
	}
	public function distance( $location, $precision = 4) {
		$dimension = mmax( array( count( $this->coordinates), count( $location->coordinates)));
		$sum = 0;
		for ( $i = 0; $i < $dimension; $i++) {
			$sum += pow( 
				isset( $this->coordinates[ $i]) ? $this->coordinates[ $i] : 0 - 
				isset( $location->coordinates[ $i]) ? $location->coordinates[ $i] : 0,
				2
			);
		}
		return $sum ? round( pow( $sum, 0.5), $precision) : 1;
	}
	public function dimension() { return count( $this->coordinates); }
}
class Node {
	public $id;
	public $location;	// Location object
	public $in;			// hash (id) of Edge objects
	public $out; 		// hash (id) of Edge objects
	// constructor
	public function __construct( $IDGen) { 
		$this->id = is_numeric( $IDGen) ? ( int)$IDGen : $IDGen->next();
		$this->in = array(); $this->out = array();
		$this->location = new Location();	// empty location just in case
	}
	public function place( $location) { $this->location = $location; }
	public function addIn( $L) { $this->in[ $L->id] = $L; $L->target = $this; }
	public function addOut( $L) { $this->out[ $L->id] = $L; $L->source = $this; }
	public function isLink( $N) { // out connecting to this node
		foreach ( $this->out as $id => $L) if ( $L->target->id == $N->id) return true;
		return false;
	}
	public function getLink( $N) { // out connecting this with N
		foreach ( $this->out as $id => $L) if ( $L->target->id == $N->id) return $L;
		die( " Node.ERROR: no link from this node(" . $this->id . ") to node(" . $N->id . ")\n");
	}
	public function getLinks() { return $this->out; }
	public function getDistance( $N) {	// N-dimensional distance, 1 hop
		if ( ! $this->location || ! $node->location) return 1;	// location object is not set 
		return $this->location->distance( $N->location);
	}
	public function isme( $N) { if ( $this->id == $N->id) return true; return false; }
	// location shortcuts
	public function x() { return $this->location->coordinates[ 0]; }
	public function y() { return $this->location->coordinates[ 1]; }
	public function z() { return $this->location->coordinates[ 2]; }
	public function nth( $n) { return $this->location->coordinates[ $n]; }
}
class Link {
	public $id;
	public $cost;
	public $bandwidth;
	public $propagation;
	// objects
	public $source;		// Node
	public $target; 		// Node
	public function __construct( $IDGen, $bandwidth = 1, $cost = 1, $propagation = 0) {
		$this->id = is_numeric( $IDGen) ? ( int)$IDGen : $IDGen->next(); 
		$this->cost = $cost;
		$this->bandwidth = $bandwidth;
		$this->propagation = $propagation;
		$this->source = NULL; $this->target = NULL;
	}
	public function distance() {	// uses Location in both target and source
		if ( ! $this->source || ! $this->target) die( " Link.ERROR: distance() cannot be calculated for link(" . $this->id . "), no source and target in this link.\n");
		if ( $this->source == $this->target) return 0;
		$source = $this->source;
		return $this->propagation ? ( $this->propagation * 300000) :  $source->getDistance( $this->target);
	}
	public function delay() { return round( $this->distance() / 300000, 6); }
	public function isme( $L) { if ( $this->source->id == $L->source->id && $this->target->id == $L->target->id) return true; return false; }
}
class Path {	// between 2 nodes, can be multihop
	public $source;				// Node object
	public $destination;		// Node object
	public $hops;				// list of Edge objects
	public function __construct( $source) {
		$this->source = $source;
		$this->destination = $source;	// default at first
		$this->hops = array();
	}
	public function addHop( $L) { 
		lpush( $this->hops, $L);
		$this->destination = $L->target;
	}
	public function getHops() { return $this->hops; }
	public function isNodeInPath( $L) {	// walk all hops
		if ( $this->source->id == $L->id) return true;
		foreach ( $this->hops as $hop) if ( $hop->target->id == $L->id) return true;
		return false;
	}
	public function getHopCount() { return count( $this->hops); }
	public function getHopIds() {
		$list = array();
		foreach ( $this->hops as $L) lpush( $list, $L->id);
		return $list;
	}
	public function getEndToEndCost( $usedistance = true, $usecost = true) {
		$delay = 0;
		foreach ( $this->hops as $L) { 
			if ( ! $usedistance && ! $usecost) $delay += 1;
			else $delay += ( $usedistance ? $L->delay() : 1) + ( $usecost ? $L->cost : 1);
			if ( ! $delay) $delay = 1;
		}
		return $delay;
	}
	public function isSamePath( $P) {
		if ( $this->getHopCount() != $P->getHopCount()) return false;
		for ( $i = 0; $i < count( $this->hops); $i++) if ( $this->hops[ $i]->id != $P->hops[ $i]->id) return false;
		return true;
	}
	public function isSamePrefix( $P) {	// P is the prefix (shorter)
		if ( count( $this->hops) < count( $P->hops)) return false;
		for ( $i = 0; $i < count( $P->hops); $i++) if ( $this->hops[ $i]->id != $P->hops[ $i]->id) return false;
		return true;
	}
	public function nodestring( $delimiter = '-') {
		$list = array( $this->source->id);
		if ( ! $this->getHopCount()) return implode( '.', $list);
		for ( $i = 0; $i < count( $this->hops); $i++) 
			array_push( $list, $this->hops[ $i]->target->id);
		return implode( $delimiter, $list);
	}
	
}
class Graph {	// simple container for nodes and edges 
	public $nodes = array();
	public $links = array();
	// GETTERS and SETTERS
	// node
	public function addNode( $N) { $this->nodes[ $N->id] = $N; }
	public function getNodes() { return $this->nodes; }
	public function getNode( $id) { return $this->nodes[ $id]; }
	public function getNodeCount() { return count( $this->nodes); }
	// link
	public function addLink( $L) { $this->links[ $L->id] = $L; }
	public function getLinks() { return $this->links; }
	public function getLink( $id) { return $this->links[ $id]; }
	public function getLinkByNodeIds( $id1, $id2) {
		foreach ( $this->links as $L) if ( $L->source->id == $id1 && $L->target->id = $id2) return $L;
		return null;
	}
	public function getLinkCount() { return count( $this->links); }
	// other functions
	public function getDimension() {
		$ds = array();
		foreach ( $this->getNodes() as $N) {
			if ( ! $N->location) continue;
			lpush( $ds, count( $N->location->coordinates));
		}
		return mmax( $ds);
	}
	public function makePathByNodeIds( $nids) {
		if ( ! count( $nids)) return null;
		if ( is_array( $nids[ 0])) $nids = lshift( $nids);	// multiple paths, use the first one
		$N = $this->getNode( ( int)$nids[ 0]); if ( ! $N) die( " Graph.ERROR: makePathByNodeIds() no node for id(" . $nids[ 0] . ")\n");
		$P = new Path( $N); lshift( $nids); 
		while ( count( $nids)) {
			$N = $P->destination; $nid = ( int)lshift( $nids); 
			$N2 = $this->getNode( $nid); if ( ! $N2) die( " Graph.ERROR: makePathByNodeIds() no node for id(" . $nids[ 0] . ")\n");
			if ( ! $N->isLink( $N2)) return die( " Graph.ERROR makePathByNodeIds() : no link between nid(" . $N->id . ") and nid(" . $N2->id . ")\n");
			$P->addHop( $N->getLink( $N2));
		}
		return $P;
	}
	public function purgeLink( $L) { 
		unset( $L->source->out[ $L->id]);
		unset( $L->target->in[ $L->id]);
		unset( $this->links[ $L->id]);
		unset( $L);
	}
	public function purgeNode( $N) {
		foreach ( $N->in as $L) $this->purgeLink( $L);
		foreach ( $N->out as $L) $this->purgeLink( $L);
		unset( $this->nodes[ $N->id]);
		unset( $N);
	}
	
}


// draw graph
// ngdrawgraph( CharLP | null, Graph, ChartSetupStyle, ChartSetupStyle(bg) | NULL, 0.2 (node size/ line width), (size -) spacer)
// warning: if S2 != null, will paint the background before each new foreground
function ngdrawgraph( $C2, $G, $S1, $S2 = null, $size, $spacer = 0, $shiftx = 0, $shifty = 0, $FS = 18) {
	$C = null;
	if ( ! $C2) { 
		list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '1', '0,0', '0.1:0.1:0.1:0.1'); $C2 = $CS[ 0];
		foreach ( $G->getNodes() as $N) $C2->train( array( $N->x()), array( $N->y()));
		$C2->autoticks( null, null, 10, 10);
	}
	extract( $C2->info()); // xmin, xmax, ymin, ymax
	$size = round( $size * mmax( array( $xmax - $xmin, $ymax - $ymin)));
	// draw nodes as rectangles
	foreach ( $G->getNodes() as $N) ngdrawnode( $C2, $N, $S1, $S2, $size, $spacer, $shiftx, $shifty);
	// draw links as polygons -- complex algorithm for calculating where
	foreach ( $G->getLinks() as $L) ngdrawlink( $C2, $L, $S1, $S2, $size, $spacer, $shiftx, $shifty);
	return $C ? array( $C, $C2) : $C2;
}
function ngdrawnode( $C2, $N, $S1, $S2, $size, $spacer, $shiftx = 0, $shifty = 0) {
	$x = $N->x(); $y = $N->y(); $w = round( 0.5 * $size, 1);
	$xys = array(); 
	lpush( $xys, ttl( "$x:-$w:$spacer:$shiftx,$y:-$w:$spacer:$shifty"));
	lpush( $xys, ttl( "$x:-$w:$spacer:$shiftx,$y:$w:-$spacer:$shifty"));
	lpush( $xys, ttl( "$x:$w:-$spacer:$shiftx,$y:$w:-$spacer:$shifty"));
	lpush( $xys, ttl( "$x:$w:-$spacer:$shiftx,$y:-$w:$spacer:$shifty"));
	if ( $S2) chartshape( $C2, $xys, $S2);	// erase if found 
	chartshape( $C2, $xys, $S1);
}
function ngdrawlink( $C2, $L, $S1, $S2, $size, $spacer, $shiftx = 0, $shifty = 0) {
	$x1 = $L->source->x(); $y1 = $L->source->y();
	$x2 = $L->target->x(); $y2 = $L->target->y();
	$xys = array(); $w1 = round( 0.2 * $size, 1); $w2 = round( 0.55 * $size, 1);  $w3 = round( 0.3 * $size, 1); 
	if ( $x1 == $x2) { // vertical line 
		$ysmall = mmin( array( $y1, $y2)); $ybig = mmax(  array( $y1, $y2)); 
		lpush( $xys, array( "$x1:-$w1:$spacer:$shiftx", "$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, array( "$x1:$w1:-$spacer:$shiftx", "$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, array( "$x1:$w1:-$spacer:$shiftx", "$ybig:-$w2:$spacer:$shifty"));
		lpush( $xys, array( "$x1:-$w1:$spacer:$shiftx", "$ybig:-$w2:$spacer:$shifty"));
	}
	if ( $y1 == $y2) { // horizontal line
		$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$y1:-$w1:$spacer:$shifty"));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$y1:$w1:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$y2:$w1:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$y2:-$w1:$spacer:$shifty"));
	}
	if ( $x1 != $x2 && $y1 != $y2 && ( $x1 < $x2 && $y1 < $y2 || $x1 > $x2 && $y1 > $y2)) { // upslope
		$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
		$ysmall = mmin( array( $y1, $y2)); $ybig = mmax( array( $y1, $y2));
		lpush( $xys, ttl( "$xsmall:$w2:-$w3:-$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ysmall:$w2:-$w3:$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx:$w3,$ybig:-$w2:$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ybig:-$w2:$w3:$spacer:$shifty"));
	}
	if ( $x1 != $x2 && $y1 != $y2 && ( $x1 < $x2 && $y1 > $y2 || $x1 > $x2 && $y1 < $y2)) { // downslope
		$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
		$ysmall = mmin( array( $y1, $y2)); $ybig = mmax( array( $y1, $y2));
		lpush( $xys, ttl( "$xsmall:$w2:-$w3:-$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
		lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ybig:-$w2:$w3:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$w3:$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
		lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ysmall:$w2:-$w3:-$spacer:$shifty"));
	}
	if ( count( $xys)) { if ( $S2) chartshape( $C2, $xys, $S2); chartshape( $C2, $xys, $S1); }
}

// graphviz functions
function graphvizwrite( $H, $path) { 	// H: [ { area, lineshort, linefull, stationshort, stationfull}, ...] -- list of station hashes
	$h = array(); foreach ( $H as $h2) { extract( $h2); htouch( $h, $lineshort); lpush( $h[ $lineshort], $stationshort); }
	$out = fopen( $path, 'w');
	fwrite( $out, "graph G {\n"); 
	foreach ( $h as $line => $stations) fwrite( $out, "   $line -- " . ltt( $stations, ' -- ') . "\n");
	fwrite( $out, "}\n");
	fclose( $out);
}
function graphviztext( $json, $size =  '11,8') { 	// depends on graphvizwrite() size in inches, default is an *.info file next to input *.dot
	$L = ttl( $json, '.'); lpop( $L); lpush( $L, 'dot'); $out = ltt( $L, '.'); 
	graphvizwrite( jsonload( $json), $out); $in = $out;
	$L = ttl( $in, '/', '', false); $in = lpop( $L); $root = ltt( $L, '/'); 
	$L = ttl( $in, '.'); lpop( $L); $out = ltt( $L, '.') . '.info';
	$path = procfindlib( 'graphviz');
	$CWD = getcwd(); chdir( $root);
	$c = "$path/bin/neato -Gsize=$size -Tdot $in -o $out"; procpipe( $c);
	if ( ! is_file( $out)) die( "ERROR! graphviztext() failed to run c[$c]\n");
	chdir( $CWD);
	return "$root/$out";
}
function graphvizpdf( $json, $legend = true, $specialine = null, $fontsize = 10, $size = '11,8') { 	// depends on graphvizwrite(), will create a PDF file with the same root
	$in2 = graphviztext( $json, $size);	// create *.info file first
	$L = ttl( $in2, '.'); lpop( $L); lpush( $L, 'pdf'); $out = ltt( $L, '.');
	$colors = ttl( '#099,#900,#990,#059,#809,#8B2,#B52,#29E,#0A0,#C0C');
	$raw = jsonload( $json); $link2line = array(); $line2stations = array();
	foreach ( $raw as $h2) {
		extract( $h2); 	// area, lineshort, linefull, stationshort, stationfull
		htouch( $line2stations, $lineshort);
		lpush( $line2stations[ $lineshort], $stationshort);
	}
	foreach ( $line2stations as $line => $stations) {
		lunshift( $stations, $line);
		for ( $i = 1; $i < count( $stations); $i++) $link2line[ $stations[ $i - 1] . ',' . $stations[ $i]] = $line;
	}
	$L = ttl( $json, '.'); lpop( $L); $root = ltt( $L, '.');
	// try to draw the PDF by yourself
	$lines = file( $in2); $line2color = array(); $station2colors = array(); $line2comment = array();	
	$stations = array(); $links = array();
	foreach ( $lines as $line) {
		$line = trim( $line); if ( ! $line) continue;
		$bads = '];'; for ( $i = 0; $i < strlen( $bads); $i++) $line = str_replace( substr( $bads, $i, 1), '', $line);
		$line = str_replace( '",', ':', $line); $line = str_replace( ', ', ':', $line);
		$line = str_replace( ',', ' ', $line);
		$line = str_replace( ':', ',', $line);
		$line = str_replace( '"', '', $line);
		$L = ttl( $line, '['); if ( count( $L) != 2) continue;
		$head = lshift( $L); $tail = lshift( $L);
		$h = tth( $tail); if ( ! isset( $h[ 'pos'])) continue;
		if ( count( ttl( $head, '--')) == 1) { 
			$h = hm( $h, lth( ttl( $h[ 'pos'], ' '), ttl( 'x,y'))); $stations[ trim( $head)] = $h; continue; 
		}
		extract( lth( ttl( $head, '--'), ttl( 'name1,name2')));
		$h = hm( $h, lth( ttl( $h[ 'pos'], ' '), ttl( 'x1,y1,x2,y2,x3,y3,x4,y4'))); 
		$k = "$name1,$name2";
		$h[ 'line'] = $link2line[ $k];
		$links[ $k] = $h;
	}
	foreach ( $raw as $h) { extract( $h); if ( ! isset( $line2color[ $lineshort])) $line2color[ $lineshort] = $lineshort == $specialine ? '#000' : ( count( $colors) ? lshift( $colors) : '#666'); $station2colors[ $lineshort] = array( $line2color[ $lineshort]); }
	foreach ( $raw as $h) { extract( $h); htouch( $station2colors, $stationshort); lpush( $station2colors[ $stationshort], $line2color[ $lineshort]); }
	foreach ( $raw as $h) { extract( $h); $line2comment[ $lineshort] = $linefull . ' (' . $area . ') ' . $linecomment; }
	$bottom = 0.05; if ( $legend) $bottom += round( ( count( $line2color) * $fontsize) / 200, 2);
	$P = plotinit(); plotpage( $P);
	$xs = array(); $ys = array(); foreach ( $stations as $k => $v) { extract( $v); lpush( $xs, $x); lpush( $ys, $y); }
	plotscale( $P, $xs, $ys, "0.05:0.05:$bottom:0.05");
	$yoff = '-5'; if ( $legend) plotline( $P, mmin( $xs), "0:$yoff", mmax( $xs), "0:$yoff", 0.2, '#000', 1.0); $yoff .= ":-$fontsize"; $used = array();
	foreach ( $links as $k => $v) {
		extract( $v); 	// x1..4, y1..4
		plotcurve( $P, $x1, $y1, $x2, $y2, $x3, $y3, $x4, $y4, 'D', $line == $specialine ? 1 : 0.5, $line2color[ $line], null, 1.0);
	}
	foreach ( $stations as $k => $v) {
		extract( $v); 	// width, height, x, y
		extract( plotstringdim( $P, $k, $fontsize)); // w, h
		$colors = $station2colors[ $k]; $add = 0.07 * count( $colors);
		foreach ( $colors as $color) {
			$h2 = hvak( $line2color, true); $line = $h2[ $color];
			$color2 = ( $line == $specialine) ? '#fff' : ( isset( $line2color[ $k]) ? '#fff' : '#000');
			$color3 = isset( $line2color[ $k]) ? $color : ( $specialine == $line ? '#000' : '#fff');
			plotellipse( $P, $x, $y, ( 0.8 + $add) * $w, ( 0.7 + $add) * $h, 0, 0, 360, 'DF', 0.5, $color, $color3);
			plotstringmc( $P, $x, $y, $k, $fontsize, $color2, 1.0);
			$add -= 0.07;
			// draw line legend if needed
			if ( isset( $used[ $line]) || ! $legend) continue;
			// draw legend
			plotellipse( $P, 0.5 * $w, "0:$yoff", 0.8 * $w, 0.7 * $h, 0, 0, 360, 'DF', 0.5, $color, $color);
			plotstringmc( $P, 0.5 * $w, "0:$yoff", $line, $fontsize, '#fff', 1.0);
			plotstringml( $P, ( 0.5 * $w) . ":$w", "0:$yoff", $line2comment[ $line], $fontsize, '#000', 1.0);
			$used[ $line] = true; $yoff .= ":-$em:-2";
		}
		
	}
	plotdump( $P, $out);
	return $out;
}


// parser for various topology types
function ngparsegml( $file) {	// returns ( 'nodes' => ( id => ( name,x,y), 'links' => ( id => ( source,target,bandwidth,metric)))
	$nodes = array();
	$links = array();
	$in = fopen( $file, 'r');
	$entry = NULL; $mode = '';
	while ( $in && ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( strpos( $line, 'node') === 0) {	// new node
			if ( $entry) array_push( $nodes, $entry);
			$entry = array(); $mode = 'node';
		}
		if ( strpos( $line, 'edge') === 0) { 	// new edge
			if ( $entry) {
				if ( $mode == 'node') array_push( $nodes, $entry);
				else array_push( $links, $entry);
				$mode = 'link';
			}
			$entry = array();
		}
		if ( is_array( $entry)) array_push( $entry, $line);
	}
	array_push( $links, $entry);
	fclose( $in);
	
	// turn arrays to hashes
	$hnodes = array(); $hlinks = array();
	foreach ( $nodes as $node) {
		$hnode = array();
		foreach ( $node as $line) {
			$split = explode( ' ', $line);
			$hnode[ array_shift( $split)] = implode( ' ', $split); 
		}
		array_push( $hnodes, $hnode);
	}
	foreach ( $links as $link) {
		$hlink = array();
		foreach ( $link as $line) {
			$split = explode( ' ', $line);
			$hlink[ array_shift( $split)] = implode( ' ', $split);
		}
		array_push( $hlinks, $hlink);
	}
	
	// go over the list
	$topo = array( 'nodes' => array(), 'links' => array());
	foreach ( $hnodes as $node) {
		$id = ( int)$node[ 'id'];
		$entry = array( 
			'name' => str_replace( '"', '', $node[ 'name']),
			'x' => ( double)$node[ 'x'],
			'y' => ( double)$node[ 'y']
		);
		$topo[ 'nodes'][ $id] = $entry;
	}
	foreach ( $hlinks as $link) {
		$b = trim( $link[ 'bandwidth']);
		if ( strpos( $b, 'G')) $b = 1000000000.0 * ( int)$b;
		if ( strpos( $b, 'M')) $b = 1000000.0 * ( int)$b;
		array_push( $topo[ 'links'], array(
			'source' => ( int)$link[ 'source'],
			'target' => ( int)$link[ 'target'],
			'bandwidth' => $b,
			'weight' => ( double)$link[ 'weight']
		));
		
	}
	return $topo;
}
// parses list of hashes into Topology object (relies on ngparsegml()
function ngmakegraph( $h) {	// h can come from ngparsegml 
	$G = new Graph(); $IDGEN = new IDGen();
	foreach ( $h[ 'nodes'] as $id => $nh) {
		$N = new Node( $id);
		$N->place( new Location( $nh[ 'x'], $nh[ 'y']));
		$G->addNode( $N);
	}
	foreach ( $h[ 'links'] as $id => $eh) {
		$L = new Link( $IDGEN, $eh[ 'bandwidth'], $eh[ 'weight'], 0);
		$L->source = $G->nodes[ ( int)$eh[ 'source']];
		$L->target = $G->nodes[ ( int)$eh[ 'target']];
		$G->links[ $L->id] = $L;
		$L->source->addOut( $L); $L->target->addIn( $L);
	}
	return $G;
}
/** writes GML format to a file
Rules:  
	graph should be directed by default, one should avoid having undirected graphs
		(if you need one, use script to create undirected GML by creating additional links for reverse directions)
	node id attribute of nodes is sequential
	node name attribute is in format: $node->name $node->id so that to keep the actual id arround)
	
	in graphics section of node, only x and y will be created, 
		(* if coordinates have >2 dimensions, something else should be figured out)
	all nodes should have Location objects with coordinates set in at least 2 dimensions
		(if not, you can use ngrandomlocations() to add random locations to your nodes)
	
*/
function ngsavegml( $G, $file, $directed = true) {
	$out = fopen( $file, 'w');
	fwrite( $out, "graph [\n");	// open graph
	fwrite( $out, "\t" . "directed " . ( $directed ? '1' : '0') . "\n");
	$nids = array(); 	// node id => sequence id
	foreach ( $G->nodes as $id => $N) {
		fwrite( $out, "\t" . "node [\n");	// open node
		fwrite( $out ,"\t\t" . "id " . $id . "\n");
		fwrite( $out, "\t\t" . 'name "Node' . $id . '"' . "\n");
		fwrite( $out, "\t\t" . "graphics [\n");	// open graphics
		fwrite( $out, "\t\t\t" . "center [\n"); 			// open center
		fwrite( $out, "\t\t\t\t" . "x " . $N->location->coordinates[ 0] . "\n");
		fwrite( $out, "\t\t\t\t" . "y " . $N->location->coordinates[ 1] . "\n");
		fwrite( $out, "\t\t\t" . "]\n");					// close center
		fwrite( $out, "\t\t" . "]\n");	// close graphics
		fwrite( $out, "\t" . "]\n");	 // close node
		$nids[ $id] = $N;
	}
	foreach ( $G->links as $id => $L) {
		fwrite( $out, "\t" . "edge [\n");	// open edge
		fwrite( $out, "\t\t" . "simplex 1\n");
		fwrite( $out, "\t\t" . "source " . $L->source->id . "\n");
		fwrite( $out, "\t\t" . "target " . $L->target->id . "\n");
		fwrite( $out, "\t\t" . "bandwidth " . $L->bandwidth . "\n");
		fwrite( $out, "\t\t" . "weight " . $L->cost . "\n");
		fwrite( $out, "\t" . "]\n"); 			// close edge
	}
	fwrite( $out, "]\n");	// close graph
	fclose( $out);
}


// larger functions, like end-to-end paths, all require R.igraph installed
/** takes full path to GML file, node id 1,2, returns list of paths (=node id lists)
* 		returns list of array( source,node1,node2...,dest) of node ids
* 		in most cases, there is only one array in the list = one shortest path exists between nodes
*		WARNING: list can also be empty = there is no path between nodes
*/
function ngRspGML( $gml, $n1, $n2, $cleanup = true) { // if cleanup=false, set path to Rscript file 
	if ( ! is_numeric( $n1)) $n1 = $n1->id;
	if ( ! is_numeric( $n2)) $n2 = $n2->id;
	$s = "library( igraph)\n";
	$s .= 'g <- read.graph( "' . $gml . '", "gml")' . "\n";
	$s .= 'get.shortest.paths( g, ' . $n1 . ', ' . $n2 . ', "out")' . "\n";
	$lines = Rscript( $s, null, false, $cleanup);
	$list = array();
	while ( count( $lines)) {
		$line = trim( lshift( $lines)); if ( ! $line) continue;
		if ( strpos( $line, '[[') !== 0) die( " ERROR Strange line($line)\n");
		$vs = Rreadlist( $lines);	// messes with lines (by reference)
		$source = ( int)lfirst( $vs);
		$dest = ( int)llast( $vs);
		if ( $source != $n1 || $dest != $n2) die( " ngRspGML() ERROR: bad e2e path, source($source) and dest($dest) are not ($nid1) and ($nid2)\n");	// start and end are not my nodes
		lpush( $list, $vs);
	}
	return $list;
}
function ngRsp( $T, $n1, $n2, $directed = true, $cleanup = true, $gml = null) {	// writes temp.gml in current dir and calls ngRspGML()
	$nid2id = hvak( hk( $T->getNodes()), true);
	$id2nid = hk( $T->getNodes());
	if ( ! $gml) { ngsavegml( $T, 'temp.gml', $directed); $gml = 'temp.gml'; }	// directed by default
	$nids = ngRspGML( $gml, $nid2id[ $n1], $nid2id[ $n2], $cleanup);
	if ( ! $nids || ! count( $nids)) return null;
	if ( is_array( $nids[ 0])) $nids = lshift( $nids);
	for ( $i = 0; $i < count( $nids); $i++) $nids[ $i] = $id2nid[ $nids[ $i]]; 
	if ( $cleanup) `rm -Rf temp.gml`; 
	return $nids;
}



?>