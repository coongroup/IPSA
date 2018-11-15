<?php
	class returnObject {
		public $IDs;
		public $modifications;
		
		public function returnObject($IDs, $modifications) {
			$this->IDs = $IDs;
			$this->modifications = $modifications;
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
	
	class Modification {
		public $name;
		public $mass;
		
		public function Modification($name, $mass) {
			$this->name = $name;
			$this->mass = $mass;
		}
	}
	
	// require database connection
	require("config.php");
	$IDs = Array();
	$mods = Array();
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	
	// Retrieve IDs from database
	$stmt = $pdo->prepare('SELECT sequence, scan_number, charge, mods FROM identifications WHERE timestamp=:timestamp ORDER BY sequence');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded identifications.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$IDs[] = new ID($result[0], $result[1], $result[2], $result[3]);
		}
	}
	
	// Retrieve mods from database
	$stmt = $pdo->prepare('SELECT name, mass FROM mods WHERE timestamp=:timestamp');
	$success = $stmt->execute(['timestamp' => $timeStamp]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded modifications.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$mods[] = new Modification($result[0], $result[1]);
		}
	}
	
	$returnObject = new returnObject($IDs, $mods);
	echo (json_encode($returnObject));
?>