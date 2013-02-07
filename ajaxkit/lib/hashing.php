<?php
// hashes objects (refs), each object should have id() function which should return object's id as array of bytes
// hashing is flexible in use of hash functions and length of key
// keys also are horizontal structures (collision control) of variable size
class HashTable {
	public $h = array();	// hash table itself
	public $count = 0;
	public $hsize = 1;	// how many entries to allow for each key (collision avoidance)
	public $length = 32;
	public $type = 'CRC24';	// ending of crypt*** hashing function from crypt.php
	public function __construct( $type, $length, $hsize) { $this->type = $type; $this->length = $length; $this->hsize = $hsize; }
	public function count( $total = false) { return $total ? $this->count : count( $this->h); }
	public function key( $id) { $k = 'crypt' . $this->type; return btail( $k( $id), $this->length); } // calculates hash key
	public function get( $id, $key = null) { // returns [ object | NULL, cost of horizontal search]   
		if ( $key === null) $key = $this->key( $id);
		if ( ! isset( $this->h[ $key])) return array( NULL, 0);
		$L =& $this->h[ $key];
		for ( $i = 0; $i < count( $L); $i++) if ( $L[ $i]->id() == $id) return array( $L[ $i], $i + 1);  
		return array( NULL, count( $L));
	}
	public function set( $e) {	// returns TRUE on success, FALSE otherwise
		$k = $this->key( $e->id());
		if ( ! isset( $this->h[ $k])) $this->h[ $k] = array();
		if ( count( $this->h[ $k]) >= $this->hsize) return false; 	// collision cannot be resolved, quit on this entry
		$this->count++; lpush( $this->h[ $k], $e);
		return true;
	}
	public function remove( $e) { // returns hcost of lookup
		$k = $this->key( $e->id());
		if ( ! isset( $this->h[ $k])) die( " ERROR! HashTable:remove() key[$key] does not exist in HashTable\n");
		$L = $this->h[ $k]; $L2 = array();
		foreach ( $L as $e2) if ( $e->id() != $e2->id()) lpush( $L2, $e2);
		$this->count -= count( $L) - count( $L2);
		if ( ! count( $L2)) unset( $this->h[ $k]); else $this->h[ $k] = $L2;
		return count( $L);
	}
	
}

?>