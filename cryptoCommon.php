<?PHP
function parseHeaders( $headers ){
    	$head = array();
    	foreach( $headers as $k=>$v )
    	{
        	$t = explode( ':', $v, 2 );
        	if( isset( $t[1] ) )
            	$head[ trim($t[0]) ] = trim( $t[1] );
        	else
        	{
            	$head[] = $v;
            	if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
                	$head['reponse_code'] = intval($out[1]);
        	}
    	}
    	if(empty($head['reponse_code'])){
    		$head['reponse_code'] = 999;
    	}
    	return $head;
}

function getURL($url, $useContext = false){
		$c = 0;
		$options = array(
  		'http'=>array(
    	'method'=>"GET",
    	'header'=>"Accept-language: en\r\n" .
        "User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n" )
		);

		$context = stream_context_create($options);
		while($c < 5){
			if($useContext){
				$page = @file_get_contents($url, false, $context);
			}
			else{
				$page = @file_get_contents($url);
			}
			$head = parseHeaders($http_response_header);
			if($head['reponse_code'] == 200){
				return $page;
			}
			else if($head['reponse_code'] == 429){
				echo "Rate Limit exceeded" . PHP_EOL;
				sleep(30);
			}
			else if($head['reponse_code'] == 404 && $c > 2){
				return "";
			}
			$c++;
			sleep(1 * $c);
		}
		echo "Error: " . $head['reponse_code'] . "<br>" . PHP_EOL;
		print_r($head);
		return "";
}

function getTransactionType($mysqli, $name){
	$n = mysqli_real_escape_string($mysqli, $name);
	$query = "select typeID from TransactionTypes where `name` = '$n';";
	return sqlGetOne($mysqli, $query);
}

function getTransactionByImportID($mysqli, $importID){
	$i = mysqli_real_escape_string($mysqli, $importID);
	$query = "select * from Transactions where `importedID` = '$i';";
	return sqlGetMany($mysqli, $query);
}

function getTransactionByImportIDAndDate($mysqli, $importID, $date){
	$i = mysqli_real_escape_string($mysqli, $importID);
	$d = mysqli_real_escape_string($mysqli, date('Y-m-d H:i:s', strtotime($date)));
	$query = "select * from Transactions where `importedID` = '$i' and `date` = '$d';";
	return sqlGetMany($mysqli, $query);
}

function getTransactionByImportIDRecievedAssetAmount($mysqli, $importID, $recieved, $amount){
	$i = mysqli_real_escape_string($mysqli, $importID);
	$r = getAssetId($mysqli, $recieved);
	$a = mysqli_real_escape_string($mysqli, $amount);
	if(empty($r)){
		echo PHP_EOL . "WARNING Asset Not Found" . PHP_EOL;
		$r = insertAsset($mysqli, $recieved);
	}
	$query = "select * from Transactions where `importedID` = '$i' and `recievedAssetID` = '$r' and `recievedQty` = '$a';";
	return sqlGetMany($mysqli, $query);
}

function getAssetId($mysqli, $symbol){
	$s = mysqli_real_escape_string($mysqli, $symbol);
	$query = "select assetID from Assets where `symbol` = '$s';";
	return sqlGetOne($mysqli, $query);
}

function getAsset($mysqli, $assetID){
	$a = mysqli_real_escape_string($mysqli, $assetID);
	$query = "select symbol from Assets where `assetID` = '$a';";
	return sqlGetOne($mysqli, $query);
}

function getExchange($mysqli, $name){
	$n = mysqli_real_escape_string($mysqli, $name);
	$query = "select exchangeID from Exchange where `Name` = '$n';";
	return sqlGetOne($mysqli, $query);
}

function insertAsset($mysqli, $asset){
	$a = mysqli_real_escape_string($mysqli, $asset);
	$query = "insert into Assets (`symbol`) values ('$a');";
	return sqlInsert($mysqli, $query);
}

function mapToQuery($map, $table){
	$query = "insert into `$table` (";
	$values = "values (";
	foreach($map as $key => $value){
		$query .= "`$key`, ";
		$values .= "'$value', ";
	}
	$query = trim($query, ", ");
	$values = trim($values, ", ");
	
	$query .= ") " . PHP_EOL;
	$query .= $values . ");";
	return $query;
}

function getAll8949Transactions($mysqli){
	$query = "select * from 8949Transactions;";
	return sqlGetMany($mysqli, $query);
}

function get8949TransactionLinkBy8949ID($mysqli, $irsID){
	$query = "select * from `Transaction-8949-link` where `8949TransactionId` = '$irsID';";
	return sqlGetMany($mysqli, $query);
}

function get8949DisposedRemaining($mysqli, $transactionId){
	$query = "select (select `sentQty` from Transactions where transactionID = '$transactionId') - 
	(select sum(qty) from `Transaction-8949-link` where dispositionTransactionId = '$transactionId') as remaining;";
	$ret = sqlGetOne($mysqli, $query);
	if(is_null($ret)){
		$query = "select `sentQty` from Transactions where transactionID = '$transactionId';";
		$ret = sqlGetOne($mysqli, $query);
	}
	return $ret;

}

function getTransactionRecieved($mysqli, $transactionId){
	$query = "select `recievedQty` from Transactions where transactionID = '$transactionId';";
	return sqlGetOne($mysqli, $query);
}

function get8949RecievedRemaining($mysqli, $transactionId){
	$query = "select (select `recievedQty` from Transactions where transactionID = '$transactionId') - 
	(select sum(qty) from `Transaction-8949-link` where recievedTransactionId = '$transactionId') as remaining;";
	$ret = sqlGetOne($mysqli, $query);
	if(is_null($ret)){
		$query = "select `recievedQty` from Transactions where transactionID = '$transactionId';";
		$ret = sqlGetOne($mysqli, $query);
	}
	return $ret;

}

function getTaxableTransactions($mysqli, $year){
	$y = mysqli_real_escape_string($mysqli, $year);
	$query = "select * from Transactions where `type` in ('3', '5', '10') and
	 `date` BETWEEN  '$y-01-01 00:00:00.000' and ' $y-12-31 23:59:59.999'
	  order by `date`;";
	//echo $query . PHP_EOL;
	return sqlGetMany($mysqli, $query);
}

function getReportingCategoryID($mysqli, $name){
	$n = mysqli_real_escape_string($mysqli, $name);
	$query = "select categoryID from ReportingCategories where `Name` = '$n';";
	return sqlGetOne($mysqli, $query);
}

function getReportingCategoryByID($mysqli, $id){
	$query = "select code from ReportingCategories where `categoryID` = '$id';";
	return sqlGetOne($mysqli, $query);
}

function mapToUpdate($mysqli, $map, $table, $key, $value){
		$query = "update $table set ";
		foreach($map as $k => $v){
			if(isset($v)){
				$ev = mysqli_real_escape_string($mysqli, $v);
				$query .= "`$k` = '$ev', ";
			}
		}
		$query = trim($query, ", ");
		$query .= " where `$key` = '$value';";
		return $query;
}

function getAllIncomeByYear($mysqli, $year){
	$query = "select sum(recievedCostUSD) from Transactions where `type` in ('2', '7', '8', '14') and
         `date` BETWEEN  '$year-01-01 00:00:00.000' and '$year-12-31 23:59:59.999'
          order by `date`;";
    return sqlGetOne($mysqli, $query);
}
?>
