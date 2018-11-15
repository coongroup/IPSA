<?php
	class Modification {
		public $name;
		public $mass;

		public function Modification($name, $mass) {
			$this->name = $name;
			$this->mass = $mass;
		}
	}
	
	require("config.php");
	
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	$truePath = $basePath . $data->fileName;
	
	try {
		// delete any old data that may have been uploaded
		$stmt = $pdo->prepare('DELETE FROM mods WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		$returnArray = Array();
		$testArray = Array();

		$handle = fopen($truePath, "r");
		$skippedHeader = false;
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				if ($line) {
					$testArray[] = $line;
					if ($skippedHeader) {
						$data = explode(",", $line);
						$returnArray[] = new Modification($data[0], trim($data[1]));
					} else {
						$skippedHeader = true;
					}		
				}
			}
		}
		
		fclose($handle);

		$stmt = $pdo->prepare('INSERT INTO mods (timestamp, name, mass) VALUES (:timestamp, :name, :mass)');
		
		foreach ($returnArray as $parsedMod) {
			$success = $stmt->execute(['timestamp' => $timeStamp, 'name' => $parsedMod->name, 'mass' => $parsedMod->mass]);
			if(!$success) {
				echo (json_encode(array("error" => "Modification file upload succeeded. However, parsing failed. Please make sure the modification file is in the correct format")));
				die;
			}
		}

		echo (json_encode($returnArray));

	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during modification upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}
?>