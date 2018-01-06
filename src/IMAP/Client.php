<?php
/**
* Contains the IMAP\Client and IMAP\Exception classes.
*
* @author    Craig Manley
* @copyright Copyright © 2016, Craig Manley (craigmanley.com)
* @version   $Id: Client.php,v 1.5 2018/01/06 00:59:51 cmanley Exp $
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
* @ignore Require dependencies.
*/
require_once(__DIR__ . '/HeaderInfo.php');



/**
* IMAP wrapper class for all the PHP imap_* functions that take a resource $stream_id as first argument.
* The constructor calls and takes the same arguments as imap_open().
* The destructor calls imap_close(), so closing the connection is done by destroying the object.
*
* @package IMAP
*/
class Client {

	protected $imap_stream;
	protected $proxy_method_cache = array(); # map of trusted method name => ReflectionFunction object pairs; built on-the-fly
	protected $driver;
	protected $pop3_uidl_to_msgnum_cache;
	protected $pop3_msgnum_to_uidl_cache;

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
		if ($info = $this->mailboxmsginfo()) {
			$this->driver = $info->Driver;
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
	* PHP magic method that proxies unknown methods to the matching imap_*() function counterpart.
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
			if ($this->driver == 'pop3') {
				# Clear UIDL cache
				if (($name == 'expunge') || ($name == 'reopen')) {
					$this->pop3_uidl_to_msgnum_cache = null;
					$this->pop3_msgnum_to_uidl_cache = null;
				}
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
					$this->debug && error_log(__METHOD__ . " $func has no parameters");
					break;
				}
				# Check the first parameter in detail.
				$p = reset($params);
				if (PHP_MAJOR_VERSION >= 7) {
					if ($p->hasType() && ($p->getType() != 'resource')) {
						$this->debug && error_log(__METHOD__ . " $func first parameter has an unexpected type (" . $p->getType() . ')');
						break;
					}
				}
				if ($p->allowsNull()) {
					$this->debug && error_log(__METHOD__ . " $func first parameter should not allow null");
					break;
				}
				if ($p->isOptional()) {
					$this->debug && error_log(__METHOD__ . " $func first parameter should not be optional");
					break;
				}
				if ($p->getName() != 'stream_id') { # name can't be guaranteed across PHP versions; disable/improve this check if problematic
					$this->debug && error_log(__METHOD__ . " $func first parameter has unexpected name (" . $p->getName() . ')');
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


	/**
	* This method casts the result of imap_headerinfo() into a HeaderInfo object and returns that.
	*
	* @param HeaderInfo $headerinfo
	* @return string|false
	*/
	public function headerinfo(/* int $msg_number [, int $fromlength = 0 [, int $subjectlength = 0 [, string $defaulthost = NULL ]]] */) {
		$params = func_get_args();
		array_unshift($params, $this->imap_stream);
		$result = call_user_func_array('\\imap_headerinfo', $params);
		if (!$result) {
			return false;
		}
		return new HeaderInfo($result);
	}


	/**
	* This is similar to imap_msgno(), except that it has a built in emulation to work for POP3 too.
	* In the case of POP3, the $uid argument must be a UIDL string as returned by the uid() or headerinfo($msgno)->uidl() methods.
	*
	* @param int|string $uid
	* @return int|false
	*/
	public function msgno($uid) {
		if (!(is_int($uid) || (is_string($uid)))) {
			throw new \InvalidArgumentException('uid must be an int or a string');
		}
		if ($this->driver != 'pop3') {
			return \imap_msgno($this->imap_stream, $uid);
		}

		# If the uid is actually a msgno int as the original imap_uid() returns for POP3, then forward call to imap_msgno().
		if (is_int($uid) || (is_string($uid) && preg_match('/^\d{1,10}$/', $uid))) {
			return \imap_msgno($this->imap_stream, $uid);
		}

		# Try cache
		if (is_array($this->pop3_uidl_to_msgnum_cache)) {
			return array_key_exists($uid, $this->pop3_uidl_to_msgnum_cache) ? $this->pop3_uidl_to_msgnum_cache[$uid] : false;
		}

		# Rebuild cache
		$num_msg = $this->num_msg();
		if (!$num_msg) {
			return false;
		}
		$result = false;
		$pop3_uidl_to_msgnum_cache = array();
		$pop3_msgnum_to_uidl_cache = array();
		for ($i=1; $i<=$num_msg; $i++) {
			if ($headerinfo = $this->headerinfo($i)) {
				$uidl = $headerinfo->uidl();
				if ($uid == $uidl) {
					$result = $i;
					#return $i;
				}
				$pop3_uidl_to_msgnum_cache[$uidl] = $i;
				$pop3_msgnum_to_uidl_cache[$i] = $uidl;
			}
		}
		$this->pop3_uidl_to_msgnum_cache = $pop3_uidl_to_msgnum_cache;
		$this->pop3_msgnum_to_uidl_cache = $pop3_msgnum_to_uidl_cache;
		return $result;
	}


	/**
	* This is similar to the imap_uid(), except that it has a built in emulation to work for POP3 too.
	* In the case of POP3, the result is an emulated UIDL string.
	*
	* @param int $msgno
	* @return int|string
	*/
	public function uid($msgno) {
		if (!(is_int($msgno) || (is_string($msgno) && preg_match('/^\d{1,10}$/', $msgno)))) {
			throw new \InvalidArgumentException('msgno must be an int');
		}
		if ($this->driver != 'pop3') {
			return \imap_uid($this->imap_stream, $msgno);
		}

		# Try cache first
		if (is_array($this->pop3_msgnum_to_uidl_cache)) {
			return array_key_exists($msgno, $this->pop3_msgnum_to_uidl_cache) ? $this->pop3_msgnum_to_uidl_cache[$msgno] : false;
		}

		# Recalculate UIDL
		$headerinfo = $this->headerinfo($msgno);
		return $headerinfo ? $headerinfo->uidl() : $headerinfo;
	}

}
