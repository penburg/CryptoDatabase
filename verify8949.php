<?PHP
require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');

$mysqli = openDB();


$irsTransactions = getAll8949Transactions($mysqli);
echo "tID\tass\taDate    \trRemaining     \tsDate       \tdRemaining     \tqty" . PHP_EOL;

foreach($irsTransactions as $irs8949){
	$tID = $irs8949['8949TransactionID'];
	$aID = $irs8949['AssetID'];
	$qty = floatval($irs8949['AssetQty']);
	$aDate = $irs8949['Acquired'];
	$sDate = $irs8949['Sold'];
	$asset = getAsset($mysqli, $aID);
	$links = get8949TransactionLinkBy8949ID($mysqli, $tID);
	$link = array();
	if(empty($links) || count($links) != 1){
		print_r($links);
		print_r($irs8949);
		echo "Linking Error" . PHP_EOL;
		exit(1);
	}
	$link = $links[0];
	$dRemaining = round(floatval(get8949DisposedRemaining($mysqli, $link['dispositionTransactionId'])), 10);
	$rRemaining = round(floatval(get8949RecievedRemaining($mysqli, $link['recievedTransactionId'])), 10);
	echo "$tID\t$asset\t$aDate\t";
	echo str_pad($rRemaining, 8) . "\t$sDate\t";
	echo str_pad($dRemaining, 8) . "\t$qty" . PHP_EOL;
	//print_r($links);
	//print_r($irs8949);
	if($dRemaining < 0 || $rRemaining < 0){
		//print_r($links);
		//print_r($irs8949);
		echo "Verification Failed" . PHP_EOL;
		exit(1);
	}
	if(floatval($link['qty']) != floatval($irs8949['AssetQty'])){
		print_r($links);
		print_r($irs8949);
		echo "Verification Failed 2" . PHP_EOL;
		exit(1);
	}
}

?>
