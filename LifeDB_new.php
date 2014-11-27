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
		public function find($pageName, $query="") { // search functionality
			return $this->searchFromDatabase($pageName, $query);
		}
		// end of public functions accessible by users

		
		private function searchFromDatabase($pageName, $query) {
			$contentOfFile = json_decode($this->fetchTotalContentOfFileAsJsonString(), true);
			$queryArray;
			if($query!="") {
				$queryArray = json_decode($query, true);
			}
			if(!$this->pageExists($contentOfFile, $pageName)) {
				return false;
			} else {
				if($query!=""&&!$queryArray) {
					return false;
				}
				$pageData = $contentOfFile[$pageName];
				$contentOfFile = NULL;
				return $this->gatherDataFromPage($pageData, $queryArray);
			}
		}

		private function gatherDataFromPage($pageData, $queryArray) {
			
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

		// this function initiate an empty file with an empty json object
		private function initiateEmptyFile() {
			$result = file_put_contents ($this->dbFileName, "{}");
			if(!$result) {
				echo "cannot create a file. Permission denied";
				return false;
			}
		}
	}
?>
