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


function insertStakeTaxLine($mysqli, $keys, $data){
	$hasable = $data[$keys['txid']] . $data[$keys['received_amount']] . $data[$keys['received_currency']];
	$importID = md5($hasable);

	$map = array();
	mapInsetIfValid($mysqli, $map, 'date', date('Y-m-d H:i:s', strtotime($data[$keys['timestamp']])));
	mapInsetIfValid($mysqli, $map, 'importedID', $importID);
	mapInsetIfValid($mysqli, $map, 'recievedQty', $data[$keys['received_amount']]);
	mapInsetIfValid($mysqli, $map, 'sentQty', $data[$keys['sent_amount']]);
	mapInsetIfValid($mysqli, $map, 'feeQty', $data[$keys['fee']]);
	
	mapInsetIfValidTableID($mysqli, $map, 'recievedAssetID', 'Assets', 'symbol', $data[$keys['received_currency']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'sentAssetID', 'Assets', 'symbol', $data[$keys['sent_currency']], 'assetID');
	mapInsetIfValidTableID($mysqli, $map, 'feeAssetID', 'Assets', 'symbol', $data[$keys['fee_currency']], 'assetID');
	
	$wallet = $data[$keys['exchange']] . ": " . $data[$keys['wallet_address']];
	$txType = $data[$keys['tx_type']];
	$comment = $data[$keys['comment']];
	if(strcmp($txType, 'STAKING') == 0){
		mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', "STAKING_REWARD", 'typeID');
		mapInsetIfValidTableID($mysqli, $map, 'recievedWalletID', 'Wallets', 'name', $wallet, 'walletID');
		mapInsetIfValid($mysqli, $map, 'recievedAddress', $data[$keys['wallet_address']]);
		mapInsetIfValid($mysqli, $map, 'recievedComment', $comment);
	}
	else if(strcmp($txType, '_MsgDelegate') == 0){
		mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', "PROTOCAL_ACTION", 'typeID');
		mapInsetIfValidTableID($mysqli, $map, 'sentWalletID', 'Wallets', 'name', $wallet, 'walletID');
		mapInsetIfValid($mysqli, $map, 'sentAddress', $data[$keys['wallet_address']]);
		mapInsetIfValid($mysqli, $map, 'sentComment', $comment);
	}
	else if(strcmp($txType, 'TRANSFER') == 0 && !empty($map['recievedQty']) && empty($map['sentQty'])){
		mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', "RECEIVE", 'typeID');
		mapInsetIfValidTableID($mysqli, $map, 'recievedWalletID', 'Wallets', 'name', $wallet, 'walletID');
		mapInsetIfValid($mysqli, $map, 'recievedAddress', $data[$keys['wallet_address']]);
		mapInsetIfValid($mysqli, $map, 'recievedComment', $comment);
	}
	else if(strcmp($txType, 'TRANSFER') == 0 && empty($map['recievedQty']) && !empty($map['sentQty'])){
		mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', "SEND", 'typeID');
		mapInsetIfValidTableID($mysqli, $map, 'sentWalletID', 'Wallets', 'name', $wallet, 'walletID');
		mapInsetIfValid($mysqli, $map, 'sentAddress', $data[$keys['wallet_address']]);
		mapInsetIfValid($mysqli, $map, 'sentComment', $comment);
	}
	else if(strcmp($txType, 'SPEND') == 0 && empty($map['recievedQty']) && !empty($map['sentQty'])){
		$type = empty($map['comment']) ? 'TRANSFER': 'PROTOCAL_ACTION';
		mapInsetIfValidTableID($mysqli, $map, 'type', 'TransactionTypes', 'name', $type, 'typeID');
		mapInsetIfValidTableID($mysqli, $map, 'sentWalletID', 'Wallets', 'name', $wallet, 'walletID');
		mapInsetIfValid($mysqli, $map, 'sentAddress', $data[$keys['wallet_address']]);
		mapInsetIfValid($mysqli, $map, 'sentComment', $comment);
	}
	else{
		echo PHP_EOL . "ERROR unknown Transaction type" . PHP_EOL;
		print_r($keys);
		print_r($data);
		exit(1);
	
	}
	
	$query = mapToQuery($map, 'Transactions');
	//echo PHP_EOL . $query . PHP_EOL;
	//return true;
	return sqlInsert($mysqli, $query);
}



$mysqli = openDB();

if($argc != 2){
	echo "Useage:" . PHP_EOL;
	echo $argv[0] . " <default-stake.Tax-Export.csv>" . PHP_EOL;
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
		if(count($data) == 13){
			$hasable = $data[$keys['txid']] . $data[$keys['received_amount']] . $data[$keys['received_currency']];
			$importID = md5($hasable);
			echo $importID . " ";
			$importedDB = getTransactionByImportID($mysqli, $importID);
			if(empty($importedDB)){
				echo " Not Found ";
				$status = insertStakeTaxLine($mysqli, $keys, $data);
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
