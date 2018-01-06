<?php
if (isset($argv)) {
	print "Usage:\n";
	print 'phpunit ' . $argv[0] . "\n";
	print "or:\n";
	print 'MAILBOX=... USER=... PASS=... phpunit ' . $argv[0] . "\n";
	class PHPUnit_Framework_TestCase {}
}


getenv('MAILBOX') && define('IMAP_TEST_MAILBOX', getenv('MAILBOX'));
getenv('USER') && define('IMAP_TEST_USER', getenv('USER'));
getenv('PASS') && define('IMAP_TEST_PASS', getenv('PASS'));


if (!defined('IMAP_TEST_MAILBOX')) {
	define('IMAP_TEST_MAILBOX', '{pop.zoho.com:995/pop3/ssl/novalidate-cert}');	# free mail service
}

class Test extends PHPUnit_Framework_TestCase {

	const CLASS_NAME = 'IMAP\\Client';
	const FILE_NAME = '../src/IMAP/Client.php';

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
			'__construct'	=> ReflectionMethod::IS_PUBLIC,
			'__call'		=> ReflectionMethod::IS_PUBLIC,
			'__destruct'	=> ReflectionMethod::IS_PUBLIC,
			'headerinfo'	=> ReflectionMethod::IS_PUBLIC,
			'msgno'			=> ReflectionMethod::IS_PUBLIC,
			'uid'			=> ReflectionMethod::IS_PUBLIC,
			#'register'	=> ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC,
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
		if (defined('IMAP_TEST_MAILBOX') && defined('IMAP_TEST_USER') && defined('IMAP_TEST_PASS')) {
			$class = static::CLASS_NAME;
			$o = new $class(
				IMAP_TEST_MAILBOX,
				IMAP_TEST_USER,
				IMAP_TEST_PASS
			);
			$this->assertTrue(is_object($o), 'Create object.');
			$num_msg = $o->num_msg();
			$this->assertEquals('integer', gettype($num_msg), 'Calling num_msgs() returns an int.');
		}
	}
}



if (isset($argv)) {
	require_once(__DIR__ . '/' . Test::FILE_NAME);
	$class = Test::CLASS_NAME;
	if (defined('IMAP_TEST_MAILBOX') && defined('IMAP_TEST_USER') && defined('IMAP_TEST_PASS')) {
		$o = new $class(
			IMAP_TEST_MAILBOX,
			IMAP_TEST_USER,
			IMAP_TEST_PASS,
			null,
			null,
			null,
			array(
				#'debug' => true
			)
		);
		$num_msg = $o->num_msg();
		print "num_msg: $num_msg\n";	
		#print_r($o->mailboxmsginfo());
		#print_r($o->fetch_overview('1:' . $num_msg)); die;		
		for ($msgno=1; $msgno<=$num_msg; $msgno++) {
			print "\nmsgno: $msgno\n";
			$uid = $o->uid($msgno);
			print "uid from msgno: $uid\n";
			print "msgno from uid: " . $o->msgno($uid) . "\n";
			$headerinfo = $o->headerinfo($msgno);
			$message_id = $headerinfo->message_id;
			print "message_id: $message_id\n";	
			print 'UIDL: ' . $headerinfo->uidl() . "\n";
			#print_r($headerinfo);
			#print_r(get_object_vars($headerinfo)); die;
			#require_once('../../functions/var_export_natural.php');
			#var_export_natural($headerinfo); die;
		}
	}
	else {
		print "Required defines missing!\n";
	}
}
