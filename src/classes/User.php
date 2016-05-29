<?php

class User
{
	private $dbh;

	public function __construct($dbh)
	{
		$this->dbh = $dbh;
	}

	public function findByTwId($twitter_id)
	{
		$sql = "SELECT * FROM `users` WHERE twitter_id = :twitter_id";
		$sth = $this->dbh->prepare($sql);
		$sth->bindParam(':twitter_id', $twitter_id, PDO::PARAM_INT);
		$sth->execute();
		$user = $sth->fetch(PDO::FETCH_ASSOC);

		if ( empty($user) ) return [];

		return [
			'twitter_id' => $user['twitter_id'],
			'screen_name' => $user['screen_name'],
			'name' => $user['name'],
			'icon' => $this->getSrcStrFromBinaryImage($user['icon']),
		];
	}

	public function findAllUsers()
	{
		$sql = "SELECT * FROM `users`";
		$sth = $this->dbh->query($sql);
		$users = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $users;
	}

	/**
	 * login
	 * @param array
	 */
	public function login($twitter_id, $screen_name, $name, $icon_url, $access_token,$access_token_secret)
	{
		$icon_url = str_replace("normal", "400x400", $icon_url);
		$icon = chunk_split(base64_encode(file_get_contents($icon_url)));
		if ( !$this->is_exist($twitter_id) ) {
			// do insert
			$sql = "INSERT INTO `users` (twitter_id,screen_name,name,icon,access_token,access_token_secret,created_at,updated_at) VALUES (:twitter_id, :screen_name, :name, :icon,:access_token, :access_token_secret,:now, :now);";
			$sth = $this->dbh->prepare($sql);
		} else {
			// do update
			$sql = "UPDATE `users` SET screen_name = :screen_name, name = :name, icon = :icon,access_token = :access_token,access_token_secret = :access_token_secret, updated_at = :now WHERE twitter_id = :twitter_id;";
			$sth = $this->dbh->prepare($sql);
		}
		$sth->bindParam(':twitter_id', $twitter_id, PDO::PARAM_INT);
		$sth->bindParam(':screen_name', $screen_name, PDO::PARAM_STR);
		$sth->bindParam(':name', $name, PDO::PARAM_STR);
		$sth->bindParam(':icon', $icon, PDO::PARAM_STR);
		$sth->bindParam(':access_token', $access_token, PDO::PARAM_STR);
		$sth->bindParam(':access_token_secret', $access_token_secret, PDO::PARAM_STR);
		$sth->bindValue(':now', time(), PDO::PARAM_INT);
		$sth->execute();

		$_SESSION['twitter_id'] = $twitter_id;
		session_regenerate_id(true);

		return $this->findByTwId($twitter_id);

	}

	private function is_exist($twitter_id)
	{
		$user = $this->findByTwId($twitter_id);
		return !empty($user);
	}

	private function getSrcStrFromBinaryImage($image)
	{
		$mime = '';
		if (strncmp("\x89PNG\x0d\x0a\x1a\x0a", $image, 8) == 0) {
			$mime = 'image/png';
		} else if (strncmp('BM', $image, 2) == 0) {
			$mime = 'image/bmp';
		} else if (strncmp('GIF87a', $image, 6) == 0 || strncmp('GIF89a', $image, 6) == 0) {
			$mime = 'image/gif';
		} else if (strncmp("\xff\xd8", $image, 2) == 0) {
			$mime = 'image/jpeg';
		} else {
			$mime = NULL;
		}
		// var_dump($mime);die;
		return "data:image/png;base64,".$image;
		return "data:".$mime.";base64,".$image;
	}
}