<?php
// custom exception classes
class BadMzTabHeaderException extends Exception { }

class ID {
	public $sequence;
	public $scanNumber;
	public $charge;
	public $mods;
	
	public function __construct($sequence, $scanNumber, $charge, $mods) {
		$this->sequence = $sequence;
		$this->scanNumber = $scanNumber;
		$this->charge = $charge;
		$this->mods = $mods;
	}

	public static function ParseScan($scanEntry) {
		// $scanEntry = ms_run[1]:spectrum=1661
		// return 1661
		$splitEntry = explode("=", $scanEntry);

		return intval($splitEntry[count($splitEntry) - 1]);
	}

	public static function ParseCharge($charge) {
		return intval($charge);
	}

	public static function ParseModifications($modificationEntry) {
		$parsedMods = Array();

		if ($modificationEntry === "none" || $modificationEntry === "null") {
			return $parsedMods;
		}
		
		$unparsedMods = explode(",", $modificationEntry);

		foreach ($unparsedMods as $unparsedMod) {
			$parsedMods[] = Modification::ParseModification($unparsedMod);
		}

		return $parsedMods;
	}

	public function StringifyMods() {
		$returnString = "";

		if (count($this->mods) == 0) {
			return $returnString;
		} else {
			$returnString = $returnString . $this->mods[0]->name . ":" . $this->mods[0]->location;
			
			for($i = 1; $i < count($this->mods); $i++) {
				$returnString = $returnString . ";" . $this->mods[$i]->name . ":" . $this->mods[$i]->location;
			}

			return $returnString;
		}
	}
}

class Modification {
	public $modType;
	public $name;
	public $deltaMass;
	public $location;

	public function __construct($location, $name, $modType = "custom", $deltaMass = 0) {
		$this->modType = $modType;
		$this->name = $name;
		$this->deltaMass = $deltaMass;
		$this->location = $location;
	}

	public static function ParseModification($stringifiedMod) {
		// EXAMPLES
		// {location}-{modType}:{modNumber or chemical modification}
		// 
		// 0-MOD:01499,8-MOD:01499
		// 
		// 0-UNIMOD:214,20-UNIMOD:214

		$unparsedMod = explode("-", $stringifiedMod);

		return new Modification(intval($unparsedMod[0]), $unparsedMod[1]);
	}
}

class Config {
	public $sequenceIndex;
	public $msScanIndex;
	public $chargeIndex;
	public $modificationIndex;

	public function __construct() {
		$this->sequenceIndex = -1;
		$this->msScanIndex = -1;
		$this->chargeIndex = -1;
		$this->modificationIndex = -1;
	}

	public function GetFieldIndices($array) {
		for ($i = 1; $i < count($array); $i++) {
			$fieldHeader = $array[$i];

			if (strcmp($fieldHeader, "sequence") == 0) {
				$this->sequenceIndex = $i;
			} else if (strcmp($fieldHeader, "spectra_ref") == 0) {
				$this->msScanIndex = $i;
			} else if (strcmp($fieldHeader, "charge") == 0) {
				$this->chargeIndex = $i;
			} else if (strcmp($fieldHeader, "modifications") == 0) {
				$this->modificationIndex = $i;
			}
		}

		if ($this->sequenceIndex == -1 || $this->msScanIndex == -1 || $this->chargeIndex == -1 || $this->modificationIndex == -1) {
			throw new BadMzTabHeaderException("Could not parse peptide sequence, scan, charge, or modifications due to bad column headers in mzTab file.");
		}
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
		
		// initialize CONFIG object
		$config = new Config();

		// extract identifications from mzTab file
		$identifications = [];
		if (($handle = fopen($truePath, "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
        // logic to  parse out identifications
	    	if (strcmp($data[0], "PSH") == 0) {
	    		$config->GetFieldIndices($data);
	    	} else if (strcmp($data[0], "PSM") == 0) {
	    		$sequence = $data[$config->sequenceIndex];
	    		$msScanIndex = ID::ParseScan($data[$config->msScanIndex]);
	    		$charge = ID::ParseCharge($data[$config->chargeIndex]);
	    		$modifications = ID::ParseModifications($data[$config->modificationIndex]);
	    		$returnArray[] = new ID($sequence, $msScanIndex, $charge, $modifications);
	    	}
	    }
	    fclose($handle);

	    // load IDs into database
			try {
				// begin transaction
				$pdo->beginTransaction();
				$stmt = $pdo->prepare('INSERT INTO identifications (scan_number, sequence, charge, mods, timestamp) VALUES (:scan_number, :sequence, :charge, :mods, :timestamp)');
				
				foreach ($returnArray as $id) {
					$success = $stmt->execute(['scan_number' => $id->scanNumber, 'sequence'=> $id->sequence, 'charge'=> $id->charge, 'mods'=> $id->StringifyMods(), 'timestamp'=>$timeStamp]);
				}

				$pdo->commit();

			} catch (Exception $e) {
				$pdo->rollBack();
				echo (json_encode(array("error" => "Identification file upload completed and was parsed, but could not be added to the database at this time. Please try again at a later date.\n" .
					"error message: " . $e->getMessage())));
				die;
			}
			if(!$success)
			{
				echo $pdo->errorInfo();
				die;
			} else {
				echo (json_encode($returnArray));
			}
		} 
	} catch (BadMzTabHeaderException $e) {
		echo (json_encode(Array("error" => "Caught thrown exception during identification upload:\n " . $e->getMessage())));
	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during identification upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}
?>