<div id="required_fields_message">以下列举的是所有的仓库名称，点击名称切换到相应的仓库</div>

<div style="text-align:center;padding:2em">
<?php

foreach ($locations as $location_id => $location_name) {
	echo "<p>";
	if($location_id==$user_info->location_id){
		echo $location_name."(当前位置)";
	}else{
		echo "<a href='employees/locations/$location_id' title='点击链接将切换到$location_name,并返回首页'>"
					.$location_name."</a>";
	}
	echo "</p>";
}

?>

</div>