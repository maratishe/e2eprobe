<?php
$RHOME = '';
// interface for R statistics library
// all this library doess forms Rscript programs for specific targets,
// runs Rscript, collects its ra output and parses it in order 
// to obtain meaningful information
//     In short, this is an efficient shortcut for most statistical processing of your data

// puts R program into file, runs Rscript and returns raw text output
function Rscript( $rstring, $tempfile = null, $skipemptylines = true, $cleanup = true, $echo = false, $quiet = true) {
	global $RHOME;
	if ( ! $tempfile) $tempfile = ftempname( 'rscript');
	if ( $tempfile && lpop( ttl( $tempfile, '.')) != 'rscript') $tempfile = ftempname( 'rscript', $tempfile);
	$out = fopen( $tempfile, 'w');
	fwrite( $out, $rstring . "\n");
	fclose( $out);
	$c = "Rscript $tempfile";
	if ( $RHOME) $c = "$RHOME/bin/$c";
	if ( $quiet) $c .= ' 2>/dev/null 3>/dev/null';
	$in = popen( $c, 'r');
	$lines = array();
	while ( $in && ! feof( $in)) { 
		$line = trim( fgets( $in));
		if ( ! $line && $skipemptylines) { if ( $echo) echo "\n"; continue; }
		if ( $echo) echo $line . "\n";
		array_push( $lines, $line);
	}
	fclose( $in);
	if ( $cleanup) `rm -Rf $tempfile`;
	return $lines;
}
// works directly on R output lines (passed by reference), so, be careful!
function Rreadlist( &$lines) { 	// reads split list in R output, list split into several lines, headed by [elementcount]
	$L = array();
	while ( count( $lines)) {
		$line = lshift( $lines);
		if ( ! trim( $line)) break;
		$L2 = ttl( trim( $line), ' ');	// safely remove empty elements
		if ( ! $L2 || ! count( $L2)) break;
		if ( strpos( $L2[ 0], '[') !== 0) break;
		$count = ( int)str_replace( '[', '', str_replace( ']', '', $L2[ 0]));
		if ( $count !== count( $L) + 1) die( "Rreadlist() ERROR: Strange R line, expecting count[" . count( $L) . "] but got line [" . trim( $line) . "], critical, so, die()\n\n");
		for ( $ii = 1; $ii < count( $L2); $ii++) lpush( $L, $L2[ $ii]);
	}
	return $L;
}
function Rreadmatrix( &$lines) {	// reads a matrix of values, returns mx object
	// first, estimate how many rows in matrix (not cols)
	$rows = array();
	while ( count( $lines)) { 
		$line = trim( lshift( $lines)); if ( ! $line) break; 
		$L = ttl( $line, ' '); $head = lshift( $L); 
		if ( strpos( $head, ',]') === false) continue; // next line
		htouch( $rows, $head); foreach ( $L as $v) lpush( $rows[ $head], $v);
	}
	return hv( $rows);	// same as mx object: [ rows: [ cols]]
}
function Rreadlisthash( &$lines) {	// reads hash of lists
	// first, estimate how many rows in matrix (not cols)
	$rows = array(); $ks = array();
	while ( count( $lines)) { 
		$line = trim( lshift( $lines)); if ( ! $line) break; 
		if ( strpos( $line, '[') === false) { $ks = ttl( $line, ' '); continue; } 
		$L = ttl( $line, ' '); $head = lshift( $L);
		$head = str_replace( '[', '', $head); $head = str_replace( ',]', '', $head);
		$line = ( int)$head; htouch( $rows, $line);
		if ( count( $L) != count( $ks)) die( " Rreadlisthash() ERROR! ks(" . ltt( $ks) . ") does not match vs(" . ltt( $L) . ")\n");
		for ( $i = 0; $i < count( $ks); $i++) $rows[ $line][ $ks[ $i]] = $L[ $i];
	}
	foreach ( $rows as $row => $h) $rows[ $row] = hv( $h);
	return hv( $rows);
}


// permutation entropy -- uses PDC package (linux only)
function Rpe( $L, $mindim = 3, $maxdim = 7, $lagmin = 1, $lagmax = 1) { 	// list of values, returns minimum PE
	$R = "library( pdc)\n";
	$R .= "pe <- entropy.heuristic( c( " . ltt( $L) . "), m.min=$mindim, m.max=$maxdim, t.min=$lagmin, t.max=$lagmax)\n";
	$R .= 'pe$entropy.values';
	$mx = mxtranspose( Rreadmatrix( Rscript( $R))); if ( ! $mx || ! is_array( $mx) || ! isset( $mx[ 2])) die( " bad R.PE\n");
	$h  = array();
	return round( mmin( $mx[ 2]), 2); // return the samelest PE among dimensions
}


