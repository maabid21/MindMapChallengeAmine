<?php

//Your MySQL user account.
$user = 'root';
 
//Your MySQL password.
$password = '';
 
//The server / hostname of your MySQL installation.
$server = 'localhost';
 
//The name of your MySQL database.
$database = 'mindmapdb';
 
//Connect using PDO.
try{
	$pdo = new PDO("mysql:host=$server;dbname=$database", $user, $password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	} catch(PDOException $e) {
  echo $sql . "<br>" . $e->getMessage();
}



// $connexion = mysql_connect('localhost','root','') or die('Erreur de connexion');
// $db = mysql_select_db('mindmapdb',$connexion) or die('Erreur de connexion base');
?>
