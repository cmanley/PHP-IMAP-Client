PHP IMAP\Client
===============

The PHP IMAP\Client class is a wrapper class for all the PHP imap_* functions that take a resource $stream_id as first argument.

### Requirements:
*  PHP 5.3.0 or newer

### Usage:
All the classes contain PHP-doc documentation, so for now, take a look at the code of IMAP.php or one of the test scripts in the t subdirectory.

**Example:**

	<?php
	require_once('/path/to/IMAP/Client.php');

	// Connect to free POP3 account:
	$client = new IMAP\Client(
		'{pop.zoho.com:995/pop3/ssl/novalidate-cert}',
		'my_user',
		'my_pass'
	);
	$num_msg = $o->num_msg();	// internally, this is forwarded to the PHP function imap_num_msg()
	print "Number of messages in queue: $num_msg\n";

	// Get messages
	for ($i=1; $i<=$num_msgs; $i++) {
		$header = $client->headerinfo($i);
		$message_id = $header->message_id;
		print "message_id: $message_id\n";

		// Grab the body for the same message
		$body = $client->body($i);
	}


	// Disconnection occurs when the object is destroyed by going out of scope, or explicitly as such:
	unset($client);


### Licensing
All of the code in this library is licensed under the MIT license as included in the LICENSE file
