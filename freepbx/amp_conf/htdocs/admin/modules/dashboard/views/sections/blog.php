<ul>
<?php
$c = 0;
if (function_exists('mb_substitute_character')) {
	mb_substitute_character(0xFFFD);
}
foreach($items as $item) {
	if($c > $limit) {
		break;
	}
	$description = $item['content'];
	if ($description) {
		if (function_exists('mb_substr')) {
			$tooltip = mb_substr(strip_tags($description,'<br>'),0,200, 'UTF-8')."...";
			mb_convert_encoding($tooltip, 'UTF-8', 'UTF-8');
		} else {
			$tooltip = substr(strip_tags($description,'<br>'),0,200)."...";
		}
	} else {
		$tooltip = "";
	}
	$href = $item['url'];
	$title = $item['title'];
	if (function_exists('mb_convert_encoding')) {
		mb_convert_encoding($title, 'UTF-8', 'UTF-8');
	}
	print "<li><small><a data-toggle='tooltip' title='$tooltip' href='$href' target='_blank'>$title</a></small></li>\n";
	$c++;
}
?>
</ul>
<script>
	$('li a').tooltip();
</script>
