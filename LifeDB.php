<?php
	class LifeDB {
		
		/**
		* private variables
		*/
		
		private $dbFileName;

		/**
		* constructor
		*/
		
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

		// this function will be used by user to insert data in page
		public function insert($pageName, $jsonData) {
			return $this->writeDataToFile($pageName, $jsonData);
		}

		// this function will be used by user to get any data from page, for multiple data array will be returned,object for single data, 0 for search failed
		public function find($pageName, $attribute="*", $query="", $startIndex=0, $numberOfData=-1) {
			return $this->getDataFromDatabase($pageName, $attribute, $query, $startIndex, $numberOfData);
		}

		// this function will be called by the user to update data
		public function update($pageName, $attributeName, $query, $options) {

		}

		// this function will be called by the user to dalete data
		public function deletePage($pageName) {
			return $this->deleteSpecifiedPageFromDatabase($pageName, $isHardDelete=false);
		}

		public function deleteRecord($pageName, $query="") {
			return $this->deleteSpecifiedRecordFromDatabase($pageName, $query);
		}
		
		// requirred to destroy the database
		public function destroyDb($willDeleteFileAlso=false) {
			return $this->destroyDatabase($willDeleteFileAlso);
		}		
		
		/**
		* private functions
		*/

		// this function deletes specied records based on the query
		private function deleteSpecifiedRecordFromDatabase($pageName, $query) {
			if(trim($query) == "") { // if query not specified total page will be removed
				$this->deleteSpecifiedPageFromDatabase($pageName, false);
			} else {
				$fileContent = json_decode($this->fetchTotalContentOfFileAsJson());
				$pageContent = $fileContent[$pageName];
				$fileContent = NULL;
				$queryObject = json_decode($query);
				$newContent = array();
				foreach($pageContent as $record) {
					if(!$this->match($record, $queryObject)) {// if the record didn't match with query keeping the record
						array_push($newContent, $record);
					}
				}
				$fileContent = json_decode($this->fetchTotalContentOfFileAsJson());
				$fileContent[$pageName] = $newContent;
				return file_put_contents ($this->dbFileName, json_encode($fileContent)); 
			}
		}

		private function deleteSpecifiedPageFromDatabase($pageName, $isHardDelete) {
			$databaseContent = json_decode($this->fetchTotalContentOfFileAsJson());
			if(!isset($databaseContent[$pageName])) {
				return false;
			} else {
				if($isHardDelete) {
					$newContent="{";
					foreach($databaseContent as $page) {
						$newContent .= json_encode($page);
						$newContent .= ",";
					}
					$newContent = substr_replace($newContent, "", -1);
					$newContent .= "}";
					return file_put_contents ($this->dbFileName, $newContent); 
				} else {
					$databaseContent[$pageName] = null;
					return true;
				}
			}
			
		}

		//this function destroys a database. Destroyed database cannot be restored
		private function destroyDatabase($willDeleteFileAlso) {
			unlink($this->dbFileName); 
			if(!$willDeleteFileAlso) {// if file should be kept then reinitiating an empty file
				$this->initiateEmptyFile();
			}
			return true;
		}

		private function getDataFromDatabase($pageName, $attribute, $query, $startIndex, $numberOfData) {
			$fileContent = $this->fetchTotalContentOfFileAsJson();
			$jsonDataFromFile = json_decode($fileContent, true);//* file's json data total
			$fileContent = NULL;

			$pageData = $jsonDataFromFile[$pageName];
			$jsonDataFromFile = NULL;
			$pageDataJson = json_encode($pageData);// specified page's data(json encoded)
			$pageData = NULL;
			
			if(trim($query) == "") {
				if(trim($attribute) == "*") { // if * is given in place of attribute name all dta will be returned
					return $pageDataJson;
				} else {
					$attributeObject = json_decode($attribute);
					if(!$attributeObject) { // if provided attribute is not a valid json dta returning false
						return false;
					} else {
						$attribute = NULL;
						$pageData = json_decode($pageDataJson, true);
						$pageDataJson = NULL;
						$outputJson = $this->createJSONDataFromSpecifiedAttribute($pageData, $attributeObject);
						$pageData = null;
						return $outputJson;
						
					}
				}
			} else {
				$queryObject = json_decode($query, true);
				if(!$queryObject) { // if query is not a valid json returning false
					return false;
				} else {
					$query = NULL;
					$pageData = json_decode($pageDataJson, true);
					$pageDataJson = NULL;
					$attributeObject = "*";
					if(trim($attribute) != "*") {
						$attributeObject = json_decode($attribute);
						if(!$attributeObject) { // returning false if attribute is not valid json
							return false;
						}
					}
					$outputJson = $this->createJSONDataFromSpecifiedQueryAndAttribute($pageData,$queryObject, $attributeObject);
					if($numberOfData != -1) {
						$outputJson = $this->pickChunkOfDataFromAllData($outputJson, $startIndex, $numberOfData);
					}
					return $outputJson;
				}
			}
		}

		private function pickChunkOfDataFromAllData($data, $startIndex, $numberOfData) {
			$dataArray = json_decode($data, true);
			$index = -1;
			$newDataArray = array();
			foreach($dataArray as $record) {
				$index++;
				if($index<$startIndex) {
					continue;
				}
				if($index>($startIndex+$numberOfData-1)) {
					break;
				}
				array_push($newDataArray, $record);
			}
			return json_encode($newDataArray);
		}

		private function createJSONDataFromSpecifiedQueryAndAttribute($pageData,$queryObject, $attributeObject) {
			$outputJson = "[";
			foreach($queryObject as $query) {
				foreach($pageData as $record) {
					if($this->match($record, $query)) {//if the query matches going for the attribute
						if($attributeObject == "*") {
							$outputJson.=json_encode($record).",";
						} else {
							$singleObject = "{";
							foreach($attributeObject as $attribute) {
								if(!isset($record[$attribute]) || $record[$attribute] == NULL) {
									$singleObject .= '"'.$attribute.'":null';
								} else {
									$singleObject .= '"'.$attribute.'":"'.$record[$attribute].'"';
								}
								$singleObject .= ",";
							}
							$singleObject = substr_replace($singleObject, "", -1);
							$singleObject.="}";
							$outputJson.=$singleObject.",";			
						}
					} else {
						continue;
					}
				}
			}
			if($outputJson!="[") {
				$outputJson = substr_replace($outputJson, "", -1);
			}
			$outputJson.="]";
			return $outputJson;
		}

		// if the record matches the query the function returns true else false
		private function match($record, $query) {
			$flag = 0;
			//foreach($queryObject as $query) {
				$operand = $this->getOperandFromQuery($query);
				$queryArray = explode($operand, $query);
				$attributeName = trim($queryArray[0]);
				$value         = trim($queryArray[1]);
				if(!isset($record[$attributeName])||$record[$attributeName]==NULL) {
					return false;
				} else {
					$receievedValueOfRecord = $record[$attributeName];
					return $this->makeOperation($receievedValueOfRecord, $value, $operand);
				}
			//}
			
		}

		// this function take the values from given and from record and perform the operation based on the operand
		private function makeOperation($receievedValueOfRecord, $value, $operand) {
			if($operand === "@eq" ) {
				if($receievedValueOfRecord === $value) {
					return true;
				} else {
					return false;
				}
			}
		}

		//this function find out which operator exists in the query
		private function getOperandFromQuery($query) {
			$operators = array('@eq', '@gt', '@lt', '@ge', '@le');
			$selectedOpearator = "";
			$query = strtolower($query);
			foreach($operators as $opeartor) {
				if(strpos($query, $opeartor) !== false) {
					return $opeartor;
				}
			}
			return false;
		}

		private function createJSONDataFromSpecifiedAttribute($pageData, $attributeObject) {
			$outputJson = "[";
			foreach($pageData as $record) {
				$singleObject = "{";
				foreach($attributeObject as $attribute) {
					if(!isset($record[$attribute]) || $record[$attribute] == NULL) {
						$singleObject .= '"'.$attribute.'":null';
					} else {
						$singleObject .= '"'.$attribute.'":"'.$record[$attribute].'"';
					}
					$singleObject .= ",";
				}
				$singleObject = substr_replace($singleObject, "", -1);
				$singleObject .= "},";
				$outputJson .= $singleObject;
			}
			$outputJson = substr_replace($outputJson, "", -1);
			$outputJson .= "]";
			return $outputJson; // returning json encoded string as output
		}

		private function writeDataToFile($pageName, $jsonData) {
			$fileContent = $this->fetchTotalContentOfFileAsJson();
			$jsonDataFromFile = json_decode($fileContent);
			$fileContent = NULL;
			$inputJsonData = json_decode($jsonData);
			if(!isset($jsonDataFromFile->$pageName)) {//if the record is not present creating an empty array for the record
				$jsonDataFromFile->$pageName = array();
				if(is_array($inputJsonData)) {
					$index = 0;
					foreach($inputJsonData as $data) { 
						$data->_id = $index;
						$data->_createdAt = time();
						array_push($jsonDataFromFile->$pageName, $data);	
						$index += 1;
					}
				} else {
					$inputJsonData->_createdAt = time();
					$inputJsonData->_id = 0;
					array_push($jsonDataFromFile->$pageName, $inputJsonData);
				}
			} else {
				if(is_array($inputJsonData)) {
					$index = count($jsonDataFromFile->$pageName);
					foreach($inputJsonData as $data) {
						$data->_id = $index;
						$data->_createdAt = time();
						array_push($jsonDataFromFile->$pageName, $data);	
						$index += 1;
					}
				} else {
					$index = count($jsonDataFromFile->$pageName);
					$inputJsonData->_id = $index;
					$inputJsonData->_createdAt = time();
					array_push($jsonDataFromFile->$pageName, $inputJsonData);
				}
			}
			file_put_contents($this->dbFileName, json_encode($jsonDataFromFile)) ;
			return 1;
		}

		// returns the content of the total db file
		private function fetchTotalContentOfFileAsJson() {
			return file_get_contents($this->dbFileName);
		}

		private function initiateEmptyFile() {
			$result = file_put_contents ($this->dbFileName, "{}");
			if(!$result) {
				echo "cannot create a file. Permission denied";
				return false;
			}
		}

		private function getRandomFileName() {
			$fileNameExtentionArray = array();
			
			//getting all jsondb file name in the directory
			foreach(glob('jsondb_*.json') as $fileName){
				array_push($fileNameExtentionArray, (int)explode(".",explode("_",$fileName)[1])[0]);
			}
			
			//if no json db file exits returning _1 named file
			if(count($fileNameExtentionArray) == 0) {
				return "jsondb_1.json";
			}

			//finiding an extention which is not used till
			$index = 1;
			while(1) {
				if(in_array($index, $fileNameExtentionArray)) {
					if(filesize("jsondb_" . $index.".json") > 2) {
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
			$unusedNewFileName = "jsondb_" . $index.".json";
			return $unusedNewFileName;
		}

	}
?>

<?php
  	$instance = new LifeDB("fiddleData_new.js");
  	//echo $instance->find("FiddleToCategory", "*", "");
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\"]", "");
  	echo $instance->find("FiddleToCategory", "*", "[\"category_id @eq 1\", \"category_id @eq 13\"]", 0, 2);

  	// $instance->insert("student","{\"name\":\"arindam\",\"title\":\"karmokar\"}");
  	// $instance->insert("student","{\"name\":\"piklu\"}");
  	// $instance->insert("teacher","{\"name\":\"shyamal\"}");
  	// $instance->insert("teacher","{\"name\":\"aritrik\"}");
  	// $instance->insert("student","[{\"name\":\"arindam1\"},{\"name\":\"piklu1\"}]");
  	//$instance->find("student", "*", "");
 	//$instance->find("student", "[\"name\",\"title\",\"class\"]", "");
 	//$instance->find("student", "[\"name\",\"class\"]", "[\"name @eq xarindam\"]");	
 	//$instance->find("student", "*", "[\"name @eq arindam\"]");	
?>
