<?php

use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\SystemBlock;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

/**
 * @todo Can this be converted to unit tests?
 *
 * @group Blocking
 * @covers \MediaWiki\Block\BlockErrorFormatter
 */
class BlockErrorFormatterTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$db = $this->createNoOpMock( IDatabase::class, [ 'getInfinity' ] );
		$db->method( 'getInfinity' )->willReturn( 'infinity' );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getReplicaDatabase' )->willReturn( $db );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
	}

	/**
	 * @dataProvider provideTestGetMessage
	 */
	public function testGetMessage( $block, $expectedKey, $expectedParams ) {
		$context = new DerivativeContext( RequestContext::getMain() );

		$formatter = $this->getServiceContainer()->getBlockErrorFormatter();
		$message = $formatter->getMessage(
			$block,
			$context->getUser(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' ),
			'1.2.3.4'
		);

		$this->assertSame( $expectedKey, $message->getKey() );
		$this->assertSame( $expectedParams, $message->getParams() );
	}

	public static function provideTestGetMessage() {
		$timestamp = '20000101000000';
		$expiry = '20010101000000';

		$databaseBlock = new DatabaseBlock( [
			'timestamp' => $timestamp,
			'expiry' => $expiry,
			'reason' => 'Test reason.',
		] );

		$systemBlock = new SystemBlock( [
			'timestamp' => $timestamp,
			'systemBlock' => 'test',
			'reason' => new Message( 'proxyblockreason' ),
		] );

		$compositeBlock = new CompositeBlock( [
			'timestamp' => $timestamp,
			'originalBlocks' => [
				$databaseBlock,
				$systemBlock
			]
		] );

		return [
			'Database block' => [
				$databaseBlock,
				'blockedtext',
				[
					'',
					'Test reason.',
					'1.2.3.4',
					'',
					null, // Block not inserted
					'00:00, 1 (january) 2001',
					'',
					'00:00, 1 (january) 2000',
				],
			],
			'Database block (autoblock)' => [
				new DatabaseBlock( [
					'timestamp' => $timestamp,
					'expiry' => $expiry,
					'auto' => true,
				] ),
				'autoblockedtext',
				[
					'',
					'(blockednoreason)',
					'1.2.3.4',
					'',
					null, // Block not inserted
					'00:00, 1 (january) 2001',
					'',
					'00:00, 1 (january) 2000',
				],
			],
			'Database block (partial block)' => [
				new DatabaseBlock( [
					'timestamp' => $timestamp,
					'expiry' => $expiry,
					'sitewide' => false,
				] ),
				'blockedtext-partial',
				[
					'',
					'(blockednoreason)',
					'1.2.3.4',
					'',
					null, // Block not inserted
					'00:00, 1 (january) 2001',
					'',
					'00:00, 1 (january) 2000',
				],
			],
			'System block (type \'test\')' => [
				$systemBlock,
				'systemblockedtext',
				[
					'',
					'(proxyblockreason)',
					'1.2.3.4',
					'',
					'test',
					'(infiniteblock)',
					'',
					'00:00, 1 (january) 2000',
				],
			],
			'System block (type \'test\') with reason parameters' => [
				new SystemBlock( [
					'timestamp' => $timestamp,
					'systemBlock' => 'test',
					'reason' => new Message( 'softblockrangesreason', [ '1.2.3.4' ] ),
				] ),
				'systemblockedtext',
				[
					'',
					'(softblockrangesreason: 1.2.3.4)',
					'1.2.3.4',
					'',
					'test',
					'(infiniteblock)',
					'',
					'00:00, 1 (january) 2000',
				],
			],
			'Composite block (original blocks not inserted)' => [
				$compositeBlock,
				'blockedtext-composite',
				[
					'',
					'(blockednoreason)',
					'1.2.3.4',
					'',
					'(blockedtext-composite-no-ids)',
					'(infiniteblock)',
					'',
					'00:00, 1 (january) 2000',
				],
			],
		];
	}

	/**
	 * @dataProvider provideTestGetMessageCompositeBlocks
	 */
	public function testGetMessageCompositeBlocks( $ids, $expected ) {
		$block = $this->getMockBuilder( CompositeBlock::class )
			->onlyMethods( [ 'getIdentifier' ] )
			->getMock();
		$block->method( 'getIdentifier' )
			->willReturn( $ids );

		$context = RequestContext::getMain();

		$formatter = $this->getServiceContainer()->getBlockErrorFormatter();
		$this->assertContains(
			$expected,
			$formatter->getMessage(
				$block,
				$context->getUser(),
				$context->getLanguage(),
				$context->getRequest()->getIP()
			)->getParams()
		);
	}

	public static function provideTestGetMessageCompositeBlocks() {
		return [
			'All original blocks are system blocks' => [
				[ 'test', 'test' ],
				'Your IP address appears in multiple blocklists',
			],
			'One original block is a database block' => [
				[ 100, 'test' ],
				'Relevant block IDs: #100 (your IP address may also appear in a blocklist)',
			],
			'Several original blocks are database blocks' => [
				[ 100, 101, 102 ],
				'Relevant block IDs: #100, #101, #102 (your IP address may also appear in a blocklist)',
			],
		];
	}

	/**
	 * @dataProvider provideTestGetMessages
	 */
	public function testGetMessages( $block, $expectedKeys ) {
		$context = new DerivativeContext( RequestContext::getMain() );

		$formatter = $this->getServiceContainer()->getBlockErrorFormatter();
		$messages = $formatter->getMessages(
			$block,
			$context->getUser(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' ),
			'1.2.3.4'
		);

		$this->assertSame( $expectedKeys, array_map( static function ( $message ) {
			return $message->getKey();
		}, $messages ) );
	}

	public static function provideTestGetMessages() {
		$timestamp = '20000101000000';
		$expiry = '20010101000000';

		$databaseBlock = new DatabaseBlock( [
			'timestamp' => $timestamp,
			'expiry' => $expiry,
			'reason' => 'Test reason.',
		] );

		$systemBlock = new SystemBlock( [
			'timestamp' => $timestamp,
			'systemBlock' => 'test',
			'reason' => new Message( 'proxyblockreason' ),
		] );

		$compositeBlock = new CompositeBlock( [
			'timestamp' => $timestamp,
			'originalBlocks' => [
				$databaseBlock,
				$systemBlock
			]
		] );

		return [
			'Database block' => [
				$databaseBlock,
				[ 'blockedtext' ],
			],

			'System block (type \'test\')' => [
				$systemBlock,
				[ 'systemblockedtext' ],
			],
			'Composite block (original blocks not inserted)' => [
				$compositeBlock,
				[ 'blockedtext', 'systemblockedtext' ],
			],
		];
	}
}
