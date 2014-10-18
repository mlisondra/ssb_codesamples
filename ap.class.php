
<?php

class News extends SimplePie{
	
	public $url;
	public $cdt;
	public $news_table = "rss_stored_2";
	private $db_conn = NULL;
	private $pie = NULL;
	public $rss_info;
	public $author_log = 'author_extraction_log';
	public $authors = 'news_authors';
	public $authors_media = 'media_author';

	public function __construct($db_conn = NULL){
		$this->db_conn = $db_conn;
		$this->pie = new SimplePie(); //create instance for SimplePie
		
	}
	/**
	* This just passes arguments to method fetch_rss_data
	* for backward compatability with current data fetching
	*/
	public function getRSS($rss){
		$this->rss_info = $rss;
		$this->get_rss($rss);
	}
	
	/**
	* Get articles for given RSS feed/url
	* @param array $rss - Contains keys for url,level1_name,level2_name
	*/
	public function get_rss(){
		extract($this->rss_info);
		$this->fetch_rss($rss_url);
	}
	
	/**
	* Retrieve rss
	* uses SimplePie
	*/
	private function fetch_rss($rss_url){
		$this->pie->set_feed_url($rss_url);
		$this->pie->enable_cache(false);
		$this->pie->init();
		$this->pie->handle_content_type();

		$level1_name = $this->rss_info['level1_name'];
		$level2_name = $this->rss_info['level2_name'];
		
		$max_pub_date = $this->get_max_pubdate($level1_name,$level2_name);
			
		foreach ($this->pie->get_items() as $item) {
			
			$title = $item->get_title();
			$link = $item->get_permalink();
			$description = $item->get_description();
			$pubDate = $item->get_date('Y-m-d H:i:s');	
			//$level1_name = $this->rss_info['level1_name'];
			//$level2_name = $this->rss_info['level2_name'];
		
			//$max_pub_date = $this->get_max_pubdate($level1_name,$level2_name);
			
			if ($pubDate > $max_pub_date) { //check to make sure that current item's publish date is more current than the most current news item in datastore
				echo $title."<br/>";
				if ($level1_name == 'google') {
					$link = preg_replace('/^.*?&amp;url=/', '', $link);
				}
			
				$item_info = array("title"=>$title,"link"=>$link,"description"=>$description,"pubDate"=>$pubDate);
				$this->insert_data($item_info);
			}
			
		}
	}
	