// string functions
function Rstrcmp( $one, $two, $cleanup = true) {
	$R = "agrep( '$one', '$two')";
	$L = Rreadlist( Rscript( $R, null, true, $cleanup));
	if ( ! $L && ! count( $L)) return 0;
	rsort( $L, SORT_NUMERIC);
	return lshift( $L);
}


// outliers
function Rdixon( $list, $cleanup = true) { // will return { Q, p-value} from Dixon outlier test, data should be ordered and preferrably normalized
	sort( $list, SORT_NUMERIC);
	$script = "library( outliers)\n";
	$script .= "dixon.test( c( " . ltt( $list) . "))\n";
	$L = Rscript( $script, 'dixon', true, $cleanup);
	foreach ( $L as $line) {
		$line = trim( $line); if ( ! $line) continue;
		$h = tth( $line); if ( ! isset( $h[ 'Q']) || ! isset( $h[ 'p-value'])) continue;
		return $h;
	}
	return null;
}

// randomness,  runs test, etc. returns hash{ statistic, pvalue}
function Rruns( $list, $skipemptylines = true, $cleanup = true) {
	$script = "library( lawstat)\n";
	$script .= "runs.test( c( " . ltt( $list) . "))\n";
	$L = Rscript( $script, 'runs', $skipemptylines, $cleanup);
	if ( ! count( $L)) return lth( ttl( '-1,-1'), ttl( 'statistic,pvalue'));
	while ( count( $L) && ! strlen( trim( llast( $L)))) lpop( $L);
	if ( ! count( $L)) return lth( ttl( '-1,-1'), ttl( 'statistic,pvalue'));
	$s = llast( $L); $s = str_replace( '<', '=', $s);
	$h = tth( $s); if ( ! isset( $h[ 'p-value'])) die( "ERROR! Cannot parse RUNS line [" . llast( $L) . "]\n");
	return lth( hv( $h), ttl( 'statistic,pvalue')); 
}

