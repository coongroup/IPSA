<?php
	$start = microtime(true);
	require("config.php");
	
	$data = json_decode(file_get_contents("php://input"));
	$truePath = $basePath . $data->fileName;
	$timeStamp = $data->timeStamp;
	
	try {
		// delete any old data that may have been uploaded
		$stmt = $pdo->prepare('DELETE FROM spectra WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		$stmt = $pdo->prepare('DELETE FROM mods WHERE timestamp = :timestamp');
		$success = $stmt->execute(['timestamp' => $timeStamp]);
		
		// begin parsing logic
		$scanNumbers = $data->validScans;
		$count = count($scanNumbers);
		
		$isScan = false;
		$foundScan = false;
		
		$iterator = 0;
		$targetScan = $scanNumbers[$iterator];

		$mz = Array();
		$intensity = Array();	
		
		$handle = fopen($truePath, "r");
		$stmt = $pdo->prepare('INSERT INTO spectra (timestamp, scan_number, intensity, mz) VALUES (:timestamp, :scan_number, :intensity, :mz)');
		
		$insertArray = Array();

		if ($handle) {
			while ($iterator <= $count && ($line = fgets($handle)) !== false) {
				// if not empty
				if ($line) {
					// if we're between begin ions and end ions
					if ($isScan) {
						// find scan
						if (!$foundScan) {
							// check to see line is target scan. scan# can be provided in the title line via TPP compatibility or as the "SCANS" parameter
							if (strpos($line, 'TITLE=') !== false) {
								// returns scan=#"
								$scanVariable = end(explode(" ", $line));
								//echo $scanVariable;
								$scanNum = intval(explode("=", $scanVariable)[1]);

								if ($targetScan != $scanNum) {
									$isScan = false;
									continue;
								} else {
									echo $scanNum;
									$foundScan = true;
									$iterator++;
								}
							}	elseif (strpos($line, 'SCANS') !== false) {
								if ($targetScan != explode("=", $line)[1]) {
									$isScan = false;
									continue;
								} else {
									$foundScan = true;
									$iterator++;
								}
							} 
						// we found a target scan. parse for info
						} else {
							// check for end of scan
							if (strpos($line, 'END IONS') !== false) {
								if (!empty($mz)) {
									$insertArray[] = array('timestamp' => $timeStamp, 'scan_number' => $targetScan, 'intensity' =>	implode(";", $intensity), 'mz' => implode(";", $mz));

									if (count($insertArray) >= 1000) {
										InsertDataViaTransaction($pdo, $stmt, $insertArray);
										$insertArray = Array();
									}
									
									$mz = Array();
									$intensity = Array();
								}
								if ($count > $iterator) {
									$targetScan = $scanNumbers[$iterator];
								}
								
								$isScan= false;
								$foundScan = false;
								continue;
							// spectral data
							} elseif (is_numeric($line[0])) {
								$data = explode(" ", $line);
								$mz[] = $data[0];
								$intensity[] = $data[1];
							}
						}
						
					} else {
						if (strcmp($line, "BEGIN IONS")) {
							$isScan = true;
						}
					}
					
				}
			}
			
			fclose($handle);
		} else {
			echo (json_encode(array("error" => "There was a problem opening this MGF file")));
			die;
		} 

		InsertDataViaTransaction($pdo, $stmt, $insertArray);
		
		if(!$success)
		{
			echo (json_encode(array("error" => "MGF file upload succeeded. However, parsing failed. Please make sure the mgf file is in the correct format")));
			die;
		} else {
			echo (json_encode(array("elapsed" => microtime(true) - $start)));
		}
	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during identification upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}

	function InsertDataViaTransaction($pdo, $stmt, $dataArray) {
		$pdo->beginTransaction();

		foreach ($dataArray as $data) {
			$success = $stmt->execute(['timestamp' => $data['timestamp'], 'scan_number' => $data['scan_number'], 'intensity' =>
					$data['intensity'], 'mz' => $data['mz']]);
			if(!$success)
			{
				echo (json_encode(array("error" => "MGF file upload succeeded. However, parsing failed. Please make sure the mgf file is in the correct format")));
				die;
			}
		}
		$pdo->commit();
	}
?>