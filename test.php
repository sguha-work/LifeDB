<?php
  include 'LifeDB.php';
?>

<?php
  	$instance = new LifeDB("fiddleData_new.js");
  	//echo $instance->find("FiddleToCategory", "*", "");
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\"]", "");
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\"]", "[\"category_id @eq 1\", \"category_id @eq 13\"]", 0, 2);
  	//echo $instance->find("FiddleToCategory", "[\"fiddle_id\",\"fiddlex_id\"]", "category_id @eq 1 && fiddle_id @ne 2");
  	echo $instance->find("FiddleToCategory","[\"fiddle_id\",\"category_id\"]","category_id @eq 17");
  	//echo $instance->update("FiddlesData","fiddle_thumb", "x.png", "fiddle_id @le 4");
?>
