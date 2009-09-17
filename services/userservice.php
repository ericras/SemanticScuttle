<?php
class UserService {
	var $db;
	var $fields = array(
        'primary'   =>  'uId',
        'username'  =>  'username',
        'password'  =>  'password');
	var $profileurl;
	var $tablename;
	var $sessionkey;
	var $cookiekey;
	var $cookietime = 1209600; // 2 weeks

	function &getInstance(&$db) {
		static $instance;
		if (!isset($instance))
		$instance =& new UserService($db);
		return $instance;
	}

	function UserService(& $db) {
		$this->db =& $db;
		$this->tablename = $GLOBALS['tableprefix'] .'users';
		$this->sessionkey = INSTALLATION_ID.'-currentuserid';
		$this->cookiekey = INSTALLATION_ID.'-login';
		$this->profileurl = createURL('profile', '%2$s');
		$this->updateSessionStability();
	}

	function _checkdns($host) {
		if (function_exists('checkdnsrr')) {
			return checkdnsrr($host);
		} else {
			return $this->_checkdnsrr($host);
		}
	}

	function _checkdnsrr($host, $type = "MX") {
		if(!empty($host)) {
			@exec("nslookup -type=$type $host", $output);
			while(list($k, $line) = each($output)) {
				if(eregi("^$host", $line)) {
					return true;
				}
			}
			return false;
		}
	}

	function _getuser($fieldname, $value) {
		$query = 'SELECT * FROM '. $this->getTableName() .' WHERE '. $fieldname .' = "'. $this->db->sql_escape($value) .'"';

		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get user', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$row =& $this->db->sql_fetchrow($dbresult);
		$this->db->sql_freeresult($dbresult);
		if ($row) {
			return $row;
		} else {
			return false;
		}
	}

