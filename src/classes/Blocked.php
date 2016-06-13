<?php

class Blocked
{
	private $dbh;

	public function __construct($dbh)
	{
		$this->dbh = $dbh;
	}

	public function getBlockedAccounts($user_id)
	{
		$sql = "SELECT a.* FROM block_logs as l" . PHP_EOL;
		$sql .= "inner join ad_accounts as a ON l.ad_id = a.id" . PHP_EOL;
		$sql .= "where l.user_id = :user_id" . PHP_EOL;
		$sql .= "order by created_at DESC" . PHP_EOL;
		$sql .= "LIMIT 100" . PHP_EOL;
		$sth = $this->dbh->prepare($sql);
		$sth->bindValue(':user_id', $user_id, PDO::PARAM_STR);
		$sth->execute();
		$blocks = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $blocks;
	}
}