<?php

if(session_status() === PHP_SESSION_NONE) session_start();
?>

<!DOCTYPE html>
<html>
<head>
	<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : "Admin Panel"; ?></title>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

	<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

	<link rel="stylesheet" type="text/css" href="assets/css/admin.css">
</head>
<body>

	<nav class="navbar navbar-dark bg-dark">
		<a href="index.php?page=dashboard" class="navbar-brand">Admin Panel</a>
	</nav>

	<div class="container-fluid">
		<div class="row">
			
		