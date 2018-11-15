<?php
	require("config.php");
	$downloadFiles = scandir($downloadPath);
	$downloadFiles = array_diff($downloadFiles, array('.', '..'));
	
	$currentTime = time();
	foreach ($downloadFiles as $file) {
		$fileAge = $currentTime - filemtime($file);

		if ($fileAge > 3600) {
			//unlink($downloadPath . $file);
		}
	}

	echo (json_encode(Array("files" => $downloadFiles)));
?>