/** reinforcement learning, requires MDP (depends on XML) package installed (seems to only install on Linux)
* automatic stuff:
*    - binaries are created with RL_ prefix
*    - 'reward' is the automatic label of the optimized variable
* setup structure: [ stage1, stage2, stage3, ... ]
*   stage structure: { 'state label': { 'action label': { action setup}}, ...}
*     action setup: { weight, dests: [ { state (label), prob (0..1)}, ...]}
*/
function RsimpleMDP( $setup, $skipemptylines = true, $cleanup = true) { 	// returns [ { stageno, stateno, state, action, weight}, ...]   list of data for each iteration
	// create the script
	$s = 'library( MDP)' . "\n";
	$s .= 'prefix <- "RL_"' . "\n";
	$s .= 'w <- binaryMDPWriter( prefix)' . "\n";
	$s .= 'label <- "reward"' . "\n";
	$s .= 'w$setWeights(c( label))' . "\n";
	$s .= 'w$process()' . "\n";
	// create map of stages and actions
	$map = array(); foreach ( $setup as $k1 => $h1) lpush( $map, hvak( hk( $h1), true));
	//echo 'MAP[' . json_encode( $map) . "]\n";
	for ( $i = 0; $i < count( $setup); $i++) {
		$h = $setup[ $i];
		$s .= '   w$stage()' . "\n";
		foreach ( $h as $label1 => $h1) {
			//echo "label1[$label1] h1[" . json_encode( $h1) . "]\n";
			$s .= '      w$state( label = "' . $label1 . '"' . ( $h1 ? '' : ', end=T') . ')' . "\n";
			if ( ! $h1) continue;	// no action state, probably terminal stage
			foreach ( $h1 as $label2 => $h2) {
				extract( $h2);	// weight, dests: [ { state, prob}]
				$fork = array(); foreach ( $dests as $h3) { 
					extract( $h3); // state, prob
					lpush( $fork, 1);
					lpush( $fork, $map[ $i + 1][ $state]);
					lpush( $fork, $prob);
				}
				$s .= '         w$action( label = "' . $label2 . '", weights = ' . $weight . ', prob = c( ' . ltt( $fork) . '), end = T)' . "\n";
			}
			$s .= '      w$endState()' . "\n";
		}
		$s .= '   w$endStage()' . "\n";
	}
	$s .= 'w$endProcess()' . "\n";
	$s .= 'w$closeWriter()' . "\n";
	$s .= "\n";
	$s .= 'stateIdxDf( prefix)' . "\n";
	$s .= 'actionInfo( prefix)' . "\n";
	$s .= 'mdp <- loadMDP( prefix)' . "\n";
	$s .= 'mdp' . "\n";
	$s .= 'valueIte( mdp , label , termValues = c( 50, 20))' . "\n";
	$s .= 'policy <- getPolicy( mdp , labels = TRUE)' . "\n";
	$s .= 'states <- stateIdxDf( prefix)' . "\n";
	$s .= 'policy <- merge( states , policy)' . "\n";
	$s .= 'policyW <- getPolicyW( mdp, label)' . "\n";
	$s .= 'policy <- merge( policy, policyW)' . "\n";
	$s .= 'policy' . "\n";
	// run the script
	$L = Rscript( $s, 'mdp', $skipemptylines, $cleanup);
	while ( count( $L) && strpos( $L[ 0], 'Run value iteration using') !== 0) lshift( $L);
	if ( count( $L) < 3) return null;	// some error, probably the problem is written wrong
	lshift( $L); lshift( $L); // header should be sId, n0, s0, lable, aLabel, w0
	if ( ! is_numeric( lshift( ttl( $L[ 0], ' ')))) lshift( $L);
	$out = array();
	foreach ( $L as $line) {
		$L2 = ttl( $line, ' ');
		$run = lshift( $L2);
		lshift( $L2);
		$stageno = lshift( $L2);
		$stateno = lshift( $L2);
		$state = lshift( $L2);
		$action = lshift( $L2);
		$weight = lshift( $L2);
		$h = tth( "run=$run,stageno=$stageno,stateno=$stateno,state=$state,action=$action,weight=$weight");
		lpush( $out, $h);
	}
	// create policy from runs
	$policy = array();
	foreach  ( $out as $h) {
		$stageno = null; extract( $h);	// stageno, state, action
		if ( ! is_numeric( $stageno)) continue;
		if ( ! isset( $policy[ $stageno])) $policy[ $stageno] = array();
		$policy[ $stageno][ $state] = $action;
	}
	ksort( $policy, SORT_NUMERIC);
	return $policy;
}

// clustering
function Rkmeans( $list, $centers, $group = true, $cleanup = true) { // returns list of cluster numbers as affiliations
	sort( $list, SORT_NUMERIC);
	$s = 'kmeans( c( ' . ltt( $list) . "), $centers)";
	$lines = Rscript( $s, 'kmeans', false, $cleanup);
	while ( count( $lines) && trim( $lines[ 0]) != 'Clustering vector:') lshift( $lines);
	if ( count( $lines)) lshift( $lines);
	$out = array();
	foreach ( $lines as $line) {
		$line = trim( $line); if ( ! $line) break;	// end of block
		$L = ttl( $line, ' '); lshift( $L);
		foreach ( $L as $v) lpush( $out, ( int)$v);
	}
	if ( count( $out) != count( $list)) return null;	// failed
	if ( ! $group) return $out; // these are just cluster belonging ... 1 through centers
	if ( count( $out) != count( $list)) die( "ERROR! Rkmeans() counts do not match    LIST(" . ltt( $list) . ")   OUT(" . ltt( $out) . ")   LINES(" . ltt( $lines, "\n") . ")\n");
	$clusters = array(); for ( $i = 0; $i < $centers; $i++) $clusters[ $i] = array();
	for ( $i = 0; $i < count( $list); $i++) {
		if ( ! isset( $out[ $i])) die( "ERROR! Rkmeans() no out[$i]   LIST(" . ltt( $list) . ")  OUT(" . ltt( $out) . ")\n");
		if ( ! isset( $clusters[ $out[ $i] - 1])) die( "ERROR! Rkmeans() no cluster(" . $out[ $i] . ") in data  LIST(" . ltt( $list) . ")  OUT(" . ltt( $out) . ")");
		lpush( $clusters[ $out[ $i] - 1], $list[ $i]);
	}
	return $clusters;
}


