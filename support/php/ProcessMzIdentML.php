<?php
// custom exception classes
class BadMzTabHeaderException extends Exception { }

class InvalidModificationException extends Exception { }

class UnsupportedScanFormatException extends Exception { }

class PeptideNode {
	public $peptideId;
	public $sequence;
	public $modifications;

	public function __construct($peptideXmlNode) {
		$this->peptideId = (string) $peptideXmlNode['id'];
		$this->sequence = (string) $peptideXmlNode->peptideSequence;
		$this->modifications = Array();
		
		if (isset($peptideXmlNode->Modification)) {
			foreach ($peptideXmlNode->Modification as $modification) {
				$this->modifications[] = Modification::ParseModification($modification, $this->peptideId);
			}
		}
	}
}

class ResultNode {
	public $peptideId;
	public $scanIdentifier;
	public $charge;

	public function __construct($resultXmlNode) {
		$this->scanIdentifier = ResultNode::ParseScanIdentifier((string) $resultXmlNode['spectrumID']);
		$bestPsm = $resultXmlNode->SpectrumIdentificationItem;

		$this->peptideId = (string) $bestPsm['Peptide_ref'];
		$this->charge = intval((string) $bestPsm['chargeState']);
	}

	private static function ParseScanIdentifier($unparsedScanIdentifier) {
		$explodedIdentifiers = explode("=", $unparsedScanIdentifier);

		// catch most cases of parsing out scan number
		// scan=18
		if ($explodedIdentifiers[0] === "scan" || $explodedIdentifiers[0] === "index" || $explodedIdentifiers[0] === "spectrum") {
			return intval($explodedIdentifiers[1]);
		} 
		// specific parsing rules for Thermo nativeID format
		// controllerType=0 controllerNumber=1 scan=18
		else if ($explodedIdentifiers[0] === "controllerType") {
			return intval($explodedIdentifiers[3]);
		}
		// specific parsing rules for Waters nativeID format
		// function=0 process=1 scan=18
		else if ($explodedIdentifiers[0] === "function") {
			return intval($explodedIdentifiers[3]);
		}
		// Throw errors for Bruker FID nativeID format and single peak list nativeID format. IPSA only works on importing a single mass spec data file.
		else if ($explodedIdentifiers[0] === "file") {
			throw new UnsupportedScanFormatException("IPSA currently does not support data from Bruker FID nativeID or single peak list nativeID formats.");
		}
		else {
			throw new UnsupportedScanFormatException("Could not parse scan numbers from the uploaded mzid file. The spectrumID attribute in the SpectrumIdentificationResult tags may be in the wrong format.");
		}
	}
}

class ID {
	public $sequence;
	public $scanNumber;
	public $charge;
	public $mods;
	
	public function __construct($peptideNode, $resultNode) {
		$this->sequence = $peptideNode->sequence;
		$this->scanNumber = $resultNode->scanIdentifier;
		$this->charge = $resultNode->charge;
		$this->mods = $peptideNode->modifications;
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

	public static function ParseModification($modificationNode, $peptideId) {
		// EXAMPLE - Contained inside a simplexml object
		//
		// <Modification location="6" residues="M" monoisotopicMassDelta="15.994919">
		//	 <cvParam accession="UNIMOD:35" name="Oxidation" cvRef="UNIMOD"/>
		// </Modification>
		// <Modification location="7" residues="M" monoisotopicMassDelta="15.994919">
		//	 <cvParam accession="UNIMOD:35" name="Oxidation" cvRef="UNIMOD"/>
		// </Modification>

		if (!isset($modificationNode['location'])) {
			throw new InvalidModificationException("Could not parse a modification location from the uploaded mzIdentML file for the peptide with ID = " . $peptideId);
		}

		$location = intval($modificationNode['location']);

		$deltaMass = 0;
		if (isset($modificationNode['monoisotopMassDelta'])) {
			$deltaMass = floatval($modificationNode['monoisotopMassDelta']);
		} else if (isset($modificationNode['avgMassDelta'])) {
			$deltaMass = floatval($modificationNode['avgMassDelta']);
		}

		$cvParam = $modificationNode->cvParam;
		$name = "";
		if (isset($cvParam['accession'])) {
			if (preg_match("/unimod:/i", $cvParam['accession'])) {
				$name = $cvParam['accession'];
			}
		} else if (isset($cvParam['name'])) {
			$name = $cvParam['name'];
		}

		return new Modification($location, (string)$name);
	}
}

	// require database connection
	require("config.php");

	// array to hold the data returned from server queries
	$returnArray = Array();
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	// get filename from post request
	$truePath = $basePath . $data->fileName;
	// get the timestamp from post request
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

		// extract identifications from mzTab file
		$identifications = [];

		$xmlFile = simplexml_load_file($truePath);

		$reportedPeptideIds = $xmlFile->SequenceCollection;
		$dataCollection = $xmlFile->DataCollection;
		$peptides = Array();

		foreach ($reportedPeptideIds->Peptide as $peptide) {
			$parsedPeptide = new PeptideNode($peptide);
			$peptides[$parsedPeptide->peptideId] = $parsedPeptide; 
		}

		$scanInformation = $xmlFile->DataCollection->AnalysisData->SpectrumIdentificationList;

		$spectrumIdentificationResults = Array();

		foreach ($scanInformation->SpectrumIdentificationResult as $scanResult) {
			$parsedIdentificationResult = new ResultNode($scanResult);
			$spectrumIdentificationResults[$parsedIdentificationResult->peptideId] = $parsedIdentificationResult;
		}

		// now combine matching peptide IDs into an ID object
		$keys = array_keys($peptides);

		for ($i = 0; $i < count($peptides); $i++) {
			$peptideId = $peptides[$keys[$i]]->peptideId;

			if (array_key_exists($peptideId, $spectrumIdentificationResults)) {
				$ids[] = new ID($peptides[$peptideId], $spectrumIdentificationResults[$peptideId]);
			}
		}

		// load IDs into database
		try {
			// begin transaction
			$pdo->beginTransaction();
			$stmt = $pdo->prepare('INSERT INTO identifications (scan_number, sequence, charge, mods, timestamp) VALUES (:scan_number, :sequence, :charge, :mods, :timestamp)');
			
			foreach ($ids as $id) {
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
			echo (json_encode($ids));
		}

	} catch (BadMzTabHeaderException $e) {
		echo (json_encode(Array("error" => "Caught thrown exception during identification upload:\n " . $e->getMessage())));
	} catch (InvalidModificationException $e) {
		echo (json_encode(Array("error" => "Caught thrown exception during identification upload:\n " . $e->getMessage())));
	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during identification upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}
?>