<?php
  include 'LifeDB.php';
?>

<?php
  $time1 = microtime(true);
	$instance = new LifeDB("fiddleData_new.js");
  	//echo $instance->find("FiddleToCategory", "*", "");
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\"]", "");
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\"]", "[\"category_id @eq 1\", \"category_id @eq 13\"]", 0, 2);
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\",\"fiddlex_id\"]", "category_id @eq 1");
  	//echo $instance->find("FiddleToCategory","[\"fiddle_id\",\"category_id\"]","category_id @eq 17");
  	//echo $instance->update("FiddlesData","fiddle_thumb", "x.png", "fiddle_id @le 4");
  	//echo $instance->insert("Student","[{\"name\":\"angshu\",\"age\":\"26\"},{\"name\":\"piklu\",\"age\":\"27\"}]");
  	//$fiddleLink = json_decode($instance->find("FiddlesData", "[\"fiddle_new_link\", \"fiddle_id\"]", "fiddle_id @eq :1"), true);
    
    //$time2 = microtime(true);
    //echo "fetch started at ".$time1." fetch finished at ".$time2." total time taken ".($time2-$time1)."</br>";
    // foreach($fiddleLink as $fiddle) {
    //   echo $fiddle["fiddle_new_link"]."</br>";
    // }

    //echo $instance->delete('FiddlesData', '*', "fiddle_id @eq :1");
    //echo $instance->find("ChildCategoryData", "[\"cat_id\"]","cat_name @eq :Chart");
  //echo $instance->find("FiddlesData", "[\"fiddle_id\"]");
  echo $instance->getPages(3,0);
?>
