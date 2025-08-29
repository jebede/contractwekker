<?php
ini_set('max_execution_time', 600);
set_time_limit(600);
if (!empty($_GET['secret']) && $_GET['secret'] == 'dsdsa-asd32189AA43213-dasadsdsa-dsaasdsa')
{
	exec('cd .. && sh sync.sh');
	echo "Done\n";
}