// correlation
/** cross-correlation function (specifically, the one implemented by R)
	$one is the first array
	$two is the second array, will be tested agains $one
	$lag is the lag in ccf() (read ccf manual in R)
	$normalize true will normalize both arrays prior to calling ccf()
	$debug should be on only when testing for weird behavior
	returns hash ( lag => ccf)
*/
function Rccf( $one, $two, $lag = 5, $normalize = true, $cleanup = true, $debug = false) {
	if ( $debug) echo "\n";
	if ( $debug) echo "Rccf, with [" . count( $one) . "] and [" . count( $two) . "] in lists\n";
	if ( $normalize) { $one = mnorm( $one); $two = mnorm( $two); }
	$rstring = 'ccf('
	. ' c(' . implode( ',', $one) . '), '
	. ' c(' . implode( ',', $two) . '), '
	. "plot = FALSE, lag.max = $lag, na.action = na.pass"
	. ')';
	if ( $debug) echo "rstring [$rstring]\n";
	$lines = Rscript( $rstring, 'ccf', true, $cleanup);
	while ( count( $lines) && strpos( $lines[ 0], 'Autocorrelations') === false) lshift( $lines); lshift( $lines);
	$out = array();
	while ( count( $lines)) {
		$ks = ttl( lshift( $lines), ' ');
		$vs = ttl( lshift( $lines), ' ');
		$out = hm( $out, lth( $vs, $ks));
	}
	return $out;
}
// takes Rccf() output and selects the best value of all lags in hash, returns double
function Rccfbest( $ccf) {
	arsort( $ccf, SORT_NUMERIC);
	$key = array_shift( array_keys( $ccf));
	return $ccf[ $key];
}
// runs Rccf with 1 lag, but returns '0'th result -- the case when lag makes no sense
function Rccfsimple( $one, $two, $normalize = true, $cleanup = true) { return htv( Rccf( $one, $two, 1, $normalize, $cleanup), '0'); } 
	

// auto-correlation
function Racf( $one, $maxlag = 15, $normalize = true, $debug = false) {
	if ( $maxlag < 3) return array();	// too small leg
	if ( $debug) echo "\n";
	if ( $debug) echo "Rccf, with [" . count( $one) . "] and [" . count( $two) . "] in lists\n";
	if ( $normalize) { $one = mnorm( $one); $two = mnorm( $two); }
	$rstring = 'acf('
	. ' c(' . implode( ',', $one) . '), '
	. ' c(' . implode( ',', $two) . '), '
	. "plot = FALSE, lag.max = $maxlag, na.action = na.pass"
	. ')';
	if ( $debug) echo "rstring [$rstring]\n";
	$lines = Rscript( $rstring, 'acf');
	if ( $debug) echo "received [" . count( $lines) . "] lines from Rscript()\n";
	if ( $debug) foreach ( $lines as $line) echo '   + [' . trim( $line) . ']' . "\n";
	
	$goodlines = array();
	while ( count( $lines)) {
		$line = trim( array_pop( $lines));
		$line = str_replace( '+', '', str_replace( '[', '', str_replace( ']', '', $line)));
		array_unshift( $goodlines, $line);
		$L = ttl( $line, ' '); if ( $L[ 0] == 0 && $L[ 1] == 1 && $L[ 2] == 2) break; 
	}
	$out = array();
	while ( count( $goodlines)) {
		$keys = ttl( array_shift( $goodlines), ' ');
		$values = ttl( array_shift( $goodlines), ' ');
		for ( $i = 0; $i < count( $keys); $i++) $out[ $keys[ $i]] = $values[ $i];
	}
	return $out;
}


