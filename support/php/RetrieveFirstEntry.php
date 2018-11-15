<?php
	class Identification {
		public $scanNumber;
		public $sequence;
		public $charge;
		public $mods;
		
		public function Identification($scanNumber, $sequence, $charge, $mods) {
			$this->scanNumber = $scanNumber;
			$this->sequence = $sequence;
			$this->charge = $charge;
			$this->mods = $mods;
		}
	}
	
	class PeakList {
		public $scanNumber;
		public $mz;
		public $intensity;
		
		public function PeakList($scanNumber, $mz, $intensity) {
			$this->scanNumber = $scanNumber;
			$this->mz = $mz;
			$this->intensity = $intensity;
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

	function AddNumRows($object, $count) {
		$object->insertCount = $count;
	}

	// require database connection
	require("config.php");
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	$targetTable = $data->target;

	$returnObject;

	switch ($targetTable) {
    case "id":
      // Retrieve IDs from database
			$stmt = $pdo->prepare('SELECT scan_number, sequence, charge, mods FROM identifications WHERE timestamp=:timestamp ORDER BY scan_number LIMIT 1');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not retrieve an example of a uploaded identification.")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					$returnObject = new Identification($result[0], $result[1], $result[2], $result[3]);
				}
			}

			$stmt = $pdo->prepare('SELECT COUNT(*) FROM identifications WHERE timestamp=:timestamp');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not count number of entries made.")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					AddNumRows($returnObject, $result[0]);
				}
			}
			break;
		case "spectrum":
			$stmt = $pdo->prepare('SELECT scan_number, mz, intensity FROM spectra WHERE timestamp=:timestamp ORDER BY scan_number LIMIT 1');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not retrieve an example of a uploaded spectra.")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					$returnObject = new PeakList($result[0], $result[1], $result[2]);
				}
			}
			$stmt = $pdo->prepare('SELECT COUNT(*) FROM spectra WHERE timestamp=:timestamp');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not count number of entries made.")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					AddNumRows($returnObject, $result[0]);
				}
			}
			break;
		case "mod":
			$stmt = $pdo->prepare('SELECT name, mass FROM mods WHERE timestamp=:timestamp ORDER BY name LIMIT 1');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not retrieve an example of a uploaded modification")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					$returnObject = new Modification($result[0], $result[1]);
				}
			}
			$stmt = $pdo->prepare('SELECT COUNT(*) FROM mods WHERE timestamp=:timestamp');
			$success = $stmt->execute(['timestamp' => $timeStamp]);
			
			if(!$success)
			{
				echo (json_encode(array("error" => "Could not count number of entries made.")));
				die;
			} else {
				while($result = $stmt->fetch())
				{
					AddNumRows($returnObject, $result[0]);
				}
			}
			break;
	}
	echo (json_encode($returnObject));
?>