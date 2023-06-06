<?php
namespace MediaWiki\Libs;

/**
 * An interface to check for emptiness of an object
 */
interface Emptiable {

	/**
	 * Check if object is empty
	 * @return bool Is it empty
	 */
	public function isEmpty();

}

class_alias( Emptiable::class, 'MediaWiki\\Emptiable' );