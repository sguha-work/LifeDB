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
		public function find($pageName, $data="", $query="") {
			return $this->getDataFromDatabase($pageName, $data, $query);
		}
		/**
		* private functions
		*/

		private function getDataFromDatabase($pageName, $data, $query) {
			$fileContent = $this->fetchTotalContentOfFileAsJson();
			$jsonDataFromFile = json_decode($fileContent, true);
			$fileContent = NULL;
			$pageData = $jsonDataFromFile[$pageName];
			$jsonDataFromFile = NULL;
			$pageDataJson = json_encode($pageData);
			$pageData = NULL;
			$this->searchForData($pageDataJson, $data);
		}

		private function searchForData($pageDataJson, $data) {
			$data = null;
			for($index=0; $index<strlen($pageDataJson); $index+=) {

			}
		}

		private function writeDataToFile($pageName, $jsonData) {
			$fileContent = $this->fetchTotalContentOfFileAsJson();
			$jsonDataFromFile = json_decode($fileContent);
			$fileContent = NULL;
			$inputJsonData = json_decode($jsonData);
			if(!isset($jsonDataFromFile->$pageName)) {//if the record is not present creating an empty array for the record
				$jsonDataFromFile->$pageName = array();
				if(is_array($inputJsonData)) {
					foreach($inputJsonData as $data) {
						array_push($jsonDataFromFile->$pageName, $data);	
					}
				} else {
					array_push($jsonDataFromFile->$pageName, $inputJsonData);
				}
			} else {
				if(is_array($inputJsonData)) {
					foreach($inputJsonData as $data) {
						array_push($jsonDataFromFile->$pageName, $data);	
					}
				} else {
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
  $instance->find("student","{\"name\":\"arindam\"}")
?>
