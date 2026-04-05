<?php require "api/comun.php"; $d=getDB(); foreach($d->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) echo $t."\n"; ?>
