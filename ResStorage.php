<?php
/**
 * ResStorage
 * 
 * Library interfaces that handles metadata and uses a FileHandler to 
 * Physically handle files.
 */

namespace CJCI\ResStorage ;
use Exception;

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once("FileManagers/RSFile.php");
require_once("FileManagers/RSFileEncrypted.php");

/**
 * CJCI
 *
 * CodeIgniter Resource storage and management class.
 * It store files, with their metadata.
 * Allows encrypted storage.
 * 
 * Config:
 * 
 * As a parameter or creating a res_storage.php file in your CodeIgniter's config dir:
 * 
 * `$config['clearkey'] = "YourSecretKey" ;`  
 * 
 * `$config['storage_dir'] = "/Path/To/Your/Filesystem/";` 
 *
 * //Optional if you want to change default file handler
 *
 * `$config['file_handler'] = "My_File_Handler_Class";` 
 *   
 * 
 * Sample usage:
 * 
 * *Instantiate*
 * `$this->load->library('res_storage');`
 * 
 * *Store a file* 
 * `$uuid = $this->res_storage->store_file('/Path/to/File.txt');`
 * 
 * *Read Metadata*
 * `var_dump($this->res_storage->metadata($uuid));`
 * 
 * *Get File contents*
 * `$contents = $this->res_storage->file_get_contents($uuid);`
 * 
 * *Read contents tobrowser using metadata*
 * `$contents = $this->res_storage->readfile($uuid);`
 * 
 * *Delete File*
 * `$this->res_storage->delete($uuid)`
 * 
 * @package		CJCI
 * @subpackage	ResStorage
 * @author		Carlos Jimenez Guirao
 * @copyright	Copyright (c) 2013, Carlos Jimenez Guirao.
 * @license		GPL V3
 * @link		http://www.CJPackages.net
 * @since		Version 1.0
 * @filesource
 */
class ResStorage {
	
	/**
     * If you use File encryption you MUST change this.
     * clearkey
     * @access protected
     * @var string
     */
	protected $clearkey 	  = "ThisSecretMustBeOverriden!";

	
	/**
     * Where files will be stored.
     * storage_dir
     * @access public
     * @var string
     */
	public  $storage_dir  = "/tmp/MustBeOverridenToo/"; 	

	/**
     * Class to use to handle phisically files. Default RSFileEncrypted
	 * use RSFile for non encrypted handling. (or write your own!)Where files will be stored.
     * file_handler
     * @access public
     * @var string
     */
	public $file_handler    = "RSFileEncrypted"; 			
	
	/**
     * Table name where all metadata is stored. 
     * table
     * @access protected
     * @var string
     */											 			
	protected $table 		  =	"res_storage";				

	/**
     * Where we will bind CI singleton to use CI database library
     * CI
     * @access private
     * @var object
     */
	private $CI;

	/**
	 * __construct
 	 * Binds CI object and checks/creates database needed to store metadata
 	 * 
	 * @param $args array Config variables from CI config dir or manually passed that contains clearkey and storage_dir keys.
	 * @return void
	 */
	public function __construct($args = array()){
		log_message('debug', "lib ResStorage Class Initialized");
        $this->CI =& get_instance();
        $this->checkDatabase();
        if (!empty($args['clearkey'])) $this->clearkey = $args['clearkey'];
        if (!empty($args['storage_dir'])) $this->storage_dir = $args['storage_dir'];
        if (!empty($args['file_handler'])) $this->file_handler = $args['file_handler'];
	}

