<?PHP
require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');

function mapInsetIfValid($mysqli, &$map, $key, $value){
	if(!empty($value) && strcmp($value, "...") != 0){
		$map[$key] = mysqli_real_escape_string($mysqli, $value);
	}
}


function mapInsetIfValidTableID($mysqli, &$map, $key, $table, $tableKey, $tableValue, $tableRet){
	if(!empty($tableValue) && strcmp($tableValue, "...") != 0){
		$value = genericGetOne($mysqli, $table, $tableKey, $tableValue, $tableRet);
		if(empty($value)){
			echo PHP_EOL . "Warnig empty $table => $tableValue, Inserting and returning inserted id" . PHP_EOL;
			$value = genericInsertOne($mysqli, $table, $tableKey, $tableValue);
			
		}
		$map[$key] = mysqli_real_escape_string($mysqli, $value);
	}
}

function mapToFind($map, $table, $ret){
	$query = "select $ret from $table where ";
	foreach($map as $key => $value){
		$query .= "`$key` = '$value' and ";
	}
	$query = substr($query, 0, -4);
	$query .= ";";
	return $query;
}

function get8949TransactionByLine($mysqli, $keys, $data){
	$map = array();
	mapInsetIfValid($mysqli, $map, 'AssetQty', $data[$keys['Additional Description']]);
	mapInsetIfValid($mysqli, $map, 'Acquired', date('Y-m-d H:i:s', strtotime($data[$keys['Date Acquired']])));
	mapInsetIfValid($mysqli, $map, 'Sold', date('Y-m-d H:i:s', strtotime($data[$keys['Date Sold']])));
	mapInsetIfValid($mysqli, $map, 'Proceeds', $data[$keys['Sales Proceeds']]);
	mapInsetIfValid($mysqli, $map, 'Cost', $data[$keys['Cost']]);

	mapInsetIfValidTableID($mysqli, $map, 'AssetID', 'Assets', 'symbol', $data[$keys['Description']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'categoryID', 'ReportingCategories', 'code', $data[$keys['Reporting Category']], 'categoryID');
	$query = mapToFind($map, "8949Transactions", "8949TransactionID");
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}



function insert8949Line($mysqli, $keys, $data, $taxYear){
	$map = array();
	mapInsetIfValid($mysqli, $map, 'AssetQty', $data[$keys['Additional Description']]);
	mapInsetIfValid($mysqli, $map, 'Acquired', date('Y-m-d H:i:s', strtotime($data[$keys['Date Acquired']])));
	mapInsetIfValid($mysqli, $map, 'Sold', date('Y-m-d H:i:s', strtotime($data[$keys['Date Sold']])));
	mapInsetIfValid($mysqli, $map, 'Proceeds', $data[$keys['Sales Proceeds']]);
	mapInsetIfValid($mysqli, $map, 'Cost', $data[$keys['Cost']]);
	mapInsetIfValid($mysqli, $map, 'TaxYear', $taxYear);
	
	mapInsetIfValidTableID($mysqli, $map, 'AssetID', 'Assets', 'symbol', $data[$keys['Description']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'categoryID', 'ReportingCategories', 'code', $data[$keys['Reporting Category']], 'categoryID');
	
	$query = mapToQuery($map, '8949Transactions');
	//echo PHP_EOL . $query . PHP_EOL;
	//return false;
	return sqlInsert($mysqli, $query);
}



$mysqli = openDB();

if($argc != 2){
	echo "Useage:" . PHP_EOL;
	echo $argv[0] . " <8949.csv>" . PHP_EOL;
	exit(1);
}

$fileName = $argv[1];

if(!is_file($fileName)){
	echo "File not found" . PHP_EOL;
	exit(1);
}

if (($handle = fopen($fileName, "r")) !== FALSE) {
	$keys = array();
	$data = fgetcsv($handle);
	foreach($data as $key => $value){
		$keys[$value] = $key;
	}
	print_r($keys);
	$stake = 0;
	//fgets($handle); //remove headder row
	while(($data = fgetcsv($handle)) !== FALSE){
		//print_r($data);
		//exit(0);
		if(count($data) == 7){
			echo md5(implode("", $data));
			$importedDB = get8949TransactionByLine($mysqli, $keys, $data);
			if(empty($importedDB)){
				echo " Not Found ";
				$status = insert8949Line($mysqli, $keys, $data, "2022");
				if(!empty($status)){
					echo ", Inserted as $status" . PHP_EOL;
				}
				else{
					echo PHP_EOL . "Failed to insert transaction" . PHP_EOL;
					exit(1);
				}
			}
			else if(count($importedDB) > 1){
				echo "WARNING importedID is used more than once" . PHP_EOL;
				print_r($importedDB);
				exit(1);
			}
			else{
				echo "In DB as " . $importedDB[0]['8949TransactionID'] . PHP_EOL;
				//exit(1);
			}
			
			
			//exit(0);
		}
		else{
			echo "Data format Error" . PHP_EOL;
			exit(1);
		}
	}
	//echo "Total Staking Trans $stake" . PHP_EOL;
}

?>
