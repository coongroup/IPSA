<?php
	$host = 'localhost';
	$db   = 'your_database_name';
	$username = 'your_database_userAccount_name';
	$password = 'your_database_userAccount_Password';
	$charset = 'utf8';
	
	$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
	$opt = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
			PDO::ATTR_EMULATE_PREPARES   => false
	];
	$pdo = new PDO($dsn, $username, $password, $opt);
	$supportFolder = "/%absolute_path_to_website_base_folder%/support/";
	$basePath = $supportFolder . "Upload Folder/";
	$downloadPath = $supportFolder . "Download Folder/";
	$relativeZipPath = "support/Download Folder/";
?>