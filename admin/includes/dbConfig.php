<?php

$host = "localhost";
$dbname = "e_com";
$user = "root";
$pass = "";

try 
{
	$DB_con = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
	$DB_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) 
{
	die("DB Connection Failed:".$e->getMessage());
}


?>