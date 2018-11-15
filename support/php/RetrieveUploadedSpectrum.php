<?php
	class returnObject {
		public $mzs;
		public $intensities;
		
		public function returnObject($mzs, $intensities) {
			$this->mzs= $mzs;
			$this->intensities= $intensities;
		}
	}
	
	// require database connection
	require("config.php");
	$mzs;
	$intensities;
	
	// decode data from post
	$data = json_decode(file_get_contents("php://input"));
	$timeStamp = $data->timeStamp;
	$scanNumber = $data->scanNumber;
	
	// Retrieve IDs from database
	$stmt = $pdo->prepare('SELECT mz, intensity FROM spectra WHERE timestamp=:timestamp AND scan_number=:scanNumber');
	$success = $stmt->execute(['timestamp' => $timeStamp, 'scanNumber' => $scanNumber]);
	
	if(!$success)
	{
		echo (json_encode(array("error" => "Could not retrieve previously uploaded spectrum")));
		die;
	} else {
		while($result = $stmt->fetch())
		{
			$mzs = explode(";", $result[0]);
			$intensities = explode(";", $result[1]);
		}
	}
	
	if (count($mzs) > 10000 )
	{
		echo (json_encode(Array("error" => "Too many mass features in spectrum. (Upper limit is 10,000)\nAre you sure the data is centroided?")));
		die;
	} else {
		echo (json_encode(new returnObject($mzs, $intensities)));
	}
?>