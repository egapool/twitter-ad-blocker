<?php

Class Blocker
{
	public $user = null;

	public $TwitterOAuth = null;

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
			$this->block($user_id, $account);
		}
	}

	public function block($user_id,$block_account)
	{
		// do block
		$this->TwitterOAuth->post("blocks/create",["screen_name" => $block_account['screen_name']]);

		// logging
		$this->logging($user_id, $block_account['id']);
	}

	/**
	 *
	 */
	private function getBlockList($user_id)
	{
		$sql = "SELECT * FROM `ad_accounts`" . PHP_EOL;
		$sql .= "WHERE id NOT IN (" . PHP_EOL;
		$sql .= "	SELECT ad_id FROM `block_logs` WHERE user_id = :user_id" . PHP_EOL;
		$sql .= ")" . PHP_EOL;
		$sth = $this->dbh->prepare($sql);
		$sth->bindValue(':user_id', $user_id, PDO::PARAM_STR);
		$sth->execute();
		$blockList = $sth->fetchAll(PDO::FETCH_ASSOC);

		$friends = $this->getFriends($user_id);

		foreach ( $blockList as $key => $block ) {

			if ( isset($friends[$block['id']]) ) {
				unset($blockList[$key]);
			}
		}

		return $blockList;
	}

	private function logging($user_id,$ad_id)
	{
		$sql = "INSERT INTO `block_logs` (user_id,ad_id,created_at) VALUES (:user_id, :ad_id, :now)";
		$sth = $this->dbh->prepare($sql);
		$sth->bindValue(':user_id', $user_id, PDO::PARAM_STR);
		$sth->bindValue(':ad_id', $ad_id, PDO::PARAM_STR);
		$sth->bindValue(':now', time(), PDO::PARAM_STR);
		$sth->execute();
	}

	private function getFriends($user_id)
	{
		$output = [];
		$res = $this->TwitterOAuth->get("friends/ids",["count" => 5000]);

        foreach ( $res->ids as $val ) {
        	$output[$val] = '';
        }
        return $output;
	}
}