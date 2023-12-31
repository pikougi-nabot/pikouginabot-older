<?php

namespace MediaWiki\Hook;

use MediaWiki\Title\ForeignTitle;
use MediaWiki\Title\Title;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "AfterImportPage" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface AfterImportPageHook {
	/**
	 * This hook is called when a page import is completed.
	 *
	 * @since 1.35
	 *
	 * @param Title $title Title under which the revisions were imported
	 * @param ForeignTitle $foreignTitle ForeignTitle object based on data provided by the XML file
	 * @param int $revCount Number of revisions in the XML file
	 * @param int $sRevCount Number of successfully imported revisions
	 * @param array $pageInfo Associative array of page information
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onAfterImportPage( $title, $foreignTitle, $revCount,
		$sRevCount, $pageInfo
	);
}
