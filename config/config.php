<?php

// *** DISALLOWS DIRECT ACCESS OF FILES, DETERS HACKERS *** //
if(!defined("IN_STORYBOT")) {
	die("Invalid access!");
}
// *** ------------------------------------------------ *** //

require_once("src/snapchat.php"); //required for Snapchat API

//***************************************************************************************************
//VARIABLES
//***************************************************************************************************

$config = array(); //initialize the $config array

// *** DATABASE VALUES *** //
$config['dbuser'] = "DB-USER"; //database user
$config['dbpass'] = "DB-PASSWORD"; //database password
$config['dbname'] = "DB-NAME"; //database we're connecting to
// *** --------------- *** //

// *** CONNECT TO DATABASE *** //
$db = new mysqli("localhost", $config['dbuser'], $config['dbpass'], $config['dbname']); //do not edit
if($db->connect_errno > 0){
    die("Database connection error: ".$db->connect_error);
}
// *** ------------------- *** //

// *** EDIT THIS TO "true" WHEN YOU ARE DONE CONFIGURING STORYBOT! *** //
$config['firstsetup'] = true; //should be "true" until you are live
// *** ----------------------------------------------------------- *** //

// *** REPLACE THIS WITH YOUR SNAPCHAT USERNAME AND PASSWORD *** //
$config['username'] = 'SNAPCHAT-USER'; //snapchat username
$config['password'] = 'SNAPCHAT-PASSWORD'; //snapchat password
// *** ----------------------------------------------------- *** //

// *** EDIT THIS TO FALSE FOR INSTANT POSTING AND TRUE FOR MODQUEUE *** //
$config['moderation'] = true; //default: true
// !!! IF SET TO FALSE, MAKE SURE TO CREATE AN AUTOMATIC CRON JOB "curl http://www.yourhost.com/bot.php" !!!
// *** ------------------------------------------------------------ *** //

// *** EDIT TRUE OR FALSE IF YOU WANT TO ALLOW OR DISALLOW A TYPE OF SNAP *** //
$config['picturesallowed'] = true; //default: true
$config['videosallowed'] = true; //default: true
// *** ------------------------------------------------------------------ *** //

// *** TIME FOR PICTURES IN SECONDS *** //
$config['picturetime'] = 3; //default: 3
// *** ---------------------------- *** //

// *** INITIALIZE SNAPCHAT *** //
$snapchat = new Snapchat($config['username'], $config['password']); //do not edit
// *** ------------------- *** //

//***************************************************************************************************
//FUNCTIONS
//***************************************************************************************************

// *** CHECK FOR BANNED SNAPCHAT USER *** //
function is_banned($username) {
	$sql = "SELECT * FROM `bans` WHERE `bans`.`username` = '".$username."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (is_banned)");
	}
	while($row = $result->fetch_assoc()){
		if($row['username'] != "") {
			return true;
		} else {
			return false;
		}
	}
}
// *** ------------------------------ *** //

// *** CHECK IF USER IS MOD *** //
function is_mod($id) {
	$sql = "SELECT * FROM `mods` WHERE `mods`.`mod_id` = '".$id."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (is_mod)");
	}
	while($row = $result->fetch_assoc()){
		if($row['banned'] == "0") {
			return true;
		} elseif($row['banned'] == "1") { 
			die("You are banned from moderation.");
		} else {
			return false;
		}
	}
}
// *** -------------------- *** //

// *** CHECK IF USER IS ADMIN *** //
function is_admin($id) {
	$sql = "SELECT * FROM `admins` WHERE `admins`.`disabled` = '0'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (is_admin)");
	}
	while($row = $result->fetch_assoc()){
		if($row['mod_id'] == $id) {
			return true;
		}
	}
	return false;
}
// *** ---------------------- *** //

// *** GENERATES MOD ID *** //
function mod_id($ip = "") {
	if($ip = "") {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	$mod_id = md5("storybot".$ip); //md5 hash of string "storybot" and the mod's IP
	return $mod_id;
}
// *** ---------------- *** //


// *** REJECTS IMAGE AND MARKS AS ARCHIVED *** //
function rejectImage($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0' AND `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (rejectImage)");
	}
	while($row = $result->fetch_assoc()){
		$snapchat = $GLOBALS['snapchat'];
		$snap = $snapchat->upload(Snapchat::MEDIA_IMAGE, file_get_contents('media/reject.jpg'));
		$snapchat->send($snap, array($row['sender']), 10);
		archiveImage($sid);
	}
}
// *** ----------------------------------- *** //