	function & getUsers($nb=0) {
		$query = 'SELECT * FROM '. $this->getTableName() .' ORDER BY `uId` DESC';
		if($nb>0) {
			$query .= ' LIMIT 0, '.$nb;
		}
		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get user', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		while ($row = & $this->db->sql_fetchrow($dbresult)) {
			$users[] = $row;
		}
		$this->db->sql_freeresult($dbresult);
		return $users;
	}

	function & getObjectUsers($nb=0) {
		$query = 'SELECT * FROM '. $this->getTableName() .' ORDER BY `uId` DESC';
		if($nb>0) {
			$query .= ' LIMIT 0, '.$nb;
		}
		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get user', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		while ($row = & $this->db->sql_fetchrow($dbresult)) {
			$users[] = new User($row[$this->getFieldName('primary')], $row[$this->getFieldName('username')]);
		}
		$this->db->sql_freeresult($dbresult);
		return $users;
	}

	function _randompassword() {
		$seed = (integer) md5(microtime());
		mt_srand($seed);
		$password = mt_rand(1, 99999999);
		$password = substr(md5($password), mt_rand(0, 19), mt_rand(6, 12));
		return $password;
	}

	function _updateuser($uId, $fieldname, $value) {
		$updates = array ($fieldname => $value);
		$sql = 'UPDATE '. $this->getTableName() .' SET '. $this->db->sql_build_array('UPDATE', $updates) .' WHERE '. $this->getFieldName('primary') .'='. intval($uId);

		// Execute the statement.
		$this->db->sql_transaction('begin');
		if (!($dbresult = & $this->db->sql_query($sql))) {
			$this->db->sql_transaction('rollback');
			message_die(GENERAL_ERROR, 'Could not update user', '', __LINE__, __FILE__, $sql, $this->db);
			return false;
		}
		$this->db->sql_transaction('commit');

		// Everything worked out, so return true.
		return true;
	}

	function getProfileUrl($id, $username) {
		return sprintf($this->profileurl, urlencode($id), urlencode($username));
	}

	function getUserByUsername($username) {
		return $this->_getuser($this->getFieldName('username'), $username);
	}

	function getObjectUserByUsername($username) {
		$user = $this->_getuser($this->getFieldName('username'), $username);
		if($user != false) {
			return new User($user[$this->getFieldName('primary')], $username);
		} else {
			return NULL;
		}
	}

	/* Takes an numerical "id" or a string "username"
	 and returns the numerical "id" if the user exists else returns NULL */
	function getIdFromUser($user) {
		if (is_int($user)) {
			return intval($user);
		} else {
			$objectUser = $this->getObjectUserByUsername($user);
			if($objectUser != NULL) {
				return $objectUser->getId();
			}
		}
		return NULL;
	}

	function getUser($id) {
		return $this->_getuser($this->getFieldName('primary'), $id);
	}

	// Momentary useful in order to go to object code
	function getObjectUser($id) {
		$user = $this->_getuser($this->getFieldName('primary'), $id);
		return new User($id, $user[$this->getFieldName('username')]);
	}

	function isLoggedOn() {
		return ($this->getCurrentUserId() !== false);
	}

	function &getCurrentUser($refresh = FALSE, $newval = NULL) {
		static $currentuser;
		if (!is_null($newval)) { //internal use only: reset currentuser
			$currentuser = $newval;
		} else if ($refresh || !isset($currentuser)) {
			if ($id = $this->getCurrentUserId()) {
				$currentuser = $this->getUser($id);
			} else {
				$currentuser = null;
			}
		}
		return $currentuser;
	}

	// Momentary useful in order to go to object code
	function getCurrentObjectUser($refresh = FALSE, $newval = NULL) {
		static $currentObjectUser;
		if (!is_null($newval)) { //internal use only: reset currentuser
			$currentObjectUser = $newval;
		} else if ($refresh || !isset($currentObjectUser)) {
			if ($id = $this->getCurrentUserId()) {
				$currentObjectUser = $this->getObjectUser($id);
			} else {
				$currentObjectUser = null;
			}
		}
		return $currentObjectUser;
	}

	function existsUserWithUsername($username) {
		if($this->getUserByUsername($username) != '') {
			return true;
		} else {
			return false;
		}
	}

	function existsUser($id) {
		if($this->getUser($id) != '') {
			return true;
		} else {
			return false;
		}
	}

	function isAdmin($userid) {
		$user = $this->getUser($userid);

		if(isset($GLOBALS['admin_users'])
		&& in_array($user['username'], $GLOBALS['admin_users'])) {
			return true;
		} else {
			return false;
		}
	}

	/* return current user id based on session or cookie */
	function getCurrentUserId() {
		if (isset($_SESSION[$this->getSessionKey()])) {
			return $_SESSION[$this->getSessionKey()];
		} else if (isset($_COOKIE[$this->getCookieKey()])) {
			$cook = split(':', $_COOKIE[$this->getCookieKey()]);
			//cookie looks like this: 'id:md5(username+password)'
			$query = 'SELECT * FROM '. $this->getTableName() .
                     ' WHERE MD5(CONCAT('.$this->getFieldName('username') .
                                     ', '.$this->getFieldName('password') .
                     ')) = \''.$this->db->sql_escape($cook[1]).'\' AND '.
			$this->getFieldName('primary'). ' = '. $this->db->sql_escape($cook[0]);

			if (! ($dbresult =& $this->db->sql_query($query)) ) {
				message_die(GENERAL_ERROR, 'Could not get user', '', __LINE__, __FILE__, $query, $this->db);
				return false;
			}

			if ($row = $this->db->sql_fetchrow($dbresult)) {
				$_SESSION[$this->getSessionKey()] = $row[$this->getFieldName('primary')];
				$this->db->sql_freeresult($dbresult);
				return $_SESSION[$this->getSessionKey()];
			}
		}
		return false;
	}

	function login($username, $password, $remember = FALSE) {
		$password = $this->sanitisePassword($password);
		$query = 'SELECT '. $this->getFieldName('primary') .' FROM '. $this->getTableName() .' WHERE '. $this->getFieldName('username') .' = "'. $this->db->sql_escape($username) .'" AND '. $this->getFieldName('password') .' = "'. $this->db->sql_escape($password) .'"';

		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get user', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		if ($row =& $this->db->sql_fetchrow($dbresult)) {
			$id = $_SESSION[$this->getSessionKey()] = $row[$this->getFieldName('primary')];
			if ($remember) {
				$cookie = $id .':'. md5($username.$password);
				setcookie($this->cookiekey, $cookie, time() + $this->cookietime, '/');
			}
			$this->db->sql_freeresult($dbresult);
			return true;
		} else {
			return false;
		}
	}

	function logout() {
		@setcookie($this->getCookiekey(), '', time() - 1, '/');
		unset($_COOKIE[$this->getCookiekey()]);
		session_unset();
		$this->getCurrentUser(TRUE, false);
	}

	function getWatchlist($uId) {
		// Gets the list of user IDs being watched by the given user.
		$query = 'SELECT watched FROM '. $GLOBALS['tableprefix'] .'watched WHERE uId = '. intval($uId);

		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get watchlist', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$arrWatch = array();
		if ($this->db->sql_numrows($dbresult) == 0)
		return $arrWatch;
		while ($row =& $this->db->sql_fetchrow($dbresult))
		$arrWatch[] = $row['watched'];
		return $arrWatch;
	}

	function getWatchNames($uId, $watchedby = false) {
		// Gets the list of user names being watched by the given user.
		// - If $watchedby is false get the list of users that $uId watches
		// - If $watchedby is true get the list of users that watch $uId
		if ($watchedby) {
			$table1 = 'b';
			$table2 = 'a';
		} else {
			$table1 = 'a';
			$table2 = 'b';
		}
		$query = 'SELECT '. $table1 .'.'. $this->getFieldName('username') .' FROM '. $GLOBALS['tableprefix'] .'watched AS W, '. $this->getTableName() .' AS a, '. $this->getTableName() .' AS b WHERE W.watched = a.'. $this->getFieldName('primary') .' AND W.uId = b.'. $this->getFieldName('primary') .' AND '. $table2 .'.'. $this->getFieldName('primary') .' = '. intval($uId) .' ORDER BY '. $table1 .'.'. $this->getFieldName('username');

		if (!($dbresult =& $this->db->sql_query($query))) {
			message_die(GENERAL_ERROR, 'Could not get watchlist', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$arrWatch = array();
		if ($this->db->sql_numrows($dbresult) == 0) {
			$this->db->sql_freeresult($dbresult);
			return $arrWatch;
		}
		while ($row =& $this->db->sql_fetchrow($dbresult)) {
			$arrWatch[] = $row[$this->getFieldName('username')];
		}
		$this->db->sql_freeresult($dbresult);
		return $arrWatch;
	}

	function getWatchStatus($watcheduser, $currentuser) {
		// Returns true if the current user is watching the given user, and false otherwise.
		$query = 'SELECT watched FROM '. $GLOBALS['tableprefix'] .'watched AS W INNER JOIN '. $this->getTableName() .' AS U ON U.'. $this->getFieldName('primary') .' = W.watched WHERE U.'. $this->getFieldName('primary') .' = '. intval($watcheduser) .' AND W.uId = '. intval($currentuser);

		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get watchstatus', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$arrWatch = array();
		if ($this->db->sql_numrows($dbresult) == 0)
		return false;
		else
		return true;
	}

	function setWatchStatus($subjectUserID) {
		if (!is_numeric($subjectUserID))
		return false;

		$currentUserID = $this->getCurrentUserId();
		$watched = $this->getWatchStatus($subjectUserID, $currentUserID);

		if ($watched) {
			$sql = 'DELETE FROM '. $GLOBALS['tableprefix'] .'watched WHERE uId = '. intval($currentUserID) .' AND watched = '. intval($subjectUserID);
			if (!($dbresult =& $this->db->sql_query($sql))) {
				$this->db->sql_transaction('rollback');
				message_die(GENERAL_ERROR, 'Could not add user to watch list', '', __LINE__, __FILE__, $sql, $this->db);
				return false;
			}
		} else {
			$values = array(
                'uId' => intval($currentUserID),
                'watched' => intval($subjectUserID)
			);
			$sql = 'INSERT INTO '. $GLOBALS['tableprefix'] .'watched '. $this->db->sql_build_array('INSERT', $values);
			if (!($dbresult =& $this->db->sql_query($sql))) {
				$this->db->sql_transaction('rollback');
				message_die(GENERAL_ERROR, 'Could not add user to watch list', '', __LINE__, __FILE__, $sql, $this->db);
				return false;
			}
		}

		$this->db->sql_transaction('commit');
		return true;
	}

	function addUser($username, $password, $email) {
		// Set up the SQL UPDATE statement.
		$datetime = gmdate('Y-m-d H:i:s', time());
		$password = $this->sanitisePassword($password);
		$values = array('username' => $username, 'password' => $password, 'email' => $email, 'uDatetime' => $datetime, 'uModified' => $datetime);
		$sql = 'INSERT INTO '. $this->getTableName() .' '. $this->db->sql_build_array('INSERT', $values);

		// Execute the statement.
		$this->db->sql_transaction('begin');
		if (!($dbresult = & $this->db->sql_query($sql))) {
			$this->db->sql_transaction('rollback');
			message_die(GENERAL_ERROR, 'Could not insert user', '', __LINE__, __FILE__, $sql, $this->db);
			return false;
		}
		$this->db->sql_transaction('commit');

		// Everything worked out, so return true.
		return true;
	}

	function updateUser($uId, $password, $name, $email, $homepage, $uContent) {
		if (!is_numeric($uId))
		return false;

		// Set up the SQL UPDATE statement.
		$moddatetime = gmdate('Y-m-d H:i:s', time());
		if ($password == '')
		$updates = array ('uModified' => $moddatetime, 'name' => $name, 'email' => $email, 'homepage' => $homepage, 'uContent' => $uContent);
		else
		$updates = array ('uModified' => $moddatetime, 'password' => $this->sanitisePassword($password), 'name' => $name, 'email' => $email, 'homepage' => $homepage, 'uContent' => $uContent);
		$sql = 'UPDATE '. $this->getTableName() .' SET '. $this->db->sql_build_array('UPDATE', $updates) .' WHERE '. $this->getFieldName('primary') .'='. intval($uId);

		// Execute the statement.
		$this->db->sql_transaction('begin');
		if (!($dbresult = & $this->db->sql_query($sql))) {
			$this->db->sql_transaction('rollback');
			message_die(GENERAL_ERROR, 'Could not update user', '', __LINE__, __FILE__, $sql, $this->db);
			return false;
		}
		$this->db->sql_transaction('commit');

		// Everything worked out, so return true.
		return true;
	}

	function getAllUsers ( ) {
		$query = 'SELECT * FROM '. $this->getTableName();

		if (! ($dbresult =& $this->db->sql_query($query)) ) {
			message_die(GENERAL_ERROR, 'Could not get users', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$rows = array();

		while ( $row = $this->db->sql_fetchrow($dbresult) ) {
			$rows[] = $row;
		}
		$this->db->sql_freeresult($dbresult);
		return $rows;
	}
	
	// Returns an array with admin uIds
	function getAdminIds() {
		$admins = array();
		foreach($GLOBALS['admin_users'] as $adminName) {
			if($this->getIdFromUser($adminName) != NULL)
			$admins[] = $this->getIdFromUser($adminName); 
		}
		return $admins;
	}

	function deleteUser($uId) {
		$query = 'DELETE FROM '. $this->getTableName() .' WHERE uId = '. intval($uId);

		if (!($dbresult = & $this->db->sql_query($query))) {
			message_die(GENERAL_ERROR, 'Could not delete user', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		return true;
	}


	function sanitisePassword($password) {
		return sha1(trim($password));
	}

	function generatePassword($uId) {
		if (!is_numeric($uId))
		return false;

		$password = $this->_randompassword();

		if ($this->_updateuser($uId, $this->getFieldName('password'), $this->sanitisePassword($password)))
		return $password;
		else
		return false;
	}

	function isReserved($username) {
		if (in_array($username, $GLOBALS['reservedusers'])) {
			return true;
		} else {
			return false;
		}
	}

	function isValidUsername($username) {
		if (strlen($username) < 4) {
			return false;
		}elseif (strlen($username) > 24) {
			// too long usernames are cut by database and may cause bugs when compared
			return false;
		} elseif (preg_match('/(\W)/', $username) > 0) {
			// forbidden non-alphanumeric characters
			return false;
		}
		return true;
	}



	function isValidEmail($email) {
		if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,6})$", $email)) {
			list($emailUser, $emailDomain) = split("@", $email);

			// Check if the email domain has a DNS record
			//if ($this->_checkdns($emailDomain)) {
			return true;
			//}
		}
		return false;
	}

	/**
	 * Sets a session variable.
	 * Updates it when it is already set.
	 * This is used to detect if cookies work.
	 *
	 * @return void
	 *
	 * @see isSessionStable()
	 */
	function updateSessionStability() {
		//find out if we have cookies enabled
		if (!isset($_SESSION['sessionStable']))) {
			$_SESSION['sessionStable'] = 0;
		} else {
			$_SESSION['sessionStable'] = 1;
		}
	}

	/**
    	 * Tells you if the session is fresh or old.
    	 * If the session is fresh, it's the first page
    	 * call with that session id. If the session is old,
    	 * we know that cookies (or session persistance) works
    	 * 
    	 * @return boolean True if the 
    	 *
    	 * @see updateSessionStability()
    	 */
	function isSessionStable() {
		return $_SESSION['sessionStable'] == 1;
	}

	// Properties
	function getTableName()       { return $this->tablename; }
	function setTableName($value) { $this->tablename = $value; }

	function getFieldName($field)         { return $this->fields[$field]; }
	function setFieldName($field, $value) { $this->fields[$field] = $value; }

	function getSessionKey()       { return $this->sessionkey; }
	function setSessionKey($value) { $this->sessionkey = $value; }

	function getCookieKey()       { return $this->cookiekey; }
	function setCookieKey($value) { $this->cookiekey = $value; }
}


/* Defines a user. Rare fields are filled if required. */
class User {

	var $id;
	var $username;
	var $name;
	var $email;
	var $homepage;
	var $content;
	var $datetime;
	var $isAdmin;

	function User($id, $username) {
		$this->id = $id;
		$this->username = $username;
	}

	function getId() {
		return $this->id;
	}

	function getUsername() {
		return $this->username;
	}

	function getName() {
		// Look for value only if not already set
		if(!isset($this->name)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$user = $userservice->getUser($this->id);
			$this->name = $user['name'];
		}
		return $this->name;
	}

	function getEmail() {
		// Look for value only if not already set
		if(!isset($this->email)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$user = $userservice->getUser($this->id);
			$this->email = $user['email'];
		}
		return $this->email;
	}

	function getHomepage() {
		// Look for value only if not already set
		if(!isset($this->homepage)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$user = $userservice->getUser($this->id);
			$this->homepage = $user['homepage'];
		}
		return $this->homepage;
	}

	function getContent() {
		// Look for value only if not already set
		if(!isset($this->content)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$user = $userservice->getUser($this->id);
			$this->content = $user['uContent'];
		}
		return $this->content;
	}

	function getDatetime() {
		// Look for value only if not already set
		if(!isset($this->content)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$user = $userservice->getUser($this->id);
			$this->datetime = $user['uDatetime'];
		}
		return $this->datetime;
	}

	function isAdmin() {
		// Look for value only if not already set
		if(!isset($this->isAdmin)) {
			$userservice =& ServiceFactory::getServiceInstance('UserService');
			$this->isAdmin = $userservice->isAdmin($this->id);
		}
		return $this->isAdmin;
	}
	
	function getNbBookmarks($range = 'public') {
		$bookmarkservice =& ServiceFactory::getServiceInstance('BookmarkService');
		return $bookmarkservice->countBookmarks($this->getId(), $range);
	}
}
?>
