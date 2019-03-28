<?php
	class Modification {
		public $name;
		public $monoisotopicMassShift;
		public $site;
		
		public function __construct($name, $monoisotopicMassShift, $site) {
			$this->name = $name;
			$this->monoisotopicMassShift = $monoisotopicMassShift;
			$this->site = $site;
		}

		public static function ParseModifications($sqlRow) {
			$returnArray = Array();

			$modPositions = "A;C;D;E;F;G;H;I;K;L;M;N;P;Q;R;S;T;V;W;Y;N-term;C-term";
			$name = $sqlRow[0];
			$monoisotopicMassShift = $sqlRow[1];
			$sites = explode(";", $modPositions);

			foreach ($sites as $site) {
				$returnArray[] = new Modification($name, $monoisotopicMassShift, $site);
			}

			return $returnArray;
		}
	}

	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;

	// require database connection
	require("config.php");
	$mods = Array();
	
	// Retrieve IDs from database
	$stmt = $pdo->prepare('SELECT name, mass FROM mods WHERE timestamp = :timestamp');
	$success = $stmt->execute(["timestamp" => $timeStamp]);

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