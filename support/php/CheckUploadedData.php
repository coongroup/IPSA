<?php
	class returnObject {
		public $ids;
		public $hasIds;
		public $hasMods;
		public $hasSpectra;
		
		public function returnObject() {
			$ids = Array();
			$hasIds = false;
			$hasMods = false;
			$hasSpectra = false;
		}
	}
	
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
	$returnObject = new returnObject();
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	
	// check and retrieve for IDs in database
	$stmt = $pdo->prepare('SELECT sequence, scan_number, charge, mods FROM identifications WHERE timestamp=:timestamp ORDER BY sequence');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded identifications.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			if (!$returnObject->hasIds) {
				$returnObject->hasIds = true;
			}
			
			$returnObject->ids[] = new ID($result[0], $result[1], $result[2], $result[3]);
		}
	}
	
	// check for mods in database
	$stmt = $pdo->prepare('SELECT mass FROM mods WHERE timestamp=:timestamp');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded modifications.")));
		die;
	} else {
		if ($stmt->rowCount() > 0) {
			$returnObject->hasMods = true;
		}
	}
	
	// check for spectra in database
	$stmt = $pdo->prepare('SELECT scan_number FROM spectra WHERE timestamp=:timestamp');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded spectra.")));
		die;
	} else {
		if ($stmt->rowCount() > 0) {
			$returnObject->hasSpectra = true;
		}
	}
	
	echo (json_encode(array("results" => $returnObject)));
?>