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


function insertCoinTrackerLine($mysqli, $keys, $data){
	$map = array();
	mapInsetIfValid($mysqli, $map, 'date', date('Y-m-d H:i:s', strtotime($data[$keys['Date']])));
	mapInsetIfValid($mysqli, $map, 'importedID', $data[$keys['Transaction ID']]);
	mapInsetIfValid($mysqli, $map, 'recievedQty', $data[$keys['Received Quantity']]);
	mapInsetIfValid($mysqli, $map, 'recievedCostUSD', $data[$keys['Received Cost Basis (USD)']]);
	mapInsetIfValid($mysqli, $map, 'recievedAddress', $data[$keys['Received Address']]);
	mapInsetIfValid($mysqli, $map, 'recievedComment', $data[$keys['Received Comment']]);
	mapInsetIfValid($mysqli, $map, 'sentQty', $data[$keys['Sent Quantity']]);
	mapInsetIfValid($mysqli, $map, 'sentCostUSD', $data[$keys['Sent Cost Basis (USD)']]);
	mapInsetIfValid($mysqli, $map, 'sentAddress', $data[$keys['Sent Address']]);
	mapInsetIfValid($mysqli, $map, 'sentComment', $data[$keys['Sent Comment']]);
	mapInsetIfValid($mysqli, $map, 'feeQty', $data[$keys['Fee Amount']]);
	mapInsetIfValid($mysqli, $map, 'feeCostUSD', $data[$keys['Fee Cost Basis (USD)']]);
	mapInsetIfValid($mysqli, $map, 'realizedReturnUSD', $data[$keys['Realized Return (USD)']]);
	mapInsetIfValid($mysqli, $map, 'feeRealizedReturnUSD', $data[$keys['Fee Realized Return (USD)']]);
	
	mapInsetIfValidTableID($mysqli, $map, 'recievedAssetID', 'Assets', 'symbol', $data[$keys['Received Currency']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'sentAssetID', 'Assets', 'symbol', $data[$keys['Sent Currency']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'feeAssetID', 'Assets', 'symbol', $data[$keys['Fee Currency']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'sentWalletID', 'Wallets', 'name', $data[$keys['Sent Wallet']], 'walletID');
	mapInsetIfValidTableID($mysqli, $map, 'recievedWalletID', 'Wallets', 'name', $data[$keys['Received Wallet']], 'walletID');
	mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', $data[$keys['Type']], 'typeID');

	$query = mapToQuery($map, 'Transactions');
	//echo PHP_EOL . $query . PHP_EOL;
	return sqlInsert($mysqli, $query);
}



$mysqli = openDB();

if($argc != 2){
	echo "Useage:" . PHP_EOL;
	echo $argv[0] . " <coinTrackerExport.csv>" . PHP_EOL;
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
		if(count($data) == 20){
			$importID = $data[$keys['Transaction ID']];
			echo $importID . " ";
			if(strcmp($data[$keys['Type']], 'STAKING_REWARD') == 0 || strcmp($data[$keys['Type']], 'TRADE') == 0){
				$stake ++;
				$recieved = $data[$keys['Received Currency']];
				$amount = $data[$keys['Received Quantity']];
				$importedDB = getTransactionByImportIDRecievedAssetAmount($mysqli, $importID, $recieved, $amount);
			}
			else{
				$importedDB = getTransactionByImportID($mysqli, $importID);
			}
			if(empty($importedDB)){
				echo " Not Found ";
				$status = insertCoinTrackerLine($mysqli, $keys, $data);
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
				echo "In DB as " . $importedDB[0]['transactionID'] . PHP_EOL;
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
