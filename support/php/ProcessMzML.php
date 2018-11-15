<?php
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

		// prepare pdo
		$stmt = $pdo->prepare('INSERT INTO spectra (timestamp, scan_number, intensity, mz) VALUES (:timestamp, :scan_number, :intensity, :mz)');

		// begin parsing logic
		$scanNumbers = $data->validScans;
		$targetScan = array_shift($scanNumbers);
		$returnMzs;
		// streamRead mzML
		$reader = new XMLReader();
		$reader->open($truePath);
		while( $reader->read() ) {
			if ($reader->name === "spectrum") {
				$idLine = explode(" ", $reader->getAttribute("id"));
				// [controllerType=0, controllerNumber=1, scan=1610]
				foreach ($idLine as $idLineItem) {
					if ((strpos($idLineItem, 'scan') !== false)) {
						$scanNum = explode("=", $idLineItem)[1];
						if ($targetScan == $scanNum) {
							$mzValues = Array();
							$intensityValues = Array();

							getSpectralData($reader, $mzValues, $intensityValues);
							insertIntoDatabase($stmt, $timeStamp, $targetScan, $mzValues, $intensityValues);
							// add to da
							if (count($scanNumbers) > 0) {
								$targetScan = array_shift($scanNumbers);
							} else {
								//echo json_encode($mzValues);
								die();
							}
						}
					}
				}
			}	
		}
		$reader->close();
		
	} catch (Exception $e) {
		echo (json_encode(Array("error" => "Caught unhandled exception during spectral upload:\n " . $e->getMessage())));
	} finally {
		unlink($truePath);
	}

	function getSpectralData($reader, &$mzValues, &$intensityValues) {
		while ($reader->read()) {
			if ($reader->name === "binaryDataArray" && count($mzValues) == 0) {
				getEncodedValues($reader, $mzValues);
			} else if ($reader->name === "binaryDataArray" && count($intensityValues) == 0) {
				getEncodedValues($reader, $intensityValues);
				break;
			}
		}
	}

	function getEncodedValues($reader, &$array) {
		$precision = -1;
		$compressed = false;
		while ($reader->read()) {
			
			// get precision
			if ($reader->name === "cvParam") {
				if ($reader->getAttribute("name") === "32-bit float") {
					$precision = 32;
				} else if ($reader->getAttribute("name") === "64-bit float") {
					$precision = 64;
				} else if ($reader->getAttribute("name") === "zlib compression") {
					$compressed = true;
				}
			} else if ($reader->name === "binary") {
				// convert to binary
				$decodedBase = base64_decode($reader->readInnerXML());
				// decompress if zlib compressed
				if ($compressed) {
					$decodedBase = zlib_decode($decodedBase);
				}
				
				// unpack to numeric array
				if ($precision == 32) {
					$array = unpack("g*", $decodedBase);
				} else {
					$array = unpack("e*", $decodedBase);
				}
				while ($reader->name !== "binaryDataArray") {
					$reader->read();
				}
        break;
			}
		}
	}

	function insertIntoDatabase($stmt, $timeStamp, $scanNumber,$mzs, $intensities) {
		if (!empty($mzs) && !empty($intensities)) {
			$success = $stmt->execute(['timestamp' => $timeStamp, 'scan_number' => $scanNumber, 'intensity' =>
					implode(";", $intensities), 'mz' => implode(";", $mzs)]);
			if(!$success)
			{
				echo (json_encode(array("error" => "MzML file upload succeeded. However, database upload failed. Please try again.")));
				die;
			}
		}
	}
?>