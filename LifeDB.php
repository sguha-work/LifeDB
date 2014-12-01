<?php
	class LifeDB {
		// this variable will holds the database file name
		private $dbFileName;
		public function __construct($fileName="") {
			if(trim($fileName) == "") {
				$this->dbFileName = $this->getRandomFileName();
				$this->initiateEmptyFile();	
			} else {
				$this->dbFileName = $fileName;
				if(!file_exists ($this->dbFileName)) {//if the file doesnot exists in disk, creating and initiating an empty file
					$this->initiateEmptyFile();	
				}
			}
		}
		// public functions accessible by users
		public function insert($pageName, $record) { // insert a record under specified page
			return $this->initiateInsertProcess($pageName, $record);
		}
		public function destroy($willDeleteFileAlso = false) { // destroy the whole database
			$this->destroyDatabase($willDeleteFileAlso);
		}
		public function find($pageName, $attributeName="*", $query="") { // search functionality
			return json_encode($this->searchFromDatabase($pageName, $attributeName, $query));
		}
		// end of public functions accessible by users
		
		private function searchFromDatabase($pageName, $attributeName, $query) {
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			
			$attributeNameArray;
			if($attributeName != "*") {
				$attributeNameArray = json_decode($attributeName, true);
			} else {
				$attributeNameArray = array();
				array_push($attributeNameArray, "*");
			}
			if(!$this->pageExists($contentOfFile, $pageName)) {
				return false;
			} else {
				if($attributeName!="*" && !$attributeNameArray) { // provide attribute name structure is invalid
					return false;
				}
				$pageData = $contentOfFile[$pageName];
				$contentOfFile = NULL;
				return $this->gatherDataFromPage($pageData,$attributeNameArray, $query);
			}
		}
		private function gatherDataFromPage($pageData,$attributeNameArray, $query) {
			if(trim($query) == "") {//if query array is empty
				$data = $pageData;
				$pageData = NULL;
				if(count($attributeNameArray) == 1 && $attributeNameArray[0] == "*") {// if no attribute is specified
					return $data;
				} else {
					return $this->getDataFilterredByAttribute($data, $attributeNameArray);
				}
			} else { // if query array is not empty
				$data = $this->getDataFromPageFilterredByQuery($pageData, $query);
				if(count($attributeNameArray) == 1 && $attributeNameArray[0] == "*") { // if no attribute is specified
					return $data;
				} else {
					return $this->getDataFilterredByAttribute($data, $attributeNameArray);
				}
			}
		}
		// filterred the provided data based on attirubte
		private function getDataFilterredByAttribute($data, $attributeNameArray) {
			$resultArray = array();
			for($index=0; $index<count($data); $index++) {
				$chunk = array();
				$record = $data[$index];
				$recordAsJson = json_encode($record);
				for($attributeIndex=0; $attributeIndex<count($attributeNameArray); $attributeIndex++) {
					$attributeName = trim($attributeNameArray[$attributeIndex]);
					if(strrpos($recordAsJson, $attributeName) == false) {
						$chunk[$attributeName] = NULL;
					} else {
						$tempArray = explode($attributeName, $recordAsJson);
						$chunk[$attributeName] = explode(',',explode(":", $tempArray[1])[1])[0];
					}
				}
				array_push($resultArray, $chunk);
			}
			return $resultArray;
		}
		private function getDataFromPageFilterredByQuery($pageData, $query) {//checkrecords from a page and if matchfoundreturnthem
			$resultArray = array();
			for($index=0; $index<count($pageData); $index++) {
				if($this->checkRecordWithQuery($pageData[$index], $query)) {
					array_push($resultArray, $pageData[$index]);
				}
			}
			return $resultArray;
		}
		private function checkRecordWithQuery($record, $query) {// check record with query
			$queryArray = $this->splitQueryBasedOnANDoperation($query);
			$flag = 1;
			for($index=0; $index<count($queryArray); $index++) {
				if(!$this->applyORseperatedQueryOnRecord($record, trim($queryArray[$index]))) {
					$flag = 0;
					break;
				}
			}
			if($flag) {
				return true;
			} else {
				return false;
			}
		}
		private function applyORseperatedQueryOnRecord($record, $queryChunk) {
			$queryChunkArray = $this->splitQueryBasedOnORoperation($queryChunk);
			$flag = 0;
			for($index=0; $index<count($queryChunkArray); $index++) {
				if($this->applyIndividualQueryOnRecord($record, $queryChunkArray[$index])) {
					$flag = 1;
					break;
				}
			}
			if($flag) {
				return true;
			} else {
				return false;
			}	
		}
		private function applyIndividualQueryOnRecord($record, $singleQuery) {
			$separatedQuery = $this->separateQueryToAttributeNameOperatorValue($singleQuery);
			if($separatedQuery['operator'] == "@eq") {
				return $this->checkEqual($record, $separatedQuery);
			} else if($separatedQuery['operator'] == "@lt") {
				return $this->checkLessThan($record, $separatedQuery);
			} else if($separatedQuery['operator'] == "@gt") {
				return $this->checkGreterThan($record, $separatedQuery);
			} else if($separatedQuery['operator'] == "@le") {
				return $this->checkLessThanEqual($record, $separatedQuery);
			} else if($separatedQuery['operator'] == "@ge") {
				return $this->checkGreterThanEqual($record, $separatedQuery);
			} else if($separatedQuery['operator'] == "@ne") {
				return $this->checkNotEqual($record, $separatedQuery);
			}
		}
		private function getValueByAttributeNameFromRecord($attributeName, $recordJSON) {
			$value = (string)(explode(",", explode(":",explode($attributeName, $recordJSON)[1])[1])[0]);
			if($value[(strlen($value)-1)]=="}") {
				$value = substr($value, 0, -1);
			}
			return $value;
		}
		private function checkNotEqual($record, $separatedQuery) { // checking not equal
			$attributeName = $separatedQuery['attribute'];
			$value="";
			if(gettype($separatedQuery['value'])!="string") {
				$value = '"'.$separatedQuery['value'].'"';
			} else {
				$value = $separatedQuery['value'];
			}
			$separatedQuery = NULL;
			$recordJSON = json_encode($record);
			$record = NULL;
			if(strpos($recordJSON, $attributeName) == false) {// if attribute doesnot exists return false
				return false;
			} else {
				$valueFromRecord = $this->getValueByAttributeNameFromRecord($attributeName, $recordJSON);
				if(trim($valueFromRecord)!=trim($value)) { // if value from record is not equal given value return true
					return true;
				} else {
					return false;
				}
			}
		}
		private function checkGreterThanEqual($record, $separatedQuery) {
			$attributeName = $separatedQuery['attribute'];
			$value = $separatedQuery['value'];
			$separatedQuery = NULL;
			if(!is_numeric($value)) { // if given value to check is non numeric returning false
				return false;
			}
			$recordJSON = json_encode($record);
			$record = NULL;
			if(strpos($recordJSON, $attributeName) == false) {// if attribute doesnot exists return false
				return false;
			} else {
				$valueFromRecord = floatval(trim($this->getValueByAttributeNameFromRecord($attributeName, $recordJSON),"\""));
				if(is_numeric($valueFromRecord)) {
					$value = (float)$value;
					if($valueFromRecord>=$value) { // if value from record is less than given value return true
						return true;
					} else {
						return false;
					}
				} else {// if value from record is non numeric return false
					return false;
				}
			}
		}
		private function checkLessThanEqual($record, $separatedQuery) {
			$attributeName = $separatedQuery['attribute'];
			$value = $separatedQuery['value'];
			$separatedQuery = NULL;
			if(!is_numeric($value)) { // if given value to check is non numeric returning false
				return false;
			}
			$recordJSON = json_encode($record);
			$record = NULL;
			if(strpos($recordJSON, $attributeName) == false) {// if attribute doesnot exists return false
				return false;
			} else {
				$valueFromRecord = floatval(trim($this->getValueByAttributeNameFromRecord($attributeName, $recordJSON),"\""));
				if(is_numeric($valueFromRecord)) {
					$value = (float)$value;
					if($valueFromRecord<=$value) { // if value from record is less than given value return true
						return true;
					} else {
						return false;
					}
				} else {// if value from record is non numeric return false
					return false;
				}
			}
		}
		private function checkGreterThan($record, $separatedQuery) {
			$attributeName = $separatedQuery['attribute'];
			$value = $separatedQuery['value'];
			$separatedQuery = NULL;
			if(!is_numeric($value)) { // if given value to check is non numeric returning false
				return false;
			}
			$recordJSON = json_encode($record);
			$record = NULL;
			if(strpos($recordJSON, $attributeName) == false) {// if attribute doesnot exists return false
				return false;
			} else {
				$valueFromRecord = floatval(trim($this->getValueByAttributeNameFromRecord($attributeName, $recordJSON),"\""));
				if(is_numeric($valueFromRecord)) {
					$value = (float)$value;
					if($valueFromRecord>$value) { // if value from record is less than given value return true
						return true;
					} else {
						return false;
					}
				} else {// if value from record is non numeric return false
					return false;
				}
			}
		}
		private function checkLessThan($record, $separatedQuery) {
			$attributeName = $separatedQuery['attribute'];
			$value = $separatedQuery['value'];
			$separatedQuery = NULL;
			if(!is_numeric($value)) { // if given value to check is non numeric returning false
				return false;
			}
			$recordJSON = json_encode($record);
			$record = NULL;
			if(strpos($recordJSON, $attributeName) == false) {// if attribute doesnot exists return false
				return false;
			} else {
				$valueFromRecord = floatval(trim($this->getValueByAttributeNameFromRecord($attributeName, $recordJSON),"\""));
				if(is_numeric($valueFromRecord)) {
					$value = (float)$value;
					if($valueFromRecord<$value) { // if value from record is less than given value return true
						return true;
					} else {
						return false;
					}
				} else {// if value from record is non numeric return false
					return false;
				}
			}
		}
		private function checkEqual($record, $separatedQuery) {
			$attributeName = $separatedQuery['attribute'];
			$value = $separatedQuery['value'];
			$separatedQuery = NULL;
			$recordJSON = json_encode($record);
			$record = NULL;
			$stringOfAttributeAndValue = '"'.$attributeName.'":'.$value;// required if value is a string
			$string2OfAttributeAndValue = '"'.$attributeName.'":"'.$value.'"';// requirred if value is a number
			if(strpos($recordJSON, $stringOfAttributeAndValue) == false) {
				if(strpos($recordJSON, $string2OfAttributeAndValue) == false) {
					return false;
				} else {
					return true;
				}
			} else {
				return true;
			}
		}
		private function separateQueryToAttributeNameOperatorValue($singleQuery) {// pick up attribute name, value and operator from the query
			$separatedArray = array();
			if(strpos($singleQuery, "@eq") != false) { // @eq equal opearator
				$separatedArray['operator'] = "@eq";
				$splitedQuery = explode("@eq", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			} else if(strpos($singleQuery, "@lt") != false) { // @lt less than opearator
				$separatedArray['operator'] = "@lt";
				$splitedQuery = explode("@lt", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			} else if(strpos($singleQuery, "@gt") != false) { // @gt greater than operator
				$separatedArray['operator'] = "@gt";
				$splitedQuery = explode("@gt", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			} else if(strpos($singleQuery, "@le") != false) { // @le lessthan equal operator
				$separatedArray['operator'] = "@le";
				$splitedQuery = explode("@le", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			} else if(strpos($singleQuery, "@ge") != false) { // @ge greater than equal operator
				$separatedArray['operator'] = "@ge";
				$splitedQuery = explode("@ge", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			} else if(strpos($singleQuery, "@ne") != false) { // @ne not equal operator
				$separatedArray['operator'] = "@ne";
				$splitedQuery = explode("@ne", $singleQuery);
				$separatedArray['attribute'] = trim($splitedQuery[0]);
				$separatedArray['value'] = trim($splitedQuery[1]);  
			}
			return $separatedArray;
		}
		private function splitQueryBasedOnORoperation($query) {// split query based on OR operation
			$resultArray = array();
			if(strpos($query, "||") != false){
				$resultArray = explode("||", $query);
			} else {
				array_push($resultArray, $query);
			}
			return $resultArray;
		}
		private function splitQueryBasedOnANDoperation($query) {// split query based on AND operation
			$resultArray = array();
			if(strpos($query, "&&") != false){
				$resultArray = explode("&&", $query);
			} else {
				array_push($resultArray, $query);
			}
			return $resultArray;
		}
		//destroy the whole database if willDeleteFileAlso variable is set true then the db file will be deleted too
		private function destroyDatabase($willDeleteFileAlso) {
			unlink($this->dbFileName); 
			if(!$willDeleteFileAlso) {// if file should be kept then reinitiating an empty file
				$this->initiateEmptyFile();
			}
			return true;
		}
		private function initiateInsertProcess($pageName, $record) {
			$recordAsJsonObject = json_decode($record, true);
			if(!$recordAsJsonObject) { // if the record is not in valid json format returning false
				return false;
			}
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			if(!$this->pageExists($contentOfFile, $pageName)) {// if the pages doesnt exists create an empty aray for the page
				$contentOfFile[$pageName] = array();
			}
			return $this->insertIntoDatabase($contentOfFile, $pageName, $recordAsJsonObject);
		}
		// this function inser a record to the specified page of database
		private function insertIntoDatabase($contentOfFile, $pageName, $recordAsJsonObject) {
			array_push($contentOfFile[$pageName], $recordAsJsonObject);
			return $this->writeJsonStringToFile(json_encode($contentOfFile[$pageName]));
		}
		// check page exists in database or not
		private function pageExists($contentOfFile, $pageName) {
			if(isset($contentOfFile[$pageName])) {
				return true;
			} else {
				return false;
			}
		}
		// returns the content of the total db file as json string
		private function fetchTotalContentOfFileAsJsonString() {
			return file_get_contents($this->dbFileName);
		}
		// this function writes a json string in the database file
		private function writeJsonStringToFile($content) {
			$output = file_put_contents($this->dbFileName, $content);
			if(!$output) {
				return false;
			} else {
				return true;
			}
		}
		// if no database file name is specified this file will return a random file name based on availebility
		private function getRandomFileName() {
			$fileNameExtentionArray = array();
			//getting all lifedb file name in the directory
			foreach(glob('lifedb_*.json') as $fileName){
				array_push($fileNameExtentionArray, (int)explode(".",explode("_",$fileName)[1])[0]);
			}
			//if no lifedb file exits returning _1 named file
			if(count($fileNameExtentionArray) == 0) {
				return "jsondb_1.json";
			}
			//finiding an extention which is not used till
			$index = 1;
			while(1) {
				if(in_array($index, $fileNameExtentionArray)) {
					if(filesize("lifedb_" . $index.".json") > 2) {
						$index+=1;
						continue;	
					} else { //if any empty database file found selecting that database
						break;
					}
				} else {
					break;
				}
			}
			//creating the new unused file name
			$unusedNewFileName = "lifedb_" . $index.".json";
			return $unusedNewFileName;
		}
		
		private function initiateEmptyFile() {// this function initiate an empty file with an empty json object
			$result = file_put_contents ($this->dbFileName, "{}");
			if(!$result) {
				echo "cannot create a file. Permission denied";
				return false;
			}
		}
	}
?>