// *** DECIDES WHICH TO POST AS *** //
function postMedia($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (postMedia)");
	}
	while($row = $result->fetch_assoc()){
		if($row['type'] == 0) {
			postImage($sid);
		} else {
			postVideo($sid);
		}
	}
}
// *** ------------------------ *** //

// *** POSTS SNAP TO STORY AND SENDS ACCEPT SNAP TO SENDER *** //
function postImage($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0' AND `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (postImage)");
	}
	while($row = $result->fetch_assoc()){
		$snapchat = $GLOBALS['snapchat'];
		$story = $snapchat->upload(Snapchat::MEDIA_IMAGE, base64_decode($row['data']));
		$snapchat->setStory($story, Snapchat::MEDIA_IMAGE, $GLOBALS['config']['picturetime']);
		$snap = $snapchat->upload(Snapchat::MEDIA_IMAGE, file_get_contents('media/accept.jpg'));
		$snapchat->send($snap, array($row['sender']), 10);
		archiveImage($sid);
	}
}
// *** --------------------------------------------------- *** //

// *** POSTS VIDEO TO STORY AND SENDS ACCEPT SNAP TO SENDER *** //
function postVideo($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0' AND `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (postVideo)");
	}
	while($row = $result->fetch_assoc()){
		$snapchat = $GLOBALS['snapchat'];
		$story = $snapchat->upload(Snapchat::MEDIA_VIDEO, base64_decode($row['data']));
		$snapchat->setStory($story, Snapchat::MEDIA_VIDEO);
		$snap = $snapchat->upload(Snapchat::MEDIA_IMAGE, file_get_contents('media/accept.jpg'));
		$snapchat->send($snap, array($row['sender']), 10);
		archiveImage($sid);
	}
}
// *** ---------------------------------------------------- *** //

// *** POSTS SNAP TO STORY AND SENDS ACCEPT SNAP TO SENDER *** //
function postManualImage($data, $sender) {
	$snapchat = $GLOBALS['snapchat'];
	$story = $snapchat->upload(Snapchat::MEDIA_IMAGE, $data);
	$snapchat->setStory($story, Snapchat::MEDIA_IMAGE, $GLOBALS['config']['picturetime']);
	$snap = $snapchat->upload(Snapchat::MEDIA_IMAGE, file_get_contents('media/accept.jpg'));
	$snapchat->send($snap, array($sender), 10);
}
// *** --------------------------------------------------- *** //

// *** POSTS VIDEO TO STORY AND SENDS ACCEPT SNAP TO SENDER *** //
function postManualVideo($data, $sender) {
	if(is_array($data)) {
		$data = end($data);
	}
	$snapchat = $GLOBALS['snapchat'];
	$story = $snapchat->upload(Snapchat::MEDIA_VIDEO, $data);
	$snapchat->setStory($story, Snapchat::MEDIA_VIDEO);
	$snap = $snapchat->upload(Snapchat::MEDIA_IMAGE, file_get_contents('media/accept.jpg'));
	$snapchat->send($snap, array($sender), 10);
}
// *** ---------------------------------------------------- *** //

// *** SAVES IMAGE TO MYSQL DATABASE *** //
function saveImage($data, $sender, $sid, $archived) {
	$sql = "INSERT INTO `images` (`data`, `sender`, `time`, `sid`, `archived`, `type`) VALUES ('".base64_encode($data)."', '".$sender."', CURRENT_TIMESTAMP, '".$sid."', '".$archived."', '0')";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (saveImage)");
	}
	return true;
}
// *** ----------------------------- *** //

// *** SAVES VIDEO TO MYSQL DATABASE *** //
function saveVideo($data, $sender, $sid, $archived) {
	if(is_array($data)) {
		$data = end($data);
	}
	$sql = "INSERT INTO `images` (`data`, `sender`, `time`, `sid`, `archived`, `type`) VALUES ('".base64_encode($data)."', '".$sender."', CURRENT_TIMESTAMP, '".$sid."', '".$archived."', '1')";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (saveVideo)");
	}
	return true;
}
// *** ----------------------------- *** //

// *** MARKS IMAGE AS ARCHIVED *** //
function archiveImage($sid) {
	$sql = "UPDATE `images` SET `archived` = '1' WHERE `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (archiveImage)");
	}
}
// *** ----------------------- *** //

// *** ADDS IMAGE TO REPORT LOG *** //
function reportImage($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (reportImage)");
	}
	while($row = $result->fetch_assoc()){
		$sql = "INSERT INTO `reports` (`data`, `sender`, `sid`, `type`) VALUES ('".$row['data']."', '".$row['sender']."', '".$row['sid']."', '".$row['type']."')";
		$GLOBALS['db']->query($sql);
		archiveImage($sid);
	}
}
// *** ----------------------- *** //