	/**
	 * checkDatabase
 	 * Checks and creates Database if not exists
	 *
	 * @return void
	 */
	public function checkDatabase(){
		if (!$this->CI->db->table_exists($this->table)){
			log_message('debug', "lib_ResStorage database doesn't exist.");
			$query = $this->CI->db->query(
					"CREATE TABLE IF NOT EXISTS `".$this->table."` (
					  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
					  `uuid` varchar(255) DEFAULT NULL,
					  `filename` varchar(255) DEFAULT NULL,
					  `path` tinytext DEFAULT NULL,
					  `mimetype` varchar(255) DEFAULT NULL,
					  `hash` varchar(255) DEFAULT NULL,
					  `b64_iv` varchar(255) DEFAULT NULL,
					  `accessed` int(11) NOT NULL DEFAULT '0',
					  `stored` datetime DEFAULT NULL,
					  `lastaccess` datetime DEFAULT NULL,
					  PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1");
			if ($query) log_message('debug', "lib_ResStorage database created sucessfully.");
			else {
				throw new Exception('lib_ResStorage database doesn\'t exist and couldn\'t be created.');
			}
		}
	}

	/**
	 * create_path
	 * 
	 * Checks and creates path
	 *
	 * @param string $path
	 * @return true|false
	 */
	public function create_path($path){
		// iterate each directory creating it if doesn't exist.
		$currentpath = "";
		foreach (explode('/',$path) as $dir) {
			$currentpath .= $dir;
			if (!empty($dir) && !is_dir($currentpath)){
				mkdir($currentpath);
			}
			$currentpath .= '/';
		}
		if (!is_readable($path)){
			throw new Exception('Couldn\'t create path: '.$path);
			return false;
		}
		return true;
	}

	/**
	 * store_file
	 * 
	 * Store a copy of an existing document
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function store_file($path){
		$file = $this->newFileHandler();
		try {
			$file->store($path);
		} catch (Exception $e) {
			throw $e;
			return false;
		}
		if ($this->CI->db->insert($this->table,$file)){
		 	return $file->uuid;
		}
		else {
			throw new Exception('Unable to store file metadata, aborting.');
			$file->delete();
			return false;
		}
		
	}

	/**
	 *  newFileHandler
	 * 	
	 * File Handler declaration. You want to override this if coded your own class so it provides correct args.
	 *	returns the correct object using correct class
	 * @return object
	 */
	private function newFileHandler(){
		if (in_array($this->file_handler, array('RSFile','RSFileEncrypted'))){
			$class = __NAMESPACE__.'\FileManagers\\'.$this->file_handler;
			return new $class($this->storage_dir, $this->clearkey);	
		}
		else{
			return new $this->file_handler($this->storage_dir, $this->clearkey);
		}
	}

	/**
	 *  getFileHandler
	 * 	
	 *  returns an initialized file handler ready to use
	 * @param string $uuid 
	 * @return object
	 */
	private function getFileHandler($uuid){
		$handler = $this->newFileHandler();
		$handler->initialize($this->metadata($uuid));
		$this->CI->db->where('uuid',$uuid)->update($this->table,array('accessed' => ($handler->accessed+1), 'lastaccess' => date('Y-m-d H:i:s')));
		return $handler;
	}

	/**
	 * readfile
	 *
	 * Returns decrypted file to be downloaded
	 * @param string $uuid 
	 */
	public function readfile($uuid){
		if($file = $this->getFileHandler($uuid)){
		 return $file->readfile();
		}else{
			throw new Exception('Unable to read file.');
			return false;
		}
	}

	/**
	 * file_get_contents
	 *
	 * Returns file contents to a string
	 * 
	 * @param string $uuid File UUID
	 * @return	string|false
	 *
	 */
	public function file_get_contents($uuid){
		if($file = $this->getFileHandler($uuid)){
			return $file->file_get_contents();
		}else{
			throw new Exception('Unable to read file contents.');
			return false;
		}
	}

	/**
	 * delete
	 *
	 * Delete file 
	 * 
	 * @param string $uuid File UUID
	 * @return	true|false
	 *
	 */
	public function delete($uuid){
		if($file = $this->getFileHandler($uuid)){
			if($file->delete()){
				$this->CI->db->where('uuid',$uuid)->delete($this->table);
				return true;
			}
			else{
				return false;
			}
		}else{
			throw new Exception('Unable to delete file. does this uuid exist?');
			return false;
		}
	}

	/**
	 * metadata
	 *
	 * Returns file metadata
	 * 
	 * @param string $uuid File UUID
	 * @return	arrayObject
	 *
	 */
	public function metadata($uuid){
		$metadata = $this->CI->db->get_where($this->table,array('uuid' => $uuid),1);
		if(empty($metadata) || $metadata->num_rows() !=1){
			throw new Exception('Unable to get file metadata from database.');
			return false;
		}
		return $metadata->row_array();
	}

}

//---------------------------------------------------------------------------------------------------
