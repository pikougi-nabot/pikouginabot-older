<?php

/**
 * @group large
 * @group Upload
 * @group Database
 *
 * @covers UploadFromUrl
 */
class UploadFromUrlTest extends ApiTestCase {
	private $user;

	protected function setUp() : void {
		parent::setUp();
		$this->user = self::$users['sysop']->getUser();

		$this->setMwGlobals( [
			'wgEnableUploads' => true,
			'wgAllowCopyUploads' => true,
		] );
		$this->setGroupPermissions( 'sysop', 'upload_by_url', true );

		if ( wfLocalFile( 'UploadFromUrlTest.png' )->exists() ) {
			$this->deleteFile( 'UploadFromUrlTest.png' );
		}
	}

	/**
	 * Ensure that the job queue is empty before continuing
	 */
	public function testClearQueue() {
		$job = JobQueueGroup::singleton()->pop();
		while ( $job ) {
			$job = JobQueueGroup::singleton()->pop();
		}
		$this->assertFalse( $job );
	}

	/**
	 * @depends testClearQueue
	 */
	public function testSetupUrlDownload( $data ) {
		$token = $this->user->getEditToken();
		$exception = false;

		try {
			$this->doApiRequest( [
				'action' => 'upload',
			] );
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertEquals( 'The "token" parameter must be set.', $e->getMessage() );
		}
		$this->assertTrue( $exception, "Got exception" );

		$exception = false;
		try {
			$this->doApiRequest( [
				'action' => 'upload',
				'token' => $token,
			], $data );
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertEquals( 'One of the parameters "filekey", "file" and "url" is required.',
				$e->getMessage() );
		}
		$this->assertTrue( $exception, "Got exception" );

		$exception = false;
		try {
			$this->doApiRequest( [
				'action' => 'upload',
				'url' => 'http://www.example.com/test.png',
				'token' => $token,
			], $data );
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertEquals( 'The "filename" parameter must be set.', $e->getMessage() );
		}
		$this->assertTrue( $exception, "Got exception" );

		$this->user->removeGroup( 'sysop' );
		$exception = false;
		try {
			$this->doApiRequest( [
				'action' => 'upload',
				'url' => 'http://www.example.com/test.png',
				'filename' => 'UploadFromUrlTest.png',
				'token' => $token,
			], $data );
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertStringStartsWith( "The action you have requested is limited to users in the group:",
				$e->getMessage() );
		}
		$this->assertTrue( $exception, "Got exception" );
	}

	/**
	 * @depends testClearQueue
	 */
	public function testSyncDownload( $data ) {
		$token = $this->user->getEditToken();

		$this->user->addGroup( 'users' );
		$data = $this->doApiRequest( [
			'action' => 'upload',
			'filename' => 'UploadFromUrlTest.png',
			'url' => 'http://upload.wikimedia.org/wikipedia/mediawiki/b/bc/Wiki.png',
			'ignorewarnings' => true,
			'token' => $token,
		], $data );

		$this->assertEquals( 'Success', $data[0]['upload']['result'] );
		$this->deleteFile( 'UploadFromUrlTest.png' );

		return $data;
	}

	protected function deleteFile( $name ) {
		$t = Title::newFromText( $name, NS_FILE );
		$this->assertTrue( $t->exists(), "File '$name' exists" );

		if ( $t->exists() ) {
			$file = wfFindFile( $name, [ 'ignoreRedirect' => true ] );
			$empty = "";
			FileDeleteForm::doDelete( $t, $file, $empty, "none", true );
			$page = WikiPage::factory( $t );
			$page->doDeleteArticle( "testing" );
		}
		$t = Title::newFromText( $name, NS_FILE );

		$this->assertFalse( $t->exists(), "File '$name' was deleted" );
	}
}