	/**
	* Insert data into database
	* requires database connection and array of data
	* @param resource $db_conn - Database connection
	* @param array $data - Data to be stored
	*/
	public function insert_data($item_info){
		extract($item_info);
		$random_pub_date = date('Y-m-d H:i:s', strtotime('- '.rand(0,15).' minute', strtotime($pubDate)));
		
		$query = "INSERT INTO `".$this->news_table."` (`createdon`,`title`,`level1_name`,`level2_name`,`link`,`description`,`pubDate`,`pubDateReal`) 
			VALUES (NOW(),:title,:level1_name,:level2_name,:link,:description,:pubDate,:pubDateReal)";
		$stmt = $this->db_conn->prepare($query);
		$stmt->bindValue(':title', $title);
		$stmt->bindValue(':level1_name', $this->rss_info['level1_name']);
		$stmt->bindValue(':level2_name', $this->rss_info['level2_name']);
		$stmt->bindValue(':link', $link);
		$stmt->bindValue(':description', $description);
		$stmt->bindValue(':pubDate', $random_pub_date);
		$stmt->bindValue(':pubDateReal', $pubDate);
		$count = $stmt->execute();
		//Possibly call the send_notification method here in the event of an error
	}
	
	
	/**
	* Get data from Alchemy service
	* @param array $news - This is the news article originally retrieved from raw RSS
	*
	*/
	//public function get_alchemy($data_type = 'sentiment',$news_url){
	public function get_alchemy($news){
		extract($news);
		$temp_array = "";
		$alchemy_sentiment_type = '';
		$alchemy_sentiment_score = '';
		
		$temp_array['stored_sysid'] =  $stored_sysid;
		
		//Get the sentiment information
		$url = 'http://ieatyourbrain.com/apis/sent/?call='.urlencode('/url/URLGetTextSentiment?outputMode=json&url='.urlencode($link));		
		$alch = $this->get_curl_data($url);
		
		if (isset($alch['docSentiment'])) {
			if(isset($alch['docSentiment']['type'])){
				$alchemy_sentiment_type = $alch['docSentiment']['type'];
			}else{
				$alchemy_sentiment_type = '';
			}
			if(isset($alch['docSentiment']['score'])){
				$alchemy_sentiment_score = $alch['docSentiment']['score'];
			}else{
				$alchemy_sentiment_score = '';
			}
		}
		$temp_array['sentiment_type'] = $alchemy_sentiment_type;
		$temp_array['sentiment_score'] = $alchemy_sentiment_score;
		
		// call to alchemy #2: get ranked keywords	
		$url = 'http://ieatyourbrain.com/apis/sent/?call='.urlencode('/url/URLGetRankedKeywords?keywordExtractMode=strict&showSourceText=0&outputMode=json&url='.urlencode($link));
		$o = $this->get_curl_data($url);
		
		$keywords = '';
		
		foreach ($o['keywords'] as $k) {
			$keywords .= '|'.$k['text'].'|';
		}
		$temp_array['keywords'] = $keywords;
		
		// call to alchemy #3: get ranked named entities
		$url = 'http://ieatyourbrain.com/apis/sent/?call='.urlencode('/url/URLGetRankedNamedEntities?showSourceText=1&outputMode=json&url='.urlencode($link));
		$alch = $this->get_curl_data($url);
		
		$temp_array['full_text'] = trim($alch['text']);
		
		
		$result = array();		
		$entities = '';
		$temp_array['entities'] = $entities;
		
		foreach($alch['entities'] as $item) {
			
			if($item['type'] == "Company" && isset($item['disambiguated'])){
			
				$entities .= '|'.$item['disambiguated']['name'].'|'; 
				$temp_array['entities'] = $entities;
				// call to yahoo api for disamiguating ticker symbols #1
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'http://d.yimg.com/autoc.finance.yahoo.com/autoc?query='.urlencode($item['disambiguated']['name']).'&callback=YAHOO.Finance.SymbolSuggest.ssCallback');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				$yhoo = curl_exec($ch);
				curl_close($ch);				
				if($yhoo != "" || !empty($yhoo)){
					if (preg_match('/"symbol":"([A-Z]+)"/', $yhoo, $symbol)) {
						preg_match('/"exchDisp":"([A-Z]+)"/', $yhoo, $exchDisp);
						if(!empty($symbol) && !empty($exchDisp)){
							$result[] = (($exchDisp[1])?$exchDisp[1].':':'').$symbol[1];
						}
					}
				}
			}//End check for key Company
		}		
			preg_match_all('/(NASDAQ|NYSE|AMEX|OTC): ?[A-Z]*/', $temp_array['full_text'], $symbols_2);

			foreach($symbols_2[0] as $symbols_2_item) {
				$result[] = preg_replace('/ /', '', $symbols_2_item);
			}			
			
			preg_match('/ \(([A-Z]*):/', $title, $symbols_3);
			if(!empty($symbols_3[1])){
				$result[] = $symbols_3[1]; 
			}
			if(count($result) == 0){
				$temp_array['symbols'] = '';
			}else{
				$temp_array['symbols'] = implode(',',array_unique($result));
			}
			
			$this->update_news($temp_array); //send data to update news item with gathered information

			// call to alchemy #4: get author
			$url = 'http://ieatyourbrain.com/apis/sent/?call='.urlencode('/url/URLGetAuthor?outputMode=json&url='.urlencode($link));			
			$a = $this->get_curl_data($url);
			
			$author_name = $this->clean_author_name($a['author']);
			