// *** DISPLAYS BASE64 IMAGE *** //
function displayImage($sid) {
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (displayImage)");
	}
	while($row = $result->fetch_assoc()){
    	if($row['sid'] == $sid) {
    		if($row['type'] == 0) {
    			return "<img src='data:image/png;base64,".$row['data']."' class='imageview' />";   			
    		} else {
    			return displayVideo($sid);
    		}
    	}
	}
}
// *** --------------------- *** //

// *** DISPLAYS BASE64 VIDEO *** //
function displayVideo($sid) { 
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0' AND `images`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (displayVideo)");
	}
	while($row = $result->fetch_assoc()){
   		return '<video width="270" height="420" controls><source type="video/webm" src="data:video/mov;base64,'.$row['data'].'"></video>';    			
	}
}
// *** --------------------- *** //

// *** DISPLAYS REPORT IMAGE *** //
function displayReportImage($sid) {
	$sql = "SELECT * FROM `reports`";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (displayReportImage)");
	}
	while($row = $result->fetch_assoc()){
    	if($row['sid'] == $sid) {
    		if($row['type'] == 0) {
    			return "<img src='data:image/png;base64,".$row['data']."' style='height: 250px; width: 160px;' />";   			
    		} else {
    			return displayReportVideo($sid);
    		}
    	}
	}
}
// *** --------------------- *** //

// *** DISPLAYS REPORT VIDEO *** //
function displayReportVideo($sid) { 
	$sql = "SELECT * FROM `reports` WHERE `reports`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (displayReportVideo)");
	}
	while($row = $result->fetch_assoc()){
   		return '<video width="160" height="250" controls><source type="video/webm" src="data:video/mov;base64,'.$row['data'].'"></video>';    			
	}
}
// *** --------------------- *** //

// *** GENERATES REPORTVIEW *** //
function generateReports() {
	$sql = "SELECT * FROM `reports` LIMIT 5";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (generateReports)");
	}
	echo "<table><tr><td>ID</td><td>Sender</td><td>Image</td><td>Ban</td><td>Ignore</td></tr>";
	while($row = $result->fetch_assoc()){
    	echo "<tr><td>";
    	echo $row['id'];
    	echo "</td><td>";
    	echo $row['sender'];
    	echo "</td><td>";
    	echo displayReportImage($row['sid']);
    	echo "</td><td><a href='action.php?type=-2&sid=".$row['sid']."' class='ban'>Ban</a></td><td><a href='action.php?type=-3&sid=".$row['sid']."' class='reports'>Ignore</a></td></tr>";
	}
	echo "</table>";
}
// *** -------------------- *** //

// *** GETS OLDEST NONARCHIVED IMAGE SID *** //
function oldestImage() {
	$sql = "SELECT * FROM `images` WHERE `images`.`archived` = '0' ORDER BY `id` ASC";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (oldestImage)");
	}
	while($row = $result->fetch_assoc()){
    	return $row['sid'];
	}
}
// *** --------------------------------- *** //

// *** GETS IMAGE SID *** //
function getImageSID($id) {
	$sql = "SELECT * FROM `images` WHERE `images`.`id` = '".$id."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (getImageSID)");
	}
	while($row = $result->fetch_assoc()){
    	return $row['sid'];
	}
}
// *** -------------- *** //

// *** ADDS MODERATOR TO DATABASE *** //
function addModerator($id) {
	if(is_mod(mod_id()) == false) {
		$sql = "INSERT INTO `mods` (`mod_id`, `regdate`) VALUES ('".$id."', '".time()."')";
		$GLOBALS['db']->query($sql);
	}
}
// *** -------------------------- *** //

// *** REMOVES REPORT FROM LOG *** //
function removeReport($sid) {
	$sql = "DELETE FROM `reports` WHERE `reports`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (removeReport)");
	}
	return true;
}
// *** ----------------------- *** //

// *** BANS SNAPCHAT USER IN DATABASE *** //
function banUser($sid, $report) {
	if($report == 1) {
		$sqlstr = "reports";
	} else {
		$sqlstr = "images";
	}
	$sql = "SELECT * FROM `".$sqlstr."` WHERE `".$sqlstr."`.`sid` = '".$sid."'";
	if(!$result = $GLOBALS['db']->query($sql)){
	    die("Database error! (banUser)");
	}
	while($row = $result->fetch_assoc()){
    	$sql = "INSERT INTO `bans` (`username`) VALUES ('".$row['sender']."')";
		$GLOBALS['db']->query($sql);
		if($report == 1) {
			removeReport($sid);
		} else {
			archiveImage($sid);
		}
		return true;
	}
}
// *** ------------------------------ *** //

?>