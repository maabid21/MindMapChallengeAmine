<?php
include('connexion.php');
$testparamname=$_POST['testparamname'];

$data = [
	[0, 0,'Mind Map','darkseagreen', 'right', '0 0'],
    [1, 0,'Getting more time', 'skyblue', 'right', '77 -22'],
    [11, 1, 'Wake up early', 'skyblue', 'right', '200 -48'],
];

//Our SQL statement. This will empty / truncate the table "mindmaptable"
$sql = "TRUNCATE TABLE `mindmaptable`";
//Prepare the SQL query.
$statement = $pdo->prepare($sql);
//Execute the statement.
$statement->execute();

$stmt = $pdo->prepare("INSERT INTO mindmaptable(leafkey, parent, text, brush, dir, loc) 
VALUES(?,?,?,?,?,?)");
try {
    $pdo->beginTransaction();
    foreach ($data as $row)
    {
        $stmt->execute($row);
    }
    $pdo->commit();
}catch (Exception $e){
    $pdo->rollback();
    throw $e;
}

echo "The mind map is created/updated successfully, the mindmaptable.ibd in the mysql data base mindmapdb.frm is updated.
 The data base is initially created with phpMyAdmin. My localhost is under xampp.
 The bd is saved in the localhost, in my case the url is C:\\xampp\mysql\data. !--- GOOD BYE --!";
echo '<span style="font-size: 20px; color: #006400; font-weight: bold;">Mohamed Amine Abid.</span> ';


//$sql="INSERT INTO mindmaptable(leafkey, parent, text) VALUES(?,?,?)";
//$stmt=$pdo->prepare($sql);
//$stmt->execute([$pathname, 5, $leafvalue]);

//$sql1 = "INSERT INTO mindmaptable (leafkey, parent, text) VALUES ('10', '15', 'bonsoir')";
//// use exec() because no results are returned
//$pdo->exec($sql1);
//echo "New record created successfully";

//$rep="INSERT into mindmaptable (`key`,`parent`,`text`,`brush`,`dir`,`loc`) VALUES('$pathname','$leafvalue','','','','')";
//$req = mysql_query($rep) or die ('Erreur SQL !'.$rep.'<br>'.mysql_error());

?>