			// if author is not returned by alchemy then record status as an error
			if ($a['status'] === 'ERROR') { 
				extract($a);
					$query = "INSERT INTO `".$this->author_log."` (`table_id`, `media_id`, `a_status`, `a_statusInfo`, `createdon`) 
						VALUES (1,'$stored_sysid','ERROR',:statusInfo,NOW())";
					$stmt = $this->db_conn->prepare($query);
					$stmt->bindValue(':statusInfo', $statusInfo);
					$count = $stmt->execute();					
			}else{
				// doublecheck if author name is returned, it sometimes does not
				if (($author_name == '') || (preg_match('/^Dan Shoe/', $author_name))) {}
				// author name is returned for sure
				else { 
					// log author name with status OK
					$query = "INSERT INTO `".$this->author_log."` (`table_id`, `media_id`, `a_status`, `a_statusInfo`, `createdon`,`a_author`) 
						VALUES (1,'$stored_sysid','OK','',NOW(),:a_author)";
					$stmt = $this->db_conn->prepare($query);
					$stmt->bindValue(':a_author', $author_name);
					$count = $stmt->execute();	
					
					$author_id = '';
					
					$query = "SELECT author_id FROM news_authors WHERE `name`='".trim($author_name)."'";
					$sql = $this->db_conn->query($query);
					$result = $sql->fetchAll();	

					if(count($result) > 0){
						extract($result[0]);
						$author_id = $author_id;
					}
			
					if($author_id == ''){ //author not found
						$query = "INSERT INTO `".$this->authors."` (`name`) 
							VALUES (:author_name)";
						$stmt = $this->db_conn->prepare($query);
						$stmt->bindValue(':author_name', $author_name);
						$count = $stmt->execute();
						$author_id = $this->db_conn->lastInsertId(); 	
					}else{
						$query = "UPDATE `".$this->authors."` SET `lastupdated` = NOW() WHERE `author_id`='$author_id';";
						$stmt = $this->db_conn->prepare($query);
						$stmt->bindValue(':keywords', $keywords);		
						$count = $stmt->execute();
					}
					
						
					// Add new relationship between Author and Media - news item
					// but make sure to check that the combination for author_id,table_id, and media_id does not exist
	
					$query = "SELECT * FROM media_author WHERE `author_id`='".$author_id."' AND `media_id`='".$stored_sysid."' AND `table_id`='1'";
					$sql = $this->db_conn->query($query);
					$result = $sql->fetchAll();	
					if(count($result) == 0){
						$query = "INSERT INTO media_author(author_id, table_id, media_id) VALUES (:author_id, '1','$stored_sysid')";						
						$stmt = $this->db_conn->prepare($query);
						$stmt->bindValue(':author_id', $author_id);
						$count = $stmt->execute();					
					}						
				}
			}
			// author ends
		
		
	}
	
	/**
	* Update news item
	* @param array $args - contains unique id of news item
	*/
	private function update_news($args){
		extract($args);
		
		$query = "UPDATE `".$this->news_table."` SET `sentiment_type` = :sentiment_type, `sentiment_score`=:sentiment_score,`full_text`=:full_text, `symbols`=:symbols,
			`entities`=:entities, `keywords`=:keywords, `sentiment_lastupdate`= NOW() WHERE `stored_sysid`='$stored_sysid';";
		$stmt = $this->db_conn->prepare($query);
		$stmt->bindValue(':sentiment_type', $sentiment_type);
		$stmt->bindValue(':sentiment_score', $sentiment_score);	
		$stmt->bindValue(':full_text', $full_text);
		$stmt->bindValue(':symbols', $symbols);
		$stmt->bindValue(':entities', $entities);
		$stmt->bindValue(':keywords', $keywords);		
		$count = $stmt->execute();
		
	}
	
	
	/**
	* Retrieve the stock symbols for given string
	* @param string $html
	* @return string $stock_symbols
	*/
	private function get_stock_symbols($html){
	
		preg_match_all('/(NASDAQ|NYSE|AMEX|OTC): ?[A-Z]*/', $alch['text'], $symbols_2);

		foreach($symbols_2[0] as $symbols_2_item) {
			$result[] = preg_replace('/ /', '', $symbols_2_item);
		}			
		
		preg_match('/ \(([A-Z]*):/', $rss_title, $symbols_3);
		if(!empty($symbols_3[1])){
			$result[] = $symbols_3[1]; 
		}

		$stock_symbols = implode(',',array_unique($result));	
	}
	
	/*
	* TODO: Use this method for sending out notifications (good or bad)
	* possibly write to a log file, too?
	*/
	private function notification(){
	
	}
	
	/**
	* Select feed
	* Could be used to get feeds by type, feeds by specific level1 and/or level2
	*/
	public function select_feed($feed_name = 'wsj'){

		$query = "SELECT `id`,`level1_name`,`level2_name`,`url`,`source_type` FROM `news_sources_hd` WHERE `level1_name` = '".$feed_name."'"; 
		$sql = $this->db_conn->query($query);
		$feeds = $sql->fetchAll();	
		
		return $feeds;
	}
	
	/**
	* Select feeds
	* Accepts an array containing level1_name values
	* @param array $feeds
	* @return array $all_feeds
	*/
	public function select_feeds($feeds){
		$temp_string = implode("','",$feeds);
		$temp_string = "'" . $temp_string . "'";
		
		$query = "SELECT `id`,`level1_name`,`level2_name`,`url`,`source_type` FROM `news_sources_hd` WHERE `level1_name` IN (".$temp_string.")"; 
		$sql = $this->db_conn->query($query);
		$all_feeds = $sql->fetchAll();	
		
		return $all_feeds;
		
	}
	
	/**
	* Get news data
	*/
	public function select_news($limit = 10){
		
		$query = "SELECT `stored_sysid`,`title`,`level1_name`,`level2_name`,`link` FROM `".$this->news_table."` WHERE `sentiment_lastupdate` IS NULL AND `createdon` >= '2013-03-01 00:00:00' ORDER BY `createdon` DESC LIMIT " . $limit;
		$sql = $this->db_conn->query($query);
		$news = $sql->fetchAll();	
		
		return $news;
	}

	private function clean_author_name($n) {
		return preg_replace('/[^a-zA-Z0-9 ]/', '', trim($n));
	}
	
	private function get_max_pubdate($level1_name,$level2_name){

		$query = "SELECT MAX(pubDateReal) AS 'max_pub_date_real' FROM `".$this->news_table."` 
								WHERE level1_name = '$level1_name' AND level2_name = '".$level2_name."';";
		$sql = $this->db_conn->query($query);
		$result = $sql->fetchAll();
		extract($result[0]);

		$max_pubDate = ($max_pub_date_real == '') ? '2000-01-01 01:00:00' : $max_pub_date_real;	
		return $max_pubDate;
	}
	
	/**
	* Perform CURL on given URL
	* @param string $url
	*/
	private function get_curl_data($url){
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$curl_data = curl_exec($ch);
		curl_close($ch);
		
		return json_decode($curl_data,true);		
	}
	
	public function get_ap(){
		
		$url = 'http://syndication.ap.org/AP.Distro.Feed/GetFeed.aspx?idList=644220&idListType=savedsearches&maxItems=100&minDateTime=&maxDateTime=';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, 'kang@passfail.com:abc123');
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$data = curl_exec($ch);
		curl_close($ch);
		$this->fetch_ap($data);

	}
	
	public function fetch_ap($raw_rss){
		$this->pie->set_raw_data($raw_rss);
		$this->pie->enable_cache(false);
		$this->pie->init();
		$this->pie->handle_content_type();

		$level1_name ='ap-exchange';
		$level2_name = '';
		$max_pub_date = $this->get_max_pubdate($level1_name,$level2_name); 
		
		foreach ($this->pie->get_items() as $item) {
			
			$title = $item->get_title();
			$link = $item->get_permalink();
			$description = $item->get_description();		
			$pubDate = $item->get_date('Y-m-d H:i:s');
			
			$this->rss_info['level1_name'] = $level1_name;
			$this->rss_info['level2_name'] = $level2_name;			
			
			if ($pubDate > $max_pub_date) { //check to make sure that current item's publish date is more current than the most current news item in datastore
				echo $title."<br/>";
				
				$item_info = array("title"=>$title,"link"=>$link,"description"=>$description,"pubDate"=>$pubDate);
				$this->insert_data($item_info);
			}
			
			
		}
	}	
}