// fitting
/** try to fit a list of values to a given distribution model, return parameter hash if successful
	$list is a simple array of values ( normalization is preferred?)
	$type is the type supported by fitdistr (read R MASS manual)
	$expectkeys: string in format key1.key2.key3 (dot-delimited list of keys to parse from fitdist output)
	returns hash ( parameter => value)
		*** distributions without START: exponential,lognormal,poisson,weibull
		*** others will require START variable assigned something
*/
function Rfitdistr( $list, $type, $cleanup = true) {	 // returns hash ( param name => param value)
	$rs = "library( MASS)\n"	// end of line is essential 
	. "fitdistr( c( " . implode( ',', $list) . '), "' . $type . '")' . "\n";
	$lines = Rscript( $rs, 'fitdistr', true, $cleanup);
	$h = null;
	while ( count( $lines) > 2) {
		$L = ttl( lshift( $lines), ' ');
		$L2 = ttl( $lines[ 0], ' ');
		if ( count( $L) != count( $L2)) continue;
		$good = true; foreach ( $L2 as $v) if ( ! is_numeric( $v)) $good = false;
		if ( ! $good) continue;
		// good data
		for ( $i = 0; $i < count( $L); $i++) $h[ $L[ $i]] = $L2[ $i];
		break;
	}
	return $h;
}
/** test a given distirbution model agains real samples
	$list is array of values to be tested
	$type string supported by ks.test() in R (read manual if in doubt)
	$params hash specific to a given distribution (read manual, and may be test in R before running automatically)
	returns hash ( D, p-value) when successful, empty hash otherwise
	*** map from distr names:  exponential=pexp,lognormal=plnorm,poisson=ppois,weibull=pweibull
*/
function Rkstest( $list, $type, $params = null, $cleanup = true) { // params is hash, returns hash of output 
	$type = is_array( $type) ? 'c(' . ltt( $type) . ')' :'"' . $type . '"';  
	$rs = "ks.test( c(" . ltt( $list) . '), ' . $type . ( $params ? ', ' . htt( $params) : '') . ")\n";
	$lines = Rscript( $rs, 'kstest', true, $cleanup);
	foreach ( $lines as $line) {
		$h = tth( str_replace( '<', '=', $line));
		if ( ! isset( $h[ 'D']) && ! isset( $h[ 'p-value'])) continue;
		return $h;
	}
	return array();
}
// linear fitting of a single list of values in R
function Rfitlinear( $list) { // returns list( b, a) in Y = aX + b, X: keys, Y: values in list
	$s = 'y = c(' . ltt( $list) . ')' . "\n";
	$s .= 'x = c(' . ltt( hk( $list)) . ')' . "\n";
	$s .= 'lm( y~x)' . "\n";
	$lines = Rscript( $s, 'fitlinear'); 
	while( count( $lines) && ! trim( llast( $lines))) lpop( $lines);
	if ( ! count( $lines)) return array( null, null);
	return ttl( lpop( $lines), ' ');
}

// PLS, specifically, SPE (squared prediction error)
function Rpls( $x, $y, $cleanup = true) { // x: list, y: list (same length), returns list of scores (SPE)
	$S = "library( pls)\n";
	$S .= "mydata = data.frame( X = as.matrix( c(" . ltt( $x) . ")), Y = as.matrix( c( " . ltt( $y) . ")))\n";
	$S .= "data = plsr( X ~ Y, data = mydata)\n";
	$S .= 'data$scores' . "\n";
	$L = Rscript( $S, 'pls', true, $cleanup);
	while ( count( $L) && trim( $L[ 0]) != 'Comp 1') lshift( $L);
	if ( ! count( $L)) return null;
	lshift( $L); $L2 = array();
	for ( $i = 0; $i < count( $y) && count( $L); $i++) lpush( $L2, lpop( ttl( lshift( $L), ' ')));
	return $L2;
}

// Kalman filter, takes input list, regressed it, and returns list of predictions
function Rkalman( $x, $degree = 1, $cleanup = true) { 	// x: list, returns prediction list of size( list) [ 0, pred 1, pred2 ...]
	$S = "library( dlm)\n";
	$S .= "dlmFilter( c( " . ltt( $x) . "), dlmModPoly( $degree))\n";
	$L = Rscript( $S, 'kalman', true, $cleanup);
	while ( count( $L) && trim( $L[ 0]) != '$f') lshift( $L); // skip until found line '$f' prediction values
	lshift( $L);	// skip the line with $f itself
	return Rreadlist( $L);
}


