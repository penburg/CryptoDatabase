<?PHP

require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');

function findRecievedByAssetBeforeDateFIFO($mysqli, $aID, $date){
	$d = mysqli_real_escape_string($mysqli, $date);
	$query = "select * from Transactions where `date` <= '$d' 
	and recievedAssetID = '$aID' and `type` in('2', '6', '7', '8', '10', '14') 
	ORDER by `date` ;";
	
	return sqlGetMany($mysqli, $query);
}

function findRecievedByAssetBeforeDateHIFO($mysqli, $aID, $date){
	$d = mysqli_real_escape_string($mysqli, $date);
	$query = "select *, (recievedCostUSD/ recievedQty) as rate 
	from Transactions where `date` <= '$d' and
	recievedAssetID = '$aID' and `type` in('2', '6', '7', '8', '10', '14') 
	ORDER by `rate` desc;";
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}


function getRecievedRemainingFromTaxed(&$transactions, $i, &$taxLines){
	foreach($taxLines as $tl){
		if($tl['link']['recievedTransactionId'] == $transactions[$i]['transactionID']){
			//echo "Before " . $transactions[$i]['recievedQty'];
			$transactions[$i]['recievedQty'] -= $tl['link']['qty'];
			if($transactions[$i]['recievedQty'] < 0.0){
				echo "Failed sanity check 2: " . $transactions[$i]['recievedQty']. PHP_EOL;
				exit(1);
			}
		}
	}
}

function filterRecievedWileTaxed($mysqli, &$taxLines, &$allRecieved){
	for($i = 0; $i < count($allRecieved); $i++){
		$recievedRate = $allRecieved[$i]['recievedCostUSD'] / $allRecieved[$i]['recievedQty'];
		$allRecieved[$i]['recievedRate'] = $recievedRate;
		$remaining = round(floatval(get8949RecievedRemaining($mysqli, $allRecieved[$i]['transactionID'])), 12);
		$allRecieved[$i]['recievedQty'] = $remaining;
		getRecievedRemainingFromTaxed($allRecieved, $i, $taxLines);
		
		if($allRecieved[$i]['recievedQty'] == 0.0){
			//unset($allRecieved[$i]);
			//echo "UNSET" . PHP_EOL;
		}
		else if($remaining < 0.0){
			echo " ERROR < 0" . PHP_EOL;
			exit(1);
		}
		else if($allRecieved[$i]['recievedQty'] < 0.0){
			//print_r($taxLines);
			echo "Failed sanity check " . $allRecieved[$i]['recievedQty']. PHP_EOL;
			exit(1);
		}
		
	}

	$allRecieved = array_values($allRecieved);
}

