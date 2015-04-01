<?php
	$LifeDBTempMemoryDump = array();
	class LifeDB {
		private $cache;
		private $dbFileName; // this variable will holds the database file name
		public function __construct($fileName="") { // constructor, if file name not specified a random file will be created
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
		public function insert($pageName, $record) { // insert a record under specified page return 1 or 0
			return $this->initiateInsertProcess($pageName, $record);
		}
		public function destroy($willDeleteFileAlso = false) { // destroy the whole database
			$this->destroyDatabase($willDeleteFileAlso);
		}
		public function find($pageName, $attributeName="*", $query="", $lowerLimit=-1, $upperLimit=-1) { // search functionality
			$cacheKey = $this->prepareCacheKey($pageName.$attributeName.$query);
			$data;
			if($this->keyExistsInCache($cacheKey)) {
				$data = json_decode($this->fetchFromCache($cacheKey), true);
			} else {
				$data = $this->searchFromDatabase($pageName, $attributeName, $query); 
				$this->writeToCache($cacheKey, $data);
			}
			if($lowerLimit!=-1&&$upperLimit!=-1) {
				($lowerLimit<0)?(($lowerLimit*(-1))):($lowerLimit*1);
				($upperLimit<0)?(($upperLimit*(-1))):($upperLimit*1);
				if($upperLimit < $lowerLimit) { // if upperLimit is less than lowerLimit then the value will be swaped
					$temp = $upperLimit;
					$upperLimit = $lowerLimit;
					$lowerLimit = $temp;
				}
				$data = $this->cropDataWithProvidedLimit($data, $lowerLimit, $upperLimit);
			}
			return json_encode($data);
		}
		public function update($pageName, $attributeName, $newValue, $query="") {
			return $this->updateToDatabase($pageName, $attributeName, $newValue, $query); 
		}
		public function delete($pageName, $attributeName="*", $query="") {
			return $this->initiateDeleteProcess($pageName, $attributeName, $query);
		}
		public function getPages($lowerLimit = -1, $upperLimit = -1) {
			return json_encode($this->fetchListOfPages($lowerLimit, $upperLimit));
		}
		// end of public functions accessible by users
		private function cropDataWithProvidedLimit($data, $lowerLimit, $upperLimit) { // this function crops the result based on the  limit provided
			$newDataSet = array();
			$index = -1;
			foreach($data as $chunk) {
				$index+=1;
				if($index<$lowerLimit) {
					continue;
				}
				if($index>$upperLimit) {
					break;
				}
				array_push($newDataSet, $chunk);
			}
			return $newDataSet;
		}
		private function fetchListOfPages($lowerLimit, $upperLimit) {
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			$pages= array();
			$index = -1;
			if($lowerLimit!=-1&&$upperLimit!=-1) {
				($lowerLimit<0)?(($lowerLimit*(-1))):($lowerLimit*1);
				($upperLimit<0)?(($upperLimit*(-1))):($upperLimit*1);
				if($upperLimit < $lowerLimit) { // if upperLimit is less than lowerLimit then the value will be swaped
					$temp = $upperLimit;
					$upperLimit = $lowerLimit;
					$lowerLimit = $temp;
				}
			}
			foreach($contentOfFile as $key=>$value) {
				$index++;
				if($lowerLimit!=-1&&$index<$lowerLimit) {
					continue;
				}
				if($upperLimit!=-1&&$index>$upperLimit) {
					break;
				}
				array_push($pages, $key);	
			}
			return $pages;			
		}
		private function initiateDeleteProcess($pageName, $attributeName, $query) {
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			if(!isset($contentOfFile[$pageName])) {
				return false;
			} else {
				$resultSet = array();
				if(trim($attributeName) == "*") {// if no attribute is provided the entire record will be deleted
					$contentFilteredByQuery;
					if(trim($query)!="") {
						$contentFilteredByQuery = $this->searchFromDatabase($pageName,"*", $query);
					} else {
						$contentFilteredByQuery = $contentOfFile[$pageName];
					}
					$resultSet = $this->matchAndDeleteContent($contentOfFile, $contentFilteredByQuery);// deleting the elements which will be updated and reinserted 
					return $this->writeJsonStringToFile(json_encode($resultSet));
				} else { // if attribute name is provided then only the attributes will be removed along with value
				}
			}
		}
		private function matchAndDeleteContent($mainContent, $subContent) { // destroy subcontent from main content
			$mainContentJson = json_encode($mainContent);
			foreach($subContent as $content) {
				$contentJson = json_encode($content);
				$contentJsonTemp1 = $contentJson.",";
				$contentJsonTemp2 = ",".$contentJson;
				if(strpos($mainContentJson, $contentJsonTemp1)) {
					$mainContentJson = str_replace($contentJsonTemp1, "", $mainContentJson);
					$contentJsonTemp1 = NULL;
				} else if(strpos($mainContentJson, $contentJsonTemp2)) {
					$mainContentJson = str_replace($contentJsonTemp2, "", $mainContentJson);
					$contentJsonTemp2 = NULL;
				}
				else {
					$mainContentJson = str_replace($contentJson, "", $mainContentJson);
				}
			}
			$mainContent = json_decode($mainContentJson, true);
			return $mainContent;
		}
		private function updateToDatabase($pageName, $attributeName, $newValue, $query) {
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			if(!isset($contentOfFile[$pageName])) {
				return false;
			} else {
				$contentFilteredByQuery;
				if(trim($query)!="") {
					$contentFilteredByQuery = $this->searchFromDatabase($pageName,"*", $query);
				} else {
					$contentFilteredByQuery = $contentOfFile[$pageName];
				}
				$resultSetWithoutMatchedContent = $this->matchAndDeleteContent($contentOfFile, $contentFilteredByQuery);// deleting the elements which will be updated and reinserted
				$contentOfFile = NULL;
				$updatedResultSet = $this->updateContent($contentFilteredByQuery, $attributeName, $newValue);
				foreach($updatedResultSet as $record) {// inserting the updated data to database
					array_push($resultSetWithoutMatchedContent[$pageName], $record);				
				}
				return $this->writeJsonStringToFile(json_encode($resultSetWithoutMatchedContent));// writing the uodate to database
			}
		}
		private function updateContent($contentFilteredByQuery, $attributeName, $newValue) {
			$attributeName = json_decode($attributeName, true);
			$newValue      = json_decode($newValue, true);
			$jsonContent   = json_encode($contentFilteredByQuery);
			$contentFilteredByQuery = NULL;
			$updatedContent = "";
			for($atValIndex = 0; $atValIndex<count($attributeName); $atValIndex++) {
				for($index=0; $index<strlen($jsonContent); $index++) {
					if($jsonContent[$index]==$attributeName[$atValIndex][0]) {
						$flag = 0;
						for($tempIndex=0; $tempIndex<strlen($attributeName[$atValIndex]); $tempIndex++) {
							if($jsonContent[$index+$tempIndex]!=$attributeName[$atValIndex][$tempIndex]) {
								$flag = 1;
								break;
							}
						}
						if($flag == 0) {// attribute value needs to be updated
							$updatedContent .= $attributeName[$atValIndex] . "\":" . '"'.$newValue[$atValIndex].'"';
							$tempIndex = $index;
							while($jsonContent[$tempIndex]!=','&&$jsonContent[$tempIndex]!='}'&&$jsonContent[$tempIndex]!='') {
								$tempIndex+=1;
							}
							$index = $tempIndex - 1;
						} else {
							$updatedContent .= $jsonContent[$index];
						}
					} else {
						$updatedContent .= $jsonContent[$index];
					}
					
				}
			}
			return json_decode($updatedContent, true);
		}
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
						$chunk[$attributeName] = explode(',',explode('":', $tempArray[1])[1])[0];
						$chunk[$attributeName] = trim($chunk[$attributeName],'}');
						$chunk[$attributeName] = trim($chunk[$attributeName],'\"');
						$chunk[$attributeName] = stripslashes($chunk[$attributeName]);
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
			$value = trim($separatedQuery['value'], '\"');
			$value = trim($value, '\'');
			$value = '"'.$value.'"';
			
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
			if(!is_numeric($value)) {
				$value = json_encode($value);
			}
			$separatedQuery = NULL;
			$recordJSON = json_encode($record);
			$record = NULL;
			$stringOfAttributeAndValue = '"'.$attributeName.'":"'.$value.'"'; 
			if(is_numeric($value)) {
				$stringOfAttributeAndValue2 = $stringOfAttributeAndValue . ",";
				$stringOfAttributeAndValue3 = $stringOfAttributeAndValue . "}";
				if(strpos($recordJSON, $stringOfAttributeAndValue2) == false) {
					if(strpos($recordJSON, $stringOfAttributeAndValue3) == false) {
						return false;
					} else {
						return true;
					}
				} else {
					return true;
				}
			}
			if(strpos($recordJSON, $stringOfAttributeAndValue) == false) {
				return false;
			} else {
				return true;
			}
		}
		private function separateQueryToAttributeNameOperatorValue($singleQuery) {// pick up attribute name, value and operator from the query
			$separatedArray = array();
			$firstSectionOfQuery = explode(":", $singleQuery)[0];
			if(strpos($firstSectionOfQuery, " @eq" ) != false) { // @eq equal opearator
				$separatedArray['operator'] = "@eq";
			} else if(strpos($firstSectionOfQuery, " @lt ") != false) { // @lt less than opearator
				$separatedArray['operator'] = "@lt";
			} else if(strpos($firstSectionOfQuery, " @gt ") != false) { // @gt greater than operator
				$separatedArray['operator'] = " @gt ";
			} else if(strpos($firstSectionOfQuery, " @le ") != false) { // @le lessthan equal operator
				$separatedArray['operator'] = "@le";
			} else if(strpos($firstSectionOfQuery, " @ge ") != false) { // @ge greater than equal operator
				$separatedArray['operator'] = "@ge";
			} else if(strpos($firstSectionOfQuery, " @ne ") != false) { // @ne not equal operator
				$separatedArray['operator'] = "@ne";
			}
			$separatedArray['attribute'] = $this->getAttributeNameFromQuery($singleQuery);
			$separatedArray['value'] = $this->getValueFromQuery($singleQuery);  
			return $separatedArray;
		}
		private function getAttributeNameFromQuery($singleQuery) {
			$attributeName = "";
			for($counter=0; $counter<strlen($singleQuery); $counter++) {
				if($singleQuery[$counter] != " ") {
					$attributeName .= $singleQuery[$counter];
				} else {
					break;
				}
			}
			return $attributeName;
		}
		private function getValueFromQuery($singleQuery) {
			$value = "";
			$counter = 0;
			while($singleQuery[$counter] != ":") {
				$counter++;
			}
			for($counter2=$counter+1; $counter2<strlen($singleQuery); $counter2++) {
				$value .= $singleQuery[$counter2];
			}
			return $value;
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
			$recordAsJsonObject = $this->addDefaultInfo($recordAsJsonObject);
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			if(!$this->pageExists($contentOfFile, $pageName)) {// if the pages doesnt exists create an empty aray for the page
				$contentOfFile[$pageName] = array();
			}
			return $this->insertIntoDatabase($contentOfFile, $pageName, $recordAsJsonObject);
		}
		// this function inser a record to the specified page of database
		private function insertIntoDatabase($contentOfFile, $pageName, $recordAsJsonObject) {
			if(isset($recordAsJsonObject[0])) {
				for($index=0; $index<count($recordAsJsonObject); $index++) {
					array_push($contentOfFile[$pageName], $recordAsJsonObject[$index]);	
				}	
			} else {
				array_push($contentOfFile[$pageName], $recordAsJsonObject);	
			}
			return $this->writeJsonStringToFile(json_encode($contentOfFile));
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
		private function addDefaultInfo($record) {
			if(isset($record[0])) {
				for($index=0; $index<count($record); $index++) {
					$presentTimeStamp = time();
					$record[$index]['_id'] = $presentTimeStamp;
					$record[$index]['_addedOn'] = $presentTimeStamp; 
				}
			} else {
				$presentTimeStamp = time();
				$record['_id'] = $presentTimeStamp;
				$record['_addedOn'] = $presentTimeStamp;
			}
			return $record;
		}
		private function keyExistsInCache($key) {
			$presentTimeStamp = time();
			$LifeDBTempMemoryDump = json_decode($this->cache, true);
			if(!isset($LifeDBTempMemoryDump[$key])) {
				return false;
			}
			$cachedDataTotal = $LifeDBTempMemoryDump[$key];
			$cacheTime = $cachedDataTotal['CACHE_CREATE_TIME'];
			if(($presentTimeStamp - $cacheTime)>20000) {
				return false;
			} else {
				return true;
			}
		}
		private function fetchFromCache($key) {
			$LifeDBTempMemoryDump = json_decode($this->cache, true);
			return $LifeDBTempMemoryDump[$key]['DATA'];
		}
		private function writeToCache($cacheKey, $data) {
			$dataSet = array();
			$dataSet['CACHE_CREATE_TIME'] = time();
			$dataSet['DATA'] = json_encode($data);
			$LifeDBTempMemoryDump = json_decode($this->cache, true);
			$LifeDBTempMemoryDump[$cacheKey] = $dataSet;
			$this->cache = json_encode($LifeDBTempMemoryDump);
			$this->cleanupCache();
		}
		private function cleanupCache() {
			$presentTimeStamp = time();
			$cachedDataTotal = json_decode($this->cache, true);
			foreach(array_keys($cachedDataTotal) as $key) {
				if(($presentTimeStamp-$cachedDataTotal[$key]['CACHE_CREATE_TIME'])>100000) {
					unset($cachedDataTotal[$key]);
				}
			}
			$this->cache = json_encode($cachedDataTotal);
		}
		private function prepareCacheKey($tempKey) {
			$newKey = str_replace('"','_',$tempKey);
			$newKey = str_replace('{','_',$newKey);
			$newKey = str_replace('}','_',$newKey);
			$newKey = str_replace('[','_',$newKey);
			$newKey = str_replace(']','_',$newKey);
			$newKey = str_replace('@','_',$newKey);
			$newKey = str_replace(' ','_',$newKey);
			$newKey = str_replace(',','_',$newKey);
			$newKey = str_replace(';','_',$newKey);
			$newKey = str_replace('!','_',$newKey);
			$newKey = str_replace('#','_',$newKey);
			$newKey = str_replace('$','_',$newKey);
			$newKey = str_replace('^','_',$newKey);
			$newKey = str_replace('&','_',$newKey);
			$newKey = str_replace('*','_',$newKey);
			$newKey = str_replace('(','_',$newKey);
			$newKey = str_replace(')','_',$newKey);
			return $newKey;
		}
	}
?>