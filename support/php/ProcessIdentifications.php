<?php
class ID {
	public $sequence;
	public $scanNumber;
	public $charge;
	public $mods;
	
	public function ID($sequence, $scanNumber, $charge, $mods) {
		$this->sequence = $sequence;
		$this->scanNumber = $scanNumber;
		$this->charge = $charge;
		$this->mods = $mods;
	}
}
	// require database connection
	require("config.php");
	$returnArray = Array();
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$truePath = $basePath . $data->fileName;
	$timeStamp = $data->timeStamp;
	
	try {
		// delete any old data that may have been uploaded
		$stmt = $pdo->prepare('DELETE FROM identifications WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "Query failed. Could not access database")));
			die;
		}
		
		$stmt = $pdo->prepare('DELETE FROM spectra WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "Query failed. Could not access database")));
			die;
		}
		
		$stmt = $pdo->prepare('DELETE FROM mods WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "Query failed. Could not access database")));
			die;
		}
		
		// remove any upstream data from database 
		
		// load IDs into database
		$success = $pdo->exec("LOAD DATA INFILE '" . $truePath . "' INTO TABLE identifications FIELDS TERMINATED BY ','" .
				" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\r\n' IGNORE 1 LINES (scan_number, sequence, charge, mods) SET timestamp = " . $timeStamp);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "Identification file upload succeeded. However, parsing failed. Please make sure the identification file is in the correct format")));
			die;
		}
		
		// return formatted IDs from database
		$stmt = $pdo->prepare('SELECT scan_number, sequence, charge, mods FROM identifications WHERE timestamp = :timestamp ORDER BY scan_number');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		if(!$success)
		{
			echo $pdo->errorInfo();
			die;
		} else {
			while($result = $stmt->fetch())
			{
				$returnArray[] = new ID($result[1], $result[0], $result[2], $result[3]);
			}
			echo (json_encode($returnArray));
		}
	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during identification upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}
?>