function calculateTaxfromTransaction($mysqli, $t, &$taxLines){
	global $year;
	$qty = $t['sentQty'];
	$aID = $t['sentAssetID'];
	$date = $t['date'];
	$longTerm = getReportingCategoryID($mysqli, "Long-Term");
	$shortTerm = getReportingCategoryID($mysqli, "Short-Term");
	$allRecieved = array();
	if($aID == 15){
		$allRecieved = findRecievedByAssetBeforeDateFIFO($mysqli, $aID, $date);
	}
	else{
		$allRecieved = findRecievedByAssetBeforeDateHIFO($mysqli, $aID, $date);
		//$allRecieved = findRecievedByAssetBeforeDateFIFO($mysqli, $aID, $date);
	}
	
	
	//echo "Recieved tx " . count($allRecieved);
	filterRecievedWileTaxed($mysqli, $taxLines, $allRecieved);
	//echo " Filted tx " . count($allRecieved) . PHP_EOL;
	while ($qty > 0){
		$irs8949 = array();
		$irs8949['AssetID'] = $t['sentAssetID'];
		$irs8949['AssetQty'] = '';
		$irs8949['Acquired'] = '';
		$irs8949['Sold'] = date("Y-m-d", strtotime($date));
		$irs8949['Proceeds'] = '';
		$irs8949['Cost'] = '';
		$irs8949['categoryID'] = '';
		$irs8949['TaxYear'] = $year;
		$sentRate = $t['sentCostUSD'] / $t['sentQty'];
		$link = array();
		$link['recievedTransactionId'] = '';
		$link['dispositionTransactionId'] = $t['transactionID'];
		$link['qty'] = '';
		$irs8949['link'] = $link; 
		//echo "Qty: $qty";
		for($i = 0; $i < count($allRecieved); $i++){
			//echo $i . PHP_EOL;
			if($allRecieved[$i]['recievedQty'] > 0 && $qty > 0){
				//print_r($allRecieved[$i]);
				$usedQty = 0;
				if($allRecieved[$i]['recievedQty'] >= $qty){
					$usedQty = $qty;
					//echo "HERE $qty" . PHP_EOL;
				}
				else if($allRecieved[$i]['recievedQty'] > 0){
					$usedQty = $allRecieved[$i]['recievedQty'];
				}
				else{
					echo "ZERO Value here" . PHP_EOL;
					exit(1);
				}
				$recievedRate = $allRecieved[$i]['recievedCostUSD'] / $allRecieved[$i]['recievedQty'];
				if(isset($allRecieved[$i]['recievedRate'])){
					$recievedRate = $allRecieved[$i]['recievedRate'];
				}
				$qty -= $usedQty;				
				//$allRecieved[$i]['recievedQty'] -= $usedQty;	
				//echo "Taking $usedQty from " . $allRecieved[$i]['transactionID'] . " remaining: " . ($allRecieved[$i]['recievedQty'] - $usedQty). PHP_EOL;
				
				$irs8949['AssetQty'] = $usedQty;
				$irs8949['Acquired'] = date("Y-m-d", strtotime($allRecieved[$i]['date']));
				$irs8949['Proceeds'] = $sentRate * $usedQty;
				$irs8949['Cost'] = $recievedRate * $usedQty;
				if(isset($t['feeCostUSD'])){
					$irs8949['Cost'] += $t['feeCostUSD'];
				}
				if(isset($allRecieved[$i]['feeCostUSD'])){
					$irs8949['Cost'] += $allRecieved[$i]['feeCostUSD'];
				}
				
				$irs8949['link']['recievedTransactionId'] = $allRecieved[$i]['transactionID'];
				$irs8949['link']['qty'] = $usedQty;
				
				$soldTime = strtotime($date);
				$recievedTime = strtotime($allRecieved[$i]['date']);
				$irs8949['categoryID'] = ($soldTime - $recievedTime) > 31536000 ? $longTerm : $shortTerm;
				
				$taxLines[] = $irs8949;

				//echo "qty Used: $usedQty, Qty Remaining: $qty, Recieved Rate: $recievedRate, Sent Rate: $sentRate " . PHP_EOL;
				
			}
			else if($allRecieved[$i]['recievedQty'] == 0.0){
				//echo "Zeroe recieved" . PHP_EOL;
				//exit(1);
				
			}
			else if ($allRecieved[$i]['recievedQty'] < 0.0){
				echo "less than zero recieved" . PHP_EOL;
				exit(1);
			}
		}
		if($qty > 0){
			echo "Failed to alocate enough recieved asset" . PHP_EOL;
			exit(1);
		
		}
	
		
		
		//exit(0);
	}
	

}

function getTransactionsWithoutCost($mysqli){
	$query = "select * from Transactions where
		(recievedAssetID is not null and recievedCostUSD is NULL) or 
		(sentAssetID is not null and sentCostUSD is NULL) or 
		(feeAssetID is not null and feeCostUSD is null);";
	return sqlGetMany($mysqli, $query);
}

function getTransactionUsdValues($mysqli, &$transaction){
	$time = strtotime($transaction['date']);
	$update = array();
	$usd = array(1, 15, 82);
	if(isset($transaction['recievedAssetID'])){
		if(!in_array($transaction['recievedAssetID'], $usd)){
			$rAsset = getAsset($mysqli, $transaction['recievedAssetID']);
			$pair = $rAsset;
			$rate = getPairValueAtTime($pair, $time);
			$update['recievedCostUSD'] = $rate * $transaction['recievedQty'];
			
		}
		else{
			$update['recievedCostUSD'] = $transaction['recievedQty'];
		}
	}
	if(isset($transaction['sentAssetID'])){
		if(!in_array($transaction['sentAssetID'], $usd)){
			$rAsset = getAsset($mysqli, $transaction['sentAssetID']);
			$pair = $rAsset;
			$rate = getPairValueAtTime($pair, $time);
			$update['sentCostUSD'] = $rate * $transaction['sentQty'];
			
		}
		else{
			$update['sentCostUSD'] = $transaction['sentQty'];
		}
	}
	if(isset($transaction['feeAssetID'])){
		if(!in_array($transaction['feeAssetID'], $usd)){
			$rAsset = getAsset($mysqli, $transaction['feeAssetID']);
			$pair = $rAsset;
			$rate = getPairValueAtTime($pair, $time);
			$update['feeCostUSD'] = $rate * $transaction['feeQty'];
			
		}
		else{
			$update['feeCostUSD'] = $transaction['feeQty'];
		}
	}
	//print_r($update);
	//exit(0);
	return $update;
	
}

