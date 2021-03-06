<?php
/**
 * File Encryption Management Class
 *
 * @package		CJCI
 * @subpackage	StorageLibrary
 * @category	CodeIgniter Library
 * @author		Carlos Jimenez Guirao
 * @link		http://WillWriteThisSoon.todo
 */

namespace CJCI\ResStorage\FileManagers;
use Exception,CJCI\ResStorage\ResStorage;

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once("RSFile.php");

/**
 * RSFileEncrypted
 * File Encryption Management Class
 *
 * @package		CJCI
 * @subpackage	StorageLibrary
 * @category	CodeIgniter Library
 * @author		Carlos Jimenez Guirao
 * @link		http://WillWriteThisSoon.todo
 */

class RSFileEncrypted extends RSFile{
	
	/**
     * Hashed provided clearkey.
     * @access private
     * @var string
     */		
	private $securekey;

	/**
     * Encryption generated IV
     * @access public
     * @var string
     */
	public  $b64_iv;

	/**
     * MCRYPT algoritm to use
     * @access private
     * @var string
     */
	private $_encription_algoritm = 'rijndael-256'; 

	/**
	 * Encryption Mode 
     * @access private
     * @var integer
     */
	private $_mcrypt_mode = MCRYPT_MODE_CBC;	

	/**
	 * Size of initialization vector
     * @access private
     * @var integer
     */
	private $_iv_size = 32;						

	
	/**
	 * Random source we use for the initialization vector
	 * MCRYPT_RAND (system random number generator) Default in mcrypt						
	 * MCRYPT_DEV_RANDOM (read data from /dev/random) Slow but more secure
	 * MCRYPT_DEV_URANDOM (read data from /dev/urandom) Faster and a bit less secure
     * @access private
     * @var integer
     */
	private $_rnd_gen = MCRYPT_DEV_URANDOM;		

	/**
	 * __construct
	 *
	 * Sets securekey based on clearkey provided and initializes IV
	 * 
	 * @param	string $storage_dir
	 * @param	string $clearkey
	 * @return	void
	 *
	 */
	public function __construct() {
		$argv = func_get_args(); 
		parent::__construct($argv[0]);
		$this->securekey = hash('sha256',$argv[1],TRUE);
        $this->b64_iv = base64_encode(mcrypt_create_iv($this->_iv_size,$this->_rnd_gen));
	}

	/**
	 * copy
	 *
	 * Copy a file from source to destination and encrypts it
	 * 
	 * @param	string $origin
	 * @param	string $destination
	 * @return	true|false 	
	 *
	 */
	protected function copy($origin, $destination){
		$fp = fopen( $destination, 'wb');
		stream_filter_append($fp, 'mcrypt.'.$this->_encription_algoritm, STREAM_FILTER_WRITE, array('iv'=>base64_decode($this->b64_iv), 'key'=>$this->securekey, 'mode' => $this->_mcrypt_mode));
		fwrite($fp, file_get_contents($origin));
		fclose($fp);
		return true;
	}

	/**
	 * file_get_contents
	 *
	 * Returns decrypted file contents to a string
	 * 
	 * @return	string
	 *
	 */
	public function file_get_contents(){
		$fp = fopen($this->get_full_path(), 'rb');
		stream_filter_append($fp, 'mdecrypt.'.$this->_encription_algoritm, STREAM_FILTER_READ, array('iv'=>base64_decode($this->b64_iv), 'key'=>$this->securekey, 'mode' => $this->_mcrypt_mode));
		$data = rtrim(stream_get_contents($fp));
		fclose($fp);
		return $data;
	}

	/**
	 * readfile
	 *
	 * Returns file contents to be downloaded
	 *
	 */
	public function readfile(){
		header('Content-Description: File Transfer');
	    header('Content-Type: '.$this->mimetype);
	    header('Content-Disposition: attachment; filename='.$this->filename);
	    header('Content-Transfer-Encoding: binary');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: public');
	    ob_clean();
	    flush();
	    die($this->file_get_contents());
	    exit;
	}

}

