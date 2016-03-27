<?php
/**
* Contains the IMAP\Client and IMAP\Exception classes.
*
* @author    Craig Manley
* @copyright Copyright © 2016, Craig Manley (craigmanley.com). All rights reserved.
* @version   $Id$
* @package   IMAP
*/
namespace IMAP;


/**
* Custom Exception class for failed IMAP\Client method calls.
*
* @package IMAP
*/
class Exception extends \Exception {}




/**
* IMAP wrapper class for all the PHP imap_* functions that take a resource $stream_id as first argument.
* The constructor calls and takes the same arguments as imap_open().
* The destructor calls imap_close(), so closing the connection is done by destroying the object.
*
* @package IMAP
*/
class Client {

	protected $imap_stream;
	protected $proxy_method_cache = array(); // map of trusted method name => ReflectionFunction object pairs; built on-the-fly
	# options:
	protected $debug;

	/**
	* Constructor.
	* See imap_open() for more information.
	*
	* @param string $mailbox
	* @param string $username
	* @param string $password
	* @param int $options = 0
	* @param int $n_retries = 0
	* @param array $params = NULL
	* @param array $objopts = NULL associative array of options for this object; supported keys: debug => bool
	*/
	public function __construct($mailbox, $username, $password, $options = 0, $n_retries = 0, array $params = null, array $objopts = null) {
		if (is_null($params)) {
			$params = array();
		}
		if (is_array($objopts) && $objopts) {
			$this->debug = (bool) @$objopts['debug'];
		}
		$this->imap_stream = \imap_open($mailbox, $username, $password, $options, $n_retries, $params);
		if (!$this->imap_stream) {
			throw new Exception("imap_open('$mailbox', '$user', '...') failed");
		}
		\imap_errors(); # clear errors from appearing at cleanup
	}


	/**
	* Destructor.
	* Closes the connection.
	*/
	public function __destruct() {
		$this->imap_stream && \imap_close($this->imap_stream);
	}


	/**
	* PHP magic method that proxies unknown methods to the matching imap_*() function counterpart as long
	* as that counterpart takes a resource $stream_id as first argument, with the exception of imap_close().
	*
	* @param string $name
	* @param array $arguments
	* @return mixed
	*/
	public function __call($name, array $arguments) {
		if (!(is_string($name) && strlen($name))) {
			throw new \BadMethodCallException('No method name given'); # this probably can't occur
		}
		$name = strtolower($name);
		do {
			if ($name == 'close') {
				$this->debug && error_log(__METHOD__ . " $name may not be called as method");
				break;
			}
			$rf = null;
			if (array_key_exists($name, $this->proxy_method_cache)) {
				$this->debug && error_log(__METHOD__ . " $name found in proxy method cache");
				$rf = $this->proxy_method_cache[$name];
			}
			else {
				$this->debug && error_log(__METHOD__ . " $name not found in proxy method cache");
				$func = '\\imap_' . $name;
				if (!function_exists($func)) {
					$this->debug && error_log(__METHOD__ . " $func does not exist");
					break;
				}
				$rf = new \ReflectionFunction($func);
				$params = $rf->getParameters();
				if (!$params) {
					$this->debug && error_log(__METHOD__ . " $func as no parameters");
					break;
				}
				$p = reset($params);
				if (!(
					((PHP_MAJOR_VERSION < 7) || $p->hasType())
					&&
					((PHP_MAJOR_VERSION < 7) || ($p->getType() == 'resource'))	// untested
					&&
					!$p->allowsNull()
					&&
					!$p->isOptional()
					&&
					($p->getName() == 'stream_id') # name can't be trusted across PHP versions
				)) {
					$this->debug && error_log(__METHOD__ . " $func doesn't have the expected first parameter");
					$rf = null;
					break;
				}
				$this->debug && error_log(__METHOD__ . " $func added to proxy method cache");
				$this->proxy_method_cache[$name] = $rf;
			}
			if ($rf) {
				$args = array($this->imap_stream);
				foreach ($arguments as &$arg) {
					$args []=& $arg;
					unset($arg);
				}
				$result = $rf->invokeArgs($args);
				if ($result === false) {
					throw new Exception("$func() call failed");
				}
				return $result;
			}
		} while(0);
		throw new \BadMethodCallException("The method '$name' does not exist");
	}

}