function getPairValueAtTime($coin, $time){
	if(strcmp("VAMMV2-RED/OP", $coin) == 0){
		return 0;
	}
	if(strcmp("YVVELO-RED-OP-F", $coin) == 0){
		return 0;
	}

	
	
	$end = $time + (3600 * 5);
	$url = "https://api.exchange.coinbase.com/products/$coin-USD/candles?start=$time&end=$end";
	$json = json_decode(getURL($url, true), true);
	
	if(is_array($json) && isset($json[0][1]) && is_numeric($json[0][1])){
		//print_r($json);
		//exit(0);
		//echo "$coin value " . $json[0][1] . " at " . date('Y-m-d H:i:s', $time) . PHP_EOL;
		//exit(0);
		return $json[0][1];
	}
	else{
		//print_r($json);
		echo "Failed to retreve value: $coin "  . date('Y-m-d H:i:s', $time) . PHP_EOL . $url . PHP_EOL;
	}
	$id = getIdFromSymbol($coin);
	if(strcmp("Worthless_NFT-Found", $id) == 0){
		echo "Value set to zero" . PHP_EOL;
		return 0;
	}
		
	$url = "https://api.coingecko.com/api/v3/coins/$id/market_chart/range?vs_currency=usd&from=$time&to=$end";

	//echo $url . PHP_EOL;
	$json = json_decode(getURL($url), true);
	if(is_array($json) && isset($json['prices'][0][1]) && is_float($json['prices'][0][1])){
		//echo "$coin value " . $json['prices'][0][1] . " at " . date('Y-m-d H:i:s', $time) . PHP_EOL;
		sleep(3);
		return $json['prices'][0][1];
	}
	else{
		echo "Failed to retreve value: $coin "  . date('Y-m-d H:i:s', $time) . PHP_EOL . $url . PHP_EOL;
		if(str_starts_with($coin, "st")){
			echo "Stride staked asset, default to zero" . PHP_EOL;
			return 0;
		}
		print_r($json);
		exit(0);
	}
	
}

function updateTransaction($mysqli, $tId, $update){
	$query = mapToUpdate($mysqli, $update, "Transactions", "transactionID", $tId);
	//echo $query . PHP_EOL;
	return sqlUpdate($mysqli, $query);
}

function getIdFromSymbol($symbol){
	global $allCoins;
	$symbol = strcmp($symbol, "STLUNA") == 0 ? '$stluna' : $symbol;
	$symbol = strcmp($symbol, "stEVMOS") == 0 ? 'EVMOS' : $symbol;
	$symbol = strcmp($symbol, "WLUNC") == 0 ? 'LUNC' : $symbol;
	foreach($allCoins as $coin){
		if(strcasecmp($coin['symbol'], $symbol) == 0){
			return $coin['id'];
		}
	}
	echo "Failed to locate id for coin $symbol" . PHP_EOL;
	if(strpos($symbol, "#") !== false){
		echo "NFT's are worthless" . PHP_EOL;
		return "Worthless_NFT-Found";
	}
	
	exit(1);
}

function insert8949Line($mysqli, $line, $year){
	print_r($line);
	$link = $line['link'];
	unset($line['link']);
	$query = mapToQuery($line, "8949Transactions");
	//echo $query;
	$irsID = sqlInsert($mysqli, $query);
	if(!empty($irsID)){
		$link['8949TransactionId'] = $irsID;
		$query = mapToQuery($link, "Transaction-8949-link");
		//echo $query;
		$success = sqlInsert($mysqli, $query);
		if(empty($success)){
			echo "Failed  to link irs transaction" . PHP_EOL;
			exit(1);
		}
	}
	else{
		echo "Failed to insert IRS 8949" . PHP_EOL;
		exit(1);
	}
	return $success;
}

