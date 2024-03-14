<?PHP
require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');

$mysqli = openDB();


$query = "CREATE DATABASE `Crypto` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;";
sqlInsert($mysqli, $query);

#-- Crypto.Assets definition

$query = "CREATE TABLE `Assets` (
  `assetID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `symbol` varchar(100) NOT NULL,
  PRIMARY KEY (`assetID`)
) ENGINE=InnoDB AUTO_INCREMENT=636 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.Exchange definition

$query = "CREATE TABLE `Exchange` (
  `exchangeId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `apiKey` varchar(100) DEFAULT NULL,
  `apiSecret` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`exchangeId`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.ReportingCategories definition

$query = "CREATE TABLE `ReportingCategories` (
  `categoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(100) NOT NULL,
  PRIMARY KEY (`categoryID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.TransactionLinkTypes definition

$query = "CREATE TABLE `TransactionLinkTypes` (
  `linkTypeId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`linkTypeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.TransactionTypes definition

$query = "CREATE TABLE `TransactionTypes` (
  `typeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`typeID`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.`8949Transactions` definition

$query = "CREATE TABLE `8949Transactions` (
  `8949TransactionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `AssetID` int(10) unsigned NOT NULL,
  `AssetQty` double DEFAULT NULL,
  `Acquired` date DEFAULT NULL,
  `Sold` date DEFAULT NULL,
  `Proceeds` double DEFAULT NULL,
  `Cost` double DEFAULT NULL,
  `categoryID` int(10) unsigned NOT NULL,
  `TaxYear` year(4) DEFAULT NULL,
  PRIMARY KEY (`8949TransactionID`),
  KEY `IRS_8949_Transactions_FK` (`AssetID`),
  KEY `Categories_FK` (`categoryID`),
  CONSTRAINT `Categories_FK` FOREIGN KEY (`categoryID`) REFERENCES `ReportingCategories` (`categoryID`),
  CONSTRAINT `IRS_8949_Transactions_FK` FOREIGN KEY (`AssetID`) REFERENCES `Assets` (`assetID`)
) ENGINE=InnoDB AUTO_INCREMENT=2586 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.Wallets definition

$query = "CREATE TABLE `Wallets` (
  `walletID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exchangeID` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`walletID`),
  KEY `ExchangeID_FK` (`exchangeID`),
  CONSTRAINT `ExchangeID_FK` FOREIGN KEY (`exchangeID`) REFERENCES `Exchange` (`exchangeId`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.Transactions definition

$query = "CREATE TABLE `Transactions` (
  `transactionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `type` int(10) unsigned NOT NULL,
  `importedID` varchar(100) NOT NULL,
  `recievedQty` double DEFAULT NULL,
  `recievedAssetID` int(10) unsigned DEFAULT NULL,
  `recievedCostUSD` double DEFAULT NULL,
  `recievedWalletID` int(10) unsigned DEFAULT NULL,
  `recievedAddress` varchar(128) DEFAULT NULL,
  `recievedComment` varchar(100) DEFAULT NULL,
  `sentQty` double DEFAULT NULL,
  `sentAssetID` int(10) unsigned DEFAULT NULL,
  `sentCostUSD` double DEFAULT NULL,
  `sentWalletID` int(10) unsigned DEFAULT NULL,
  `sentAddress` varchar(128) DEFAULT NULL,
  `sentComment` varchar(100) DEFAULT NULL,
  `feeQty` double DEFAULT NULL,
  `feeAssetID` int(10) unsigned DEFAULT NULL,
  `feeCostUSD` double DEFAULT NULL,
  `realizedReturnUSD` double DEFAULT NULL,
  `feeRealizedReturnUSD` double DEFAULT NULL,
  PRIMARY KEY (`transactionID`),
  KEY `Coinbase_FK` (`type`),
  KEY `Coinbase_FK_1` (`recievedAssetID`),
  KEY `recievedWalletID_FK` (`recievedWalletID`),
  KEY `sentAssetID_FK` (`sentAssetID`),
  KEY `sentWalletID_FK` (`sentWalletID`),
  KEY `Transactions_Assets_FK` (`feeAssetID`),
  CONSTRAINT `Coinbase_FK` FOREIGN KEY (`type`) REFERENCES `TransactionTypes` (`typeID`),
  CONSTRAINT `Coinbase_FK_1` FOREIGN KEY (`recievedAssetID`) REFERENCES `Assets` (`assetID`),
  CONSTRAINT `Transactions_Assets_FK` FOREIGN KEY (`feeAssetID`) REFERENCES `Assets` (`assetID`),
  CONSTRAINT `recievedWalletID_FK` FOREIGN KEY (`recievedWalletID`) REFERENCES `Wallets` (`walletID`),
  CONSTRAINT `sentAssetID_FK` FOREIGN KEY (`sentAssetID`) REFERENCES `Assets` (`assetID`),
  CONSTRAINT `sentWalletID_FK` FOREIGN KEY (`sentWalletID`) REFERENCES `Wallets` (`walletID`)
) ENGINE=InnoDB AUTO_INCREMENT=4106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.`Transaction-8949-link` definition

$query = "CREATE TABLE `Transaction-8949-link` (
  `tranLinkId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `8949TransactionId` int(10) unsigned NOT NULL,
  `recievedTransactionId` int(10) unsigned DEFAULT NULL,
  `dispositionTransactionId` int(10) unsigned NOT NULL,
  `qty` double NOT NULL,
  PRIMARY KEY (`tranLinkId`),
  KEY `Transaction_8949_link_FK` (`recievedTransactionId`),
  KEY `Transaction_8949_link_FK_1` (`8949TransactionId`),
  KEY `disposition_FK` (`dispositionTransactionId`),
  CONSTRAINT `Transaction_8949_link_FK` FOREIGN KEY (`recievedTransactionId`) REFERENCES `Transactions` (`transactionID`),
  CONSTRAINT `Transaction_8949_link_FK_1` FOREIGN KEY (`8949TransactionId`) REFERENCES `8949Transactions` (`8949TransactionID`),
  CONSTRAINT `disposition_FK` FOREIGN KEY (`dispositionTransactionId`) REFERENCES `Transactions` (`transactionID`)
) ENGINE=InnoDB AUTO_INCREMENT=2586 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);

#-- Crypto.TransactionLink definition

$query = "CREATE TABLE `TransactionLink` (
  `transactionLinkId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from` int(10) unsigned NOT NULL,
  `to` int(10) unsigned NOT NULL,
  `fee` double DEFAULT NULL,
  `type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`transactionLinkId`),
  KEY `TransferLink_FK` (`from`),
  KEY `TransferLink_FK_1` (`to`),
  KEY `TransactionLink_FK` (`type`),
  CONSTRAINT `TransactionLink_FK` FOREIGN KEY (`type`) REFERENCES `TransactionLinkTypes` (`linkTypeId`),
  CONSTRAINT `TransferLink_FK` FOREIGN KEY (`from`) REFERENCES `Transactions` (`transactionID`),
  CONSTRAINT `TransferLink_FK_1` FOREIGN KEY (`to`) REFERENCES `Transactions` (`transactionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
sqlInsert($mysqli, $query);
?>
