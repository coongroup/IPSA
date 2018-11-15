<?php
	require("config.php");
	
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	$oldTimeStamp = $data->oldTimeStamp;
	
	// delete any old data that may have been uploaded
	$stmt = $pdo->prepare('UPDATE identifications SET timestamp=:timestamp WHERE timestamp=:oldTimeStamp');
	$success = $stmt->execute(['timestamp' => $timeStamp, 'oldTimeStamp' =>$oldTimeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not update previously uploaded identifications.")));
		die;
	}
	
	$stmt = $pdo->prepare('UPDATE mods SET timestamp=:timestamp WHERE timestamp=:oldTimeStamp');
	$success = $stmt->execute(['timestamp' => $timeStamp, 'oldTimeStamp' =>$oldTimeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not update previously uploaded modifications.")));
		die;
	}
	
	$stmt = $pdo->prepare('UPDATE spectra SET timestamp=:timestamp WHERE timestamp=:oldTimeStamp');
	$success = $stmt->execute(['timestamp' => $timeStamp, 'oldTimeStamp' =>$oldTimeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not update previously uploaded spectra.")));
		die;
	}
?>