<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class Session_Mongo extends Session{
    
    
    protected $_db;
    
    protected $_collection;

    protected $_connection;

    //The current session id
    protected $_session_id;

    // The old session id
    protected $_update_id;

    //The key for mongo that will contain the array of session data
    protected $_contents_key;

    protected $_gc = 500;

    protected $_name = 'mng_session';

    protected $_lifetime = 1209600;
    
    public function __construct(array $config = NULL, $id = NULL)
	{

		// Load the database
		$this->_db = new Mongo($config['host']);
		$this->_db = $this->_db->$config['db'];

		if (isset($config['collection']))
		{
			// Set the table name
			$this->_collection = (string) $config['collection'];
		}

		if (isset($config['gc']))
		{
			// Set the gc chance
			$this->_gc = (int) $config['gc'];
		}

		if (isset($config['contents_key']))
		{
			// Set the gc chance
			$this->_contents_key = (string) $config['contents_key'];
		}


		$this->_connection = $this->_db->sessions;

		parent::__construct($config, $id);

		if (mt_rand(0, $this->_gc) === $this->_gc)
		{
			// Run garbage collection
			// This will average out to run once every X requests
			$this->_gc();
		}
		
	}



    public function id(){
	return $this->_session_id;
    }


    protected function _read($id=NULL){
	
	if ($id OR $id = Cookie::get($this->_name))
		
		{
			
			$result = $this->_connection->findOne( array('session_id'=>$id ) );

			if (!empty($result))
			{
				// Set the current session id
				
				$this->_session_id = $this->_update_id = $id;
				
				// Return the contents
				$data = $result;
				//$this->_regenerate();
				return $data[$this->_contents_key];
			}
		}

		// Create a new session id
		$this->_regenerate();

		return NULL;
    }

    protected function _regenerate(){
		do
		{
			// Create a new session id
			$id = str_replace('.', '-', uniqid(NULL, TRUE));

			// Get the the id from the database
			$result = $this->_connection->findOne(array('session_id'=>$id));
		}
		while (!empty($result));

		return $this->_session_id = $id;
		

    }

    protected function _write(){
	
	if ($this->_update_id === NULL)
		{
			// Insert a new row
			$this->_connection->insert(array('session_id'=>$this->_session_id,$this->_contents_key=>$this->_arrayData(), 'last_active'=>time()));
		}
		else
		{
			// Update the row
			
			
			if ($this->_update_id !== $this->_session_id)
			{
				// Also update the session id
				
				$this->_connection->update(array('session_id'=>$this->_update_id), array('session_id'=>$this->_session_id,$this->_contents_key=>$this->_arrayData(),'last_active'=>time()));
				
			}else{
				
				$this->_connection->update(array('session_id'=>$this->_update_id), array('session_id'=>$this->_update_id,$this->_contents_key=>$this->_arrayData(),'last_active'=>time()));
			}
		}

		// Execute the query
		

		// The update and the session id are now the same
		$this->_update_id = $this->_session_id;
		
		

		// Update the cookie with the new session id
		Cookie::set($this->_name, $this->_session_id, $this->_lifetime);

		return TRUE;
    }

    protected function _destroy(){
	
	if ($this->_update_id === NULL)
		{
			// Session has not been created yet
			return TRUE;
		}

		// Delete the current session

		try
		{
			// Execute the query
			$this->_connection->remove(array('session_id'=>$this->_update_id));

			// Delete the cookie
			Cookie::delete($this->_name);
		}
		catch (Exception $e)
		{
			// An error occurred, the session has not been deleted
			return FALSE;
		}

		return TRUE;

    }

    protected function  _restart() {
	$this->_regenerate();
	
	return TRUE;
    }

    protected function _gc()
	{
		if ($this->_lifetime)
		{
			// Expire sessions when their lifetime is up
			$expires = $this->_lifetime;
		}
		else
		{
			// Expire sessions after one month
			$expires = Date::MONTH;
		}

		// Delete all sessions that have expired

		$this->_connection->remove(array('last_active'=> array('$gt'=>time()-$expires)));
	}


	public function read($id = NULL)
	{
		$data = NULL;
		
		try
		{
			if (is_string($data = $this->_read($id)))
			{
				if ($this->_encrypted)
				{
					// Decrypt the data using the default key
					$data = Encrypt::instance($this->_encrypted)->decode($data);
				}
				else
				{
					// Decode the base64 encoded data
					$data = base64_decode($data);
				}

				// Unserialize the data
				$data = unserialize($data);
			}
			else
			{
				// Ignore these, session is valid, likely no data though.
			}
		}
		catch (Exception $e)
		{
			// Error reading the session, usually
			// a corrupt session.
			throw new Session_Exception('Error reading session data.', NULL, Session_Exception::SESSION_CORRUPT);
		}

		if (is_array($data))
		{
			// Load the data locally
			$this->_data = $data;
		}
	}

	public function write()
	{
		if (headers_sent() OR $this->_destroyed)
		{
			// Session cannot be written when the headers are sent or when
			// the session has been destroyed
			return FALSE;
		}

		// Set the last active timestamp
		$this->_data['last_active'] = time();

		try
		{
			return $this->_write();
		}
		catch (Exception $e)
		{
			// Log & ignore all errors when a write fails
			Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e))->write();

			return FALSE;
		}
	}

	protected function _arrayData(){
	    return $this->_data;
	}

}

?>
