<?php
	class Modification {
		public $unimodId;
		public $name;
		public $monoisotopicMassShift;
		public $site;
		
		public function __construct($unimodId, $name, $monoisotopicMassShift, $site) {
			$this->unimodId = $unimodId;
			$this->name = $name;
			$this->monoisotopicMassShift = $monoisotopicMassShift;
			$this->site = $site;
		}

		public static function ParseModifications($sqlRow) {
			$returnArray = Array();

			$unimodId = "UNIMOD:" . $sqlRow[0];
			$name = $sqlRow[1];
			$monoisotopicMassShift = $sqlRow[3];
			$sites = explode(";", $sqlRow[6]);

			foreach ($sites as $site) {
				$returnArray[] = new Modification($unimodId, $name, $monoisotopicMassShift, $site);
			}

			return $returnArray;
		}
	}
	
	// require database connection
	require("config.php");
	$mods = Array();
	
	// Retrieve IDs from database
	$stmt = $pdo->prepare('SELECT * FROM unimods ORDER BY accession');
	$success = $stmt->execute();

	if(!$success)
	{
		echo (json_encode(array("error" => "Could not define modification options.")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$mods = array_merge($mods, Modification::ParseModifications($result));
		}
	}
	
	echo (json_encode($mods));
?>