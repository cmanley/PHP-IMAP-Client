<?php
if (isset($argv)) {
	print "Running outside of phpunit. Consider using phpunit.\n";
	class PHPUnit_Framework_TestCase {}
}


class Test extends PHPUnit_Framework_TestCase {

	const CLASS_NAME = 'IMAP\\HeaderInfo';
	const FILE_NAME = '../src/IMAP/HeaderInfo.php';

	public static function get_test_data() {
		return (object) array(
			'date' => 'Wed, 26 Aug 2015 02:41:35 +0200',
			'Date' => 'Wed, 26 Aug 2015 02:41:35 +0200',
			'subject' => 'Test message',
			'Subject' => 'Test message',
			'message_id' => '<55DD0B3F.3070803@bean.org>',
			'toaddress' => 'bla@zoho.com',
			'to' => array(
				 (object) array(
					 'mailbox' => 'bla',
					 'host' => 'zoho.com',
				 ),
			),
			'fromaddress' => 'Mr. Bean <mr@bean.org>',
			'from' => array(
				 (object) array(
					 'personal' => 'Mr. Bean',
					 'mailbox' => 'beanbag',
					 'host' => 'bean.org',
				 ),
			),
			'reply_toaddress' => 'Mr. Bean <mr@bean.org>',
			'reply_to' => array(
				 (object) array(
					 'personal' => 'Mr. Bean',
					 'mailbox' => 'beanbag',
					 'host' => 'bean.org',
				 ),
			),
			'senderaddress' => 'Mr. Bean <mr@bean.org>',
			'sender' => array(
				 (object) array(
					 'personal' => 'Mr. Bean',
					 'mailbox' => 'beanbag',
					 'host' => 'bean.org',
				 ),
			),
			'Recent' => 'N',
			'Unseen' => ' ',
			'Flagged' => ' ',
			'Answered' => ' ',
			'Deleted' => ' ',
			'Draft' => ' ',
			'Msgno' => '	1',
			'MailDate' => '26-Aug-2015 02:41:35 +0200',
			'Size' => '589',
			'udate' => 1440549695,
			'uidl' => '<66DD0B3F.3070803@bean.org>.ebb003572fb7275f4d56de37104fa58e',
		);
	}

    public function testRequire() {
    	$file = __DIR__ . '/' . static::FILE_NAME;
		$this->assertFileExists($file);
		$this->assertTrue((boolean) include $file, 'Check include result');
    }

    public function testClassExists() {
    	$class = static::CLASS_NAME;
		$this->assertTrue(class_exists($class), 'Check that class name "' . $class . '" exists.');
	}

    public function testMethodsExist() {
		$class = static::CLASS_NAME;
		$methods = array(
			'__construct'			=> ReflectionMethod::IS_PUBLIC,
			'__set'					=> ReflectionMethod::IS_PUBLIC,
			'headerinfo_to_uidl'	=> ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC,
			'uidl'					=> ReflectionMethod::IS_PUBLIC,
		);
		foreach ($methods as $name => $expected_modifiers) {
			$exists = method_exists($class, $name);
			$this->assertTrue($exists, "Check method $class::$name() exists.");
			if ($exists) {
				$method = new ReflectionMethod($class, $name);
				$actual_modifiers = $method->getModifiers() & (
					ReflectionMethod::IS_STATIC |
					ReflectionMethod::IS_PUBLIC |
					ReflectionMethod::IS_PROTECTED |
					ReflectionMethod::IS_PRIVATE |
					ReflectionMethod::IS_ABSTRACT |
					ReflectionMethod::IS_FINAL
				);
				#error_log("$name expected: " . $expected_modifiers);
				#error_log("$name actual:   " . $actual_modifiers);
				$this->assertEquals($expected_modifiers, $actual_modifiers, "Expected $class::$name() modifiers to be \"" . join(' ', Reflection::getModifierNames($expected_modifiers)) . '" but got "' . join(' ', Reflection::getModifierNames($actual_modifiers)) . '" instead.');
			}
		}
	}

	public function testCreate() {
		$class = static::CLASS_NAME;
		$o = new $class(static::get_test_data());
		$this->assertTrue(is_object($o), 'Create object.');
		$uidl = $o->uidl();
		$this->assertEquals('string', gettype($uidl), 'Calling uidl() returns a string.');
	}

}


if (isset($argv)) {
	require_once(__DIR__ . '/' . Test::FILE_NAME);
	$class = Test::CLASS_NAME;
	$data = Test::get_test_data();
	$o = new $class($data);
	$uidl = $o->uidl();
	print "UIDL: $uidl\n";
}