// PCA
/** select top N principle components based an a matrix (matrixmath)
*	$percentize true|false, if true, will turn fractions into percentage points
*	$round how many decimal numbers to round to
*	returns hashlist ( std.dev, prop, cum.prop)
*/
function Rpcastats( $mx, $howmany = 10, $percentize = true, $round = 2) { // returns hashlist
	$lines = Rscript( "summary( princomp( " . mx2r( $mx) . "))");
	//echo "[" . count( $lines) . "] lines\n";
	if ( ! $lines) return array();
	while ( strpos( $lines[ 0], 'Importance of components') !== 0) array_shift( $lines);
	array_shift( $lines);
	$H = array();
	while ( count( $lines) && count( array_keys( $H)) < $howmany) {
		$tags = ttl( array_shift( $lines), ' ');
		//echo "tags: " . ltt( $tags, ' ') . "\n";
		for ( $i = 0; $i < count( $tags); $i++) {
			$tags[ $i] = array_pop( explode( '.', $tags[ $i]));
		}
		$labels = ttl( 'std.dev,prop,cum.prop');
		while ( count( $labels)) {
			$label = array_shift( $labels);
			$L = ttl( array_shift( $lines), ' ');
			$tags2 = $tags;
			while ( count( $tags2)) {
				$tag = array_pop( $tags2);
				$H[ $tag][ $label] = array_pop( $L);
			}
			
		}
		
	}
	ksort( $H, SORT_NUMERIC);
	$list = array_values( $H);
	while ( count( $list) > $howmany) array_pop( $list);
	if ( $percentize) for ( $i = 0; $i < count( $list); $i++) foreach ( $list[ $i] as $k => $v) if ( $k != 'std.dev') $list[ $i][ $k] = round( 100 * $v, $round);
	return $list;
}
function Rpcascores( $mx, $comp) { // which component, returns list of size of mx's width
	$text = "pca <- princomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
	$text .= "pca" . '$' . "scores[,$comp]\n";
	$lines = Rscript( $text, 'pca');
	//echo "[" . count( $lines) . "] lines\n";
	if ( ! $lines) return array();
	$list = array();
	foreach ( $lines as $line) {
		$L = ttl( $line, ' '); array_shift( $L);
		foreach ( $L as $v) array_push( $list, $v);
	}
	while ( count( $list) > count( $mx)) array_pop( $list);
	return $list;
}
function Rpcaloadings( $mx, $comp, $cleanup = true) { // which component, returns list of size of mx's width
	$text = "pca <- princomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
	$text .= "pca" . '$' . "loadings[,$comp]\n";
	$lines = Rscript( $text, 'pca', true, $cleanup);
	//echo "[" . count( $lines) . "] lines\n";
	if ( ! $lines) return array();
	$list = array();
	foreach ( $lines as $line) {
		$L = ttl( $line, ' '); array_shift( $L);
		foreach ( $L as $v) array_push( $list, $v);
	}
	while ( count( $list) > count( $mx)) array_pop( $list);
	return $list;
}
function Rpcarotation( $mx, $cleanup = true) { // returns MX[ row1[ PC1, PC2,...]], ...] -- standard matrix
	$text = "pca <- prcomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
	$text .= 'pca$rotation' . "\n";
	$lines = Rscript( $text, 'pcarotation', true, $cleanup);
	return Rreadlisthash( $lines);
}


// distributions, use R to generate values from various distributions
function Rdist( $rscript, $cleanup = true) { return Rreadlist( Rscript( $rscript, null, true, $cleanup)); } // general distribution runner/reader, output should always be R list
function Rdistbinom( $period, $howmany = 10) { 	// probability is 1/period, default howmany is 100 * period
	$prob = round( 1 / $period, 6); 
	if ( ! $howmany) $howmany = $period * 1000;
	if ( $howmany > 1000000) $howmany = $period * 1000;
	return Rdist( "rbinom( $howmany, 1, $prob)");
}
function Rdistpoisson( $mean, $howmany = 1000) { return Rdist( "rpois( $howmany, $mean)"); }
function Rdensity( $L, $cleanup = true) { 	// returns { x, y} of density
	$R = 'd <- density( c(' . ltt( $L) . '))' . "\n";
	$x = Rreadlist( Rscript( $R . 'd$x', null, true, $cleanup));
	$y = Rreadlist( Rscript( $R . 'd$y', null, true, $cleanup));
	return array( 'x' => $x, 'y' => $y);
}
function Rhist( $L, $breaks = 20, $digits = 3, $cleanup = true) { 	// y value = bin counts
	$R = 'd <- hist( c(' . ltt( $L) . "), prob=1, breaks=$breaks)" . "\n";
	$y = Rreadlist( Rscript( $R . 'd$counts', null, true, $cleanup));
	$step = ( 1 / $breaks) * ( mmax( $L) - mmin( $L));
	$x = 0.5 * $step; $h = array();
	foreach ( $y as $v) { $h[ '' . round( $x, $digits)] = $v; $x += $step; }
	return $h;
}


?>