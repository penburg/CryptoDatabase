<?PHP
require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');


function getTransactionsByAssetDate($mysqli, $assetID, $date, $recieved){
	$key = $recieved ? "recievedAssetID" : "sentAssetID";
	$base = strtotime($date);
	$start = date('Y-m-d H:i:s', strtotime("-1 hour", $base));
	$end = date('Y-m-d H:i:s', strtotime("+25 hour", $base));
	$query = "select * from Transactions where `$key` = '$assetID' and `date` between '$start' and '$end';";
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}

function getTransactionsByAssetAndRecievedOnDate($mysqli, $assetID, $date){
	$base = strtotime($date);
	$start = date('Y-m-d H:i:s', strtotime("-1 hour", $base));
	$end = date('Y-m-d H:i:s', strtotime("+25 hour", $base));
	$types = "1, 2, 6, 8, 14";
	
	$query = "select * from Transactions where `recievedAssetID` = '$assetID' and `date` between '$start' and '$end' and type in ($types);";
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}

function getTransactionsByAssetAndSentOnDate($mysqli, $assetID, $date){
	$base = strtotime($date);
	$start = date('Y-m-d H:i:s', strtotime("-1 minute", $base));
	$end = date('Y-m-d H:i:s', strtotime("+1 day, +1 minute", $base));
	$types = "3, 5, 10";
	
	$query = "select * from Transactions where `sentAssetID` = '$assetID' and `date` between '$start' and '$end' and type in ($types);";
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}



function establish8949TransactionLink($mysqli, $irsID, $qty, $aTransactionID, $dTransactionID){
	$map = array();
	$map['8949TransactionId'] = $irsID;
	$map['qty'] = floatval($qty);
	$map['recievedTransactionId'] = $aTransactionID;
	$map['dispositionTransactionId'] = $dTransactionID;
	$query = mapToQuery($map, 'Transaction-8949-link');
	//echo $query . PHP_EOL;
	return sqlInsert($mysqli, $query);
}

$mysqli = openDB();

$irsTransactions = getAll8949Transactions($mysqli);
//print_r($irsTransactions);
$count = 0;
foreach($irsTransactions as $irs8949){
	$links = get8949TransactionLinkBy8949ID($mysqli, $irs8949['8949TransactionID']);
	echo md5(implode(" ", $irs8949));
	if(empty($links)){
		//print_r($irs8949);
		echo " No Links found" . PHP_EOL;
		$recieved = getTransactionsByAssetAndRecievedOnDate($mysqli, $irs8949['AssetID'], $irs8949['Acquired']);
		$sent = getTransactionsByAssetAndSentOnDate($mysqli, $irs8949['AssetID'], $irs8949['Sold']);
		//$sent = getTransactionsByAssetDate($mysqli, $irs8949['AssetID'], $irs8949['Sold'], false);
		//$recieved = getTransactionsByAssetDate($mysqli, $irs8949['AssetID'], $irs8949['Acquired'], true);
		//print_r($recieved);
		//print_r($sent);
		//exit(1);
		for($i = 0; $i < count($sent); $i++){
			$remaing = get8949DisposedRemaining($mysqli, $sent[$i]['transactionID']);
			if($remaing != null){
				if(floatval($remaing) == 0){
					unset($sent[$i]);
				}
				else if(floatval($remaing) < 0){
					echo "Negitive value" . PHP_EOL;
					exit(1);
				}
				else{
					$sent[$i]['sentQty'] = floatval($remaing);
				}
			}
			if(!empty($sent[$i])){
				if(floatval($sent[$i]['sentQty']) < floatval($irs8949['AssetQty'])){
					unset($sent[$i]);
				}
				else if(floatval($sent[$i]['sentQty']) == floatval($irs8949['AssetQty'])){
					$c = count($sent);
					$sent = array($sent[$i]);
					$i = $c + 1;
				}
			}
			
		}
		$sent = array_values($sent);
		
		for($i = 0; $i < count($recieved); $i++){
			$remaing = get8949RecievedRemaining($mysqli, $recieved[$i]['transactionID']);
			if($remaing != null){
				if(floatval($remaing) == 0){
						unset($recieved[$i]);
				}
				else if(floatval($remaing) < 0){
					echo "Negitive value" . PHP_EOL;
					exit(1);
				}
				else{
					$recieved[$i]['recievedQty'] = floatval($remaing);
				}
			}
			
			else if(floatval($recieved[$i]['recievedQty']) < floatval($irs8949['AssetQty'])){
				unset($recieved[$i]);
			}
			else if(floatval($recieved[$i]['recievedQty']) == floatval($irs8949['AssetQty'])){
				$c = count($recieved);
				$recieved = array($recieved[$i]);
				$i = $c + 1;
			}
			
		}
		$recieved = array_values($recieved);
		
		if(count($recieved) == 1 && count($sent) == 1){
			echo "Transactions found" . PHP_EOL;
			$success = establish8949TransactionLink($mysqli, $irs8949['8949TransactionID'], $irs8949['AssetQty'], $recieved[0]['transactionID'], $sent[0]['transactionID']);
			if($success){
				echo "Inserted as linkid: $success (" . $recieved[0]['transactionID'] . " => " . $sent[0]['transactionID'] . ")". PHP_EOL;
			}
			else{
				echo "Failed to insert link" . PHP_EOL;
				exit(1);
			}
		}
		else{
			echo "Unable to coorelate data" . PHP_EOL;
			//$sent = getTransactionsByAssetDate($mysqli, $irs8949['AssetID'], $irs8949['Sold'], false);
			//$recieved = getTransactionsByAssetDate($mysqli, $irs8949['AssetID'], $irs8949['Acquired'], true);
			echo "data" . PHP_EOL;
			print_r($irs8949);
			echo "recieved" . PHP_EOL;
			print_r($recieved);
			echo "sent" . PHP_EOL;
			print_r($sent);
			exit(0);
		}
		
	}
	else{
		echo " Links found" . PHP_EOL;
	}
	$count++;
	//if($count > 60){
	//	exit(0);
	//}
}

?>
