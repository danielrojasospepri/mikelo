<?php require "api/comun.php"; $db = getDB(); $r = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN); foreach($r as $t) echo $t."\n"; ?>
