<?php

namespace MediaWiki\Hook;

use MediaWiki\Specials\SpecialBlock;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "SpecialBlockModifyFormFields" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface SpecialBlockModifyFormFieldsHook {
	/**
	 * Use this hook to add more fields to Special:Block
	 *
	 * @since 1.35
	 *
	 * @param SpecialBlock $sp SpecialPage object, for context
	 * @param array &$fields Current HTMLForm fields
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSpecialBlockModifyFormFields( $sp, &$fields );
}
