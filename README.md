PHP IMAP\Client
===============

The `IMAP\Client` class is a wrapper class for the [imap_*](http://php.net/manual/en/ref.imap.php) functions exported by the [imap](http://php.net/manual/en/book.imap.php) extension.
A connection is made when you instantiate the object and closed again when the object is destroyed.
The public methods of this class have the same names as the [imap_*](http://php.net/manual/en/ref.imap.php) functions but with the `imap_` prefix dropped off.

### POP3 UIDL support:
The standard [imap_uid()](http://php.net/manual/en/function.imap-uid.php) function does not support POP3, but the `uid()` and `msgno()` methods of this class do by means of emulating a POP3 UIDL when a POP3 connection is being used. This is done by creating a virtually unique hash of a combination of several key message fields. This is an example of an emulated UIDL:
`<55DD0CCE.4060108@server.org>.b12b7081af03636a1cb783c0a4f7701a`
As you can see, the hash is prefixed by the `message_id` header if it is present (which is usually the case).

### Requirements:
*  PHP 5.3 or newer with the [imap](http://php.net/manual/en/book.imap.php) extension.

### Usage:
All the classes contain PHP-doc documentation, so for now, take a look at the code of [IMAP/Client.php](../master/src/IMAP/Client.php) or one of the test scripts in the [t](../master/t/) subdirectory.

**Example:**

	<?php
	require_once('/path/to/IMAP/Client.php');

	# Connect to free POP3 account:
	$client = new IMAP\Client(
		'{pop.zoho.com:995/pop3/ssl/novalidate-cert}',
		'my_user',
		'my_pass'
	);
	$num_msg = $o->num_msg();	# internally, this is forwarded to the PHP function imap_num_msg()
	print "Number of messages in queue: $num_msg\n";

	# Get messages
	for ($i=1; $i<=$num_msgs; $i++) {
		$headerinfo = $client->headerinfo($i);	# returns a HeaderInfo object
		$message_id = $headerinfo->message_id;
		print "message_id: $message_id\n";

		# Grab the body for the same message
		$body = $client->body($i);
		
		# Print the uid or emulated UIDL in the case of POP3 connections
		print 'UID: ' . $client->uid($i) . "\n";

		# or alternatively, since we are using POP3...
		print 'POP3 emulated UIDL: ' . $headerinfo->uidl() . "\n";
	}

	# Disconnection occurs when the object is destroyed by going out of scope, or explicitly as such:
	unset($client);


### Licensing
All of the code in this library is licensed under the MIT license as included in the LICENSE file
