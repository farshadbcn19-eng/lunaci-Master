<?php
$pid = 58;
$post = get_post($pid);
if (!$post) {
	echo "ABORT: post $pid not found\n";
} elseif ($post->post_title !== 'Collections') {
	echo "ABORT: safety guard - post $pid title is \"{$post->post_title}\", expected \"Collections\"\n";
} else {
	$result = wp_delete_post($pid, true);
	if ($result) {
		echo "OK: deleted post $pid (Collections)\n";
	} else {
		echo "FAIL: wp_delete_post returned false\n";
	}
	$check = get_post($pid);
	echo "verify: post $pid now " . ($check ? "STILL EXISTS" : "gone") . "\n";
}
echo "done\n";
