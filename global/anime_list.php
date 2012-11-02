<?php

class AnimeList {
  public $dbConn;
  public $user_id;
  public $entries, $list;
  public $startTime, $endTime;
  public $listAvg, $listStdDev, $entryAvg, $entryStdDev;
  private $statusStrings, $scoreStrings, $episodeStrings, $cachedAnime;
  public function __construct($database, $user_id=Null) {
    $this->dbConn = $database;
    // strings with which to build feed messages.
    // the status messages we build will be different depending on 1) whether or not this is the first entry, and 2) what the status actually is.
    $this->statusStrings = array(0 => array(0 => "did something mysterious with [ANIME]",
                                      1 => "is now watching [ANIME]",
                                      2 => "marked [ANIME] as completed",
                                      3 => "marked [ANIME] as on-hold",
                                      4 => "marked [ANIME] as dropped",
                                      6 => "plans to watch [ANIME]"),
                                  1 => array(0 => "removed [ANIME]",
                                            1 => "started watching [ANIME]",
                                            2 => "finished [ANIME]",
                                            3 => "put [ANIME] on hold",
                                            4 => "dropped [ANIME]",
                                            6 => "now plans to watch [ANIME]"));
    $this->scoreStrings = array(0 => array("rated [ANIME] a [SCORE]/10", "and rated it a [SCORE]/10"),
                          1 => array("unrated [ANIME]", "and unrated it"));
    $this->episodeStrings = array("is now watching episode [EPISODE]/[TOTAL_EPISODES] of [ANIME]", "and finished episode [EPISODE]/[TOTAL_EPISODES]");
    $this->cachedAnime = [];
    $this->listAvg = $this->listStdDev = $this->entryAvg = $this->entryStdDev = 0;
    if ($user_id === 0) {
      $this->user_id = 0;
      $this->username = $this->startTime = $this->endTime = "";
      $this->entries = $this->list = [];
    } else {
      $userInfo = $this->dbConn->queryFirstRow("SELECT `user_id`, MIN(`time`) AS `start_time`, MAX(`time`) AS `end_time` FROM `anime_lists` WHERE `user_id` = ".intval($user_id));
      if (!$userInfo) {
        return False;
      }
      $this->user_id = intval($userInfo['user_id']);
      $this->startTime = intval($userInfo['start_time']);
      $this->endTime = intval($userInfo['end_time']);
      $this->entries = $this->getEntries();
      $this->list = $this->getList();
    }
  }
  public function allow($authingUser, $action) {
    // takes a user object and an action and returns a bool.
    switch($action) {
      case 'new':
      case 'edit':
      case 'delete':
        if ($authingUser->id == $this->user_id || ($authingUser->isModerator() || $authingUser->isAdmin()) ) {
          return True;
        }
        return False;
        break;
      case 'index':
        if ($authingUser->isAdmin()) {
          return True;
        }
        return False;
        break;
      case 'show':
        return True;
        break;
      default:
        return False;
        break;
    }
  }
  public function create_or_update($entry) {
    /*
      Creates or updates an existing anime list entry for the current user.
      Takes an array of entry parameters.
      Returns the resultant anime_list entry ID.
    */
    $params = [];
    foreach ($entry as $parameter => $value) {
      if (!is_array($value)) {
        if ($parameter == 'anime_id' || $parameter == 'user_id' || $parameter == 'status' || $parameter == 'score' || $parameter == 'episode') {
          if (is_numeric($value)) {
            $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".intval($value);
          }
        } else {
          $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".$this->dbConn->quoteSmart($value);
        }
      }
    }

    try {
      $user = new User($this->dbConn, intval($entry['user_id']));
      $anime = new Anime($this->dbConn, intval($entry['anime_id']));
    } catch (Exception $e) {
      return False;
    }
    // check to see if this is an update.
    if (isset($this->entries[intval($entry['id'])])) {
      $updateDependency = $this->dbConn->stdQuery("UPDATE `anime_lists` SET ".implode(", ", $params)." WHERE `id` = ".intval($entry['id'])." LIMIT 1");
      if (!$updateDependency) {
        return False;
      }
      // update list locally.
      if ($this->list[intval($entry['anime_id'])]['score'] != intval($entry['score']) || $this->list[intval($entry['anime_id'])]['status'] != intval($entry['status']) || $this->list[intval($entry['anime_id'])]['episode'] != intval($entry['episode'])) {
        if (intval($entry['status']) == 0) {
          unset($this->list[intval($entry['anime_id'])]);
        } else {
          $this->list[intval($entry['anime_id'])] = array('anime_id' => intval($entry['anime_id']), 'time' => $entry['time'], 'score' => intval($entry['score']), 'status' => intval($entry['status']), 'episode' => intval($entry['episode']));
        }
      }
      $returnValue = intval($entry['id']);
    } else {
      $timeString = (isset($entry['time']) ? "" : ", `time` = NOW()");
      $insertDependency = $this->dbConn->stdQuery("INSERT INTO `anime_lists` SET ".implode(",", $params).$timeString);
      if (!$insertDependency) {
        return False;
      }
      // insert list locally.
      if (intval($entry['status']) == 0) {
        unset($this->list[intval($entry['anime_id'])]);
      } else {
        $this->list[intval($entry['anime_id'])] = array('anime_id' => intval($entry['anime_id']), 'time' => $entry['time'], 'score' => intval($entry['score']), 'status' => intval($entry['status']), 'episode' => intval($entry['episode']));
      }
      $returnValue = intval($this->dbConn->insert_id);
    }
    $this->entries[intval($returnValue)] = $entry;
    return $returnValue;
  }
  public function delete($entries=False) {
    /*
      Deletes anime list entries.
      Takes an array of entry ids as input, defaulting to all entries.
      Returns a boolean.
    */
    if ($entries === False) {
      $entries = array_keys($this->animeEntries);
    }
    if (is_numeric($entries)) {
      $entries = [intval($entries)];
    }
    $entryIDs = array();
    foreach ($entries as $entry) {
      if (is_numeric($entry)) {
        $entryIDs[] = intval($entry);
      }
    }
    if (count($entryIDs) > 0) {
      $drop_entries = $this->dbConn->stdQuery("DELETE FROM `anime_lists` WHERE `user_id` = ".intval($this->user_id)." AND `id` IN (".implode(",", $entryIDs).") LIMIT ".count($entryIDs));
      if (!$drop_entries) {
        return False;
      }
    }
    foreach ($entryIDs as $entryID) {
      unset($this->animeEntries[intval($entryID)]);
    }
    return True;
  }
  public function getEntries() {
    // retrieves a list of arrays corresponding to anime list entries belonging to this user.
    $returnList = $this->dbConn->queryAssoc("SELECT `id`, `anime_id`, `time`, `status`, `score`, `episode` FROM `anime_lists` WHERE `user_id` = ".intval($this->user_id)." ORDER BY `time` DESC", "id");
    $this->entryAvg = $this->entryStdDev = $entrySum = 0;
    $entryCount = count($returnList);
    foreach ($returnList as $entry) {
      $entrySum += intval($entry['score']);
    }
    $this->entryAvg = ($entryCount === 0) ? 0 : $entrySum / $entryCount;
    $entrySum = 0;
    if ($entryCount > 1) {
      foreach ($returnList as $entry) {
        $entrySum += pow(intval($entry['score']) - $this->entryAvg, 2);
      }
      $this->entryStdDev = pow($entrySum / ($entryCount - 1), 0.5);
    }
    return $returnList;
  }
  public function getList() {
    // retrieves a list of anime_id, time, status, score, episode arrays corresponding to the latest list entry for each anime this user has watched.
    $returnList = $this->dbConn->queryAssoc("SELECT `anime_lists`.`id`, `anime_id`, `time`, `score`, `status`, `episode` FROM (
                                              SELECT MAX(`id`) AS `id` FROM `anime_lists`
                                              WHERE `user_id` = ".intval($this->user_id)."
                                              GROUP BY `anime_id`
                                            ) `p` INNER JOIN `anime_lists` ON `anime_lists`.`id` = `p`.`id`
                                            WHERE `status` != 0
                                            ORDER BY `status` ASC, `score` DESC", "anime_id");
    $this->listAvg = $this->listStdDev = $listSum = $listCount = 0;
    foreach ($returnList as $entry) {
      if ($entry['score'] != 0) {
        $listCount++;
        $listSum += intval($entry['score']);
      }
    }
    $this->listAvg = ($listCount === 0) ? 0 : $listSum / $listCount;
    $listSum = 0;
    if ($listCount > 1) {
      foreach ($returnList as $entry) {
        if ($entry['score'] != 0) {
          $listSum += pow(intval($entry['score']) - $this->listAvg, 2);
        }
      }
      $this->listStdDev = pow($listSum / ($listCount - 1), 0.5);
    }
    return $returnList;
  }
  public function entries($maxTime=Null, $limit=Null) {
    // Returns a list of up to $limit entries up to $maxTime.
    $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
    $outputTimezone = new DateTimeZone(OUTPUT_TIMEZONE);
    if ($maxTime === Null) {
      $nowTime = new DateTime();
      $nowTime->setTimezone($outputTimezone);
      $maxTime = $nowTime;
    }
    $returnList = [];
    $entryCount = 0;
    foreach ($this->entries as $entry) {
      $entryDate = new DateTime($value['time'], $serverTimezone);
      if ($entryDate > $maxTime) {
        continue;
      }
      $entry['user_id'] = intval($this->user_id);
      $returnList[] = $entry;
      $entryCount++;
      if ($limit !== Null && $entryCount >= $limit) {
        return $returnList;
      }
    }
    return $returnList;
  }
  public function listSection($status=Null, $score=Null) {
    // returns a section of this user's anime list.
    return array_filter($this->list, function($value) use ($status, $score) {
      return (($status !== Null && intval($value['status']) === $status) || ($score !== Null && intval($value['score']) === $score));
    });
  }
  public function prevEntry($anime_id, $beforeTime) {
    // Returns the previous entry in this user's anime list for $anime_id and before $beforeTime.
    $prevEntry = array('status' => 0, 'score' => 0, 'episode' => 0);
    $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
    foreach ($this->entries as $entry) {
      $entryDate = new DateTime($entry['time'], $serverTimezone);
      if ($entryDate >= $beforeTime) {
        continue;
      }
      if ($entry['anime_id'] == $anime_id) {
        return $entry;
      }
    }
    return $prevEntry;
  }
  public function feedEntry($entry, $user, $currentUser) {
    // fetch the previous feed entry and compare values against current entry.
    if (!isset($this->cachedAnime[intval($entry['anime_id'])])) {
      $this->cachedAnime[intval($entry['anime_id'])] = new Anime($this->dbConn, intval($entry['anime_id']));
    }
    $entryAnime = $this->cachedAnime[intval($entry['anime_id'])];

    $outputTimezone = new DateTimeZone(OUTPUT_TIMEZONE);
    $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
    $nowTime = new DateTime("now", $outputTimezone);

    $entryTime = new DateTime($entry['time'], $serverTimezone);
    $diffInterval = $nowTime->diff($entryTime);
    $prevEntry = $this->prevEntry($entryAnime->id, $entryTime);

    $statusChanged = (bool) ($entry['status'] != $prevEntry['status']);
    $scoreChanged = (bool) ($entry['score'] != $prevEntry['score']);
    $episodeChanged = (bool) ($entry['episode'] != $prevEntry['episode']);
    
    // concatenate appropriate parts of this status text.
    $statusTexts = [];
    if ($statusChanged) {
      $statusTexts[] = $this->statusStrings[intval((bool)$prevEntry)][intval($entry['status'])];
    }
    if ($scoreChanged) {
      $statusTexts[] = $this->scoreStrings[intval($entry['score'] == 0)][intval($statusChanged)];
    }
    if ($episodeChanged) {
      $statusTexts[] = $this->episodeStrings[intval($statusChanged || $scoreChanged)];
    }
    $statusText = implode(" ", $statusTexts);

    // replace placeholders.
    $statusText = str_replace("[ANIME]", $entryAnime->link("show", $entryAnime->title), $statusText);
    $statusText = str_replace("[SCORE]", $entry['score'], $statusText);
    $statusText = str_replace("[EPISODE]", $entry['episode'], $statusText);
    $statusText = str_replace("[TOTAL_EPISODES]", $entryAnime->episodeCount, $statusText);
    $statusText = ucfirst($statusText);

    $output = "";
    if ($statusText != '') {
      $output .= "  <li class='feedEntry row-fluid'>
        <div class='feedDate' data-time='".$entryTime->format('U')."'>".ago($diffInterval)."</div>
        <div class='feedAvatar'>".$user->link("show", "<img class='feedAvatarImg' src='".escape_output($user->avatarPath)."' />", True)."</div>
        <div class='feedText'>
          <div class='feedUser'>".$user->link("show", $user->username)."</div>
          ".$statusText.".\n";
      if ($currentUser->id === $user->id) {
        $output .= "            <ul class='feedEntryMenu hidden'><li>".$this->entryLink(intval($entry['id']), "delete", "<i class='icon-trash'></i> Delete", True)."</li></ul>";
      }
      $output .= "          </div>
      </li>\n";
    }
    return $output;
  }
  public function entryLink($id, $action="show", $text=Null, $raw=False) {
    // returns an HTML link to an entry link, with text provided.
    $text = ($text === Null) ? "List" : $text;
    return "<a href='/anime_list.php?action=".urlencode($action)."&id=".intval($id)."&user_id=".intval($this->user_id)."'>".($raw ? $text : escape_output($text))."</a>";
  }
  public function link($action="show", $text=Null, $raw=False) {
    // returns an HTML link to the current tag's profile, with text provided.
    $text = ($text === Null) ? "List" : $text;
    return "<a href='/user.php?action=".urlencode($action)."&id=".intval($this->user_id)."#list'>".($raw ? $text : escape_output($text))."</a>";
  }
}
?>