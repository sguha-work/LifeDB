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
		public function find($pageName, $attribute="*", $query="") {
			return $this->getDataFromDatabase($pageName, $attribute, $query);
		}

		// this function will be called by the user to update data
		public function update($pageName, $attributeName, $query, $options) {

		}

		// this function will be called by the user to dalete data
		public function delete($pageName, $attributeName, $query) {

		}		
		
		/**
		* private functions
		*/

		private function getDataFromDatabase($pageName, $attribute, $query) {
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
					return $outputJson;
				}
			}
		}

		private function createJSONDataFromSpecifiedQueryAndAttribute($pageData,$queryObject, $attributeObject) {
			$outputJson = "[";
			foreach($pageData as $record) {
				if($this->match($record, $queryObject)) {//if the query matches going for the attribute
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
			if($outputJson!="[") {
				$outputJson = substr_replace($outputJson, "", -1);
			}
			$outputJson.="]";
			echo $outputJson;
			return $outputJson;
		}

		// if the record matches the query the function returns true else false
		private function match($record, $queryObject) {
			$flag = 0;
			foreach($queryObject as $query) {
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
			}
			
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
  	$instance = new LifeDB("jsondb_1.json");
  	// $instance->insert("student","{\"name\":\"arindam\",\"title\":\"karmokar\"}");
  	// $instance->insert("student","{\"name\":\"piklu\"}");
  	// $instance->insert("teacher","{\"name\":\"shyamal\"}");
  	// $instance->insert("teacher","{\"name\":\"aritrik\"}");
  	// $instance->insert("student","[{\"name\":\"arindam1\"},{\"name\":\"piklu1\"}]");
  	//$instance->find("student", "*", "");
 	//$instance->find("student", "[\"name\",\"title\",\"class\"]", "");
 	$instance->find("student", "[\"name\",\"class\"]", "[\"name @eq xarindam\"]");	
 	//$instance->find("student", "*", "[\"name @eq arindam\"]");	
?>