function verify($mysqli, $irs8949){
	//print_r($irs8949);
	$tID = "stub";
	$aID = $irs8949['AssetID'];
	$qty = floatval($irs8949['AssetQty']);
	$aDate = $irs8949['Acquired'];
	$sDate = $irs8949['Sold'];
	$asset = getAsset($mysqli, $aID);
	$link = $irs8949['link'];
	$dRemaining = round(floatval(get8949DisposedRemaining($mysqli, $link['dispositionTransactionId']) - $qty), 10);
	$rRemaining = round(floatval(get8949RecievedRemaining($mysqli, $link['recievedTransactionId']) - $qty), 10);
	echo "$tID\t$asset\t$aDate\t";
	echo str_pad($rRemaining, 8) . "\t$sDate\t";
	echo str_pad($dRemaining, 8) . "\t$qty" . PHP_EOL;
	//print_r($links);
	//print_r($irs8949);
	if($dRemaining < 0 || $rRemaining < 0){
		//print_r($links);
		//print_r($irs8949);
		
		echo "Verification Failed" . PHP_EOL;
		echo "Id: " . $link['recievedTransactionId'] . " Recieved: " . getTransactionRecieved($mysqli, $link['recievedTransactionId']) . " Recieved Remaining: " . round(floatval(get8949RecievedRemaining($mysqli, $link['recievedTransactionId'])), 10) .  " Qty wanted: $qty" . PHP_EOL;
		exit(1);
	}
	if(floatval($link['qty']) != floatval($irs8949['AssetQty'])){
		print_r($links);
		print_r($irs8949);
		echo "Verification Failed 2" . PHP_EOL;
		//exit(1);
	}

}

$mysqli = $mysqli = openDB();

if($argc != 2){
	echo "Useage:" . PHP_EOL;
	echo $argv[0] . " <Tax Year>" . PHP_EOL;
	exit(1);
}
$year = $argv[1];
$mostlyTaxable = getTaxableTransactions($mysqli, $year);
$taxable = array();
$nonTaxable = array();
$usdId = getAssetId($mysqli, 'USD');
$usdcId = getAssetId($mysqli, 'USDC');
$usdceId = getAssetId($mysqli, 'USDC.E');
$typeSend = getTransactionType($mysqli, "SEND");

foreach($mostlyTaxable as $t){
	if($t['type'] != $typeSend){
		$taxable[] = $t;
	}
	else if($t['sentAssetID'] == $usdcId || $t['sentAssetID'] == $usdceId){
		if(strpos($t['sentComment'], "Spent USDC") !== false){
			$taxable[] = $t;
		}
		else{
			$nonTaxable[] = $t;
		}
	}
	else{
		$nonTaxable[] = $t;
	}
}


echo "Count tax " . count($taxable) . " nontax " . count($nonTaxable) . PHP_EOL;
$irsLines = array();

foreach($taxable as $t){
	//print_r($t);
	calculateTaxfromTransaction($mysqli, $t, $irsLines);
	//sleep(1);
	//exit(0);

}
$cost = 0;
$proc = 0;
$addToDB = false;
echo "tID\tass\taDate    \trRemaining     \tsDate       \tdRemaining     \tqty" . PHP_EOL;
foreach($irsLines as $line){
	$cost += $line['Cost'];
	$proc += $line['Proceeds'];
	verify($mysqli, $line);
	
	if($addToDB){
		$success = insert8949Line($mysqli, $line, $year);
		if(!$success){
			echo "Failed to add irs8949 line to db" . PHP_EOL;
			exit(0);
		}
	}
}



//print_r($irsLines);
$allIncome = getAllIncomeByYear($mysqli, $year);
echo "Count " . count($irsLines) . PHP_EOL;
echo "Total cost $cost, Proceeds, $proc gain loss: " . ($proc - $cost) . PHP_EOL;
echo "All Income: $allIncome" . PHP_EOL;
?>
