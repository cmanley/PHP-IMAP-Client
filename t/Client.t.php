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
			# public
			'__construct',
			'__call',
			'__destruct',
		);
		foreach ($methods as $method) {
			$this->assertTrue(method_exists($class, $method), "Check method $class::$method() exists.");
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
			$header = $o->headerinfo($msgno);
			$message_id = $header->message_id;
			print "message_id: $message_id\n";	
		}
	}
	else {
		print "Required defines missing!\n";
	}
}
