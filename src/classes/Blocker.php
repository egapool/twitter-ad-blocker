<?php

Class Blocker
{
	public $user = null;

	public $TwitterOAuthr = null;

	public function __construct($dbh, $user, $TwitterOAuth)
	{
		$this->dbh 	= $dbh;
		$this->user = $user;
		$this->TwitterOAuth = $TwitterOAuth;
	}

	public function blockAll()
	{
		$user_id = $this->user['id'];
		$blockList = $this->getBlockList($user_id);

		if ( empty($blockList) ) return;

		foreach ( $blockList as $account ) {
			$this->block($user_id, $account['screen_name']);
		}
	}

	public function block($user_id,$block_account)
	{
		// do block
		$this->TwitterOAuthr->post("blocks/create",["screen_name" => $block_account['screen_name']]);

		// loggin
		echo 'blocked ' . $block_account['screen_name'] . "\n";

	}

	/**
	 *
	 */
	public function getBlockList($user_id)
	{
		$sql = "SELECT * FROM `ad_accounts`" . PHP_EOL;
		$sql .= "WHERE id NOT IN (" . PHP_EOL;
		$sql .= "	SELECT ad_id FROM `block_logs` WHERE user_id = :user_id" . PHP_EOL;
		$sql .= ")" . PHP_EOL;
		$sth = $this->dbh->prepare($sql);
		$sth->bindValue(':user_id', $user_id, PDO::PARAM_STR);
		$sth->execute();
		$blockList = $sth->fetchAll(PDO::FETCH_ASSOC);

		return $blockList;
	}

	public function logging($user_id,$ad_id)
	{

	}
}