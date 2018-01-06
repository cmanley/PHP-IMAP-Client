<?php
/**
* Contains the IMAP\HeaderInfo class.
*
* @author    Craig Manley
* @copyright Copyright © 2017 Craig Manley (craigmanley.com)
* @version   $Id: HeaderInfo.php,v 1.1 2018/01/06 00:59:51 cmanley Exp $
* @package   IMAP
*/
namespace IMAP;



/**
* IMAP wrapper class for all the PHP imap_* functions that take a resource $stream_id as first argument.
* The constructor calls and takes the same arguments as imap_open().
* The destructor calls imap_close(), so closing the connection is done by destroying the object.
*
* @package IMAP
*/
class HeaderInfo {

	protected $_locked = false;	# when true, setting properties is denied.
	protected $_uidl;

	/**
	* Constructor.
	* Encapsulates the result of the imap_headerinfo() function.
	*
	* @param \stdClass $headerinfo
	*/
	public function __construct(\stdClass $headerinfo) {
		foreach (get_object_vars($headerinfo) as $k => $v) {
			$this->$k = $v;
		}
		$this->_locked = true;
	}


	/**
	* PHP magic method that sets a attribute value using attribute assignment.
	* @throws \BadMethodCallException when attempting to set values after instantiation
	*/
	public function __set($key, $value) {
		if ($this->_locked && !(property_exists($this, $key) && ($this->$key === $value))) {
			throw new \BadMethodCallException('Illegal attempt to set attribute "' . $key . '" in read-only object "' . get_class($this) . '"');
		}
		$this->$key = $value;
	}


	/**
	* Static helper method that returns an emulated UIDL for the given message header as returned by imap_headerinfo().
	* The PHP IMAP module does not support POP3 UIDLs which is the reason that this method exists.
	*
	* @param array|\stdClass|HeaderInfo $headerinfo
	* @return string|false
	*/
	public static function headerinfo_to_uidl($headerinfo) {
		if (!$headerinfo) {
			return false;
		}
		if (is_object($headerinfo)) {
			if (!((get_class($headerinfo) == '\\stdClass') || is_a($headerinfo, __NAMESPACE__ . '\\HeaderInfo'))) {
				throw new \InvalidArgumentException('The given argument must be either an array, a \stdClass object, or an instance of ' . __NAMESPACE__ . '\\HeaderInfo');
			}
		}
		elseif (is_array($headerinfo)) {	# experimental undocumented feature
			$headerinfo = (object) $headerinfo;
		}
		else {
			throw new \InvalidArgumentException('The given argument must be either an array, a \stdClass object, or an instance of ' . __NAMESPACE__ . '\\HeaderInfo');
		}
		$hashvars = array();
		foreach (array(
			'toaddress',
			'fromaddress',
			'ccaddress',
			'bccaddress',
			'reply_toaddress',
			'senderaddress',
			'return_pathaddress',
			'date',
			'message_id',
			'subject',
			'Size',
		) as $key) {
			if (isset($headerinfo->$key) && is_scalar($headerinfo->$key) && strlen($headerinfo->$key)) {
				$hashvars []= $headerinfo->$key;
			}
		}
		if (!$hashvars) {
			return false;
		}
		$result = null;
		# http://php.net/manual/en/function.hash.php
		if (isset($headerinfo->message_id) && is_scalar($headerinfo->message_id) && strlen($headerinfo->message_id)) {
			$result = $headerinfo->message_id . '.' . hash('md5', join("\n", $hashvars));
		}
		else {
			$result = hash('sha1', join("\n", $hashvars));
		}
		return $result;
	}


	/**
	* Returns an emulated UIDL.
	* The PHP IMAP module does not support POP3 UIDLs which is the reason this method exists.
	*
	* @return string|false
	*/
	public function uidl() {
		if (is_null($this->_uidl)) {
			$uidl = static::headerinfo_to_uidl($this);
			$this->_locked = false;
			$this->uidl = $uidl;
			$this->_locked = true;
		}
		return $this->uidl;
	}
}
