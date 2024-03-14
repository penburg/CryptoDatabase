<?PHP

function sqlInsert($mysqli, $query){
	if($mysqli->query($query)){
		//echo "Insert: " . $mysqli->insert_id . PHP_EOL;
		return $mysqli->insert_id;
	}
	else{
		failedAt("sqlInsert", $query . " - " . $mysqli->error);
	}
	
}
	
function sqlGetOne($mysqli, $query){
	$result = $mysqli->query($query);
	if($result->num_rows >= 1){
		$ret = $result->fetch_array();
		return $ret[0];
	}
	else{
		//var_dump($result);
		return "";
	}	
}
	
function sqlGetMany($mysqli, $query){
	$result = $mysqli->query($query);
	//echo $query . PHP_EOL;
	//print_r($result);
	if($result->num_rows >= 1){
		$ret = $result->fetch_all(MYSQLI_ASSOC);
		return $ret;
	}
	else{
		//var_dump($result);
		return array();
	}	
}
	
function sqlOpen($host, $user, $pass, $database){
	$mysqli = new mysqli($host, $user, $pass, $database);
	if ($mysqli -> connect_errno) {
  		echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  		exit();
	}
	return $mysqli;
}

function failedAt($page, $cause){
	echo "Failed at page $page" . PHP_EOL;
	echo "With error - $cause" . PHP_EOL;
	exit(1);
}
	
function sqlUpdate($mysqli, $query){
	$status = $mysqli->query($query);
	if ($status === true){
		return true;
	}
	else{
		echo "Error updating record: " . $conn->error;
		return false;
	}

}
	
function genericGetOne($mysqli, $table, $key, $value, $ret){
	$v = mysqli_real_escape_string($mysqli, $value);
	$query = "select `$ret` from $table where `$key` = '$v';";
	//echo $query . PHP_EOL;
	return sqlGetOne($mysqli, $query);
}

function genericInsertOne($mysqli, $table, $key, $value){
	$v = mysqli_real_escape_string($mysqli, $value);
	$query = "insert into $table (`$key`) values ('$v');";
	//echo $query . PHP_EOL;
	return sqlInsert($mysqli, $query);
}
?>
