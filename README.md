# Cryptocurrency Database
**A Crypto Database & IRS 8949 generation tool set**

This is a quick set of PHP scripts that will create a database of your cryptocurrency transactions. Importing them from CoinTracker and Stake.tax. And assisting in creating an IRS 8949 CSV file that may be useful for tax purposes.

## Motivation
October 2023 TaxBit shutdown there consumer crypto tax reporting operations. I was unable to find a suitably alternative that could track exchange staking rewards, on chain staking transactions & purchases made with stable coins accurately and at a reasonable cost. 

## Warranty
NONE

## Prerequisites
MariaDB compatible SQL server
(optional) CSV Export from CoinTracker
(optional) CSV Export(s) from stake.tax
(optional) CSV 8949 from taxbit for previous years 

## Installation
Create a dbSecrets.php file with a single function to open a connection to your database.
See dbSecrets.example 

## Usage

### Create the database tables
php createDB.php

### Import from cointracker.com
php importCoinTracker.php <pathToExported.csv>

### Import from stake.tax
php importStakeTax.php <pathToExported.csv>

### Import from taxbit 8949
IRS 8949 reports transactions by the DAY while our imported transactions are acuate to the second. So manual adding, adjusting, swapping may be needed. 

php importOLD8949.php <pathTo8949.csv>
php update8949Links.php

### Verify 8949 transactions
php verify8949.php

### Get Staking income by year
php calcStaking.php <year>

## Generate an 8949
This will update the database so manual deletion is required to “recalculate”
php calc8949.php <year>
